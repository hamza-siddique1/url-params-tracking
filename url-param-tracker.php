<?php
/**
 * Plugin Name: URL Parameter Tracker
 * Description: Captures all URL parameters from incoming visits and stores them as JSON for marketing attribution analysis.
 * Version: 1.0.0
 * Author: Hamza Siddique
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'UPT_TABLE', 'url_params_log' );
define( 'UPT_VERSION', '1.0.3' );

register_activation_hook( __FILE__, 'upt_create_table' );

function upt_create_table() {
    global $wpdb;
    $table      = $wpdb->prefix . UPT_TABLE;
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table (
        id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        created_at  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
        page_url    TEXT                NOT NULL,
        referrer    TEXT,
        ip_address  VARCHAR(45),
        user_agent  TEXT,
        params      JSON,
        PRIMARY KEY (id),
        INDEX idx_created (created_at)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
    update_option( 'upt_db_version', UPT_VERSION );
}

add_action( 'wp', 'upt_capture_params' );

function upt_capture_params() {
    if ( empty( $_GET ) ) return;

    if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) return;
    if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) return;

    global $wpdb;
    $table = $wpdb->prefix . UPT_TABLE;

    $params = [];
    foreach ( $_GET as $key => $value ) {
        $params[ sanitize_text_field( $key ) ] = is_array( $value )
            ? array_map( 'sanitize_text_field', $value )
            : sanitize_text_field( $value );
    }

    $page_url  = ( is_ssl() ? 'https' : 'http' ) . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    $referrer  = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( $_SERVER['HTTP_REFERER'] ) : '';
    $ip        = upt_get_ip();
    $ua        = isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( $_SERVER['HTTP_USER_AGENT'], 0, 500 ) : '';

    $wpdb->insert(
        $table,
        [
            'page_url'   => esc_url_raw( $page_url ),
            'referrer'   => $referrer,
            'ip_address' => $ip,
            'user_agent' => $ua,
            'params'     => wp_json_encode( $params ),
        ],
        [ '%s', '%s', '%s', '%s', '%s' ]
    );
}

function upt_get_ip() {
    return '';
}

add_action( 'admin_menu', 'upt_admin_menu' );

function upt_admin_menu() {
    add_menu_page(
        'URL Param Tracker',
        'Param Tracker',
        'manage_options',
        'url-param-tracker',
        'upt_admin_page',
        'dashicons-chart-bar',
        30
    );
    add_submenu_page(
        'url-param-tracker',
        'All Logs',
        'All Logs',
        'manage_options',
        'url-param-tracker',
        'upt_admin_page'
    );
    add_submenu_page(
        'url-param-tracker',
        'Param Coverage',
        'Param Coverage',
        'manage_options',
        'upt-coverage',
        'upt_coverage_page'
    );
    add_submenu_page(
        'url-param-tracker',
        'Settings',
        'Settings',
        'manage_options',
        'upt-settings',
        'upt_settings_page'
    );
}

add_action( 'admin_enqueue_scripts', 'upt_enqueue_assets' );

function upt_enqueue_assets( $hook ) {
    if ( strpos( $hook, 'url-param-tracker' ) === false && strpos( $hook, 'upt-' ) === false ) return;
    wp_enqueue_style( 'upt-admin', plugin_dir_url( __FILE__ ) . 'admin/admin.css', [], UPT_VERSION );
    wp_enqueue_script( 'upt-admin', plugin_dir_url( __FILE__ ) . 'admin/admin.js', [ 'jquery' ], UPT_VERSION, true );
    wp_localize_script( 'upt-admin', 'UPT', [
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'upt_nonce' ),
    ]);
}

function upt_admin_page() {
    require_once plugin_dir_path( __FILE__ ) . 'admin/page-logs.php';
}
function upt_coverage_page() {
    require_once plugin_dir_path( __FILE__ ) . 'admin/page-coverage.php';
}
function upt_settings_page() {
    require_once plugin_dir_path( __FILE__ ) . 'admin/page-settings.php';
}

add_action( 'wp_ajax_upt_delete_row', 'upt_ajax_delete_row' );

function upt_ajax_delete_row() {
    check_ajax_referer( 'upt_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();
    global $wpdb;
    $id = intval( $_POST['id'] ?? 0 );
    $wpdb->delete( $wpdb->prefix . UPT_TABLE, [ 'id' => $id ], [ '%d' ] );
    wp_send_json_success();
}

add_action( 'wp_ajax_upt_clear_logs', 'upt_ajax_clear_logs' );

function upt_ajax_clear_logs() {
    check_ajax_referer( 'upt_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();
    global $wpdb;
    $wpdb->query( 'TRUNCATE TABLE ' . $wpdb->prefix . UPT_TABLE );
    wp_send_json_success();
}

add_action( 'admin_init', 'upt_handle_settings' );

function upt_handle_settings() {
    if (
        isset( $_POST['upt_save_settings'] ) &&
        check_admin_referer( 'upt_settings_save' ) &&
        current_user_can( 'manage_options' )
    ) {
        update_option( 'upt_retention_days', intval( $_POST['upt_retention_days'] ?? 90 ) );
        update_option( 'upt_exclude_bots',   isset( $_POST['upt_exclude_bots'] ) ? 1 : 0 );
        wp_redirect( admin_url( 'admin.php?page=upt-settings&saved=1' ) );
        exit;
    }
}

register_activation_hook( __FILE__, 'upt_schedule_cleanup' );
register_deactivation_hook( __FILE__, 'upt_unschedule_cleanup' );

function upt_schedule_cleanup() {
    if ( ! wp_next_scheduled( 'upt_daily_cleanup' ) ) {
        wp_schedule_event( time(), 'daily', 'upt_daily_cleanup' );
    }
}
function upt_unschedule_cleanup() {
    wp_clear_scheduled_hook( 'upt_daily_cleanup' );
}
add_action( 'upt_daily_cleanup', 'upt_run_cleanup' );

function upt_run_cleanup() {
    $days = intval( get_option( 'upt_retention_days', 90 ) );
    if ( $days <= 0 ) return;
    global $wpdb;
    $table = $wpdb->prefix . UPT_TABLE;
    $wpdb->query( $wpdb->prepare(
        "DELETE FROM $table WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
        $days
    ));
}

function upt_is_bot() {
    $ua = strtolower( $_SERVER['HTTP_USER_AGENT'] ?? '' );
    $bots = [ 'bot', 'crawl', 'spider', 'slurp', 'mediapartners', 'facebookexternalhit', 'wget', 'curl' ];
    foreach ( $bots as $b ) {
        if ( strpos( $ua, $b ) !== false ) return true;
    }
    return false;
}

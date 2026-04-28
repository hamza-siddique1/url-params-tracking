<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap upt-wrap">
    <h1 class="upt-title">
        <span class="dashicons dashicons-admin-settings"></span>
        Tracker Settings
    </h1>

    <?php if ( isset( $_GET['saved'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>
    <?php endif; ?>

    <form method="post" class="upt-settings-form">
        <?php wp_nonce_field( 'upt_settings_save' ); ?>

        <table class="form-table">
            <tr>
                <th scope="row"><label for="upt_retention_days">Log Retention (days)</label></th>
                <td>
                    <input type="number" id="upt_retention_days" name="upt_retention_days"
                           value="<?php echo intval( get_option('upt_retention_days', 90) ); ?>"
                           min="1" max="3650" class="upt-input">
                    <p class="description">Logs older than this many days are deleted automatically. Set 0 to keep forever.</p>
                </td>
            </tr>
            <tr>
                <th scope="row">Exclude Bots</th>
                <td>
                    <label>
                        <input type="checkbox" name="upt_exclude_bots" value="1"
                               <?php checked( get_option('upt_exclude_bots', 1), 1 ); ?>>
                        Skip logging known bots/crawlers
                    </label>
                </td>
            </tr>
        </table>

        <button type="submit" name="upt_save_settings" class="upt-btn upt-btn-primary">Save Settings</button>
    </form>

    <hr>

    <h2>Database Info</h2>
    <?php
    global $wpdb;
    $table = $wpdb->prefix . UPT_TABLE;
    $count = $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
    $size  = $wpdb->get_var( "SELECT ROUND((data_length + index_length) / 1024 / 1024, 2)
                               FROM information_schema.TABLES
                               WHERE table_schema = DATABASE()
                               AND table_name = '$table'" );
    ?>
    <table class="form-table">
        <tr><th>Table</th><td><code><?php echo esc_html($table); ?></code></td></tr>
        <tr><th>Total rows</th><td><?php echo number_format($count); ?></td></tr>
        <tr><th>Table size</th><td><?php echo esc_html($size ?? '—'); ?> MB</td></tr>
    </table>
</div>

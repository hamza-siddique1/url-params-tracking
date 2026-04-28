<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<?php
global $wpdb;
$table = $wpdb->prefix . UPT_TABLE;

$date_from = sanitize_text_field( $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days')) );
$date_to   = sanitize_text_field( $_GET['date_to']   ?? date('Y-m-d') );

$total = $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(*) FROM $table WHERE DATE(created_at) BETWEEN %s AND %s",
    $date_from, $date_to
) );

$rows_raw = $wpdb->get_results( $wpdb->prepare(
    "SELECT params FROM $table WHERE DATE(created_at) BETWEEN %s AND %s AND params IS NOT NULL",
    $date_from, $date_to
) );

$key_counts = [];
foreach ( $rows_raw as $r ) {
    $p = json_decode( $r->params, true );
    if ( ! is_array( $p ) ) continue;
    foreach ( $p as $k => $v ) {
        $key_counts[$k] = ( $key_counts[$k] ?? 0 ) + 1;
    }
}
arsort( $key_counts );

$priority_params = [ 'gclid', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'fbclid', 'msclkid', 'ttclid', 'dclid' ];
?>
<div class="wrap upt-wrap">
    <h1 class="upt-title">
        <span class="dashicons dashicons-visibility"></span>
        Param Coverage Analysis
    </h1>

    <form method="get" class="upt-filters">
        <input type="hidden" name="page" value="upt-coverage">
        <div class="upt-filter-row">
            <label>From: <input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>" class="upt-input"></label>
            <label>To: <input type="date" name="date_to" value="<?php echo esc_attr($date_to); ?>" class="upt-input"></label>
            <button type="submit" class="upt-btn upt-btn-primary">Analyze</button>
        </div>
    </form>

    <div class="upt-stats-row">
        <div class="upt-stat-card">
            <span class="upt-stat-num"><?php echo number_format($total); ?></span>
            <span class="upt-stat-label">Total Visits (with params)</span>
        </div>
        <div class="upt-stat-card">
            <span class="upt-stat-num"><?php echo count($key_counts); ?></span>
            <span class="upt-stat-label">Unique Param Keys Seen</span>
        </div>
        <div class="upt-stat-card">
            <span class="upt-stat-num"><?php echo isset($key_counts['gclid']) ? number_format($key_counts['gclid']) : '0'; ?></span>
            <span class="upt-stat-label">Visits WITH gclid</span>
        </div>
        <div class="upt-stat-card upt-stat-warn">
            <span class="upt-stat-num"><?php echo $total > 0 ? number_format($total - ($key_counts['gclid'] ?? 0)) : '0'; ?></span>
            <span class="upt-stat-label">Visits MISSING gclid</span>
        </div>
    </div>

    <h2 class="upt-section-title">Marketing Attribution Params</h2>
    <div class="upt-table-wrap">
        <table class="upt-table">
            <thead>
                <tr>
                    <th>Param Key</th>
                    <th>Visits Present</th>
                    <th>Coverage %</th>
                    <th>Visits Missing</th>
                    <th>Coverage Bar</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $priority_params as $pk ) :
                    $count = $key_counts[$pk] ?? 0;
                    $pct   = $total > 0 ? round( $count / $total * 100, 1 ) : 0;
                    $missing = $total - $count;
                    $cls = $pct >= 80 ? 'upt-good' : ( $pct >= 20 ? 'upt-warn' : 'upt-bad' );
                ?>
                <tr>
                    <td><code class="upt-param-code"><?php echo esc_html($pk); ?></code></td>
                    <td><?php echo number_format($count); ?></td>
                    <td class="<?php echo $cls; ?>"><?php echo $pct; ?>%</td>
                    <td class="<?php echo $missing > 0 ? 'upt-bad' : ''; ?>"><?php echo number_format($missing); ?></td>
                    <td>
                        <div class="upt-bar-track">
                            <div class="upt-bar-fill <?php echo $cls; ?>" style="width:<?php echo $pct; ?>%"></div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ( ! empty( $key_counts ) ) : ?>
    <h2 class="upt-section-title">All Detected Params</h2>
    <div class="upt-table-wrap">
        <table class="upt-table">
            <thead>
                <tr>
                    <th>Param Key</th>
                    <th>Occurrences</th>
                    <th>Coverage %</th>
                    <th>Is Priority Param</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $key_counts as $k => $cnt ) :
                    $pct = $total > 0 ? round( $cnt / $total * 100, 1 ) : 0;
                ?>
                <tr>
                    <td><code class="upt-param-code"><?php echo esc_html($k); ?></code></td>
                    <td><?php echo number_format($cnt); ?></td>
                    <td><?php echo $pct; ?>%</td>
                    <td><?php echo in_array($k, $priority_params) ? '<span class="upt-badge-green">✓ Yes</span>' : '<span class="upt-badge-gray">No</span>'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

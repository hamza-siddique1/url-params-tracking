<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<?php
global $wpdb;
$table = $wpdb->prefix . UPT_TABLE;

$search     = sanitize_text_field( $_GET['s'] ?? '' );
$date_from  = sanitize_text_field( $_GET['date_from'] ?? '' );
$date_to    = sanitize_text_field( $_GET['date_to'] ?? '' );
$param_key  = sanitize_text_field( $_GET['param_key'] ?? '' );
$per_page   = 50;
$current_page = max( 1, intval( $_GET['paged'] ?? 1 ) );
$offset     = ( $current_page - 1 ) * $per_page;

$where  = '1=1';
$values = [];

if ( $search ) {
    $where   .= ' AND (page_url LIKE %s OR params LIKE %s)';
    $values[] = '%' . $wpdb->esc_like( $search ) . '%';
    $values[] = '%' . $wpdb->esc_like( $search ) . '%';
}
if ( $date_from ) {
    $where   .= ' AND DATE(created_at) >= %s';
    $values[] = $date_from;
}
if ( $date_to ) {
    $where   .= ' AND DATE(created_at) <= %s';
    $values[] = $date_to;
}
if ( $param_key ) {
    $where   .= ' AND JSON_EXTRACT(params, %s) IS NOT NULL';
    $values[] = '$.' . $param_key;
}

$sql_base = "FROM $table WHERE $where";
$total    = $values
    ? $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) $sql_base", ...$values ) )
    : $wpdb->get_var( "SELECT COUNT(*) $sql_base" );

$query = "SELECT * $sql_base ORDER BY created_at DESC LIMIT $per_page OFFSET $offset";
$rows  = $values
    ? $wpdb->get_results( $wpdb->prepare( $query, ...$values ) )
    : $wpdb->get_results( $query );

$total_pages = ceil( $total / $per_page );

$all_keys_raw = $wpdb->get_col( "SELECT DISTINCT JSON_KEYS(params) FROM $table WHERE params IS NOT NULL LIMIT 500" );
$all_keys = [];
foreach ( $all_keys_raw as $k ) {
    $decoded = json_decode( $k, true );
    if ( is_array( $decoded ) ) $all_keys = array_merge( $all_keys, $decoded );
}
$all_keys = array_unique( $all_keys );
sort( $all_keys );
?>
<div class="wrap upt-wrap">
    <h1 class="upt-title">
        <span class="dashicons dashicons-chart-bar"></span>
        URL Parameter Logs
        <span class="upt-badge"><?php echo number_format( $total ); ?> entries</span>
    </h1>

    <?php if ( isset( $_GET['deleted'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p>Row deleted.</p></div>
    <?php endif; ?>

    <form method="get" class="upt-filters">
        <input type="hidden" name="page" value="url-param-tracker">
        <div class="upt-filter-row">
            <input type="text" name="s" placeholder="Search URL or param value…" value="<?php echo esc_attr( $search ); ?>" class="upt-input">

            <select name="param_key" class="upt-select">
                <option value="">— Filter by param key —</option>
                <?php foreach ( $all_keys as $key ) : ?>
                    <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $param_key, $key ); ?>>
                        <?php echo esc_html( $key ); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <input type="date" name="date_from" value="<?php echo esc_attr( $date_from ); ?>" class="upt-input" title="From date">
            <input type="date" name="date_to"   value="<?php echo esc_attr( $date_to );   ?>" class="upt-input" title="To date">

            <button type="submit" class="upt-btn upt-btn-primary">Filter</button>
            <a href="?page=url-param-tracker" class="upt-btn upt-btn-ghost">Reset</a>
            <button type="button" id="upt-clear-all" class="upt-btn upt-btn-danger">Clear All Logs</button>
        </div>
    </form>

    <?php if ( empty( $rows ) ) : ?>
        <div class="upt-empty">
            <span class="dashicons dashicons-search"></span>
            <p>No entries found. Visits with URL params will appear here automatically.</p>
        </div>
    <?php else : ?>

    <?php
    $result_keys = [];
    foreach ( $rows as $row ) {
        $p = json_decode( $row->params, true );
        if ( $p ) $result_keys = array_merge( $result_keys, array_keys( $p ) );
    }
    $result_keys = array_unique( $result_keys );
    $priority = [ 'gclid', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'fbclid', 'msclkid', 'ttclid' ];
    $sorted_keys = array_merge(
        array_intersect( $priority, $result_keys ),
        array_diff( $result_keys, $priority )
    );
    ?>

    <div class="upt-table-wrap">
        <table class="upt-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Time</th>
                    <th>Page URL</th>
                    <th>Referrer</th>
                    <th>IP</th>
                    <?php foreach ( $sorted_keys as $k ) : ?>
                        <th class="upt-param-col <?php echo in_array( $k, $priority ) ? 'upt-key-priority' : ''; ?>">
                            <?php echo esc_html( $k ); ?>
                        </th>
                    <?php endforeach; ?>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $rows as $row ) :
                    $params = json_decode( $row->params, true ) ?: [];
                ?>
                <tr data-id="<?php echo intval( $row->id ); ?>">
                    <td class="upt-id"><?php echo intval( $row->id ); ?></td>
                    <td class="upt-date"><?php echo esc_html( wp_date( 'd M Y H:i', strtotime( $row->created_at ) ) ); ?></td>
                    <td class="upt-url" title="<?php echo esc_attr( $row->page_url ); ?>">
                        <a href="<?php echo esc_url( $row->page_url ); ?>" target="_blank">
                            <?php echo esc_html( substr( $row->page_url, 0, 60 ) . ( strlen( $row->page_url ) > 60 ? '…' : '' ) ); ?>
                        </a>
                    </td>
                    <td class="upt-ref" title="<?php echo esc_attr( $row->referrer ); ?>">
                        <?php echo esc_html( $row->referrer ? substr( $row->referrer, 0, 40 ) . '…' : '—' ); ?>
                    </td>
                    <td class="upt-ip"><?php echo esc_html( $row->ip_address ?: '—' ); ?></td>

                    <?php foreach ( $sorted_keys as $k ) : ?>
                        <td class="upt-param-val <?php echo isset( $params[$k] ) ? 'upt-present' : 'upt-missing'; ?>">
                            <?php if ( isset( $params[$k] ) ) : ?>
                                <span class="upt-val" title="<?php echo esc_attr( $params[$k] ); ?>">
                                    <?php echo esc_html( substr( $params[$k], 0, 20 ) . ( strlen( $params[$k] ) > 20 ? '…' : '' ) ); ?>
                                </span>
                            <?php else : ?>
                                <span class="upt-dash">—</span>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>

                    <td>
                        <button class="upt-btn upt-btn-sm upt-delete-row" data-id="<?php echo intval( $row->id ); ?>">
                            <span class="dashicons dashicons-trash"></span>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ( $total_pages > 1 ) : ?>
    <div class="upt-pagination">
        <span class="upt-page-info">
            Page <?php echo $current_page; ?> of <?php echo $total_pages; ?>
            (<?php echo number_format( $total ); ?> total)
        </span>
        <div class="upt-page-links">
            <?php
            $base_url = add_query_arg( array_filter([
                'page'      => 'url-param-tracker',
                's'         => $search,
                'date_from' => $date_from,
                'date_to'   => $date_to,
                'param_key' => $param_key,
            ]), admin_url( 'admin.php' ) );

            for ( $p = 1; $p <= min( $total_pages, 20 ); $p++ ) :
                $active = $p === $current_page;
            ?>
                <a href="<?php echo esc_url( add_query_arg( 'paged', $p, $base_url ) ); ?>"
                   class="upt-page-link <?php echo $active ? 'active' : ''; ?>">
                    <?php echo $p; ?>
                </a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</div>

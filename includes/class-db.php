<?php
defined('ABSPATH') || exit;

/**
 * Handles database creation for copy history.
 * Table: {wp_prefix}verihuman_copy_history
 * Columns: id, product_id, product_name, generated_copy, platform, tone, created_at
 */
class Verihuman_DB
{

    public static function install()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'verihuman_copy_history';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            product_id bigint(20) NOT NULL,
            product_name varchar(255) NOT NULL,
            generated_copy longtext NOT NULL,
            platform varchar(50) NOT NULL DEFAULT 'woocommerce',
            tone varchar(50) NOT NULL DEFAULT 'professional',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY product_id (product_id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public static function get_history(int $product_id, int $limit = 10): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'verihuman_copy_history';
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE product_id = %d ORDER BY created_at DESC LIMIT %d",
                $product_id,
                $limit
            ),
            ARRAY_A
        );
    }

    public static function save_history(int $product_id, string $product_name, string $generated_copy, string $platform, string $tone): void
    {
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'verihuman_copy_history',
            [
                'product_id' => $product_id,
                'product_name' => $product_name,
                'generated_copy' => $generated_copy,
                'platform' => $platform,
                'tone' => $tone,
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s']
        );
    }

    public static function delete_history_item(int $id): void
    {
        global $wpdb;
        $wpdb->delete($wpdb->prefix . 'verihuman_copy_history', ['id' => $id], ['%d']);
    }
}

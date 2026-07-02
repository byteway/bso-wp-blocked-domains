<?php
if (!defined('ABSPATH')) exit;

class BSO_DB {
    public static function create_table() {
        global $wpdb;
        $table = bso_table_name();
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            domain VARCHAR(255) NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY domain_unique (domain)
        ) {$charset_collate};";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public static function drop_table() {
        global $wpdb;
        $table = bso_table_name();
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }

    public static function insert_domains($domains) {
        global $wpdb;
        $table = bso_table_name();
        foreach ($domains as $d) {
            $d = trim($d);
            if ($d === '') continue;
            $wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$table} (domain) VALUES (%s)", $d));
        }
    }

    public static function get_domains($search = '', $per_page = 0, $offset = 0) {
        global $wpdb;
        $table = bso_table_name();
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            if ($per_page > 0) return $wpdb->get_col($wpdb->prepare("SELECT domain FROM {$table} WHERE domain LIKE %s ORDER BY domain ASC LIMIT %d OFFSET %d", $like, $per_page, $offset));
            return $wpdb->get_col($wpdb->prepare("SELECT domain FROM {$table} WHERE domain LIKE %s ORDER BY domain ASC", $like));
        }
        if ($per_page > 0) return $wpdb->get_col($wpdb->prepare("SELECT domain FROM {$table} ORDER BY domain ASC LIMIT %d OFFSET %d", $per_page, $offset));
        return $wpdb->get_col("SELECT domain FROM {$table} ORDER BY domain ASC");
    }

    public static function count_domains($search = '') {
        global $wpdb;
        $table = bso_table_name();
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            return intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE domain LIKE %s", $like)));
        }
        return intval($wpdb->get_var("SELECT COUNT(*) FROM {$table}"));
    }
}

// Activation/uninstall hooks
register_activation_hook(BSO_PLUGIN_FILE, array('BSO_DB', 'create_table'));
register_uninstall_hook(BSO_PLUGIN_FILE, array('BSO_DB', 'drop_table'));

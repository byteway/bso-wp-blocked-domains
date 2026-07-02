<?php

if (!defined('ABSPATH')) {
    exit;
}

class BSO_DB {
    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . 'bso_blocked_domains';
    }

    public static function create_table() {
        global $wpdb;

        $table = self::table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            domain VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY domain_unique (domain)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public static function drop_table() {
        global $wpdb;
        $table = self::table_name();
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }

    public static function is_domain_blocked($domain) {
        global $wpdb;

        $normalized = bso_normalize_domain($domain);
        if ($normalized === '') {
            return false;
        }

        $table = self::table_name();
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE domain = %s LIMIT 1",
                $normalized
            )
        );

        return !empty($exists);
    }

    public static function get_domains($search = '', $per_page = 0, $offset = 0) {
        global $wpdb;

        $table = self::table_name();
        $search = trim((string) $search);
        $per_page = (int) $per_page;
        $offset = (int) $offset;

        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            if ($per_page > 0) {
                return $wpdb->get_col(
                    $wpdb->prepare(
                        "SELECT domain FROM {$table} WHERE domain LIKE %s ORDER BY domain ASC LIMIT %d OFFSET %d",
                        $like,
                        $per_page,
                        $offset
                    )
                );
            }

            return $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT domain FROM {$table} WHERE domain LIKE %s ORDER BY domain ASC",
                    $like
                )
            );
        }

        if ($per_page > 0) {
            return $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT domain FROM {$table} ORDER BY domain ASC LIMIT %d OFFSET %d",
                    $per_page,
                    $offset
                )
            );
        }

        return $wpdb->get_col("SELECT domain FROM {$table} ORDER BY domain ASC");
    }

    public static function count_domains($search = '') {
        global $wpdb;

        $table = self::table_name();
        $search = trim((string) $search);

        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            return (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table} WHERE domain LIKE %s",
                    $like
                )
            );
        }

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
    }

    public static function get_existing_domains($domains) {
        global $wpdb;

        $domains = array_values(array_unique(array_filter(array_map('strval', (array) $domains))));
        if (empty($domains)) {
            return array();
        }

        $table = self::table_name();
        $placeholders = implode(', ', array_fill(0, count($domains), '%s'));
        $sql = "SELECT domain FROM {$table} WHERE domain IN ({$placeholders}) ORDER BY domain ASC";

        $prepared = call_user_func_array(array($wpdb, 'prepare'), array_merge(array($sql), $domains));
        return $wpdb->get_col($prepared);
    }

    public static function insert_domain($domain) {
        global $wpdb;

        $table = self::table_name();
        $result = $wpdb->query(
            $wpdb->prepare(
                "INSERT IGNORE INTO {$table} (domain) VALUES (%s)",
                $domain
            )
        );

        if ($result === false) {
            return false;
        }

        return (int) $result;
    }

    public static function insert_domains_bulk($domains) {
        $inserted = 0;
        foreach ((array) $domains as $domain) {
            $result = self::insert_domain($domain);
            if ($result === false) {
                return false;
            }
            $inserted += (int) $result;
        }

        return $inserted;
    }

    public static function update_domain($old_domain, $new_domain) {
        global $wpdb;

        $table = self::table_name();
        $result = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET domain = %s WHERE domain = %s",
                $new_domain,
                $old_domain
            )
        );

        if ($result === false) {
            return false;
        }

        return (int) $result;
    }

    public static function delete_domains($domains) {
        global $wpdb;

        $domains = array_values(array_unique(array_filter(array_map('strval', (array) $domains))));
        if (empty($domains)) {
            return 0;
        }

        $table = self::table_name();
        $placeholders = implode(', ', array_fill(0, count($domains), '%s'));
        $sql = "DELETE FROM {$table} WHERE domain IN ({$placeholders})";
        $prepared = call_user_func_array(array($wpdb, 'prepare'), array_merge(array($sql), $domains));
        $result = $wpdb->query($prepared);

        if ($result === false) {
            return false;
        }

        return (int) $result;
    }
}

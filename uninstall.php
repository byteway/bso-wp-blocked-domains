<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;
$table = $wpdb->prefix . 'bso_blocked_domains';
$wpdb->query("DROP TABLE IF EXISTS {$table}");

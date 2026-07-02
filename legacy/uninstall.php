<?php
/**
 * Uninstall handler for BSO Block Email Domains
 * Removes plugin database table when plugin is uninstalled.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;
$table = $wpdb->prefix . 'bso_blocked_domains';
$wpdb->query("DROP TABLE IF EXISTS {$table}");

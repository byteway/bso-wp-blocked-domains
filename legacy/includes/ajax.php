<?php
if (!defined('ABSPATH')) exit;

add_action('wp_ajax_bso_add_domain', 'bso_ajax_add_domain');
add_action('wp_ajax_bso_update_domain', 'bso_ajax_update_domain');
add_action('wp_ajax_bso_delete_domains', 'bso_ajax_delete_domains');
add_action('wp_ajax_bso_import_init', 'bso_ajax_import_init');
add_action('wp_ajax_bso_import_chunk', 'bso_ajax_import_chunk');
add_action('wp_ajax_bso_export_invalid', 'bso_ajax_export_invalid');
add_action('wp_ajax_bso_export_list', 'bso_ajax_export_list');
add_action('wp_ajax_bso_set_page_size', 'bso_ajax_set_page_size');
add_action('wp_ajax_bso_restore_domains', 'bso_ajax_restore_domains');

// Note: function implementations are still located in main file and will be moved into this file in a subsequent refactor step.

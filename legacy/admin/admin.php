<?php
if (!defined('ABSPATH')) exit;

require_once BSO_PLUGIN_DIR . 'admin/class-bso-list-table.php';

function bso_admin_menu() {
    add_options_page(
        __('Block Email Domains', 'block-email-domains'),
        __('Block Email Domains', 'block-email-domains'),
        'manage_options',
        'block-email-domains',
        'bso_admin_page'
    );
}
add_action('admin_menu', 'bso_admin_menu');

function bso_admin_enqueue() {
    $hook = get_current_screen();
}
add_action('admin_enqueue_scripts', 'bso_admin_enqueue');

function bso_admin_page() {
    if (!current_user_can('manage_options')) wp_die(__('You do not have sufficient permissions to access this page.', 'block-email-domains'));

    // enqueue admin scripts/styles
    wp_enqueue_script('bso-sweetalert2', BSO_PLUGIN_URL . 'vendor/sweetalert2.min.js', array('jquery'), '11.0.0', true);
    wp_enqueue_script('bso-admin-js', BSO_PLUGIN_URL . 'bso-admin.js', array('bso-sweetalert2'), '1.0', true);
    wp_localize_script('bso-admin-js', 'bsoAdmin', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce_manage' => wp_create_nonce('bso_manage_domains'),
        'nonce_save' => wp_create_nonce('save_blocked_domains'),
        'strings' => array(
            'add_empty' => __('Enter a domain to add', 'block-email-domains'),
            'add_invalid' => __('Invalid domain', 'block-email-domains'),
            'add_failed' => __('Add failed', 'block-email-domains'),
            'request_failed' => __('Request failed', 'block-email-domains'),
            'delete_failed' => __('Delete failed', 'block-email-domains'),
            'delete_confirm' => __('Delete selected domain(s)? This cannot be undone.', 'block-email-domains'),
            'deleted' => __('Deleted %d domains.', 'block-email-domains')
        )
    ));

    // Also localize strings into bsoAdmin.strings if not present
    if (!wp_script_is('bso-admin-js', 'enqueued')) {
        // fallback
    }

    // handle import file upload (preview)
    $import_preview = '';
    $import_counts = array();
    $invalid_preview = array();
    if (isset($_FILES['import_file']) && isset($_FILES['import_file']['tmp_name']) && $_FILES['import_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        try {
            check_admin_referer('save_blocked_domains');
            $contents = file_get_contents($_FILES['import_file']['tmp_name']);
            if ($contents === false) throw new Exception('Failed to read uploaded file');
            $lines = preg_split('/\r\n|\r|\n/', $contents);
            $parsed = bso_parse_import_lines($lines);
            $imported = $parsed['valid'];
            $invalid_lines = $parsed['invalid'];
            $unique_imported = count($imported);
            $current = get_blocked_domains();
            $current = array_values(array_filter(array_map('trim', $current)));
            $current = array_unique($current);
            $duplicates = count(array_intersect($imported, $current));
            if (!empty($imported)) {
                $import_preview = implode(PHP_EOL, $imported);
                $import_counts = array('total_lines'=>count($lines),'unique_imported'=>$unique_imported,'duplicates'=>$duplicates,'invalid_count'=>count($invalid_lines));
                $invalid_preview = $invalid_lines;
            } else {
                echo '<div class="error"><p>' . esc_html(__('No valid domains found in the uploaded file.', 'block-email-domains')) . '</p></div>';
            }
        } catch (Throwable $e) {
            echo '<div class="error"><p>' . esc_html(__('Import preview failed: ', 'block-email-domains')) . esc_html($e->getMessage()) . '</p></div>';
        }
    }

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__('Block Email Domains', 'block-email-domains') . '</h1>';

    // Add / Import UI
    echo '<h2>' . esc_html__('Manage blocked domains', 'block-email-domains') . '</h2>';
    echo '<form method="post" enctype="multipart/form-data">';
    wp_nonce_field('save_blocked_domains');
    echo '<label for="import_file">' . esc_html__('Import domains from file (one per line):', 'block-email-domains') . '</label> ';
    echo '<input type="file" name="import_file" accept=".txt" /> ';
    echo '<input type="submit" class="button" value="' . esc_attr__('Upload and Preview', 'block-email-domains') . '" />';
    echo '</form>';

    // Add single domain input
    echo '<p style="margin-top:1em;"><label for="bso-new-domain">' . esc_html__('Add new blocked domain:', 'block-email-domains') . '</label> ';
    echo '<input type="text" id="bso-new-domain" placeholder="example.com" style="margin-left:6px;" /> ';
    echo '<button id="bso-add-domain" class="button">' . esc_html__('Add', 'block-email-domains') . '</button></p>';

    // Show import preview summary if available
    if (!empty($import_preview)) {
        echo '<h3>' . esc_html__('Import Summary', 'block-email-domains') . '</h3>';
        echo '<p>' . esc_html__('Review and then click Start Import to insert domains.', 'block-email-domains') . '</p>';
        echo '<ul>';
        echo '<li>' . esc_html__('Total lines in file:', 'block-email-domains') . ' <strong>' . intval($import_counts['total_lines']) . '</strong></li>';
        echo '<li>' . esc_html__('Unique domains found in file:', 'block-email-domains') . ' <strong>' . intval($import_counts['unique_imported']) . '</strong></li>';
        echo '<li>' . esc_html__('Duplicates compared to configured blocked domains:', 'block-email-domains') . ' <strong>' . intval($import_counts['duplicates']) . '</strong></li>';
        echo '<li>' . esc_html__('Invalid lines:', 'block-email-domains') . ' <strong>' . intval($import_counts['invalid_count']) . '</strong></li>';
        echo '</ul>';
        echo '<form id="bso-import-form">';
        wp_nonce_field('save_blocked_domains');
        echo '<input type="hidden" id="import_preview" value="' . esc_attr($import_preview) . '" />';
        echo '<input type="button" id="bso-confirm-import" class="button-primary" value="' . esc_attr__('Start Import', 'block-email-domains') . '" />';
        echo '</form>';
    }

    // Show list table
    $table = new BSO_List_Table();
    $table->prepare_items();
    // explicit search form (visible)
    $current_search = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';
    echo '<form method="get" style="margin-bottom:0.8em;">';
    echo '<input type="hidden" name="page" value="block-email-domains" />';
    echo '<input type="text" name="s" value="' . esc_attr($current_search) . '" placeholder="' . esc_attr__('Search domains', 'block-email-domains') . '" /> ';
    echo '<input type="submit" class="button" value="' . esc_attr__('Search', 'block-email-domains') . '" />';
    if ($current_search !== '') echo ' <a class="button" href="' . esc_url(add_query_arg(array('page'=>'block-email-domains'))) . '">' . esc_html__('Clear', 'block-email-domains') . '</a>';
    echo '</form>';
    echo '<form method="post">';
    $table->display();
    echo '</form>';

    // Export and bulk actions area
    echo '<p style="margin-top:0.8em;">';
    echo '<button id="bso-delete-selected" class="button button-danger">' . esc_html__('Delete selected', 'block-email-domains') . '</button> ';
    echo '<label><input type="checkbox" id="bso-select-all-matching" /> ' . esc_html__('Select all matching results across pages', 'block-email-domains') . '</label> ';
    $export_url = admin_url('admin-ajax.php') . '?action=bso_export_list&nonce=' . wp_create_nonce('bso_manage_domains');
    if (!empty($_REQUEST['s'])) $export_url .= '&search=' . urlencode(sanitize_text_field($_REQUEST['s']));
    echo '<a class="button" href="' . esc_url($export_url) . '">' . esc_html__('Export visible/filtered list (CSV)', 'block-email-domains') . '</a>';
    echo '</p>';

    echo '</div>';
}

// AJAX handlers are still in main file or includes — implement as needed in includes/ajax.php

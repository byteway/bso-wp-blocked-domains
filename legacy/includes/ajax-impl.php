<?php
if (!defined('ABSPATH')) exit;

// Implement AJAX handlers here by delegating to functions in includes or admin files
// bso_ajax_add_domain, bso_ajax_update_domain, bso_ajax_delete_domains, etc.

function bso_ajax_add_domain() {
    if (!current_user_can('manage_options')) wp_send_json_error(array('message'=>'insufficient_permissions'),403);
    $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
    // allow nonce either bso_manage_domains or save_blocked_domains
    if (!wp_verify_nonce($nonce, 'bso_manage_domains') && !wp_verify_nonce($nonce, 'save_blocked_domains')) wp_send_json_error(array('message'=>'invalid_nonce'),400);
    $domain = isset($_POST['domain']) ? sanitize_text_field($_POST['domain']) : '';
    if ($domain === '') wp_send_json_error(array('message'=>'empty_domain'),400);
    $ascii = bso_domain_to_ascii($domain);
    if ($ascii === '' || !bso_is_valid_domain($ascii)) wp_send_json_error(array('message'=>'invalid_domain'),400);
    global $wpdb;
    $table = bso_table_name();
    $res = $wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$table} (domain) VALUES (%s)", $ascii));
    if ($res === false) wp_send_json_error(array('message'=>'db_error'),500);
    wp_send_json_success(array('inserted' => ($res > 0), 'domain' => $ascii));
}

function bso_ajax_update_domain() {
    if (!current_user_can('manage_options')) wp_send_json_error(array('message'=>'insufficient_permissions'),403);
    $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
    if (!wp_verify_nonce($nonce, 'bso_manage_domains')) {
        // allow fallback to save_blocked_domains nonce
        if (!wp_verify_nonce($nonce, 'save_blocked_domains')) wp_send_json_error(array('message'=>'invalid_nonce'),400);
    }
    $old = isset($_POST['old']) ? sanitize_text_field($_POST['old']) : '';
    $new = isset($_POST['new']) ? sanitize_text_field($_POST['new']) : '';
    if ($old === '' || $new === '') wp_send_json_error(array('message'=>'invalid_params'),400);
    $new_ascii = bso_domain_to_ascii($new);
    if ($new_ascii === '' || !bso_is_valid_domain($new_ascii)) wp_send_json_error(array('message'=>'invalid_domain'),400);
    global $wpdb;
    $table = bso_table_name();
    $updated = $wpdb->update($table, array('domain'=>$new_ascii), array('domain'=>$old), array('%s'), array('%s'));
    if ($updated === false) wp_send_json_error(array('message'=>'db_error'),500);
    if ($updated === 0) $wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$table} (domain) VALUES (%s)", $new_ascii));
    wp_send_json_success(array('domain'=>$new_ascii));
}

function bso_ajax_delete_domains() {
    if (!current_user_can('manage_options')) wp_send_json_error(array('message'=>'insufficient_permissions'),403);
    $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
    if (!wp_verify_nonce($nonce, 'bso_manage_domains')) {
        if (!wp_verify_nonce($nonce, 'save_blocked_domains')) wp_send_json_error(array('message'=>'invalid_nonce'),400);
    }
    global $wpdb;
    $table = bso_table_name();
    $deleted = 0; $undo_key = '';
    if (!empty($_POST['delete_all'])) {
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        if ($search === '') {
            $all = $wpdb->get_col("SELECT domain FROM {$table}");
            if (!empty($all)) { $undo_key = wp_generate_password(12, false, false); set_transient('bso_deleted_'.$undo_key,$all,60); $deleted = $wpdb->query("DELETE FROM {$table}"); }
            wp_send_json_success(array('deleted_count'=>$deleted,'undo_key'=>$undo_key));
        } else {
            $like = '%'.$wpdb->esc_like($search).'%';
            $rows = $wpdb->get_col($wpdb->prepare("SELECT domain FROM {$table} WHERE domain LIKE %s", $like));
            if (!empty($rows)) { $undo_key = wp_generate_password(12, false, false); set_transient('bso_deleted_'.$undo_key,$rows,60); $deleted = $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE domain LIKE %s", $like)); }
            wp_send_json_success(array('deleted_count'=>$deleted,'undo_key'=>$undo_key));
        }
    }
    if (!empty($_POST['domains']) && is_array($_POST['domains'])) {
        $domains = array_map('sanitize_text_field', $_POST['domains']);
        if (!empty($domains)) { $undo_key = wp_generate_password(12,false,false); set_transient('bso_deleted_'.$undo_key,$domains,60); }
        foreach ($domains as $d) { $res = $wpdb->delete($table, array('domain'=>$d), array('%s')); if ($res !== false && $res>0) $deleted += $res; }
    }
    wp_send_json_success(array('deleted_count'=>$deleted,'undo_key'=>$undo_key));
}

// import/init/export/set page size/restore implementations exist previously; those can be moved here as needed.

add_action('wp_ajax_bso_add_domain','bso_ajax_add_domain');
add_action('wp_ajax_bso_update_domain','bso_ajax_update_domain');
add_action('wp_ajax_bso_delete_domains','bso_ajax_delete_domains');

// Import initialization: store parsed items in transient for chunked import
function bso_ajax_import_init() {
    if (!current_user_can('manage_options')) wp_send_json_error(array('message'=>'insufficient_permissions'),403);
    $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
    if (!wp_verify_nonce($nonce, 'save_blocked_domains')) wp_send_json_error(array('message'=>'invalid_nonce'),400);
    $import_preview = isset($_POST['import_preview']) ? $_POST['import_preview'] : '';
    $lines = preg_split('/\r\n|\r|\n/', $import_preview);
    $parsed = bso_parse_import_lines($lines);
    $items = $parsed['valid'];
    $invalid = $parsed['invalid'];
    if (empty($items)) wp_send_json_error(array('message'=>'no_items','invalid_count'=>count($invalid),'invalid_preview'=>array_slice($invalid,0,10)),400);
    $key = 'bso_import_' . wp_generate_password(12, false, false);
    set_transient($key, $items, 60 * 60);
    set_transient($key . '_invalid', $invalid, 60 * 60);
    wp_send_json_success(array('key'=>$key,'total'=>count($items),'invalid_count'=>count($invalid),'invalid_preview'=>array_slice($invalid,0,10)));
}

// Import chunk handler: insert slice into DB
function bso_ajax_import_chunk() {
    if (!current_user_can('manage_options')) wp_send_json_error(array('message'=>'insufficient_permissions'),403);
    $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
    if (!wp_verify_nonce($nonce, 'save_blocked_domains')) wp_send_json_error(array('message'=>'invalid_nonce'),400);
    $key = isset($_POST['key']) ? sanitize_text_field($_POST['key']) : '';
    $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
    $length = isset($_POST['length']) ? intval($_POST['length']) : 100;
    if ($key === '' || $length <= 0) wp_send_json_error(array('message'=>'invalid_params'),400);
    $items = get_transient($key);
    if ($items === false) wp_send_json_error(array('message'=>'expired'),410);
    $chunk = array_slice($items, $start, $length);
    $before_count = BSO_DB::count_domains();
    BSO_DB::insert_domains($chunk);
    $after_count = BSO_DB::count_domains();
    $imported = $after_count - $before_count;
    if ($start + $length >= count($items)) delete_transient($key);
    wp_send_json_success(array('imported'=>$imported,'merged_total'=>$after_count));
}

// Export invalid preview as CSV (supports invalid_preview JSON or transient key)
function bso_ajax_export_invalid() {
    if (!current_user_can('manage_options')) wp_send_json_error(array('message'=>'insufficient_permissions'),403);
    $nonce = isset($_REQUEST['nonce']) ? sanitize_text_field($_REQUEST['nonce']) : '';
    if (!wp_verify_nonce($nonce, 'save_blocked_domains')) wp_send_json_error(array('message'=>'invalid_nonce'),400);
    $invalid = array();
    if (!empty($_REQUEST['invalid_preview'])) {
        $json = wp_unslash($_REQUEST['invalid_preview']);
        $decoded = json_decode($json, true);
        if (is_array($decoded)) $invalid = $decoded;
    } elseif (!empty($_REQUEST['key'])) {
        $key = sanitize_text_field($_REQUEST['key']);
        $invalid = get_transient($key . '_invalid');
        if ($invalid === false) $invalid = array();
    }
    if (empty($invalid)) wp_send_json_error(array('message'=>'no_invalid'),400);
    $filename = 'bso-import-invalid-lines-' . date('Ymd-His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output','w');
    fputcsv($out, array('line','value'));
    foreach ($invalid as $item) fputcsv($out, array(intval($item['line']), $item['value']));
    fclose($out);
    exit;
}

// Export list as CSV
function bso_ajax_export_list() {
    if (!current_user_can('manage_options')) wp_send_json_error(array('message'=>'insufficient_permissions'),403);
    $nonce = isset($_REQUEST['nonce']) ? sanitize_text_field($_REQUEST['nonce']) : '';
    if (!wp_verify_nonce($nonce, 'bso_manage_domains')) wp_send_json_error(array('message'=>'invalid_nonce'),400);
    $search = isset($_REQUEST['search']) ? sanitize_text_field($_REQUEST['search']) : '';
    $rows = BSO_DB::get_domains($search);
    if (empty($rows)) wp_send_json_error(array('message'=>'no_items'),400);
    $filename = 'bso-blocked-domains-' . date('Ymd-His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output','w');
    fputcsv($out, array('domain'));
    foreach ($rows as $r) fputcsv($out, array($r));
    fclose($out);
    exit;
}

// Set page size
function bso_ajax_set_page_size() {
    if (!current_user_can('manage_options')) wp_send_json_error(array('message'=>'insufficient_permissions'),403);
    $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
    if (!wp_verify_nonce($nonce, 'bso_manage_domains')) wp_send_json_error(array('message'=>'invalid_nonce'),400);
    $size = isset($_POST['size']) ? intval($_POST['size']) : 50;
    $allowed = array(10,25,50,100);
    if (!in_array($size, $allowed)) wp_send_json_error(array('message'=>'invalid_size'),400);
    update_option('bso_page_size', $size);
    wp_send_json_success(array('size'=>$size));
}

// Restore deleted domains
function bso_ajax_restore_domains() {
    if (!current_user_can('manage_options')) wp_send_json_error(array('message'=>'insufficient_permissions'),403);
    $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
    if (!wp_verify_nonce($nonce, 'bso_manage_domains')) wp_send_json_error(array('message'=>'invalid_nonce'),400);
    $key = isset($_POST['key']) ? sanitize_text_field($_POST['key']) : '';
    if ($key === '') wp_send_json_error(array('message'=>'invalid_key'),400);
    $trans_key = 'bso_deleted_' . $key;
    $items = get_transient($trans_key);
    if ($items === false || !is_array($items) || empty($items)) wp_send_json_error(array('message'=>'no_items'),404);
    BSO_DB::insert_domains($items);
    delete_transient($trans_key);
    wp_send_json_success(array('restored_count'=>count($items)));
}


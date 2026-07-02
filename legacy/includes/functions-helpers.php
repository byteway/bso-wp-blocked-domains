<?php
if (!defined('ABSPATH')) exit;

// Helper to convert IDN domain to ASCII (punycode)
function bso_domain_to_ascii($domain) {
    $domain = trim($domain);
    if ($domain === '') return '';
    if (function_exists('idn_to_ascii')) {
        if (defined('INTL_IDNA_VARIANT_UTS46')) {
            $ascii = idn_to_ascii($domain, 0, INTL_IDNA_VARIANT_UTS46);
        } else {
            $ascii = idn_to_ascii($domain);
        }

// Block registration by email domain (front-end)
function bso_block_email_domains($errors, $sanitized_user_login, $user_email) {
    $blocked_domains = get_blocked_domains();
    $email_domain = substr(strrchr($user_email, "@"), 1);
    if (in_array($email_domain, $blocked_domains)) {
        $errors->add('blocked_email_domain', __('Registration using this email domain is not allowed.', 'block-email-domains'));
    }
    return $errors;
}
add_filter('registration_errors', 'bso_block_email_domains', 10, 3);

// Block when admin creates/updates user via profile
function bso_block_email_domains_admin($errors, $update, $user) {
    $blocked_domains = get_blocked_domains();
    $email_domain = substr(strrchr($user->user_email, "@"), 1);
    if (in_array($email_domain, $blocked_domains)) {
        $errors->add('blocked_email_domain', __('Registration using this email domain is not allowed.', 'block-email-domains'));
    }
}
add_action('user_profile_update_errors', 'bso_block_email_domains_admin', 10, 3);
        if ($ascii === false) return '';
        return $ascii;
    }
    return $domain;
}

function bso_is_valid_domain($domain) {
    $domain = trim($domain);
    if ($domain === '') return false;
    $ascii = bso_domain_to_ascii($domain);
    if ($ascii === '') return false;
    $domain = $ascii;
    if (preg_match('/\s|@/', $domain)) return false;
    if (strpos($domain, '.') === false) return false;
    if ($domain[0] === '.' || substr($domain, -1) === '.') return false;
    if (strlen($domain) > 253) return false;
    $domain = strtolower($domain);
    $labels = explode('.', $domain);
    foreach ($labels as $label) {
        if ($label === '' || strlen($label) > 63) return false;
        if ($label[0] === '-' || substr($label, -1) === '-') return false;
        if (!preg_match('/^[A-Za-z0-9-]+$/', $label)) return false;
    }
    return true;
}

function bso_normalize_domain_list($lines) {
    $items = array_map('sanitize_text_field', $lines);
    $items = array_values(array_filter(array_map('trim', $items)));
    $items = array_unique($items);
    return $items;
}

// Table name helper
function bso_table_name() {
    global $wpdb;
    return $wpdb->prefix . 'bso_blocked_domains';
}

// Load plugin textdomain
function bso_load_textdomain() {
    load_plugin_textdomain('block-email-domains', false, dirname(plugin_basename(__FILE__)) . '/../languages');
}
add_action('plugins_loaded', 'bso_load_textdomain');

// Backwards-compatible wrappers
function bso_insert_domains($domains) {
    return BSO_DB::insert_domains($domains);
}

function get_blocked_domains() {
    return BSO_DB::get_domains();
}

function bso_save_all_domains_db($domains) {
    // Truncate and insert
    global $wpdb;
    $table = bso_table_name();
    $wpdb->query("TRUNCATE TABLE {$table}");
    BSO_DB::insert_domains($domains);
}

// Parse import lines and return valid and invalid entries with line numbers
function bso_parse_import_lines($lines) {
    $valid = array();
    $invalid = array();
    $lineNo = 0;
    foreach ($lines as $raw) {
        $lineNo++;
        $val = trim($raw);
        if ($val === '') continue;
        $san = sanitize_text_field($val);
        if (bso_is_valid_domain($san)) {
            $valid[] = $san;
        } else {
            $invalid[] = array('line' => $lineNo, 'value' => $val);
        }
    }
    $valid = array_values(array_unique($valid));
    return array('valid' => $valid, 'invalid' => $invalid);
}

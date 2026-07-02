<?php

if (!defined('ABSPATH')) {
    exit;
}

function bso_domain_to_ascii($domain) {
    $domain = trim((string) $domain);
    if ($domain === '') {
        return '';
    }

    if (!function_exists('idn_to_ascii')) {
        return strtolower($domain);
    }

    if (defined('INTL_IDNA_VARIANT_UTS46')) {
        $ascii = idn_to_ascii($domain, 0, INTL_IDNA_VARIANT_UTS46);
    } else {
        $ascii = idn_to_ascii($domain);
    }

    if ($ascii === false || $ascii === null) {
        return '';
    }

    return strtolower($ascii);
}

function bso_normalize_domain($domain) {
    $domain = sanitize_text_field((string) $domain);
    $domain = trim($domain);

    if ($domain === '' || strpos($domain, '@') !== false) {
        return '';
    }

    return bso_domain_to_ascii($domain);
}

function bso_is_valid_domain($domain) {
    $domain = bso_normalize_domain($domain);
    if ($domain === '') {
        return false;
    }

    if (strpos($domain, '.') === false) {
        return false;
    }

    if ($domain[0] === '.' || substr($domain, -1) === '.') {
        return false;
    }

    if (strlen($domain) > 253) {
        return false;
    }

    $labels = explode('.', $domain);
    foreach ($labels as $label) {
        if ($label === '' || strlen($label) > 63) {
            return false;
        }
        if ($label[0] === '-' || substr($label, -1) === '-') {
            return false;
        }
        if (!preg_match('/^[a-z0-9-]+$/', $label)) {
            return false;
        }
    }

    return true;
}

function bso_extract_domain_from_email($email) {
    $email = sanitize_email((string) $email);
    if ($email === '' || strpos($email, '@') === false) {
        return '';
    }

    $domain = substr(strrchr($email, '@'), 1);
    return bso_normalize_domain($domain);
}

function bso_add_blocked_domain_error($errors) {
    $errors->add(
        'blocked_email_domain',
        __('Registratie met dit e-maildomein is niet toegestaan.', 'block-email-domains')
    );
}

function bso_validate_registration_domain($errors, $sanitized_user_login, $user_email) {
    $domain = bso_extract_domain_from_email($user_email);
    if ($domain !== '' && BSO_DB::is_domain_blocked($domain)) {
        bso_add_blocked_domain_error($errors);
    }

    return $errors;
}
add_filter('registration_errors', 'bso_validate_registration_domain', 10, 3);

function bso_validate_profile_domain($errors, $update, $user) {
    if (!is_object($user) || empty($user->user_email)) {
        return;
    }

    $domain = bso_extract_domain_from_email($user->user_email);
    if ($domain !== '' && BSO_DB::is_domain_blocked($domain)) {
        bso_add_blocked_domain_error($errors);
    }
}
add_action('user_profile_update_errors', 'bso_validate_profile_domain', 10, 3);

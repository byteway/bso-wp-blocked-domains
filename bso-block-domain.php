<?php
/*
Plugin Name: BSO Block Email Domains
Plugin URI: https://byteway.eu/block-email-domains
Description: Blocks specific email domains during new user registration.
Version: 1.0
Author: Byteway Software Ontwikkeling
License: GPLv2 or later
Text Domain: block-email-domains

Disallowed domains: https://github.com/disposable-email-domains/disposable-email-domains/blob/main/disposable_email_blocklist.conf

Text file contains values without quotes or comma.
*/

// Function to get blocked domains from the text file
function get_blocked_domains() {
    $file_path = plugin_dir_path(__FILE__) . 'blocked-domains.txt';
    $blocked_domains = array();

    if (file_exists($file_path)) {
        $blocked_domains = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    }

    return $blocked_domains;
}

// Function to save blocked domains to the text file
function save_blocked_domains($domains) {
    $file_path = plugin_dir_path(__FILE__) . 'blocked-domains.txt';
    file_put_contents($file_path, implode(PHP_EOL, $domains));
}

// Function to block email domains during registration
function block_email_domains($errors, $sanitized_user_login, $user_email) {
    $blocked_domains = get_blocked_domains();
    $email_domain = substr(strrchr($user_email, "@"), 1);

    if (in_array($email_domain, $blocked_domains)) {
        $errors->add('blocked_email_domain', __('Registration using this email domain is not allowed.'));
    }

    return $errors;
}
add_filter('registration_errors', 'block_email_domains', 10, 3);

// Function to block email domains during admin user creation
function block_email_domains_admin($errors, $update, $user) {
    $blocked_domains = get_blocked_domains();
    $email_domain = substr(strrchr($user->user_email, "@"), 1);

    if (in_array($email_domain, $blocked_domains)) {
        $errors->add('blocked_email_domain', __('Registration using this email domain is not allowed.'));
    }
}
add_action('user_profile_update_errors', 'block_email_domains_admin', 10, 3);

// Function to add the admin menu item
function block_email_domains_menu() {
    add_options_page(
        'Block Email Domains',
        'Block Email Domains',
        'manage_options',
        'block-email-domains',
        'block_email_domains_options_page'
    );
}
add_action('admin_menu', 'block_email_domains_menu');

// Function to display the admin options page
function block_email_domains_options_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    if (isset($_POST['blocked_domains'])) {
        check_admin_referer('save_blocked_domains');
        $domains = array_map('sanitize_text_field', explode(PHP_EOL, $_POST['blocked_domains']));
        save_blocked_domains($domains);
        echo '<div class="updated"><p>Blocked domains updated.</p></div>';
    }

    $blocked_domains = implode(PHP_EOL, get_blocked_domains());
    ?>
    <div class="wrap">
        <h1>Block Email Domains</h1>
        <form method="post" action="">
            <?php wp_nonce_field('save_blocked_domains'); ?>
            <textarea name="blocked_domains" rows="10" cols="50" class="large-text"><?php echo esc_textarea($blocked_domains); ?></textarea>
            <p class="submit">
                <input type="submit" class="button-primary" value="Save Changes" />
            </p>
        </form>
    </div>
    <?php
}

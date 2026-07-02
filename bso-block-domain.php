<?php
/*
Plugin Name: BSO Block Email Domains
Plugin URI: https://byteway.eu/wp/disallowed-domains/
Description: Blocks specific email domains during user registration and profile updates.
Version: 2.0.0
Author: Byteway Software Ontwikkeling
License: GPLv2 or later
Text Domain: block-email-domains
*/

if (!defined('ABSPATH')) {
    exit;
}

define('BSO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BSO_PLUGIN_FILE', __FILE__);
define('BSO_PLUGIN_VERSION', '2.0.0');

require_once BSO_PLUGIN_DIR . 'includes/class-bso-db.php';
require_once BSO_PLUGIN_DIR . 'includes/domain-validation.php';
require_once BSO_PLUGIN_DIR . 'includes/class-bso-domain-service.php';

if (is_admin()) {
    require_once BSO_PLUGIN_DIR . 'admin/class-bso-admin-page.php';
}

function bso_load_textdomain() {
    load_plugin_textdomain('block-email-domains', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('plugins_loaded', 'bso_load_textdomain');

register_activation_hook(BSO_PLUGIN_FILE, array('BSO_DB', 'create_table'));

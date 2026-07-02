<?php
/*
Plugin Name: BSO Block Email Domains
Plugin URI: https://byteway.eu/wp/disallowed-domains/
Description: Blocks specific email domains during new user registration. DB-backed blocked-domains list with import, add/edit, export and admin management UI. Includes IDN/punycode support, localized admin UI and undo for deletes.
Version: 1.7
Author: Byteway Software Ontwikkeling
License: GPLv2 or later
Text Domain: block-email-domains
*/

if (!defined('ABSPATH')) exit;

define('BSO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BSO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BSO_PLUGIN_FILE', __FILE__);

// Core includes
require_once BSO_PLUGIN_DIR . 'includes/functions-helpers.php';
require_once BSO_PLUGIN_DIR . 'includes/class-bso-db.php';
require_once BSO_PLUGIN_DIR . 'includes/ajax.php';
require_once BSO_PLUGIN_DIR . 'includes/ajax-impl.php';

// Admin
if (is_admin()) {
    require_once BSO_PLUGIN_DIR . 'admin/class-bso-list-table.php';
    require_once BSO_PLUGIN_DIR . 'admin/admin.php';
}

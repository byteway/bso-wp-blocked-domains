<?php

if (!defined('ABSPATH')) {
    exit;
}

class BSO_Admin_Page {
    const OPTION_PAGE_SIZE = 'bso_page_size';

    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'register_menu'));

        add_action('admin_post_bso_add_domain', array(__CLASS__, 'handle_add_domain'));
        add_action('admin_post_bso_update_domain', array(__CLASS__, 'handle_update_domain'));
        add_action('admin_post_bso_delete_domains', array(__CLASS__, 'handle_delete_domains'));
        add_action('admin_post_bso_import_domains', array(__CLASS__, 'handle_import_domains'));
        add_action('admin_post_bso_set_page_size', array(__CLASS__, 'handle_set_page_size'));
        add_action('admin_post_bso_restore_domains', array(__CLASS__, 'handle_restore_domains'));
        add_action('admin_post_bso_export_domains', array(__CLASS__, 'handle_export_domains'));
    }

    public static function register_menu() {
        add_options_page(
            __('Block Email Domains', 'block-email-domains'),
            __('Block Email Domains', 'block-email-domains'),
            'manage_options',
            'bso-blocked-domains',
            array(__CLASS__, 'render_page')
        );
    }

    public static function render_page() {
        self::require_capability();

        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $paged = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
        $edit_domain = isset($_GET['edit']) ? sanitize_text_field(wp_unslash($_GET['edit'])) : '';
        $page_size = self::get_page_size();

        $total = BSO_DB::count_domains($search);
        $total_pages = max(1, (int) ceil($total / $page_size));

        if ($paged > $total_pages) {
            $paged = $total_pages;
        }

        $offset = ($paged - 1) * $page_size;
        $domains = BSO_DB::get_domains($search, $page_size, $offset);

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Block Email Domains', 'block-email-domains') . '</h1>';
        echo '<p>' . esc_html__('Beheer hier de lijst met geblokkeerde e-maildomeinen.', 'block-email-domains') . '</p>';

        self::render_notice();
        self::render_add_form();
        self::render_edit_form($edit_domain, $search, $paged);
        self::render_import_form();
        self::render_controls($search, $page_size);
        self::render_table($domains, $search);
        self::render_pagination($paged, $total_pages, $search);

        echo '</div>';
    }

    private static function render_notice() {
        $notice = isset($_GET['bso_notice']) ? sanitize_key($_GET['bso_notice']) : '';
        if ($notice === '') {
            return;
        }

        $class = 'notice notice-info is-dismissible';
        $message = '';

        if ($notice === 'added') {
            $class = 'notice notice-success is-dismissible';
            $message = __('Domein toegevoegd.', 'block-email-domains');
        } elseif ($notice === 'updated') {
            $class = 'notice notice-success is-dismissible';
            $message = __('Domein bijgewerkt.', 'block-email-domains');
        } elseif ($notice === 'deleted') {
            $class = 'notice notice-success is-dismissible';
            $deleted = isset($_GET['deleted']) ? (int) $_GET['deleted'] : 0;
            $message = sprintf(__('Verwijderd: %d domeinen.', 'block-email-domains'), $deleted);
        } elseif ($notice === 'restored') {
            $class = 'notice notice-success is-dismissible';
            $restored = isset($_GET['restored']) ? (int) $_GET['restored'] : 0;
            $message = sprintf(__('Hersteld: %d domeinen.', 'block-email-domains'), $restored);
        } elseif ($notice === 'imported') {
            $class = 'notice notice-success is-dismissible';
            $inserted = isset($_GET['inserted']) ? (int) $_GET['inserted'] : 0;
            $invalid = isset($_GET['invalid']) ? (int) $_GET['invalid'] : 0;
            $message = sprintf(__('Import klaar. Toegevoegd: %1$d, ongeldig: %2$d.', 'block-email-domains'), $inserted, $invalid);
        } elseif ($notice === 'saved_page_size') {
            $class = 'notice notice-success is-dismissible';
            $message = __('Paginagrootte opgeslagen.', 'block-email-domains');
        } elseif ($notice === 'error') {
            $class = 'notice notice-error is-dismissible';
            $message = isset($_GET['bso_message']) ? sanitize_text_field(wp_unslash($_GET['bso_message'])) : __('Er is een fout opgetreden.', 'block-email-domains');
        }

        if ($message === '') {
            return;
        }

        echo '<div class="' . esc_attr($class) . '"><p>' . esc_html($message) . '</p>';

        if ($notice === 'deleted' && isset($_GET['undo']) && $_GET['undo'] !== '') {
            $undo_key = sanitize_text_field(wp_unslash($_GET['undo']));
            $url = wp_nonce_url(
                admin_url('admin-post.php?action=bso_restore_domains&undo=' . rawurlencode($undo_key)),
                'bso_manage_domains'
            );
            echo '<p><a class="button button-secondary" href="' . esc_url($url) . '">' . esc_html__('Ongedaan maken', 'block-email-domains') . '</a></p>';
        }

        echo '</div>';
    }

    private static function render_add_form() {
        echo '<h2>' . esc_html__('Domein toevoegen', 'block-email-domains') . '</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('bso_manage_domains');
        echo '<input type="hidden" name="action" value="bso_add_domain" />';
        echo '<input type="text" name="domain" class="regular-text" placeholder="voorbeeld.nl" required /> ';
        submit_button(__('Toevoegen', 'block-email-domains'), 'primary', 'submit', false);
        echo '</form>';
    }

    private static function render_import_form() {
        echo '<h2>' . esc_html__('Import (een domein per regel)', 'block-email-domains') . '</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('bso_manage_domains');
        echo '<input type="hidden" name="action" value="bso_import_domains" />';
        echo '<textarea name="import_text" rows="7" class="large-text code" placeholder="voorbeeld.nl&#10;voorbeeld.com"></textarea>';
        echo '<p>';
        submit_button(__('Importeren', 'block-email-domains'), 'secondary', 'submit', false);
        echo '</p>';
        echo '</form>';
    }

    private static function render_edit_form($edit_domain, $search, $paged) {
        if ($edit_domain === '') {
            return;
        }

        echo '<h2>' . esc_html__('Domein bewerken', 'block-email-domains') . '</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('bso_manage_domains');
        echo '<input type="hidden" name="action" value="bso_update_domain" />';
        echo '<input type="hidden" name="old_domain" value="' . esc_attr($edit_domain) . '" />';
        echo '<input type="hidden" name="redirect_search" value="' . esc_attr($search) . '" />';
        echo '<input type="hidden" name="redirect_paged" value="' . esc_attr((string) $paged) . '" />';
        echo '<input type="text" name="new_domain" class="regular-text" value="' . esc_attr($edit_domain) . '" required /> ';
        submit_button(__('Opslaan', 'block-email-domains'), 'primary', 'submit', false);
        echo '</form>';
    }

    private static function render_controls($search, $page_size) {
        echo '<h2>' . esc_html__('Overzicht', 'block-email-domains') . '</h2>';
        echo '<form method="get" action="' . esc_url(admin_url('options-general.php')) . '">';
        echo '<input type="hidden" name="page" value="bso-blocked-domains" />';
        echo '<input type="search" name="s" value="' . esc_attr($search) . '" placeholder="' . esc_attr__('Zoek domein', 'block-email-domains') . '" /> ';
        submit_button(__('Zoeken', 'block-email-domains'), 'secondary', 'submit', false);
        echo '</form>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-top:10px;">';
        wp_nonce_field('bso_manage_domains');
        echo '<input type="hidden" name="action" value="bso_set_page_size" />';
        echo '<label for="bso_page_size">' . esc_html__('Items per pagina', 'block-email-domains') . '</label> ';
        echo '<input id="bso_page_size" type="number" min="10" max="500" name="page_size" value="' . esc_attr((string) $page_size) . '" style="width:90px;" /> ';
        submit_button(__('Opslaan', 'block-email-domains'), 'secondary', 'submit', false);
        echo '</form>';

        $export_url = wp_nonce_url(admin_url('admin-post.php?action=bso_export_domains&s=' . rawurlencode($search)), 'bso_manage_domains');
        echo '<p><a class="button" href="' . esc_url($export_url) . '">' . esc_html__('Exporteer CSV (huidige filter)', 'block-email-domains') . '</a></p>';
    }

    private static function render_table($domains, $search) {
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('bso_manage_domains');
        echo '<input type="hidden" name="action" value="bso_delete_domains" />';
        echo '<input type="hidden" name="search" value="' . esc_attr($search) . '" />';

        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        echo '<th style="width:30px;"><input type="checkbox" onclick="jQuery(\'.bso-checkbox\').prop(\'checked\', this.checked)"></th>';
        echo '<th>' . esc_html__('Domein', 'block-email-domains') . '</th>';
        echo '<th style="width:340px;">' . esc_html__('Acties', 'block-email-domains') . '</th>';
        echo '</tr></thead><tbody>';

        if (empty($domains)) {
            echo '<tr><td colspan="3">' . esc_html__('Geen domeinen gevonden.', 'block-email-domains') . '</td></tr>';
        } else {
            foreach ($domains as $domain) {
                $edit_url = add_query_arg(
                    array(
                        'page' => 'bso-blocked-domains',
                        's' => $search,
                        'edit' => $domain,
                    ),
                    admin_url('options-general.php')
                );

                echo '<tr>';
                echo '<td><input class="bso-checkbox" type="checkbox" name="domains[]" value="' . esc_attr($domain) . '" /></td>';
                echo '<td><code>' . esc_html($domain) . '</code></td>';
                echo '<td>';

                echo '<a class="button button-small" href="' . esc_url($edit_url) . '">' . esc_html__('Bewerken', 'block-email-domains') . '</a>';

                echo '</td>';
                echo '</tr>';
            }
        }

        echo '</tbody></table>';

        echo '<p style="margin-top:10px;">';
        submit_button(__('Selectie verwijderen', 'block-email-domains'), 'delete', 'submit', false);
        echo ' ';
        echo '<button type="submit" class="button" name="delete_all_matching" value="1" onclick="return confirm(\'' . esc_js(__('Alle gefilterde domeinen verwijderen?', 'block-email-domains')) . '\');">' . esc_html__('Alles verwijderen op basis van filter', 'block-email-domains') . '</button>';
        echo '</p>';

        echo '</form>';
    }

    private static function render_pagination($current_page, $total_pages, $search) {
        if ($total_pages <= 1) {
            return;
        }

        $base = admin_url('options-general.php?page=bso-blocked-domains&s=' . rawurlencode($search) . '&paged=%#%');
        echo '<div class="tablenav"><div class="tablenav-pages">';
        echo wp_kses_post(
            paginate_links(array(
                'base' => $base,
                'format' => '',
                'current' => $current_page,
                'total' => $total_pages,
                'type' => 'plain',
            ))
        );
        echo '</div></div>';
    }

    public static function handle_add_domain() {
        self::guard_manage_request();

        $domain = isset($_POST['domain']) ? wp_unslash($_POST['domain']) : '';
        $result = BSO_Domain_Service::add_domain($domain);

        if (!$result['ok']) {
            self::redirect_with_error($result['message']);
        }

        self::redirect_with_notice('added', self::redirect_state_from_request());
    }

    public static function handle_update_domain() {
        self::guard_manage_request();

        $old_domain = isset($_POST['old_domain']) ? wp_unslash($_POST['old_domain']) : '';
        $new_domain = isset($_POST['new_domain']) ? wp_unslash($_POST['new_domain']) : '';
        $result = BSO_Domain_Service::update_domain($old_domain, $new_domain);

        if (!$result['ok']) {
            self::redirect_with_error($result['message']);
        }

        self::redirect_with_notice('updated', self::redirect_state_from_request());
    }

    public static function handle_delete_domains() {
        self::guard_manage_request();

        $search = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';
        $delete_all = !empty($_POST['delete_all_matching']);

        if ($delete_all) {
            $domains = BSO_DB::get_domains($search, 0, 0);
        } else {
            $domains = isset($_POST['domains']) ? (array) wp_unslash($_POST['domains']) : array();
        }

        $result = BSO_Domain_Service::delete_domains($domains, true);

        if (!$result['ok']) {
            self::redirect_with_error($result['message']);
        }

        self::redirect_with_notice('deleted', array_merge(self::redirect_state_from_request(), array(
            'deleted' => (int) $result['data']['deleted_count'],
            'undo' => $result['data']['undo_key'],
        )));
    }

    public static function handle_import_domains() {
        self::guard_manage_request();

        $raw_input = isset($_POST['import_text']) ? wp_unslash($_POST['import_text']) : '';
        if (trim($raw_input) === '') {
            self::redirect_with_error(__('Geen importdata ontvangen.', 'block-email-domains'));
        }

        $init = BSO_Domain_Service::import_init($raw_input);
        if (!$init['ok']) {
            self::redirect_with_error($init['message']);
        }

        $start = 0;
        $chunk_size = 200;
        $inserted_total = 0;

        while (true) {
            $step = BSO_Domain_Service::import_chunk($init['data']['key'], $start, $chunk_size);
            if (!$step['ok']) {
                self::redirect_with_error($step['message']);
            }

            $inserted_total += (int) $step['data']['inserted'];
            if (!empty($step['data']['done'])) {
                break;
            }

            $start = (int) $step['data']['next_start'];
        }

        self::redirect_with_notice('imported', array_merge(self::redirect_state_from_request(), array(
            'inserted' => $inserted_total,
            'invalid' => (int) $init['data']['invalid_count'],
        )));
    }

    public static function handle_set_page_size() {
        self::guard_manage_request();

        $size = isset($_POST['page_size']) ? (int) $_POST['page_size'] : 50;
        if ($size < 10) {
            $size = 10;
        }
        if ($size > 500) {
            $size = 500;
        }

        update_option(self::OPTION_PAGE_SIZE, $size);
        self::redirect_with_notice('saved_page_size', self::redirect_state_from_request());
    }

    public static function handle_restore_domains() {
        self::guard_manage_request('GET');

        $undo_key = isset($_GET['undo']) ? wp_unslash($_GET['undo']) : '';
        $result = BSO_Domain_Service::restore_domains($undo_key);

        if (!$result['ok']) {
            self::redirect_with_error($result['message']);
        }

        self::redirect_with_notice('restored', array_merge(self::redirect_state_from_request('GET'), array(
            'restored' => (int) $result['data']['restored_count'],
        )));
    }

    public static function handle_export_domains() {
        self::guard_manage_request('GET');

        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $domains = BSO_DB::get_domains($search, 0, 0);

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="blocked-domains.csv"');

        $output = fopen('php://output', 'w');
        fputcsv($output, array('domain'));

        foreach ($domains as $domain) {
            fputcsv($output, array($domain));
        }

        fclose($output);
        exit;
    }

    private static function require_capability() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Je hebt geen rechten voor deze pagina.', 'block-email-domains'));
        }
    }

    private static function guard_manage_request($method = 'POST') {
        self::require_capability();

        if (strtoupper($method) === 'GET') {
            check_admin_referer('bso_manage_domains');
            return;
        }

        check_admin_referer('bso_manage_domains');
    }

    private static function get_page_size() {
        $size = (int) get_option(self::OPTION_PAGE_SIZE, 50);
        if ($size < 10) {
            $size = 10;
        }
        if ($size > 500) {
            $size = 500;
        }

        return $size;
    }

    private static function redirect_with_notice($notice, $extra = array()) {
        $args = array_merge(array('page' => 'bso-blocked-domains', 'bso_notice' => $notice), $extra);
        wp_safe_redirect(add_query_arg($args, admin_url('options-general.php')));
        exit;
    }

    private static function redirect_with_error($message) {
        self::redirect_with_notice('error', array_merge(self::redirect_state_from_request(), array('bso_message' => (string) $message)));
    }

    private static function redirect_state_from_request($method = 'POST') {
        $source = strtoupper($method) === 'GET' ? $_GET : $_POST;
        $args = array();

        if (isset($source['search'])) {
            $args['s'] = sanitize_text_field(wp_unslash($source['search']));
        } elseif (isset($source['redirect_search'])) {
            $args['s'] = sanitize_text_field(wp_unslash($source['redirect_search']));
        } elseif (isset($source['s'])) {
            $args['s'] = sanitize_text_field(wp_unslash($source['s']));
        }

        if (isset($source['paged'])) {
            $args['paged'] = max(1, (int) $source['paged']);
        } elseif (isset($source['redirect_paged'])) {
            $args['paged'] = max(1, (int) $source['redirect_paged']);
        }

        return $args;
    }
}

BSO_Admin_Page::init();

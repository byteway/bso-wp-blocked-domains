<?php
if (!defined('ABSPATH')) exit;

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class BSO_List_Table extends WP_List_Table {
    public function __construct() {
        parent::__construct(array(
            'singular' => 'bso_domain',
            'plural' => 'bso_domains',
            'ajax' => false
        ));
    }

    public function get_columns() {
        $columns = array(
            'cb' => '<input type="checkbox" id="bso-check-all" />',
            'domain' => __('Domain', 'block-email-domains'),
            'actions' => __('Actions', 'block-email-domains')
        );
        return $columns;
    }

    public function column_cb($item) {
        return sprintf('<input type="checkbox" class="bso-domain-chk" name="domains[]" value="%s" />', esc_attr($item));
    }

    public function column_domain($item) {
        return esc_html($item);
    }

    public function column_actions($item) {
        $edit = sprintf('<button type="button" class="button bso-edit-row" data-domain="%s">%s</button>', esc_attr($item), __('Edit', 'block-email-domains'));
        $del = sprintf('<button type="button" class="button bso-delete-row" data-domain="%s">%s</button>', esc_attr($item), __('Delete', 'block-email-domains'));
        return $edit . ' ' . $del;
    }

    public function get_bulk_actions() {
        return array('delete' => __('Delete', 'block-email-domains'));
    }

    public function prepare_items() {
        $per_page = intval(get_option('bso_page_size', 50));
        $current_page = $this->get_pagenum();
        $search = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';
        $total_items = BSO_DB::count_domains($search);
        $this->set_pagination_args(array('total_items' => $total_items, 'per_page' => $per_page));
        $this->_column_headers = array($this->get_columns(), array(), array());
        $offset = ($current_page - 1) * $per_page;
        $items = BSO_DB::get_domains($search, $per_page, $offset);
        $this->items = $items;
    }
}

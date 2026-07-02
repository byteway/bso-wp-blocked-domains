<?php

if (!defined('ABSPATH')) {
    exit;
}

function bso_frontend_default_info_title() {
    return __('E-maildomeincontrole', 'block-email-domains');
}

function bso_frontend_default_info_text() {
    return __('Registratie met sommige e-maildomeinen is niet toegestaan. Gebruik een alternatief e-mailadres als je registratie wordt geweigerd.', 'block-email-domains');
}

function bso_shortcode_blocked_domain_info($atts = array()) {
    $atts = shortcode_atts(
        array(
            'title' => '',
            'text' => '',
            'class' => '',
        ),
        $atts,
        'bso_blocked_domain_info'
    );

    $title = trim((string) $atts['title']);
    $text = trim((string) $atts['text']);

    if ($title === '') {
        $title = bso_frontend_default_info_title();
    }

    if ($text === '') {
        $text = bso_frontend_default_info_text();
    }

    $class = 'bso-blocked-domain-info';
    if (!empty($atts['class'])) {
        $class .= ' ' . sanitize_html_class($atts['class']);
    }

    $html = '<section class="' . esc_attr($class) . '">';
    $html .= '<h3>' . esc_html($title) . '</h3>';
    $html .= '<p>' . esc_html($text) . '</p>';
    $html .= '</section>';

    return $html;
}

add_shortcode('bso_blocked_domain_info', 'bso_shortcode_blocked_domain_info');

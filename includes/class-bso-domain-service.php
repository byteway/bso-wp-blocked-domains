<?php

if (!defined('ABSPATH')) {
    exit;
}

class BSO_Domain_Service {
    const UNDO_TTL = 300;
    const IMPORT_TTL = 1800;

    private static function result($ok, $code, $message, $data = array()) {
        return array(
            'ok' => (bool) $ok,
            'code' => (string) $code,
            'message' => (string) $message,
            'data' => (array) $data,
        );
    }

    public static function normalize_domains($domains) {
        $normalized = array();
        $invalid = array();

        foreach ((array) $domains as $index => $domain) {
            $value = bso_normalize_domain($domain);
            if ($value === '' || !bso_is_valid_domain($value)) {
                $invalid[] = array(
                    'index' => $index,
                    'value' => (string) $domain,
                );
                continue;
            }
            $normalized[] = $value;
        }

        $normalized = array_values(array_unique($normalized));

        return array(
            'valid' => $normalized,
            'invalid' => $invalid,
        );
    }

    public static function add_domain($domain) {
        $normalized = bso_normalize_domain($domain);
        if (!bso_is_valid_domain($normalized)) {
            return self::result(false, 'invalid_domain', __('Ongeldig domein.', 'block-email-domains'));
        }

        if (BSO_DB::is_domain_blocked($normalized)) {
            return self::result(false, 'domain_exists', __('Domein bestaat al in de blokkeerlijst.', 'block-email-domains'));
        }

        $inserted = BSO_DB::insert_domain($normalized);
        if ($inserted === false) {
            return self::result(false, 'db_insert_failed', __('Opslaan van domein is mislukt.', 'block-email-domains'));
        }

        return self::result(true, 'domain_added', __('Domein toegevoegd.', 'block-email-domains'), array(
            'domain' => $normalized,
            'inserted' => (int) $inserted,
        ));
    }

    public static function update_domain($old_domain, $new_domain) {
        $old = bso_normalize_domain($old_domain);
        $new = bso_normalize_domain($new_domain);

        if (!bso_is_valid_domain($old) || !bso_is_valid_domain($new)) {
            return self::result(false, 'invalid_domain', __('Ongeldig domein.', 'block-email-domains'));
        }

        if ($old === $new) {
            return self::result(true, 'domain_unchanged', __('Geen wijziging nodig.', 'block-email-domains'), array(
                'domain' => $new,
            ));
        }

        if (!BSO_DB::is_domain_blocked($old)) {
            return self::result(false, 'domain_not_found', __('Bestaand domein niet gevonden.', 'block-email-domains'));
        }

        if (BSO_DB::is_domain_blocked($new)) {
            return self::result(false, 'domain_exists', __('Nieuw domein bestaat al in de blokkeerlijst.', 'block-email-domains'));
        }

        $updated = BSO_DB::update_domain($old, $new);
        if ($updated === false) {
            return self::result(false, 'db_update_failed', __('Bijwerken van domein is mislukt.', 'block-email-domains'));
        }

        return self::result(true, 'domain_updated', __('Domein bijgewerkt.', 'block-email-domains'), array(
            'old' => $old,
            'new' => $new,
            'updated' => (int) $updated,
        ));
    }

    public static function delete_domains($domains, $store_undo = true) {
        $normalized = self::normalize_domains($domains);
        if (empty($normalized['valid'])) {
            return self::result(false, 'no_valid_domains', __('Geen geldige domeinen om te verwijderen.', 'block-email-domains'), array(
                'invalid' => $normalized['invalid'],
            ));
        }

        $existing = BSO_DB::get_existing_domains($normalized['valid']);
        if (empty($existing)) {
            return self::result(false, 'nothing_to_delete', __('Geen bestaande domeinen gevonden om te verwijderen.', 'block-email-domains'));
        }

        $deleted = BSO_DB::delete_domains($existing);
        if ($deleted === false) {
            return self::result(false, 'db_delete_failed', __('Verwijderen van domeinen is mislukt.', 'block-email-domains'));
        }

        $undo_key = '';
        if ($store_undo) {
            $undo_key = self::store_undo_payload($existing);
        }

        return self::result(true, 'domains_deleted', __('Domeinen verwijderd.', 'block-email-domains'), array(
            'deleted_count' => (int) $deleted,
            'deleted_domains' => $existing,
            'undo_key' => $undo_key,
            'invalid' => $normalized['invalid'],
        ));
    }

    public static function restore_domains($undo_key) {
        $undo_key = sanitize_text_field((string) $undo_key);
        if ($undo_key === '') {
            return self::result(false, 'missing_undo_key', __('Ontbrekende herstelcode.', 'block-email-domains'));
        }

        $transient_key = self::undo_transient_key($undo_key);
        $payload = get_transient($transient_key);

        if (!is_array($payload) || empty($payload['domains'])) {
            return self::result(false, 'undo_not_found', __('Herstelgegevens niet gevonden of verlopen.', 'block-email-domains'));
        }

        $inserted = BSO_DB::insert_domains_bulk($payload['domains']);
        if ($inserted === false) {
            return self::result(false, 'db_restore_failed', __('Herstellen van domeinen is mislukt.', 'block-email-domains'));
        }

        delete_transient($transient_key);

        return self::result(true, 'domains_restored', __('Domeinen hersteld.', 'block-email-domains'), array(
            'restored_count' => (int) $inserted,
            'domains' => $payload['domains'],
        ));
    }

    public static function parse_import_lines($lines) {
        $valid = array();
        $invalid = array();

        foreach ((array) $lines as $index => $line) {
            $line_no = $index + 1;
            $raw = trim((string) $line);
            if ($raw === '') {
                continue;
            }

            $normalized = bso_normalize_domain($raw);
            if ($normalized === '' || !bso_is_valid_domain($normalized)) {
                $invalid[] = array(
                    'line' => $line_no,
                    'value' => (string) $line,
                );
                continue;
            }

            $valid[] = $normalized;
        }

        $valid = array_values(array_unique($valid));

        return array(
            'valid' => $valid,
            'invalid' => $invalid,
            'total_valid' => count($valid),
            'total_invalid' => count($invalid),
        );
    }

    public static function import_init($raw_input) {
        $text = (string) $raw_input;
        $lines = preg_split('/\r\n|\r|\n/', $text);
        $parsed = self::parse_import_lines($lines);

        $import_key = wp_generate_password(12, false, false);
        $transient_key = self::import_transient_key($import_key);

        set_transient($transient_key, array(
            'valid' => $parsed['valid'],
            'invalid' => $parsed['invalid'],
            'created_at' => time(),
        ), self::IMPORT_TTL);

        return self::result(true, 'import_initialized', __('Import is voorbereid.', 'block-email-domains'), array(
            'key' => $import_key,
            'total' => $parsed['total_valid'],
            'invalid_count' => $parsed['total_invalid'],
        ));
    }

    public static function import_chunk($import_key, $start, $length) {
        $import_key = sanitize_text_field((string) $import_key);
        $start = max(0, (int) $start);
        $length = max(1, (int) $length);

        if ($import_key === '') {
            return self::result(false, 'missing_import_key', __('Ontbrekende importcode.', 'block-email-domains'));
        }

        $payload = get_transient(self::import_transient_key($import_key));
        if (!is_array($payload) || !isset($payload['valid']) || !is_array($payload['valid'])) {
            return self::result(false, 'import_not_found', __('Importgegevens niet gevonden of verlopen.', 'block-email-domains'));
        }

        $total = count($payload['valid']);
        $chunk = array_slice($payload['valid'], $start, $length);

        if (empty($chunk)) {
            return self::result(true, 'import_complete', __('Geen items meer om te importeren.', 'block-email-domains'), array(
                'start' => $start,
                'length' => $length,
                'processed' => 0,
                'inserted' => 0,
                'total' => $total,
                'done' => true,
            ));
        }

        $inserted = BSO_DB::insert_domains_bulk($chunk);
        if ($inserted === false) {
            return self::result(false, 'db_import_failed', __('Importeren van chunk is mislukt.', 'block-email-domains'));
        }

        $processed = count($chunk);
        $next_start = $start + $processed;

        return self::result(true, 'import_chunk_done', __('Importchunk verwerkt.', 'block-email-domains'), array(
            'start' => $start,
            'next_start' => $next_start,
            'length' => $length,
            'processed' => $processed,
            'inserted' => (int) $inserted,
            'total' => $total,
            'done' => ($next_start >= $total),
        ));
    }

    private static function store_undo_payload($domains) {
        $domains = array_values(array_unique(array_filter(array_map('strval', (array) $domains))));
        if (empty($domains)) {
            return '';
        }

        $undo_key = wp_generate_password(12, false, false);
        set_transient(self::undo_transient_key($undo_key), array(
            'domains' => $domains,
            'created_at' => time(),
        ), self::UNDO_TTL);

        return $undo_key;
    }

    private static function undo_transient_key($key) {
        return 'bso_deleted_' . $key;
    }

    private static function import_transient_key($key) {
        return 'bso_import_' . $key;
    }
}

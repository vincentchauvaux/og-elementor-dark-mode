<?php
/**
 * Détection des couleurs ACF sur les termes de taxonomie et mapping mode sombre.
 */

if (!defined('ABSPATH')) {
    exit;
}

final class OGDM_ACF_Term_Colors {
    /** @var array<int, array<string, string>>|null */
    private static $sources_cache = null;

    /** @var array<int, array<string, mixed>>|null */
    private static $scan_cache = null;

    /**
     * Variables CSS dynamiques remplacées en mode sombre (filtrable).
     *
     * @return string[]
     */
    public static function get_dynamic_css_vars() {
        $vars = ['--gl-term-color', '--gl-current-term-color'];

        return apply_filters('ogdm_dynamic_css_vars', $vars);
    }

    /**
     * Sources ACF : champs color_picker liés aux taxonomies.
     * Filtre manuel : ogdm_acf_term_color_sources → [['taxonomy'=>'…','field'=>'…','dark_field'=>'…'], …]
     *
     * @return array<int, array{taxonomy: string, field: string, dark_field: string, label: string}>
     */
    public static function get_sources() {
        if (self::$sources_cache !== null) {
            return self::$sources_cache;
        }

        $manual = apply_filters('ogdm_acf_term_color_sources', null);
        if (is_array($manual) && $manual !== []) {
            self::$sources_cache = self::normalize_sources($manual);

            return self::$sources_cache;
        }

        self::$sources_cache = self::discover_acf_color_fields();

        return self::$sources_cache;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array{taxonomy: string, field: string, dark_field: string, label: string}>
     */
    private static function normalize_sources(array $rows) {
        $out = [];

        foreach ($rows as $row) {
            if (empty($row['taxonomy']) || empty($row['field'])) {
                continue;
            }
            $taxonomy = sanitize_key((string) $row['taxonomy']);
            $field = sanitize_key((string) $row['field']);
            if ($taxonomy === '' || $field === '') {
                continue;
            }

            $dark_field = isset($row['dark_field']) ? sanitize_key((string) $row['dark_field']) : self::guess_dark_field_name($field);

            $out[] = [
                'taxonomy' => $taxonomy,
                'field' => $field,
                'dark_field' => $dark_field,
                'label' => isset($row['label']) ? (string) $row['label'] : $field,
            ];
        }

        return $out;
    }

    /**
     * Parcourt les groupes ACF attachés aux taxonomies publiques.
     *
     * @return array<int, array{taxonomy: string, field: string, dark_field: string, label: string}>
     */
    private static function discover_acf_color_fields() {
        if (!function_exists('acf_get_field_groups') || !function_exists('acf_get_fields')) {
            return [];
        }

        $taxonomies = get_taxonomies(['public' => true], 'names');
        if (!is_array($taxonomies)) {
            return [];
        }

        $seen = [];
        $out = [];

        foreach ($taxonomies as $taxonomy) {
            $groups = acf_get_field_groups(['taxonomy' => $taxonomy]);
            if (!is_array($groups)) {
                continue;
            }

            foreach ($groups as $group) {
                $fields = acf_get_fields($group['key'] ?? '');
                if (!is_array($fields)) {
                    continue;
                }

                $color_fields = self::flatten_color_picker_fields($fields);
                foreach ($color_fields as $field) {
                    $name = (string) ($field['name'] ?? '');
                    if ($name === '' || self::is_dark_field_name($name)) {
                        continue;
                    }

                    $key = $taxonomy . '|' . $name;
                    if (isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;

                    $out[] = [
                        'taxonomy' => $taxonomy,
                        'field' => $name,
                        'dark_field' => self::guess_dark_field_name($name, $color_fields),
                        'label' => (string) ($field['label'] ?? $name),
                    ];
                }
            }
        }

        return $out;
    }

    /**
     * @param array<int, array<string, mixed>> $fields
     * @return array<int, array<string, mixed>>
     */
    private static function flatten_color_picker_fields(array $fields) {
        $out = [];

        foreach ($fields as $field) {
            if (!is_array($field)) {
                continue;
            }
            $type = (string) ($field['type'] ?? '');
            if ($type === 'color_picker' && !empty($field['name'])) {
                $out[] = $field;
            }
            if (!empty($field['sub_fields']) && is_array($field['sub_fields'])) {
                foreach (self::flatten_color_picker_fields($field['sub_fields']) as $sub) {
                    $out[] = $sub;
                }
            }
        }

        return $out;
    }

    private static function is_dark_field_name($name) {
        $name = strtolower((string) $name);

        return (bool) preg_match('/(?:_sombre|_dark|_night)$/', $name);
    }

    /**
     * @param array<int, array<string, mixed>> $sibling_fields
     */
    private static function guess_dark_field_name($field_name, array $sibling_fields = []) {
        $field_name = sanitize_key((string) $field_name);
        $candidates = [
            $field_name . '_sombre',
            $field_name . '_dark',
            'couleur_sombre',
        ];

        if ($field_name === 'couleur') {
            $candidates[] = 'couleur_sombre';
        }

        $names = [];
        foreach ($sibling_fields as $f) {
            if (!empty($f['name'])) {
                $names[] = (string) $f['name'];
            }
        }

        foreach ($candidates as $candidate) {
            if (in_array($candidate, $names, true)) {
                return $candidate;
            }
        }

        return $field_name . '_sombre';
    }

    /**
     * Normalise une valeur couleur ACF (hex, rgb, tableau).
     */
    public static function normalize_acf_color($color, $default = '') {
        if ($color === null || $color === false || $color === '') {
            return $default;
        }

        if (is_string($color)) {
            $color = trim($color);
            if ($color === '') {
                return $default;
            }
            if (strpos($color, '#') === 0) {
                $hex = sanitize_hex_color($color);

                return $hex ?: $default;
            }
            if (stripos($color, 'rgb') === 0) {
                $sanitized = ogdm_sanitize_css_color_value($color);

                return $sanitized !== '' ? $sanitized : $default;
            }

            return $default;
        }

        if (is_array($color)) {
            if (!empty($color['value'])) {
                $hex = sanitize_hex_color((string) $color['value']);

                return $hex ?: $default;
            }
            if (isset($color['red'], $color['green'], $color['blue'])) {
                $r = min(255, max(0, (int) $color['red']));
                $g = min(255, max(0, (int) $color['green']));
                $b = min(255, max(0, (int) $color['blue']));

                return sprintf('rgb(%d,%d,%d)', $r, $g, $b);
            }
        }

        return $default;
    }

    /**
     * Clé stable pour le map (hex minuscule ou rgb normalisé).
     */
    public static function color_map_key($css_color) {
        $css = ogdm_sanitize_css_color_value((string) $css_color);
        if ($css === '') {
            return '';
        }

        $hex = sanitize_hex_color($css);
        if ($hex) {
            return strtolower($hex);
        }

        if (preg_match('/^rgb\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\)$/i', $css, $m)) {
            return sprintf(
                'rgb(%d,%d,%d)',
                (int) $m[1],
                (int) $m[2],
                (int) $m[3]
            );
        }

        return strtolower($css);
    }

    /**
     * Couleurs uniques trouvées sur les termes (+ défaut sombre ACF si présent).
     *
     * @return array<string, array{id: string, color: string, dark_default: string, label: string, taxonomy: string, field: string, terms: string[]}>
     */
    public static function scan_unique_colors() {
        if (self::$scan_cache !== null) {
            return self::$scan_cache;
        }

        $sources = self::get_sources();
        if ($sources === [] || !function_exists('get_field')) {
            self::$scan_cache = [];

            return self::$scan_cache;
        }

        $rows = [];

        foreach ($sources as $source) {
            $terms = get_terms([
                'taxonomy' => $source['taxonomy'],
                'hide_empty' => false,
            ]);

            if (empty($terms) || is_wp_error($terms)) {
                continue;
            }

            foreach ($terms as $term) {
                $light_raw = get_field($source['field'], $term);
                if ($light_raw === null || $light_raw === false || $light_raw === '') {
                    $light_raw = get_field($source['field'], $source['taxonomy'] . '_' . $term->term_id);
                }

                $light = self::normalize_acf_color($light_raw);
                if ($light === '') {
                    continue;
                }

                $key = self::color_map_key($light);
                if ($key === '') {
                    continue;
                }

                $dark_default = '';
                if ($source['dark_field'] !== '') {
                    $dark_raw = get_field($source['dark_field'], $term);
                    if ($dark_raw === null || $dark_raw === false || $dark_raw === '') {
                        $dark_raw = get_field($source['dark_field'], $source['taxonomy'] . '_' . $term->term_id);
                    }
                    $dark_default = self::normalize_acf_color($dark_raw);
                }

                if (!isset($rows[$key])) {
                    $rows[$key] = [
                        'id' => md5($key),
                        'color' => $light,
                        'dark_default' => $dark_default,
                        'label' => $source['label'],
                        'taxonomy' => $source['taxonomy'],
                        'field' => $source['field'],
                        'terms' => [],
                    ];
                }

                if ($dark_default !== '' && $rows[$key]['dark_default'] === '') {
                    $rows[$key]['dark_default'] = $dark_default;
                }

                if (!in_array($term->name, $rows[$key]['terms'], true)) {
                    $rows[$key]['terms'][] = $term->name;
                }
            }
        }

        self::$scan_cache = $rows;

        return self::$scan_cache;
    }

    /**
     * @return array<string, string> clé couleur claire => couleur CSS mode sombre
     */
    public static function get_dark_color_map() {
        $settings = get_option('ogdm_settings', []);
        $saved = isset($settings['acf_color_map']) && is_array($settings['acf_color_map'])
            ? $settings['acf_color_map']
            : [];

        $scanned = self::scan_unique_colors();
        $out = [];

        foreach ($scanned as $key => $row) {
            $id = $row['id'];
            if (isset($saved[$id])) {
                $dark = ogdm_sanitize_css_color_value((string) $saved[$id]);
                if ($dark !== '') {
                    $out[$key] = $dark;
                    continue;
                }
            }

            if (!empty($row['dark_default'])) {
                $dark = ogdm_sanitize_css_color_value((string) $row['dark_default']);
                if ($dark !== '') {
                    $out[$key] = $dark;
                }
            }
        }

        return apply_filters('ogdm_acf_dark_color_map', $out, $scanned);
    }

    /**
     * Map pour le front : plusieurs alias de clé → couleur sombre.
     *
     * @return array<string, string>
     */
    public static function get_front_color_map() {
        $map = self::get_dark_color_map();
        $out = [];

        foreach ($map as $key => $dark) {
            $out[$key] = $dark;

            $hex = ogdm_normalize_color_to_hex($key);
            if ($hex) {
                $out[strtolower($hex)] = $dark;
            }

            if (preg_match('/rgba?\(/i', $key)) {
                $normalized = ogdm_sanitize_css_color_value($key);
                if ($normalized !== '') {
                    $out[$normalized] = $dark;
                }
            }
        }

        return $out;
    }

    public static function clear_cache() {
        self::$sources_cache = null;
        self::$scan_cache = null;
    }
}

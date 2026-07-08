<?php
/**
 * Plugin Name: Hakou Dark Mode
 * Plugin URI: https://hakou.be/
 * Description: Dark mode custom pour Elementor avec couleurs globales du kit et widget switcher.
 * Version: 1.5.1
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Hakou
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: hakou-dark-mode
 */

if (!defined('ABSPATH')) {
    exit;
}

define('HKDM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('HKDM_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('HKDM_VERSION', '1.5.1');
define('HKDM_STORAGE_KEY', 'hkdm_dark_mode');
define('HKDM_DARK_CLASS', 'hakou-dark-mode');

require_once HKDM_PLUGIN_PATH . 'includes/class-hkdm-elementor-dark-controls.php';
require_once HKDM_PLUGIN_PATH . 'includes/class-hkdm-acf-term-colors.php';

add_action('elementor/loaded', ['HKDM_Elementor_Dark_Controls', 'init']);

/**
 * Migration des options depuis les identifiants OG (ogdm_*).
 */
function hkdm_migrate_legacy_options() {
    $legacy_settings = get_option('ogdm_settings');
    if ($legacy_settings !== false && get_option('hkdm_settings') === false) {
        add_option('hkdm_settings', $legacy_settings);
    }

    $legacy_labels = get_option('ogdm_admin_use_claire_labels');
    if ($legacy_labels !== false && get_option('hkdm_admin_use_claire_labels') === false) {
        add_option('hkdm_admin_use_claire_labels', $legacy_labels);
    }
}
add_action('init', 'hkdm_migrate_legacy_options', 1);

/**
 * Convertit une couleur CSS (hex, rgb, rgba) en #rrggbb pour <input type="color">.
 */
function hkdm_normalize_color_to_hex($color) {
    $color = trim((string) $color);
    $hex = sanitize_hex_color($color);
    if ($hex) {
        return strtolower($hex);
    }
    if (preg_match('/rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/', $color, $m)) {
        $r = min(255, max(0, (int) $m[1]));
        $g = min(255, max(0, (int) $m[2]));
        $b = min(255, max(0, (int) $m[3]));

        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    return '#000000';
}

/**
 * Valeur CSS sûre : transparent, hex 3/6/8, rgb, rgba.
 */
function hkdm_sanitize_css_color_value($raw) {
    $raw = trim((string) $raw);
    if ($raw === '' || strlen($raw) > 120) {
        return '';
    }

    if (strtolower($raw) === 'transparent') {
        return 'transparent';
    }

    $hex = sanitize_hex_color($raw);
    if ($hex) {
        return $hex;
    }

    if (preg_match('/^#([0-9A-Fa-f]{8})$/', $raw, $m)) {
        return '#' . strtolower($m[1]);
    }

    if (preg_match('/^rgb\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*\)$/i', $raw, $m)) {
        return sprintf(
            'rgb(%d,%d,%d)',
            min(255, max(0, (int) $m[1])),
            min(255, max(0, (int) $m[2])),
            min(255, max(0, (int) $m[3]))
        );
    }

    if (preg_match('/^rgba\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*([\d.]+%?)\s*\)$/i', $raw, $m)) {
        $a_raw = trim($m[4]);
        if (substr($a_raw, -1) === '%') {
            $av = min(100.0, max(0.0, (float) substr($a_raw, 0, -1))) / 100.0;
        } else {
            $av = min(1.0, max(0.0, (float) $a_raw));
        }
        $a_str = rtrim(rtrim(sprintf('%.4f', $av), '0'), '.') ?: '0';

        return sprintf(
            'rgba(%d,%d,%d,%s)',
            min(255, max(0, (int) $m[1])),
            min(255, max(0, (int) $m[2])),
            min(255, max(0, (int) $m[3])),
            $a_str
        );
    }

    return '';
}

/**
 * @return array{hex: string, opacity: int, css: string}
 */
function hkdm_split_saved_color_for_ui($css, $fallback_hex) {
    $fb = sanitize_hex_color($fallback_hex) ?: '#000000';
    $css = trim((string) $css);
    if ($css === '') {
        return ['hex' => $fb, 'opacity' => 100, 'css' => $fb];
    }
    if (strtolower($css) === 'transparent') {
        return ['hex' => $fb, 'opacity' => 0, 'css' => 'transparent'];
    }

    $hex = sanitize_hex_color($css);
    if ($hex) {
        return ['hex' => $hex, 'opacity' => 100, 'css' => $hex];
    }

    if (preg_match('/^#([0-9A-Fa-f]{8})$/', $css, $m)) {
        $digits = strtolower($m[1]);
        $hex6 = '#' . substr($digits, 0, 6);
        $alpha_byte = hexdec(substr($digits, 6, 2));
        $opacity = (int) round(($alpha_byte / 255) * 100);

        return ['hex' => $hex6, 'opacity' => $opacity, 'css' => '#' . $digits];
    }

    if (preg_match('/^rgba\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*([\d.]+%?)\s*\)$/i', $css, $m)) {
        $hex6 = sprintf('#%02x%02x%02x', (int) $m[1], (int) $m[2], (int) $m[3]);
        $a_raw = trim($m[4]);
        $av = substr($a_raw, -1) === '%'
            ? min(100.0, max(0.0, (float) substr($a_raw, 0, -1)))
            : min(100.0, max(0.0, (float) $a_raw) * 100.0);

        return ['hex' => $hex6, 'opacity' => (int) round($av), 'css' => $css];
    }

    $sanitized = hkdm_sanitize_css_color_value($css);

    return ['hex' => $fb, 'opacity' => 100, 'css' => $sanitized !== '' ? $sanitized : $css];
}

function hkdm_build_color_from_picker($hex_raw, $opacity_pct) {
    $opacity_pct = min(100, max(0, (int) $opacity_pct));
    $hex = sanitize_hex_color($hex_raw);
    if (!$hex) {
        return '';
    }
    if ($opacity_pct <= 0) {
        return 'transparent';
    }
    if ($opacity_pct >= 100) {
        return $hex;
    }

    $digits = strtolower(ltrim($hex, '#'));
    if (strlen($digits) === 6) {
        $r = hexdec(substr($digits, 0, 2));
        $g = hexdec(substr($digits, 2, 2));
        $b = hexdec(substr($digits, 4, 2));
    } elseif (strlen($digits) === 3) {
        $r = hexdec(str_repeat($digits[0], 2));
        $g = hexdec(str_repeat($digits[1], 2));
        $b = hexdec(str_repeat($digits[2], 2));
    } else {
        return '';
    }

    $alpha = (int) round($opacity_pct / 100 * 255);

    return sprintf('#%02x%02x%02x%02x', $r, $g, $b, $alpha);
}

function hkdm_kit_color_admin_default($kit_color) {
    $css = hkdm_sanitize_css_color_value($kit_color);
    if ($css !== '') {
        return $css;
    }

    return hkdm_normalize_color_to_hex($kit_color);
}

/**
 * Couleurs globales du kit Elementor actif (system_colors + custom_colors).
 *
 * @return array<int, array{id: string, title: string, color: string}>
 */
function hkdm_get_elementor_kit_colors() {
    if (!class_exists('\Elementor\Plugin')) {
        return [];
    }

    $kits = \Elementor\Plugin::$instance->kits_manager;
    if (!$kits) {
        return [];
    }

    $kit = $kits->get_active_kit();
    if (!$kit || !method_exists($kit, 'get_settings')) {
        return [];
    }

    $settings = $kit->get_settings();
    $system = isset($settings['system_colors']) && is_array($settings['system_colors'])
        ? $settings['system_colors']
        : [];
    $custom = isset($settings['custom_colors']) && is_array($settings['custom_colors'])
        ? $settings['custom_colors']
        : [];

    $rows = [];
    foreach (array_merge($system, $custom) as $row) {
        if (empty($row['_id']) || empty($row['color'])) {
            continue;
        }
        $rows[] = [
            'id' => (string) $row['_id'],
            'title' => isset($row['title']) ? (string) $row['title'] : (string) $row['_id'],
            'color' => (string) $row['color'],
        ];
    }

    return $rows;
}

/**
 * Extrait une couleur lisible depuis un réglage kit Elementor.
 */
function hkdm_resolve_kit_color_value($raw, array $kit_colors = []) {
    if (is_array($raw)) {
        if (!empty($raw['color'])) {
            return (string) $raw['color'];
        }
        if (!empty($raw['value'])) {
            return (string) $raw['value'];
        }
    }

    $raw = trim((string) $raw);
    if ($raw === '') {
        return '';
    }

    if (preg_match('/globals\/colors\?id=([a-z0-9_-]+)/i', $raw, $m)) {
        foreach ($kit_colors as $row) {
            if ($row['id'] === $m[1]) {
                return (string) $row['color'];
            }
        }
    }

    return $raw;
}

/**
 * Couleurs bouton du kit Elementor (Theme Style → Bouton).
 *
 * @return array<int, array{id: string, title: string, color: string, kit_key: string}>
 */
function hkdm_get_elementor_kit_button_colors() {
    if (!class_exists('\Elementor\Plugin')) {
        return [];
    }

    $kits = \Elementor\Plugin::$instance->kits_manager;
    if (!$kits) {
        return [];
    }

    $kit = $kits->get_active_kit();
    if (!$kit || !method_exists($kit, 'get_settings')) {
        return [];
    }

    $settings = $kit->get_settings();
    if (!is_array($settings)) {
        return [];
    }

    $palette = hkdm_get_elementor_kit_colors();
    $defs = [
        ['id' => 'btn_text', 'kit_key' => 'button_text_color', 'title' => __('Texte du bouton', 'hakou-dark-mode')],
        ['id' => 'btn_bg', 'kit_key' => 'button_background_color', 'title' => __('Fond du bouton', 'hakou-dark-mode')],
        ['id' => 'btn_border', 'kit_key' => 'button_border_color', 'title' => __('Bordure du bouton', 'hakou-dark-mode')],
        ['id' => 'btn_hover_text', 'kit_key' => 'button_hover_text_color', 'title' => __('Texte du bouton (survol)', 'hakou-dark-mode')],
        ['id' => 'btn_hover_bg', 'kit_key' => 'button_hover_background_color', 'title' => __('Fond du bouton (survol)', 'hakou-dark-mode')],
        ['id' => 'btn_hover_border', 'kit_key' => 'button_hover_border_color', 'title' => __('Bordure du bouton (survol)', 'hakou-dark-mode')],
    ];

    $rows = [];
    foreach ($defs as $def) {
        $key = $def['kit_key'];
        if (!isset($settings[$key])) {
            continue;
        }
        $color = hkdm_resolve_kit_color_value($settings[$key], $palette);
        if ($color === '') {
            continue;
        }
        $rows[] = [
            'id' => $def['id'],
            'title' => $def['title'],
            'color' => $color,
            'kit_key' => $key,
        ];
    }

    return $rows;
}

/**
 * @return array<string, string> id => couleur CSS mode sombre
 */
function hkdm_get_dark_color_map() {
    $settings = get_option('hkdm_settings', []);
    $map = isset($settings['color_map']) && is_array($settings['color_map'])
        ? $settings['color_map']
        : [];

    $out = [];
    foreach ($map as $id => $value) {
        $id = sanitize_key($id);
        if ($id === '') {
            continue;
        }
        $color = hkdm_sanitize_css_color_value((string) $value);
        if ($color !== '') {
            $out[$id] = $color;
        }
    }

    // Migration depuis l’ancien format fixe (4 champs).
    if ($out === [] && !empty($settings['primary'])) {
        $legacy = [
            'primary' => $settings['primary'] ?? '',
            'secondary' => $settings['secondary'] ?? '',
            'text' => $settings['text'] ?? '',
            'accent' => $settings['accent'] ?? '',
        ];
        foreach ($legacy as $id => $value) {
            $color = hkdm_sanitize_css_color_value((string) $value);
            if ($color !== '') {
                $out[sanitize_key($id)] = $color;
            }
        }
    }

    return $out;
}

/**
 * @return array<string, string> id => couleur CSS mode sombre
 */
function hkdm_get_dark_button_color_map() {
    $settings = get_option('hkdm_settings', []);
    $map = isset($settings['button_color_map']) && is_array($settings['button_color_map'])
        ? $settings['button_color_map']
        : [];

    $out = [];
    foreach ($map as $id => $value) {
        $id = sanitize_key($id);
        if ($id === '') {
            continue;
        }
        $color = hkdm_sanitize_css_color_value((string) $value);
        if ($color !== '') {
            $out[$id] = $color;
        }
    }

    return $out;
}

function hkdm_build_dark_mode_css() {
    $chunks = [];

    $color_map = hkdm_get_dark_color_map();
    if ($color_map !== []) {
        $lines = ['html.hakou-dark-mode,body.hakou-dark-mode{'];
        foreach ($color_map as $id => $color) {
            $id = preg_replace('/[^a-zA-Z0-9_-]/', '', $id);
            if ($id === '' || $color === '') {
                continue;
            }
            $lines[] = sprintf('--e-global-color-%s:%s;', $id, $color);
        }
        $lines[] = '}';
        $chunks[] = implode('', $lines);
    }

    $btn_map = hkdm_get_dark_button_color_map();
    if ($btn_map !== []) {
        $text = $btn_map['btn_text'] ?? '';
        $bg = $btn_map['btn_bg'] ?? '';
        $border = $btn_map['btn_border'] ?? '';
        $hover_text = $btn_map['btn_hover_text'] ?? '';
        $hover_bg = $btn_map['btn_hover_bg'] ?? '';
        $hover_border = $btn_map['btn_hover_border'] ?? '';

        $base_sel = 'body.hakou-dark-mode button:not(.hakou-dark-toggle),body.hakou-dark-mode .elementor-button:not(.hakou-dark-toggle),body.hakou-dark-mode .elementor-button-link:not(.hakou-dark-toggle)';
        $hover_sel = 'body.hakou-dark-mode button:not(.hakou-dark-toggle):hover,body.hakou-dark-mode .elementor-button:not(.hakou-dark-toggle):hover,body.hakou-dark-mode .elementor-button-link:not(.hakou-dark-toggle):hover';

        $decl = [];
        if ($text) {
            $decl[] = 'color:' . $text;
        }
        if ($bg) {
            $decl[] = 'background-color:' . $bg;
        }
        if ($border) {
            $decl[] = 'border-color:' . $border;
        }
        if ($decl !== []) {
            $chunks[] = $base_sel . '{' . implode(';', $decl) . ';}';
        }

        $hover_decl = [];
        if ($hover_text) {
            $hover_decl[] = 'color:' . $hover_text;
        }
        if ($hover_bg) {
            $hover_decl[] = 'background-color:' . $hover_bg;
        }
        if ($hover_border) {
            $hover_decl[] = 'border-color:' . $hover_border;
        }
        if ($hover_decl !== []) {
            $chunks[] = $hover_sel . '{' . implode(';', $hover_decl) . ';}';
        }
    }

    return implode('', $chunks);
}

/**
 * Indique si une icône Elementor (Font Awesome ou SVG) est utilisable.
 *
 * @param array{value?: mixed, library?: string} $icon
 */
function hkdm_elementor_icon_is_configured(array $icon) {
    $library = isset($icon['library']) ? (string) $icon['library'] : '';
    if ($library === '') {
        return false;
    }

    if ($library === 'svg') {
        return is_array($icon['value'] ?? null) && !empty($icon['value']['id']);
    }

    return is_string($icon['value'] ?? null) && $icon['value'] !== '';
}

/**
 * Normalise une valeur du contrôle Elementor ICONS (Font Awesome ou SVG téléversé).
 *
 * @param mixed $raw
 * @return array{value: string|array{id?: int, url?: string}, library: string}
 */
function hkdm_sanitize_elementor_icon($raw) {
    if (is_string($raw)) {
        $decoded = json_decode(wp_unslash($raw), true);
        $raw = is_array($decoded) ? $decoded : [];
    }
    if (!is_array($raw) || empty($raw['library'])) {
        return ['value' => '', 'library' => ''];
    }

    $library = sanitize_text_field((string) $raw['library']);

    if ($library === 'svg') {
        $value = $raw['value'] ?? [];
        if (is_string($value)) {
            $decoded_value = json_decode($value, true);
            $value = is_array($decoded_value) ? $decoded_value : [];
        }
        if (!is_array($value)) {
            return ['value' => '', 'library' => ''];
        }

        $id = isset($value['id']) ? absint($value['id']) : 0;
        $url = isset($value['url']) ? esc_url_raw((string) $value['url']) : '';

        if ($id === 0 && $url === '') {
            return ['value' => '', 'library' => ''];
        }

        $normalized = [];
        if ($id > 0) {
            $normalized['id'] = $id;
        }
        if ($url !== '') {
            $normalized['url'] = $url;
        }

        return [
            'library' => 'svg',
            'value' => $normalized,
        ];
    }

    if (empty($raw['value']) || !is_scalar($raw['value'])) {
        return ['value' => '', 'library' => ''];
    }

    return [
        'value' => sanitize_text_field((string) $raw['value']),
        'library' => $library,
    ];
}

function hkdm_enqueue_elementor_icon_fonts(array $icon) {
    $icon = hkdm_sanitize_elementor_icon($icon);
    if (
        !hkdm_elementor_icon_is_configured($icon)
        || $icon['library'] === 'svg'
        || !did_action('elementor/loaded')
    ) {
        return;
    }

    if (method_exists('\Elementor\Icons_Manager', 'enqueue_icon_fonts')) {
        \Elementor\Icons_Manager::enqueue_icon_fonts($icon);
    }
}

function hkdm_render_elementor_icon_html(array $icon, $wrapper_class = 'hakou-dark-toggle__el-icon') {
    $icon = hkdm_sanitize_elementor_icon($icon);
    if (
        !hkdm_elementor_icon_is_configured($icon)
        || !did_action('elementor/loaded')
        || !class_exists('\Elementor\Icons_Manager')
    ) {
        return '';
    }

    ob_start();
    \Elementor\Icons_Manager::render_icon($icon, [
        'aria-hidden' => 'true',
        'class' => $wrapper_class,
    ]);
    $html = (string) ob_get_clean();

    if ($html === '') {
        return '';
    }

    if ($icon['library'] === 'svg' && strpos($html, $wrapper_class) === false) {
        return '<span class="' . esc_attr($wrapper_class) . '" aria-hidden="true">' . $html . '</span>';
    }

    return $html;
}

function hkdm_switch_face_inner_html(array $icon, $emoji_char) {
    $el = hkdm_render_elementor_icon_html($icon);
    if ($el !== '') {
        return $el;
    }

    return '<span class="hakou-dark-toggle__emoji" aria-hidden="true">' . esc_html($emoji_char) . '</span>';
}

/*
|--------------------------------------------------------------------------
| ENQUEUE FRONT
|--------------------------------------------------------------------------
*/

function hkdm_print_head_sync_script() {
    $dark_class = HKDM_DARK_CLASS;
    $storage_key = HKDM_STORAGE_KEY;

    echo '<script id="hkdm-head-sync">';
    echo '(function(){var k=' . wp_json_encode($storage_key) . ',c=' . wp_json_encode($dark_class) . ';';
    echo 'var on=false;try{var s=localStorage.getItem(k);on=s==="1";}catch(e){}';
    echo 'document.documentElement.classList.toggle(c,on);';
    echo 'if(document.body){document.body.classList.toggle(c,on);}})();';
    echo '</script>';
}

add_action('wp_head', 'hkdm_print_head_sync_script', 0);

add_action('wp_enqueue_scripts', function () {
    $deps = [];
    if (wp_style_is('elementor-frontend', 'registered')) {
        $deps[] = 'elementor-frontend';
    }

    wp_enqueue_style(
        'hkdm-style',
        HKDM_PLUGIN_URL . 'assets/dark-mode.css',
        $deps,
        HKDM_VERSION
    );

    wp_enqueue_script(
        'hkdm-script',
        HKDM_PLUGIN_URL . 'assets/dark-mode.js',
        [],
        HKDM_VERSION,
        true
    );

    wp_localize_script('hkdm-script', 'hkdmSettings', [
        'dark_class' => HKDM_DARK_CLASS,
        'storage_key' => HKDM_STORAGE_KEY,
        'acf_color_map' => HKDM_ACF_Term_Colors::get_front_color_map(),
        'dynamic_css_vars' => HKDM_ACF_Term_Colors::get_dynamic_css_vars(),
    ]);

    $inline = hkdm_build_dark_mode_css();
    if ($inline !== '') {
        wp_add_inline_style('hkdm-style', $inline);
    }
}, 99);

/*
|--------------------------------------------------------------------------
| ADMIN MENU + ASSETS
|--------------------------------------------------------------------------
*/

add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== 'toplevel_page_hakou-dark-mode') {
        return;
    }
    wp_enqueue_style('hkdm-admin', HKDM_PLUGIN_URL . 'assets/admin.css', [], HKDM_VERSION);
    wp_enqueue_script(
        'hkdm-admin',
        HKDM_PLUGIN_URL . 'assets/admin.js',
        ['jquery'],
        HKDM_VERSION,
        true
    );
});

add_action('admin_menu', function () {
    add_menu_page(
        'Hakou Dark Mode',
        'Hakou Dark Mode',
        'manage_options',
        'hakou-dark-mode',
        'hkdm_settings_page',
        'dashicons-lightbulb'
    );
});

/*
|--------------------------------------------------------------------------
| SETTINGS PAGE
|--------------------------------------------------------------------------
*/

/**
 * @param array<string, mixed> $css_fields
 * @return array<string, string>
 */
function hkdm_save_color_map_from_post(array $css_fields) {
    $out = [];

    foreach ($css_fields as $id => $raw) {
        $id = sanitize_key((string) $id);
        if ($id === '') {
            continue;
        }
        $color = hkdm_sanitize_css_color_value(trim((string) $raw));
        if ($color !== '') {
            $out[$id] = $color;
        }
    }

    return $out;
}

/**
 * Pipette compacte : carré couleur + curseur alpha intégré.
 *
 * @param array{hex: string, opacity: int, css: string} $ui
 */
function hkdm_render_admin_color_picker($field_name, $id, array $ui, $kit_default, $kit_hex) {
    ?>
    <div class="hkdm-picker" data-color-row data-kit-default="<?php echo esc_attr($kit_default); ?>" data-kit-hex="<?php echo esc_attr($kit_hex); ?>">
        <input type="hidden" name="<?php echo esc_attr($field_name); ?>[<?php echo esc_attr($id); ?>]" class="hkdm-picker__value" value="<?php echo esc_attr($ui['css']); ?>">
        <div class="hkdm-picker__surface">
            <span class="hkdm-picker__checker" aria-hidden="true"></span>
            <span class="hkdm-picker__fill" aria-hidden="true"></span>
            <input type="color" class="hkdm-picker__hex" value="<?php echo esc_attr($ui['hex']); ?>" aria-label="<?php esc_attr_e('Couleur', 'hakou-dark-mode'); ?>">
            <input type="range" class="hkdm-picker__alpha" min="0" max="100" value="<?php echo esc_attr((string) (int) $ui['opacity']); ?>" aria-label="<?php esc_attr_e('Opacité', 'hakou-dark-mode'); ?>">
        </div>
        <code class="hkdm-picker__hint"><?php echo esc_html($ui['css']); ?></code>
    </div>
    <?php
}

function hkdm_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    // Option admin : inverser uniquement les libellés (sombre -> claire), sans impacter la logique.
    if (isset($_POST['hkdm_toggle_wording']) && isset($_POST['hkdm_wording_nonce'])
        && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['hkdm_wording_nonce'])), 'hkdm_toggle_wording')) {
        $use_claire_labels = isset($_POST['hkdm_wording_light']) ? (bool) $_POST['hkdm_wording_light'] : false;
        update_option('hkdm_admin_use_claire_labels', $use_claire_labels ? 1 : 0);
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Préférence d’affichage admin mise à jour.', 'hakou-dark-mode') . '</p></div>';
    }

    if (isset($_POST['hkdm_save']) && isset($_POST['hkdm_nonce'])
        && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['hkdm_nonce'])), 'hkdm_save_settings')) {

        $color_map = hkdm_save_color_map_from_post(
            isset($_POST['dark_color']) && is_array($_POST['dark_color']) ? wp_unslash($_POST['dark_color']) : []
        );

        $button_color_map = hkdm_save_color_map_from_post(
            isset($_POST['dark_button_color']) && is_array($_POST['dark_button_color']) ? wp_unslash($_POST['dark_button_color']) : []
        );

        $acf_color_map = hkdm_save_color_map_from_post(
            isset($_POST['dark_acf_color']) && is_array($_POST['dark_acf_color']) ? wp_unslash($_POST['dark_acf_color']) : []
        );

        update_option('hkdm_settings', [
            'color_map' => $color_map,
            'button_color_map' => $button_color_map,
            'acf_color_map' => $acf_color_map,
        ]);

        HKDM_ACF_Term_Colors::clear_cache();

        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Réglages enregistrés.', 'hakou-dark-mode') . '</p></div>';
    }

    $settings = get_option('hkdm_settings', []);
    $saved_map = isset($settings['color_map']) && is_array($settings['color_map'])
        ? $settings['color_map']
        : [];
    $saved_button_map = isset($settings['button_color_map']) && is_array($settings['button_color_map'])
        ? $settings['button_color_map']
        : [];
    $saved_acf_map = isset($settings['acf_color_map']) && is_array($settings['acf_color_map'])
        ? $settings['acf_color_map']
        : [];
    $kit_colors = hkdm_get_elementor_kit_colors();
    $kit_button_colors = hkdm_get_elementor_kit_button_colors();
    $acf_term_colors = HKDM_ACF_Term_Colors::scan_unique_colors();
    $acf_sources = HKDM_ACF_Term_Colors::get_sources();
    $use_claire_labels = (bool) get_option('hkdm_admin_use_claire_labels', 0);

    $version_label = $use_claire_labels
        ? __('version claire', 'hakou-dark-mode')
        : __('version sombre', 'hakou-dark-mode');
    $mode_label = $use_claire_labels
        ? __('mode claire', 'hakou-dark-mode')
        : __('mode sombre', 'hakou-dark-mode');
    $Mode_label = $use_claire_labels
        ? __('Mode claire', 'hakou-dark-mode')
        : __('Mode sombre', 'hakou-dark-mode');

    ?>
    <div class="wrap">
        <h1><?php echo esc_html__('Hakou Dark Mode', 'hakou-dark-mode'); ?></h1>

        <?php if (!class_exists('\Elementor\Plugin')) : ?>
            <div class="notice notice-warning"><p>
                <?php echo esc_html__('Elementor doit être actif pour lire les couleurs du kit.', 'hakou-dark-mode'); ?>
            </p></div>
        <?php endif; ?>

        <form method="post" style="margin:12px 0 0;">
            <?php wp_nonce_field('hkdm_toggle_wording', 'hkdm_wording_nonce'); ?>
            <input type="hidden" name="hkdm_toggle_wording" value="1" />
            <div class="hkdm-wording-switch">
                <label class="hkdm-switch" aria-label="<?php echo esc_attr__('Afficher “sombre” comme “claire” (libellés admin uniquement).', 'hakou-dark-mode'); ?>">
                    <input
                        type="checkbox"
                        name="hkdm_wording_light"
                        value="1"
                        <?php checked($use_claire_labels, true); ?>
                    />
                    <span class="hkdm-switch__slider" aria-hidden="true"></span>
                </label>
                <span class="hkdm-wording-switch__text">
                    <?php echo esc_html__('Afficher “sombre” comme “claire” (libellés admin uniquement).', 'hakou-dark-mode'); ?>
                </span>
            </div>
            <p style="margin-top:8px;">
                <button type="submit" class="button"><?php echo esc_html__('Appliquer', 'hakou-dark-mode'); ?></button>
            </p>
        </form>

        <form method="post">
            <?php wp_nonce_field('hkdm_save_settings', 'hkdm_nonce'); ?>

            <h2 class="title"><?php echo esc_html('Couleurs globales → ' . $version_label); ?></h2>
            <p class="description">
                <?php echo esc_html('Ces couleurs ne s’appliquent que lorsque le site est en ' . $mode_label . ' (classe hakou-dark-mode). En mode clair, ce sont les couleurs du kit Elementor qui comptent.'); ?>
                <?php echo esc_html__(' Si un bouton Elementor reste foncé en mode clair, vérifiez sa couleur globale dans Site Settings (ex. --e-global-color-…).', 'hakou-dark-mode'); ?>
                <?php echo esc_html(' Par widget : onglet Style → section « ' . $Mode_label . ' » (sous chaque bloc de style qui contient des couleurs).'); ?>
                <?php echo esc_html__(' Switch : widget « Dark Mode Switch ».', 'hakou-dark-mode'); ?>
                <?php echo esc_html__(' Pipette : clic sur la pastille (couleur + transparence en bas).', 'hakou-dark-mode'); ?>
            </p>
            <p style="margin-top:8px;">
                <button type="button" class="button" id="hkdm-reset-colors-default"><?php echo esc_html__('Défaut (couleurs globales du kit)', 'hakou-dark-mode'); ?></button>
                <button type="button" class="button" id="hkdm-reset-button-colors-default"><?php echo esc_html__('Défaut (couleurs bouton du kit)', 'hakou-dark-mode'); ?></button>
                <button type="button" class="button" id="hkdm-reset-acf-colors-default"><?php echo esc_html__('Défaut (couleurs ACF détectées)', 'hakou-dark-mode'); ?></button>
            </p>

            <table class="widefat striped hkdm-admin-table hkdm-admin-table--globals">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Couleur kit', 'hakou-dark-mode'); ?></th>
                        <th><?php echo esc_html__('Variable CSS', 'hakou-dark-mode'); ?></th>
                        <th><?php echo esc_html('Couleur en ' . $mode_label); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($kit_colors === []) : ?>
                        <tr>
                            <td colspan="3"><?php echo esc_html__('Aucune couleur de kit trouvée.', 'hakou-dark-mode'); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($kit_colors as $row) :
                            $id = $row['id'];
                            $kit_default = hkdm_kit_color_admin_default($row['color']);
                            $kit_hex = hkdm_normalize_color_to_hex($row['color']);
                            $saved_css = isset($saved_map[$id]) ? (string) $saved_map[$id] : '';
                            if ($saved_css === '') {
                                $saved_css = $kit_default;
                            }
                            $ui = hkdm_split_saved_color_for_ui($saved_css, $kit_hex);
                            ?>
                            <tr>
                                <td class="hkdm-col-kit">
                                    <span class="hkdm-kit-title"><?php echo esc_html($row['title']); ?></span>
                                    <span class="hkdm-kit-swatch" style="background-color:<?php echo esc_attr($row['color']); ?>" title="<?php echo esc_attr($row['color']); ?>"></span>
                                </td>
                                <td><code class="hkdm-var-code">--e-global-color-<?php echo esc_html($id); ?></code></td>
                                <td class="hkdm-col-picker">
                                    <?php hkdm_render_admin_color_picker('dark_color', $id, $ui, $kit_default, $kit_hex); ?>
                                </td>
                            </tr>
                            
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <h2 class="title" style="margin-top:28px;"><?php echo esc_html('Couleurs bouton Elementor → ' . $version_label); ?></h2>
            <p class="description">
                <?php echo esc_html__('Valeurs du kit (Site Settings → Theme Style → Bouton). Le switch dark mode est exclu : son style reste dans le widget.', 'hakou-dark-mode'); ?>
            </p>

            <table class="widefat striped hkdm-admin-table hkdm-admin-table--buttons">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Réglage kit', 'hakou-dark-mode'); ?></th>
                        <th><?php echo esc_html__('Couleur kit', 'hakou-dark-mode'); ?></th>
                        <th><?php echo esc_html('Couleur en ' . $mode_label); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($kit_button_colors === []) : ?>
                        <tr>
                            <td colspan="3"><?php echo esc_html__('Aucune couleur bouton trouvée dans le kit.', 'hakou-dark-mode'); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($kit_button_colors as $row) :
                            $id = $row['id'];
                            $kit_default = hkdm_kit_color_admin_default($row['color']);
                            $kit_hex = hkdm_normalize_color_to_hex($row['color']);
                            $saved_css = isset($saved_button_map[$id]) ? (string) $saved_button_map[$id] : '';
                            if ($saved_css === '') {
                                $saved_css = $kit_default;
                            }
                            $ui = hkdm_split_saved_color_for_ui($saved_css, $kit_hex);
                            ?>
                            <tr>
                                <td class="hkdm-col-kit">
                                    <span class="hkdm-kit-title"><?php echo esc_html($row['title']); ?></span>
                                    <span class="hkdm-kit-swatch" style="background-color:<?php echo esc_attr($row['color']); ?>" title="<?php echo esc_attr($row['color']); ?>"></span>
                                </td>
                                <td><span class="hkdm-kit-ref"><?php echo esc_html($row['color']); ?></span></td>
                                <td class="hkdm-col-picker">
                                    <?php hkdm_render_admin_color_picker('dark_button_color', $id, $ui, $kit_default, $kit_hex); ?>
                                </td>
                            </tr>
                            
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <h2 class="title" style="margin-top:28px;"><?php echo esc_html('Couleurs ACF (taxonomies) → ' . $version_label); ?></h2>
            <p class="description">
                <?php echo esc_html__('Couleurs détectées automatiquement sur les termes via les champs color_picker ACF (ex. couleur de catégorie). Utilisées pour les variables CSS dynamiques (--gl-term-color, --gl-current-term-color, etc.).', 'hakou-dark-mode'); ?>
                <?php if ($acf_sources !== []) : ?>
                    <?php
                    $source_labels = array_map(function ($s) {
                        return $s['taxonomy'] . ' → ' . $s['field']
                            . ($s['dark_field'] !== '' ? ' (+ ' . $s['dark_field'] . ')' : '');
                    }, $acf_sources);
                    ?>
                    <br>
                    <strong><?php echo esc_html__('Champs scannés :', 'hakou-dark-mode'); ?></strong>
                    <?php echo esc_html(implode(' · ', $source_labels)); ?>
                <?php endif; ?>
            </p>

            <?php if (!function_exists('get_field')) : ?>
                <div class="notice notice-warning inline"><p>
                    <?php echo esc_html__('ACF doit être actif pour détecter les couleurs de termes.', 'hakou-dark-mode'); ?>
                </p></div>
            <?php endif; ?>

            <table class="widefat striped hkdm-admin-table hkdm-admin-table--acf">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Couleur ACF (mode clair)', 'hakou-dark-mode'); ?></th>
                        <th><?php echo esc_html__('Source', 'hakou-dark-mode'); ?></th>
                        <th><?php echo esc_html('Couleur en ' . $mode_label); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($acf_term_colors === []) : ?>
                        <tr>
                            <td colspan="3"><?php echo esc_html__('Aucune couleur ACF trouvée sur les termes.', 'hakou-dark-mode'); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($acf_term_colors as $row) :
                            $id = $row['id'];
                            $kit_default = !empty($row['dark_default'])
                                ? hkdm_kit_color_admin_default($row['dark_default'])
                                : hkdm_kit_color_admin_default($row['color']);
                            $kit_hex = hkdm_normalize_color_to_hex($row['color']);
                            $saved_css = isset($saved_acf_map[$id]) ? (string) $saved_acf_map[$id] : '';
                            if ($saved_css === '') {
                                $saved_css = $kit_default;
                            }
                            $ui = hkdm_split_saved_color_for_ui($saved_css, $kit_hex);
                            $terms_preview = implode(', ', array_slice($row['terms'], 0, 3));
                            if (count($row['terms']) > 3) {
                                $terms_preview .= '…';
                            }
                            ?>
                            <tr>
                                <td class="hkdm-col-kit">
                                    <span class="hkdm-kit-swatch" style="background-color:<?php echo esc_attr($row['color']); ?>" title="<?php echo esc_attr($row['color']); ?>"></span>
                                    <code class="hkdm-var-code"><?php echo esc_html($row['color']); ?></code>
                                </td>
                                <td>
                                    <span class="hkdm-kit-title"><?php echo esc_html($row['label']); ?></span>
                                    <span class="hkdm-kit-ref"><?php echo esc_html($row['taxonomy'] . ' · ' . $row['field']); ?></span>
                                    <?php if ($terms_preview !== '') : ?>
                                        <br><span class="hkdm-kit-ref"><?php echo esc_html($terms_preview); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="hkdm-col-picker">
                                    <?php hkdm_render_admin_color_picker('dark_acf_color', $id, $ui, $kit_default, $kit_hex); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <p style="margin-top:20px;">
                <button class="button button-primary" name="hkdm_save" value="1"><?php echo esc_html__('Enregistrer', 'hakou-dark-mode'); ?></button>
            </p>
        </form>
    </div>
    <?php
}

/**
 * @param mixed $raw
 */
function hkdm_switch_sanitize_icon_apply($raw) {
    $allowed = ['fill', 'stroke', 'both'];
    $raw = sanitize_key((string) $raw);

    return in_array($raw, $allowed, true) ? $raw : 'both';
}

/**
 * Sélecteurs couleur icône (Font Awesome + SVG) selon fill / stroke / les deux.
 *
 * @param 'light'|'dark' $face
 * @return array<string, string>
 */
function hkdm_switch_widget_icon_color_selectors($face) {
    $face_class = $face === 'dark' ? '.hakou-dark-toggle__face--dark' : '.hakou-dark-toggle__face--light';
    $shape_tags = ['path', 'circle', 'rect', 'polygon', 'ellipse', 'line'];
    $selectors = [];

    foreach (['fill', 'stroke', 'both'] as $apply) {
        $root = '{{WRAPPER}}.hkdm-icon-apply-' . $apply . ' ' . $face_class . ' .hakou-dark-toggle__el-icon';

        $selectors[$root . ' i'] = 'color: {{VALUE}} !important;';
        $selectors[$root . ' .hakou-dark-toggle__emoji'] = 'color: {{VALUE}} !important;';

        if ($apply === 'fill' || $apply === 'both') {
            $selectors[$root . ' svg'] = 'color: {{VALUE}} !important; fill: {{VALUE}} !important;';
            foreach ($shape_tags as $tag) {
                $selectors[$root . ' svg ' . $tag] = 'fill: {{VALUE}} !important; stroke: none !important;';
            }
        }

        if ($apply === 'stroke' || $apply === 'both') {
            $path_rule = $apply === 'both'
                ? 'fill: {{VALUE}} !important; stroke: {{VALUE}} !important;'
                : 'stroke: {{VALUE}} !important; fill: none !important;';

            foreach ($shape_tags as $tag) {
                $selectors[$root . ' svg ' . $tag] = $path_rule;
            }
        }
    }

    return $selectors;
}

/*
|--------------------------------------------------------------------------
| SHORTCODE + MARKUP SWITCH
|--------------------------------------------------------------------------
*/

/**
 * @param array<string, mixed> $atts
 */
function hkdm_render_switch_markup($atts = []) {
    $icon_light = hkdm_sanitize_elementor_icon($atts['icon_light'] ?? []);
    $icon_dark = hkdm_sanitize_elementor_icon($atts['icon_dark'] ?? []);

    hkdm_enqueue_elementor_icon_fonts($icon_light);
    hkdm_enqueue_elementor_icon_fonts($icon_dark);

    $inner_light = hkdm_switch_face_inner_html($icon_light, '🌙');
    $inner_dark = hkdm_switch_face_inner_html($icon_dark, '☀️');

    $inner = '<span class="hakou-dark-toggle__face hakou-dark-toggle__face--light">' . $inner_light . '</span>'
        . '<span class="hakou-dark-toggle__face hakou-dark-toggle__face--dark">' . $inner_dark . '</span>';

    $default_label = __('Basculer le mode sombre', 'hakou-dark-mode');
    $label = isset($atts['label']) ? trim((string) $atts['label']) : '';
    if ($label === '') {
        $label = $default_label;
    }

    $classes = 'hakou-dark-toggle';
    if (!empty($atts['from_widget'])) {
        $classes .= ' hakou-dark-toggle--widget';
    }

    return '<button type="button" class="' . esc_attr($classes) . '" aria-label="' . esc_attr($label) . '">' . $inner . '</button>';
}

add_shortcode('hakou_dark_switch', function ($atts) {
    $atts = shortcode_atts(['label' => ''], $atts, 'hakou_dark_switch');

    return hkdm_render_switch_markup($atts);
});

/*
|--------------------------------------------------------------------------
| ELEMENTOR WIDGET
|--------------------------------------------------------------------------
*/

add_action('elementor/widgets/register', function ($widgets_manager) {
    if (!class_exists('\Elementor\Widget_Base')) {
        return;
    }

    if (!class_exists('HKDM_Switch_Widget')) {
        class HKDM_Switch_Widget extends \Elementor\Widget_Base {
            public function get_name() {
                return 'hkdm-switch';
            }

            public function get_title() {
                return __('Dark Mode Switch', 'hakou-dark-mode');
            }

            public function get_icon() {
                return 'eicon-adjust';
            }

            public function get_categories() {
                return ['general'];
            }

            public function get_keywords() {
                return ['dark', 'mode', 'toggle', 'switch', 'hakou', 'sombre', 'lune'];
            }

            protected function register_controls() {
                $this->start_controls_section('section_content', [
                    'label' => __('Contenu', 'hakou-dark-mode'),
                ]);

                $this->add_control('aria_label', [
                    'label' => __('Libellé accessibilité', 'hakou-dark-mode'),
                    'type' => \Elementor\Controls_Manager::TEXT,
                    'default' => __('Basculer le mode sombre', 'hakou-dark-mode'),
                ]);

                $this->add_control('icon_light', [
                    'label' => __('Icône (site en mode clair)', 'hakou-dark-mode'),
                    'type' => \Elementor\Controls_Manager::ICONS,
                    'default' => [
                        'value' => 'fas fa-moon',
                        'library' => 'fa-solid',
                    ],
                ]);

                $this->add_control('icon_dark', [
                    'label' => __('Icône (site en mode sombre)', 'hakou-dark-mode'),
                    'type' => \Elementor\Controls_Manager::ICONS,
                    'default' => [
                        'value' => 'fas fa-sun',
                        'library' => 'fa-solid',
                    ],
                ]);

                $this->end_controls_section();

                $btn_light = 'body:not(.hakou-dark-mode) {{WRAPPER}} .hakou-dark-toggle';
                $btn_dark = 'body.hakou-dark-mode {{WRAPPER}} .hakou-dark-toggle';

                $this->start_controls_section('section_style', [
                    'label' => __('Apparence', 'hakou-dark-mode'),
                    'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                ]);

                $this->add_responsive_control('button_size', [
                    'label' => __('Taille du bouton', 'hakou-dark-mode'),
                    'type' => \Elementor\Controls_Manager::SLIDER,
                    'size_units' => ['px'],
                    'range' => [
                        'px' => ['min' => 24, 'max' => 200],
                    ],
                    'default' => [
                        'size' => 50,
                        'unit' => 'px',
                    ],
                    'selectors' => [
                        '{{WRAPPER}} .hakou-dark-toggle' => 'width: {{SIZE}}{{UNIT}} !important; height: {{SIZE}}{{UNIT}} !important; min-width: 0 !important; min-height: 0 !important;',
                    ],
                ]);

                $this->add_control('button_radius', [
                    'label' => __('Coins arrondis', 'hakou-dark-mode'),
                    'type' => \Elementor\Controls_Manager::SLIDER,
                    'size_units' => ['px', '%'],
                    'range' => [
                        'px' => ['min' => 0, 'max' => 100],
                        '%' => ['min' => 0, 'max' => 50],
                    ],
                    'default' => [
                        'size' => 50,
                        'unit' => '%',
                    ],
                    'selectors' => [
                        '{{WRAPPER}} .hakou-dark-toggle' => 'border-radius: {{SIZE}}{{UNIT}} !important;',
                    ],
                ]);

                $this->add_responsive_control('icon_size', [
                    'label' => __('Taille de l’icône', 'hakou-dark-mode'),
                    'type' => \Elementor\Controls_Manager::SLIDER,
                    'size_units' => ['px', 'em'],
                    'range' => [
                        'px' => ['min' => 12, 'max' => 80],
                        'em' => ['min' => 0.5, 'max' => 4],
                    ],
                    'default' => [
                        'size' => 22,
                        'unit' => 'px',
                    ],
                    'selectors' => [
                        '{{WRAPPER}} .hakou-dark-toggle .hakou-dark-toggle__el-icon' => 'font-size: {{SIZE}}{{UNIT}};',
                        '{{WRAPPER}} .hakou-dark-toggle .hakou-dark-toggle__el-icon svg' => 'width: 1em; height: 1em;',
                        '{{WRAPPER}} .hakou-dark-toggle .hakou-dark-toggle__emoji' => 'font-size: {{SIZE}}{{UNIT}};',
                    ],
                ]);

                $this->add_control('icon_color_apply', [
                    'label' => __('SVG : appliquer la couleur sur', 'hakou-dark-mode'),
                    'type' => \Elementor\Controls_Manager::SELECT,
                    'default' => 'both',
                    'options' => [
                        'fill' => __('Remplissage (fill)', 'hakou-dark-mode'),
                        'stroke' => __('Contour (stroke)', 'hakou-dark-mode'),
                        'both' => __('Fill + contour', 'hakou-dark-mode'),
                    ],
                    'description' => __(
                        'Pour les icônes téléversées (SVG). Font Awesome : la couleur s’applique toujours au glyphe.',
                        'hakou-dark-mode'
                    ),
                ]);

                $this->start_controls_tabs('style_mode_tabs');

                $this->start_controls_tab('style_tab_light', [
                    'label' => __('Mode clair', 'hakou-dark-mode'),
                ]);

                $this->add_control('button_bg_light', [
                    'label' => __('Fond du bouton', 'hakou-dark-mode'),
                    'type' => \Elementor\Controls_Manager::COLOR,
                    'default' => '#ffffff',
                    'selectors' => [
                        $btn_light => 'background-color: {{VALUE}} !important; background-image: none !important;',
                    ],
                ]);

                $this->add_control('button_border_color_light', [
                    'label' => __('Bordure', 'hakou-dark-mode'),
                    'type' => \Elementor\Controls_Manager::COLOR,
                    'selectors' => [
                        $btn_light => 'border: 1px solid {{VALUE}} !important;',
                    ],
                ]);

                $this->add_control('icon_color_light', [
                    'label' => __('Couleur de l’icône', 'hakou-dark-mode'),
                    'type' => \Elementor\Controls_Manager::COLOR,
                    'default' => '#111111',
                    'selectors' => hkdm_switch_widget_icon_color_selectors('light'),
                ]);

                $this->end_controls_tab();

                $this->start_controls_tab('style_tab_dark', [
                    'label' => __('Mode sombre', 'hakou-dark-mode'),
                ]);

                $this->add_control('button_bg_dark', [
                    'label' => __('Fond du bouton', 'hakou-dark-mode'),
                    'type' => \Elementor\Controls_Manager::COLOR,
                    'default' => '#111111',
                    'selectors' => [
                        $btn_dark => 'background-color: {{VALUE}} !important; background-image: none !important;',
                    ],
                ]);

                $this->add_control('button_border_color_dark', [
                    'label' => __('Bordure', 'hakou-dark-mode'),
                    'type' => \Elementor\Controls_Manager::COLOR,
                    'selectors' => [
                        $btn_dark => 'border: 1px solid {{VALUE}} !important;',
                    ],
                ]);

                $this->add_control('icon_color_dark', [
                    'label' => __('Couleur de l’icône', 'hakou-dark-mode'),
                    'type' => \Elementor\Controls_Manager::COLOR,
                    'default' => '#ffffff',
                    'selectors' => hkdm_switch_widget_icon_color_selectors('dark'),
                ]);

                $this->end_controls_tab();

                $this->end_controls_tabs();

                $this->end_controls_section();
            }

            protected function render() {
                $settings = $this->get_settings_for_display();

                $icon_apply = hkdm_switch_sanitize_icon_apply($settings['icon_color_apply'] ?? 'both');
                $this->add_render_attribute('_wrapper', 'class', 'hkdm-icon-apply-' . $icon_apply);

                echo hkdm_render_switch_markup([
                    'label' => isset($settings['aria_label']) ? trim((string) $settings['aria_label']) : '',
                    'icon_light' => $settings['icon_light'] ?? [],
                    'icon_dark' => $settings['icon_dark'] ?? [],
                    'from_widget' => true,
                ]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }
        }
    }

    $widgets_manager->register(new HKDM_Switch_Widget());
});

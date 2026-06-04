<?php
/**
 * Ajoute une variante « mode sombre » pour chaque contrôle couleur de l’onglet Style Elementor.
 */

if (!defined('ABSPATH')) {
    exit;
}

final class OGDM_Elementor_Dark_Controls {
    private const SECTION_PREFIX = 'ogdm_dark_';
    private const CONTROL_PREFIX = 'ogdm_dark_';

    /** Widgets déjà gérés manuellement (sections clair/sombre dédiées). */
    private const SKIP_ELEMENTS = [
        'ogdm-switch',
    ];

    public static function init() {
        if (!did_action('elementor/loaded')) {
            return;
        }

        add_action('elementor/element/after_section_end', [__CLASS__, 'inject_dark_section'], 20, 3);
    }

    /**
     * @param \Elementor\Controls_Stack $element
     * @param string $section_id
     * @param array<string, mixed> $args
     */
    public static function inject_dark_section($element, $section_id, $args) {
        if (($args['tab'] ?? '') !== \Elementor\Controls_Manager::TAB_STYLE) {
            return;
        }

        if (strpos($section_id, self::SECTION_PREFIX) === 0) {
            return;
        }

        $element_name = method_exists($element, 'get_name') ? (string) $element->get_name() : '';
        if ($element_name !== '' && in_array($element_name, self::SKIP_ELEMENTS, true)) {
            return;
        }

        $color_controls = self::collect_color_controls($element, $section_id);
        if ($color_controls === []) {
            return;
        }

        $element->start_controls_section(
            self::SECTION_PREFIX . $section_id,
            [
                'label' => __('Mode sombre', 'og-elementor-dark-mode'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $element->add_control(
            self::SECTION_PREFIX . $section_id . '_hint',
            [
                'type' => \Elementor\Controls_Manager::RAW_HTML,
                'raw' => '<p class="elementor-control-field-description">'
                    . esc_html__(
                        'Ces couleurs s’appliquent lorsque le site est en mode sombre (classe og-dark-mode). Laissez vide pour garder la couleur du mode clair.',
                        'og-elementor-dark-mode'
                    )
                    . '</p>',
            ]
        );

        $is_first = true;
        foreach ($color_controls as $control_id => $control) {
            self::register_dark_color_control($element, $control_id, $control, $is_first);
            $is_first = false;
        }

        $element->end_controls_section();
    }

    /**
     * @param \Elementor\Controls_Stack $element
     * @return array<string, array<string, mixed>>
     */
    private static function collect_color_controls($element, $section_id) {
        $found = [];

        foreach ($element->get_controls() as $control_id => $control) {
            if (($control['section'] ?? '') !== $section_id) {
                continue;
            }

            if (strpos((string) $control_id, self::CONTROL_PREFIX) === 0) {
                continue;
            }

            $type = $control['type'] ?? '';
            if ($type !== \Elementor\Controls_Manager::COLOR && $type !== 'color') {
                continue;
            }

            $selectors = $control['selectors'] ?? null;
            if (!is_array($selectors) || $selectors === []) {
                continue;
            }

            $found[$control_id] = $control;
        }

        return $found;
    }

    /**
     * @param \Elementor\Controls_Stack $element
     * @param array<string, mixed> $control
     */
    private static function register_dark_color_control($element, $control_id, array $control, $is_first) {
        $dark_selectors = self::prefix_selectors_for_dark_mode($control['selectors']);
        if ($dark_selectors === []) {
            return;
        }

        $label = isset($control['label']) ? (string) $control['label'] : $control_id;
        $dark_id = self::CONTROL_PREFIX . $control_id;

        $dark_args = [
            'label' => sprintf(
                /* translators: %s: label of the light mode color control */
                __('%s — mode sombre', 'og-elementor-dark-mode'),
                $label
            ),
            'type' => \Elementor\Controls_Manager::COLOR,
            'selectors' => $dark_selectors,
        ];

        if ($is_first) {
            $dark_args['separator'] = 'before';
        }

        foreach (['condition', 'conditions', 'alpha', 'default', 'global', 'description'] as $key) {
            if (isset($control[$key])) {
                $dark_args[$key] = $control[$key];
            }
        }

        if (!empty($control['responsive']) || !empty($control['is_responsive'])) {
            $element->add_responsive_control($dark_id, $dark_args);
            return;
        }

        $element->add_control($dark_id, $dark_args);
    }

    /**
     * Préfixe les sélecteurs pour n’appliquer les styles qu’en mode sombre.
     *
     * @param array<string, string> $selectors
     * @return array<string, string>
     */
    public static function prefix_selectors_for_dark_mode(array $selectors) {
        $out = [];

        foreach ($selectors as $selector => $css_rule) {
            if (!is_string($selector) || $selector === '' || !is_string($css_rule)) {
                continue;
            }

            if (stripos($selector, 'og-dark-mode') !== false) {
                $out[$selector] = $css_rule;
                continue;
            }

            $parts = preg_split('/\s*,\s*/', $selector);
            if (!is_array($parts)) {
                $parts = [$selector];
            }

            $prefixed = [];
            foreach ($parts as $part) {
                $part = trim($part);
                if ($part === '') {
                    continue;
                }
                if (stripos($part, 'og-dark-mode') !== false) {
                    $prefixed[] = $part;
                } else {
                    $prefixed[] = 'body.og-dark-mode ' . $part;
                }
            }

            if ($prefixed !== []) {
                $out[implode(', ', $prefixed)] = $css_rule;
            }
        }

        return $out;
    }
}

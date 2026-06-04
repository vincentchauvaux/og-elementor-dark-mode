# OG Elementor Dark Mode — v1.4.2

## Widget Dark Mode Switch — Style

Une section **Apparence** : dimensions + onglets **Mode clair** / **Mode sombre** (bouton + icône).

**SVG : appliquer la couleur sur** : fill | stroke | both (classe `ogdm-icon-apply-*` sur le wrapper).

## Style Elementor (autres widgets)

Section **Mode sombre** auto par bloc Style avec couleurs — `includes/class-ogdm-elementor-dark-controls.php`.

## Couleurs ACF (taxonomies)

`includes/class-ogdm-acf-term-colors.php` — scan auto des champs ACF `color_picker` sur les taxonomies publiques.

- Admin **OG Dark Mode** → section « Couleurs ACF (taxonomies) »
- Map clair → sombre pour variables dynamiques (`--gl-term-color`, `--gl-current-term-color`, filtrables)
- Défaut sombre : champ ACF `{field}_sombre` / `couleur_sombre` si présent sur le terme
- Front : `dark-mode.js` applique la map au toggle + après mutations DOM (Loop Grid AJAX)
- Event : `ogdm:mode-changed` (detail.dark) pour re-sync thème enfant
- Filtres : `ogdm_acf_term_color_sources`, `ogdm_dynamic_css_vars`, `ogdm_acf_dark_color_map`

## ZIP

`powershell -ExecutionPolicy Bypass -File build-zip.ps1

## D�p�t Git

- Remote : `git@github.com:vincentchauvaux/og-elementor-dark-mode.git`
- Script ZIP : `build-zip.ps1` (sortie `og-elementor-dark-mode.zip`)
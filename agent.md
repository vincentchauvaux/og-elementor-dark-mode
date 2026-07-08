# Hakou Dark Mode — v1.5.1

## Identifiants plugin

- Fichier principal : `hakou-dark-mode.php`
- Slug dossier / ZIP : `hakou-dark-mode`
- Text domain : `hakou-dark-mode`
- Préfixe PHP/JS : `hkdm_` / `HKDM_`
- Classe CSS mode sombre : `hakou-dark-mode`
- Switch front : `hakou-dark-toggle`
- Menu admin : `hakou-dark-mode`
- Shortcode : `[hakou_dark_switch]`
- Widget Elementor : `hkdm-switch`

## Préparation WordPress.org (public)

- Header plugin aligné publication : `Requires at least: 6.0`, licence GPLv2+, `License URI`.
- `readme.txt` converti format répertoire WP.org (tags, tested up to, description, screenshots, changelog).
- Fichier `LICENSE.txt` ajouté (GPLv2+).
- ZIP de release via `build-zip.ps1` (sortie `hakou-dark-mode.zip`).
- Assets WP.org dans `assets/` : `icon-256x256.png`, `banner-772x250.png`, `screenshot-1.png`.
- Back office : mini-switch “sombre -> claire” (libellés uniquement) avec UI toggle.
- Site / auteur : `https://hakou.be/` — Hakou.
- Migration legacy : options `ogdm_*` et localStorage `ogdm_dark_mode` migrés automatiquement.
- `readme.txt` : `Tested up to: 7.0` (requis soumission WP.org).

## Widget Dark Mode Switch — Style

Une section **Apparence** : dimensions + onglets **Mode clair** / **Mode sombre** (bouton + icône).

**SVG : appliquer la couleur sur** : fill | stroke | both (classe `hkdm-icon-apply-*` sur le wrapper).

## Style Elementor (autres widgets)

Section **Mode sombre** auto par bloc Style avec couleurs — `includes/class-hkdm-elementor-dark-controls.php`.

## Couleurs ACF (taxonomies)

`includes/class-hkdm-acf-term-colors.php` — scan auto des champs ACF `color_picker` sur les taxonomies publiques.

- Admin **Hakou Dark Mode** → section « Couleurs ACF (taxonomies) »
- Map clair → sombre pour variables dynamiques (`--gl-term-color`, `--gl-current-term-color`, filtrables)
- Défaut sombre : champ ACF `{field}_sombre` / `couleur_sombre` si présent sur le terme
- Front : `dark-mode.js` applique la map au toggle + après mutations DOM (Loop Grid AJAX)
- Event : `hkdm:mode-changed` (detail.dark) pour re-sync thème enfant
- Filtres : `hkdm_acf_term_color_sources`, `hkdm_dynamic_css_vars`, `hkdm_acf_dark_color_map`

## ZIP

`powershell -ExecutionPolicy Bypass -File build-zip.ps1`

## Dépôt Git

- Remote : `git@github.com:vincentchauvaux/og-elementor-dark-mode.git`
- Script ZIP : `build-zip.ps1` (sortie `hakou-dark-mode.zip`)
- Branche suivie : `master` (`origin/master`)

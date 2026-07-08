# Hakou Dark Mode — v1.4.7

## Préparation WordPress.org (public)

- Header plugin aligné publication : `Requires at least: 6.0`, licence GPLv2+, `License URI`.
- `readme.txt` converti format répertoire WP.org (tags, tested up to, description, screenshots, changelog).
- Fichier `LICENSE.txt` ajouté (GPLv2+).
- ZIP de release régénéré via `build-zip.ps1` avec `LICENSE.txt` inclus.
- Assets WP.org ajoutés dans `assets/` :
  - `icon-256x256.png`
  - `banner-772x250.png`
  - `screenshot-1.png`
- ZIP de release régénéré à nouveau après ajout des assets.
- `readme.txt` ajusté : section `== Screenshots ==` ne liste plus que `screenshot-1.png`.
- Back office : ajout d’un mini-switch “sombre -> claire” (libellés uniquement).
- Version bump en `1.4.5` (+ zip régénéré).
- Admin toggle UI : ajout de style “switch” (v1.4.6).
- Bannière WP.org mise à jour.
- Renommage publication : site de référence (`opengraphy.com` -> `hakou.be`), titre et auteur (OG Elementor Dark Mode -> Hakou Dark Mode).

## Widget Dark Mode Switch — Style

Une section **Apparence** : dimensions + onglets **Mode clair** / **Mode sombre** (bouton + icône).

**SVG : appliquer la couleur sur** : fill | stroke | both (classe `ogdm-icon-apply-*` sur le wrapper).

## Style Elementor (autres widgets)

Section **Mode sombre** auto par bloc Style avec couleurs — `includes/class-ogdm-elementor-dark-controls.php`.

## Couleurs ACF (taxonomies)

`includes/class-ogdm-acf-term-colors.php` — scan auto des champs ACF `color_picker` sur les taxonomies publiques.

- Admin **Hakou Dark Mode** → section « Couleurs ACF (taxonomies) »
- Map clair → sombre pour variables dynamiques (`--gl-term-color`, `--gl-current-term-color`, filtrables)
- Défaut sombre : champ ACF `{field}_sombre` / `couleur_sombre` si présent sur le terme
- Front : `dark-mode.js` applique la map au toggle + après mutations DOM (Loop Grid AJAX)
- Event : `ogdm:mode-changed` (detail.dark) pour re-sync thème enfant
- Filtres : `ogdm_acf_term_color_sources`, `ogdm_dynamic_css_vars`, `ogdm_acf_dark_color_map`

## ZIP

`powershell -ExecutionPolicy Bypass -File build-zip.ps1`

## Dépôt Git

- Remote : `git@github.com:vincentchauvaux/og-elementor-dark-mode.git`
- Script ZIP : `build-zip.ps1` (sortie `og-elementor-dark-mode.zip`)
- Branche suivie : `master` (`origin/master`)
- Dernier commit poussé : `9cb4aa5` (plugin initial : `2a5bd7f`)
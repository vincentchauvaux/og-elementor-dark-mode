(function () {
  var cfg = window.hkdmSettings || {};
  var darkClass = cfg.dark_class || 'hakou-dark-mode';
  var storageKey = cfg.storage_key || 'hkdm_dark_mode';
  var acfColorMap = cfg.acf_color_map || {};
  var dynamicCssVars = cfg.dynamic_css_vars || ['--gl-term-color', '--gl-current-term-color'];

  function isDarkOn() {
    try {
      var stored = localStorage.getItem(storageKey);
      if (stored === null && storageKey === 'hkdm_dark_mode') {
        stored = localStorage.getItem('ogdm_dark_mode');
        if (stored !== null) {
          localStorage.setItem(storageKey, stored);
        }
      }
      if (stored === '1') {
        return true;
      }
      if (stored === '0') {
        return false;
      }
    } catch (e) {
      /* localStorage indisponible */
    }
    return false;
  }

  function applyDark(on) {
    document.documentElement.classList.toggle(darkClass, on);
    if (document.body) {
      document.body.classList.toggle(darkClass, on);
    }
  }

  function expandHex3(hex) {
    return hex
      .split('')
      .map(function (c) {
        return c + c;
      })
      .join('');
  }

  function colorMapKey(color) {
    color = String(color || '').trim();
    if (!color) {
      return '';
    }

    if (/^#[0-9a-f]{3}$/i.test(color)) {
      return '#' + expandHex3(color.slice(1).toLowerCase());
    }

    if (/^#[0-9a-f]{6}$/i.test(color)) {
      return color.toLowerCase();
    }

    var rgbMatch = color.match(/^rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/i);
    if (rgbMatch) {
      return (
        'rgb(' +
        parseInt(rgbMatch[1], 10) +
        ',' +
        parseInt(rgbMatch[2], 10) +
        ',' +
        parseInt(rgbMatch[3], 10) +
        ')'
      );
    }

    return color.toLowerCase();
  }

  function resolveDarkColor(lightColor) {
    var key = colorMapKey(lightColor);
    if (!key) {
      return lightColor;
    }

    return acfColorMap[key] || acfColorMap[lightColor] || lightColor;
  }

  function findCard(marker) {
    return (
      marker.closest('.e-loop-item') ||
      marker.closest('.elementor-loop-item') ||
      marker.closest('article') ||
      marker.closest('.elementor-widget-wrap') ||
      marker.closest('.elementor-container') ||
      marker.parentElement
    );
  }

  function storeLightVar(varName, lightValue) {
    if (!document.body || !lightValue) {
      return;
    }
    var attr = 'data-hkdm-light' + varName.replace(/^--/, '--');
    if (!document.body.getAttribute(attr)) {
      document.body.setAttribute(attr, lightValue);
    }
  }

  function getStoredLightVar(varName) {
    if (!document.body) {
      return '';
    }
    var attr = 'data-hkdm-light' + varName.replace(/^--/, '--');
    return document.body.getAttribute(attr) || '';
  }

  function applyMarkerColors(isDark) {
    document.querySelectorAll('[data-term-color]').forEach(function (marker) {
      var light = marker.getAttribute('data-hkdm-light-color') || marker.getAttribute('data-term-color') || '';
      if (!light) {
        return;
      }

      if (!marker.getAttribute('data-hkdm-light-color')) {
        marker.setAttribute('data-hkdm-light-color', light);
      }

      var color = isDark ? resolveDarkColor(light) : light;
      var card = findCard(marker);

      if (card) {
        card.style.setProperty('--gl-term-color', color);
      }
    });
  }

  function applyDynamicCssVars(isDark) {
    if (!document.body) {
      return;
    }

    dynamicCssVars.forEach(function (varName) {
      var light = getStoredLightVar(varName);

      if (!light) {
        light = getComputedStyle(document.body).getPropertyValue(varName).trim();
        if (light) {
          storeLightVar(varName, light);
        }
      }

      if (!light) {
        return;
      }

      var color = isDark ? resolveDarkColor(light) : light;
      document.body.style.setProperty(varName, color);
    });
  }

  function applyDynamicAcfColors() {
    if (!acfColorMap || Object.keys(acfColorMap).length === 0) {
      return;
    }

    var isDark = document.documentElement.classList.contains(darkClass);
    applyMarkerColors(isDark);
    applyDynamicCssVars(isDark);
  }

  function dispatchModeChanged(on) {
    document.dispatchEvent(
      new CustomEvent('hkdm:mode-changed', {
        detail: { dark: on },
      })
    );
  }

  function initDynamicColorsObserver() {
    if (!acfColorMap || Object.keys(acfColorMap).length === 0) {
      return;
    }

    applyDynamicAcfColors();

    if (typeof MutationObserver === 'undefined') {
      return;
    }

    var timer = null;
    var observer = new MutationObserver(function () {
      clearTimeout(timer);
      timer = setTimeout(applyDynamicAcfColors, 50);
    });

    observer.observe(document.body, {
      childList: true,
      subtree: true,
      attributes: true,
      attributeFilter: ['data-term-color', 'style'],
    });
  }

  function setDark(on) {
    applyDark(on);
    try {
      localStorage.setItem(storageKey, on ? '1' : '0');
    } catch (e) {
      /* localStorage indisponible */
    }
    applyDynamicAcfColors();
    dispatchModeChanged(on);
  }

  function init() {
    applyDark(isDarkOn());
    initDynamicColorsObserver();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.hakou-dark-toggle');
    if (!btn) {
      return;
    }
    e.preventDefault();
    setDark(!document.documentElement.classList.contains(darkClass));
  });
})();

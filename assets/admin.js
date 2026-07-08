(function ($) {
  function hexToRgb(hex) {
    hex = String(hex || '').replace('#', '').trim();
    if (hex.length === 3) {
      hex = hex
        .split('')
        .map(function (c) {
          return c + c;
        })
        .join('');
    }
    if (hex.length !== 6) {
      return null;
    }
    return {
      r: parseInt(hex.slice(0, 2), 16),
      g: parseInt(hex.slice(2, 4), 16),
      b: parseInt(hex.slice(4, 6), 16),
    };
  }

  function buildCss(hex, opacity) {
    var op = parseInt(opacity, 10);
    if (isNaN(op)) {
      op = 100;
    }
    if (op <= 0) {
      return 'transparent';
    }
    var rgb = hexToRgb(hex);
    if (!rgb) {
      return '';
    }
    if (op >= 100) {
      return '#' + String(hex).replace('#', '').slice(0, 6).toLowerCase();
    }
    var a = Math.round((op / 100) * 255)
      .toString(16)
      .padStart(2, '0');
    return (
      '#' +
      String(hex).replace('#', '').slice(0, 6).toLowerCase() +
      a
    );
  }

  function parseCssToUi(css, fallbackHex) {
    css = String(css || '').trim();
    if (css === 'transparent') {
      return { hex: fallbackHex, opacity: 0, css: 'transparent' };
    }
    if (/^#[0-9a-fA-F]{8}$/.test(css)) {
      var digits = css.slice(1);
      var alpha = parseInt(digits.slice(6, 8), 16) / 255;
      return {
        hex: '#' + digits.slice(0, 6),
        opacity: Math.round(alpha * 100),
        css: css.toLowerCase(),
      };
    }
    if (/^#[0-9a-fA-F]{6}$/.test(css)) {
      return { hex: css.toLowerCase(), opacity: 100, css: css.toLowerCase() };
    }
    return { hex: fallbackHex, opacity: 100, css: css };
  }

  function updateAlphaGradient($picker, hex) {
    var rgb = hexToRgb(hex);
    if (!rgb) {
      return;
    }
    var start = 'rgba(' + rgb.r + ',' + rgb.g + ',' + rgb.b + ',0)';
    var end = 'rgb(' + rgb.r + ',' + rgb.g + ',' + rgb.b + ')';
    var grad = 'linear-gradient(to right,' + start + ',' + end + ')';
    $picker.find('.hkdm-picker__alpha').css('background', grad);
  }

  function paintPicker($picker) {
    var $hex = $picker.find('.hkdm-picker__hex');
    var $alpha = $picker.find('.hkdm-picker__alpha');
    var $value = $picker.find('.hkdm-picker__value');
    var $fill = $picker.find('.hkdm-picker__fill');
    var $hint = $picker.find('.hkdm-picker__hint');
    var css = buildCss($hex.val(), $alpha.val());

    if (css) {
      $value.val(css);
    }
    $hint.text(css || '');

    if (css === 'transparent') {
      $picker.addClass('is-transparent');
      $fill.css('background', 'transparent');
    } else {
      $picker.removeClass('is-transparent');
      $fill.css('background', css);
    }
    updateAlphaGradient($picker, $hex.val());
  }

  function applyCssToPicker($picker, css) {
    var kitHex = $picker.attr('data-kit-hex') || '#000000';
    var ui = parseCssToUi(css, kitHex);
    $picker.find('.hkdm-picker__hex').val(ui.hex);
    $picker.find('.hkdm-picker__alpha').val(ui.opacity);
    paintPicker($picker);
  }

  function initPicker($picker) {
    paintPicker($picker);
    $picker.find('.hkdm-picker__hex, .hkdm-picker__alpha').on('input change', function () {
      paintPicker($picker);
    });
    $picker.find('.hkdm-picker__surface').on('click', function (e) {
      if (!$(e.target).hasClass('hkdm-picker__alpha')) {
        $picker.find('.hkdm-picker__hex').trigger('click');
      }
    });
  }

  $(function () {
    $('.hkdm-picker[data-color-row]').each(function () {
      initPicker($(this));
    });

    $('#hkdm-reset-colors-default').on('click', function (e) {
      e.preventDefault();
      $('.hkdm-admin-table--globals .hkdm-picker').each(function () {
        applyCssToPicker($(this), $(this).attr('data-kit-default') || '');
      });
    });

    $('#hkdm-reset-button-colors-default').on('click', function (e) {
      e.preventDefault();
      $('.hkdm-admin-table--buttons .hkdm-picker').each(function () {
        applyCssToPicker($(this), $(this).attr('data-kit-default') || '');
      });
    });

    $('#hkdm-reset-acf-colors-default').on('click', function (e) {
      e.preventDefault();
      $('.hkdm-admin-table--acf .hkdm-picker').each(function () {
        applyCssToPicker($(this), $(this).attr('data-kit-default') || '');
      });
    });

    $('form').on('submit', function () {
      $('.hkdm-picker[data-color-row]').each(function () {
        paintPicker($(this));
      });
    });
  });
})(jQuery);

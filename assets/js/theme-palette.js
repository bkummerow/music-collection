/**
 * WCAG 2.x contrast-aware light theme palette from a single primary hex.
 * Exposed as global MusicThemePalette for app.js and inline boot scripts.
 */
(function (global) {
  'use strict';

  var BG_SURFACE = '#ffffff';
  var TEXT_ON_SURFACE = '#333333';
  var TEXT_MUTED_ON_SURFACE = '#6c757d';
  var WHITE = '#ffffff';
  var CONTRAST_AA_TEXT = 4.5;
  var CONTRAST_AA_UI = 3;
  var COLLECTION_TINT_MAX_PERCENT = 12;
  var COLLECTION_TINT_TARGET_MIN_PERCENT = 7;
  var COLLECTION_HOVER_TINT_DELTA = 4;
  var COLLECTION_HEADER_TINT_DELTA = 7;

  var CSS_VAR_KEYS = [
    '--primary-color',
    '--primary-dark',
    '--secondary-color',
    '--link-color',
    '--gradient-color-1',
    '--gradient-color-2',
    '--collection-surface',
    '--collection-surface-hover',
    '--collection-surface-header-hover',
    '--panel-surface',
    '--collection-text-muted',
    '--bg-primary',
  ];

  /**
   * Normalize #RGB or #RRGGBB to lowercase #rrggbb.
   *
   * @param {string} hex
   * @returns {string|null}
   */
  function normalizeHex(hex) {
    if (typeof hex !== 'string') {
      return null;
    }
    var value = hex.trim();
    if (/^#[0-9A-Fa-f]{3}$/.test(value)) {
      var r = value.charAt(1);
      var g = value.charAt(2);
      var b = value.charAt(3);
      value = '#' + r + r + g + g + b + b;
    }
    if (!/^#[0-9A-Fa-f]{6}$/.test(value)) {
      return null;
    }
    return value.toLowerCase();
  }

  /**
   * @param {string} hex #rrggbb
   * @returns {{ r: number, g: number, b: number }}
   */
  function hexToRgb(hex) {
    var normalized = normalizeHex(hex);
    var n = parseInt(normalized.slice(1), 16);
    return {
      r: (n >> 16) & 255,
      g: (n >> 8) & 255,
      b: n & 255,
    };
  }

  /**
   * @param {number} r 0-255
   * @param {number} g 0-255
   * @param {number} b 0-255
   * @returns {string}
   */
  function rgbToHex(r, g, b) {
    var toByte = function (c) {
      var clamped = Math.max(0, Math.min(255, Math.round(c)));
      var hex = clamped.toString(16);
      return hex.length === 1 ? '0' + hex : hex;
    };
    return '#' + toByte(r) + toByte(g) + toByte(b);
  }

  /**
   * WCAG relative luminance for sRGB hex.
   *
   * @param {string} hex
   * @returns {number}
   */
  function relativeLuminance(hex) {
    var rgb = hexToRgb(hex);
    var channels = [rgb.r, rgb.g, rgb.b].map(function (c) {
      var s = c / 255;
      return s <= 0.03928 ? s / 12.92 : Math.pow((s + 0.055) / 1.055, 2.4);
    });
    return 0.2126 * channels[0] + 0.7152 * channels[1] + 0.0722 * channels[2];
  }

  /**
   * WCAG 2.x contrast ratio (always >= 1).
   *
   * @param {string} hexA
   * @param {string} hexB
   * @returns {number}
   */
  function contrastRatio(hexA, hexB) {
    var l1 = relativeLuminance(hexA);
    var l2 = relativeLuminance(hexB);
    var lighter = Math.max(l1, l2);
    var darker = Math.min(l1, l2);
    return (lighter + 0.05) / (darker + 0.05);
  }

  /**
   * @param {number} r 0-255
   * @param {number} g 0-255
   * @param {number} b 0-255
   * @returns {{ h: number, s: number, l: number }}
   */
  function rgbToHsl(r, g, b) {
    r /= 255;
    g /= 255;
    b /= 255;
    var max = Math.max(r, g, b);
    var min = Math.min(r, g, b);
    var h = 0;
    var s = 0;
    var l = (max + min) / 2;

    if (max !== min) {
      var d = max - min;
      s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
      switch (max) {
        case r:
          h = (g - b) / d + (g < b ? 6 : 0);
          break;
        case g:
          h = (b - r) / d + 2;
          break;
        default:
          h = (r - g) / d + 4;
          break;
      }
      h /= 6;
    }

    return { h: h * 360, s: s, l: l };
  }

  /**
   * @param {number} h 0-360
   * @param {number} s 0-1
   * @param {number} l 0-1
   * @returns {{ r: number, g: number, b: number }}
   */
  function hslToRgb(h, s, l) {
    var hue = ((h % 360) + 360) % 360;
    var c = (1 - Math.abs(2 * l - 1)) * s;
    var x = c * (1 - Math.abs(((hue / 60) % 2) - 1));
    var m = l - c / 2;
    var rp = 0;
    var gp = 0;
    var bp = 0;

    if (hue < 60) {
      rp = c;
      gp = x;
    } else if (hue < 120) {
      rp = x;
      gp = c;
    } else if (hue < 180) {
      gp = c;
      bp = x;
    } else if (hue < 240) {
      gp = x;
      bp = c;
    } else if (hue < 300) {
      rp = x;
      bp = c;
    } else {
      rp = c;
      bp = x;
    }

    return {
      r: (rp + m) * 255,
      g: (gp + m) * 255,
      b: (bp + m) * 255,
    };
  }

  /**
   * @param {string} hex
   * @param {number} lightness 0-1
   * @returns {string}
   */
  function hexWithLightness(hex, lightness) {
    var rgb = hexToRgb(hex);
    var hsl = rgbToHsl(rgb.r, rgb.g, rgb.b);
    var next = hslToRgb(hsl.h, hsl.s, Math.max(0, Math.min(1, lightness)));
    return rgbToHex(next.r, next.g, next.b);
  }

  /**
   * Darken a color (reduce HSL L) until foreground/background meets target contrast.
   *
   * @param {string} fgHex Starting foreground
   * @param {string} bgHex Background
   * @param {number} minRatio Target ratio
   * @returns {string}
   */
  function darkenUntilContrast(fgHex, bgHex, minRatio) {
    var normalized = normalizeHex(fgHex);
    if (!normalized) {
      return fgHex;
    }
    if (contrastRatio(normalized, bgHex) >= minRatio) {
      return normalized;
    }

    var rgb = hexToRgb(normalized);
    var hsl = rgbToHsl(rgb.r, rgb.g, rgb.b);
    var step = 0.02;
    var lightness = hsl.l;

    while (lightness > 0.05) {
      lightness -= step;
      var rgbOut = hslToRgb(hsl.h, hsl.s, lightness);
      var candidate = rgbToHex(rgbOut.r, rgbOut.g, rgbOut.b);
      if (contrastRatio(candidate, bgHex) >= minRatio) {
        return candidate;
      }
    }

    var floorRgb = hslToRgb(hsl.h, hsl.s, 0.05);
    return rgbToHex(floorRgb.r, floorRgb.g, floorRgb.b);
  }

  /**
   * Mix a brand hex with white (sRGB lerp).
   *
   * @param {string} hex
   * @param {number} primaryPercent 0-100
   * @returns {string}
   */
  function mixWithWhite(hex, primaryPercent) {
    var rgb = hexToRgb(hex);
    var ratio = Math.max(0, Math.min(100, primaryPercent)) / 100;
    return rgbToHex(
      rgb.r * ratio + 255 * (1 - ratio),
      rgb.g * ratio + 255 * (1 - ratio),
      rgb.b * ratio + 255 * (1 - ratio)
    );
  }

  /**
   * Whether primary body text meets AA on a collection background.
   *
   * @param {string} surfaceHex
   * @returns {boolean}
   */
  function collectionSurfacePassesPrimaryText(surfaceHex) {
    return (
      contrastRatio(TEXT_ON_SURFACE, surfaceHex) >= CONTRAST_AA_TEXT &&
      contrastRatio('#495057', surfaceHex) >= CONTRAST_AA_TEXT
    );
  }

  /**
   * Darken muted copy until it meets AA on every collection surface in the set.
   *
   * @param {string[]} surfaceHexes
   * @returns {string}
   */
  function collectionTextMutedForSurfaces(surfaceHexes) {
    var muted = TEXT_MUTED_ON_SURFACE;
    var i;
    for (i = 0; i < surfaceHexes.length; i++) {
      if (contrastRatio(muted, surfaceHexes[i]) < CONTRAST_AA_TEXT) {
        muted = darkenUntilContrast(muted, surfaceHexes[i], CONTRAST_AA_TEXT);
      }
    }
    return muted;
  }

  /**
   * Link color that meets AA on white and on primary-tinted collection surfaces.
   *
   * @param {string} seedHex User primary
   * @param {string[]} surfaceHexes Backgrounds where links appear
   * @returns {string}
   */
  function linkColorForSurfaces(seedHex, surfaceHexes) {
    var link = normalizeHex(seedHex) || seedHex;
    var i;
    for (i = 0; i < surfaceHexes.length; i++) {
      if (contrastRatio(link, surfaceHexes[i]) < CONTRAST_AA_TEXT) {
        link = darkenUntilContrast(link, surfaceHexes[i], CONTRAST_AA_TEXT);
      }
    }
    return link;
  }

  /**
   * Primary-tinted collection surfaces; stronger tint with adjusted muted text for AA.
   *
   * @param {string} seedHex User primary
   * @returns {{ surface: string, hover: string, headerHover: string, tintPercent: number, textMuted: string }}
   */
  function buildCollectionSurfaces(seedHex) {
    var normalized = normalizeHex(seedHex);
    if (!normalized) {
      return {
        surface: WHITE,
        hover: '#f8f9fa',
        headerHover: '#e9ecef',
        tintPercent: 0,
        textMuted: TEXT_MUTED_ON_SURFACE,
      };
    }

    var chosenPercent = 0;
    var surface = WHITE;
    var percent;

    for (percent = COLLECTION_TINT_MAX_PERCENT; percent >= COLLECTION_TINT_TARGET_MIN_PERCENT; percent -= 0.5) {
      var strongCandidate = mixWithWhite(normalized, percent);
      if (collectionSurfacePassesPrimaryText(strongCandidate)) {
        chosenPercent = percent;
        surface = strongCandidate;
        break;
      }
    }

    if (chosenPercent === 0) {
      var textSamples = [TEXT_ON_SURFACE, '#495057', TEXT_MUTED_ON_SURFACE];
      for (percent = COLLECTION_TINT_MAX_PERCENT; percent >= 3; percent -= 0.5) {
        var legacyCandidate = mixWithWhite(normalized, percent);
        var passes = true;
        var i;
        for (i = 0; i < textSamples.length; i++) {
          if (contrastRatio(textSamples[i], legacyCandidate) < CONTRAST_AA_TEXT) {
            passes = false;
            break;
          }
        }
        if (passes) {
          chosenPercent = percent;
          surface = legacyCandidate;
          break;
        }
      }
    }

    var hoverPercent = chosenPercent + COLLECTION_HOVER_TINT_DELTA;
    var headerPercent = chosenPercent + COLLECTION_HEADER_TINT_DELTA;
    var hover = mixWithWhite(normalized, hoverPercent);
    var headerHover = mixWithWhite(normalized, headerPercent);

    while (
      hoverPercent > chosenPercent &&
      !collectionSurfacePassesPrimaryText(hover)
    ) {
      hoverPercent -= 0.5;
      hover = mixWithWhite(normalized, hoverPercent);
    }

    while (
      headerPercent > hoverPercent &&
      !collectionSurfacePassesPrimaryText(headerHover)
    ) {
      headerPercent -= 0.5;
      headerHover = mixWithWhite(normalized, headerPercent);
    }

    var textMuted = collectionTextMutedForSurfaces([surface, hover, headerHover]);

    return {
      surface: surface,
      hover: hover,
      headerHover: headerHover,
      tintPercent: chosenPercent,
      textMuted: textMuted,
    };
  }

  /**
   * Build gradient stop 2: same family, hue shift, slightly deeper for depth.
   *
   * @param {string} primaryHex
   * @returns {string}
   */
  function buildGradientStopTwo(primaryHex) {
    var rgb = hexToRgb(primaryHex);
    var hsl = rgbToHsl(rgb.r, rgb.g, rgb.b);
    var hue = (hsl.h + 32) % 360;
    var sat = Math.min(1, hsl.s + 0.08);
    var light = Math.max(0.22, Math.min(0.62, hsl.l - 0.12));
    var stop = hslToRgb(hue, sat, light);
    return rgbToHex(stop.r, stop.g, stop.b);
  }

  /**
   * Warn when the user's chosen primary cannot meet WCAG on white as-is.
   *
   * @param {string} primaryHex
   * @returns {{ message: string, contrastOnWhite: number }|null}
   */
  function getPrimaryContrastWarning(primaryHex) {
    var seed = normalizeHex(primaryHex);
    if (!seed) {
      return {
        message:
          'Enter a valid hex color (for example #667eea). Invalid colors cannot meet contrast requirements.',
        contrastOnWhite: 0,
      };
    }

    var onWhite = contrastRatio(seed, WHITE);
    if (onWhite >= CONTRAST_AA_TEXT) {
      return null;
    }

    if (onWhite >= CONTRAST_AA_UI) {
      return {
        message:
          'This primary color does not meet WCAG 2.x contrast on white backgrounds. Selecting it means links, buttons, and borders will use darkened tones instead of your exact color.',
        contrastOnWhite: onWhite,
      };
    }

    return {
      message:
        'This primary color is too light for accessible contrast on white backgrounds. The app cannot use it for interactive elements while meeting WCAG guidelines; controls will appear much darker, and the page gradient may be hard to see.',
      contrastOnWhite: onWhite,
    };
  }

  /**
   * Generate light-mode semantic colors from one user primary.
   *
   * @param {string} primaryHex
   * @returns {object|null}
   */
  function buildLightThemePalette(primaryHex) {
    var seed = normalizeHex(primaryHex);
    if (!seed) {
      return null;
    }

    var gradientColor1 = seed;
    var gradientColor2 = buildGradientStopTwo(seed);

    var primaryColor = darkenUntilContrast(seed, BG_SURFACE, CONTRAST_AA_UI);
    var primaryDark = darkenUntilContrast(seed, WHITE, CONTRAST_AA_TEXT);
    var secondaryColor = gradientColor2;

    if (contrastRatio(primaryDark, WHITE) < CONTRAST_AA_TEXT) {
      primaryDark = darkenUntilContrast(primaryDark, WHITE, CONTRAST_AA_TEXT);
    }

    var buttonTextColor = contrastRatio(WHITE, primaryDark) >= CONTRAST_AA_TEXT ? WHITE : TEXT_ON_SURFACE;
    var collectionSurfaces = buildCollectionSurfaces(seed);
    var linkColor = linkColorForSurfaces(seed, [
      BG_SURFACE,
      collectionSurfaces.surface,
      collectionSurfaces.hover,
      collectionSurfaces.headerHover,
    ]);

    return {
      primaryColor: primaryColor,
      primaryDark: primaryDark,
      secondaryColor: secondaryColor,
      linkColor: linkColor,
      gradientColor1: gradientColor1,
      gradientColor2: gradientColor2,
      buttonTextColor: buttonTextColor,
      collectionSurface: collectionSurfaces.surface,
      collectionSurfaceHover: collectionSurfaces.hover,
      collectionSurfaceHeaderHover: collectionSurfaces.headerHover,
      collectionTextMuted: collectionSurfaces.textMuted,
      collectionTintPercent: collectionSurfaces.tintPercent,
      chosenPrimary: seed,
      contrastWarning: getPrimaryContrastWarning(seed),
      adjustedForContrast: primaryColor !== seed || linkColor !== seed,
      contrasts: {
        primaryOnSurface: contrastRatio(primaryColor, BG_SURFACE),
        linkOnSurface: contrastRatio(linkColor, BG_SURFACE),
        linkOnCollectionSurface: contrastRatio(linkColor, collectionSurfaces.surface),
        whiteOnPrimaryDark: contrastRatio(WHITE, primaryDark),
        textOnSurface: contrastRatio(TEXT_ON_SURFACE, BG_SURFACE),
        textOnCollectionSurface: contrastRatio(
          TEXT_ON_SURFACE,
          collectionSurfaces.surface
        ),
        mutedOnCollectionSurface: contrastRatio(
          collectionSurfaces.textMuted,
          collectionSurfaces.surface
        ),
      },
    };
  }

  /**
   * Apply palette tokens to documentElement and optional body gradient.
   *
   * @param {object} palette From buildLightThemePalette
   * @param {{ applyBodyGradient?: boolean }} [options]
   */
  function applyToDocument(palette, options) {
    if (!palette || typeof document === 'undefined') {
      return;
    }
    var opts = options || {};
    var root = document.documentElement;

    root.style.setProperty('--primary-color', palette.primaryColor);
    root.style.setProperty('--primary-dark', palette.primaryDark);
    root.style.setProperty('--secondary-color', palette.secondaryColor);
    root.style.setProperty('--link-color', palette.linkColor);
    root.style.setProperty('--gradient-color-1', palette.gradientColor1);
    root.style.setProperty('--gradient-color-2', palette.gradientColor2);
    root.style.setProperty('--collection-surface', palette.collectionSurface);
    root.style.setProperty('--collection-surface-hover', palette.collectionSurfaceHover);
    root.style.setProperty(
      '--collection-surface-header-hover',
      palette.collectionSurfaceHeaderHover
    );
    root.style.setProperty('--panel-surface', palette.collectionSurfaceHover);
    root.style.setProperty('--collection-text-muted', palette.collectionTextMuted);
    root.style.setProperty('--bg-primary', palette.collectionSurface);

    if (opts.applyBodyGradient !== false && document.body) {
      document.body.style.background =
        'linear-gradient(135deg, ' +
        palette.gradientColor1 +
        ' 0%, ' +
        palette.gradientColor2 +
        ' 100%)';
    }
  }

  /**
   * Remove inline theme overrides set by applyToDocument.
   */
  function clearFromDocument() {
    if (typeof document === 'undefined') {
      return;
    }
    var root = document.documentElement;
    CSS_VAR_KEYS.forEach(function (key) {
      root.style.removeProperty(key);
    });
    if (document.body) {
      document.body.style.background = '';
    }
  }

  global.MusicThemePalette = {
    buildLightThemePalette: buildLightThemePalette,
    getPrimaryContrastWarning: getPrimaryContrastWarning,
    applyToDocument: applyToDocument,
    clearFromDocument: clearFromDocument,
    contrastRatio: contrastRatio,
    normalizeHex: normalizeHex,
    DEFAULT_PRIMARY: '#667eea',
  };
})(typeof window !== 'undefined' ? window : this);

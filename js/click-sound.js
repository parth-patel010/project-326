/**
 * FoodMitra hotel-admin UI click sound (EatnSay-style).
 * Capture-phase listener so every button/link/onclick fires, including dynamic nodes.
 */
(function () {
  if (window.__FmClickSoundInit) return;
  window.__FmClickSoundInit = true;

  var SOUND_PATH = 'assets/ui-click.mp3';
  // Resolve relative to current page (ha-api/, popup root, etc.)
  try {
    var base = document.querySelector('script[src*="click-sound.js"]');
    if (base && base.src) {
      SOUND_PATH = base.src.replace(/js\/click-sound\.js.*$/i, 'assets/ui-click.mp3');
    }
  } catch (e) {}

  var template = new Audio(SOUND_PATH);
  template.volume = 0.5;
  template.preload = 'auto';

  window.playClickSound = function () {
    try {
      var s = template.cloneNode();
      s.volume = 0.5;
      var p = s.play();
      if (p && typeof p.catch === 'function') p.catch(function () {});
    } catch (e) {}
  };

  // Unlock audio on first user gesture (autoplay policy)
  document.addEventListener(
    'pointerdown',
    function unlock() {
      template.play().then(function () {
        template.pause();
        template.currentTime = 0;
      }).catch(function () {});
    },
    { once: true, capture: true }
  );

  document.addEventListener(
    'click',
    function (e) {
      var t = e.target && e.target.closest
        ? e.target.closest(
            'button, a, [onclick], .btn, .menu-card, .cat-chip, .sidebar-item, .category-icon-option, label.category-icon-option, input[type="submit"], input[type="button"], [role="button"]'
          )
        : null;
      if (!t) return;
      if (t.disabled || t.getAttribute('aria-disabled') === 'true') return;
      if (t.href && String(t.href).indexOf('logout') !== -1) return;
      window.playClickSound();
    },
    true
  );
})();

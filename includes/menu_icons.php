<?php

declare(strict_types=1);

/**
 * Category icon keys used by the customer app (constants/menu-data.ts).
 * Admin preview uses Material Symbols names.
 *
 * @return list<array{key:string,label:string,symbol:string}>
 */
function ha_menu_icons(): array
{
    return [
        ['key' => 'meal', 'label' => 'Meal / Thali', 'symbol' => 'restaurant'],
        ['key' => 'tea', 'label' => 'Tea / Hot drinks', 'symbol' => 'emoji_food_beverage'],
        ['key' => 'drink', 'label' => 'Cold drinks', 'symbol' => 'local_cafe'],
        ['key' => 'snack', 'label' => 'Snacks', 'symbol' => 'bakery_dining'],
        ['key' => 'dessert', 'label' => 'Dessert', 'symbol' => 'icecream'],
        ['key' => 'salad', 'label' => 'Salad / Light', 'symbol' => 'eco'],
        ['key' => 'all', 'label' => 'General / All', 'symbol' => 'grid_view'],
    ];
}

function ha_menu_icon_symbol(string $key): string
{
    foreach (ha_menu_icons() as $icon) {
        if ($icon['key'] === $key) {
            return $icon['symbol'];
        }
    }
    return 'restaurant';
}

function ha_normalize_menu_icon(string $key): string
{
    $key = strtolower(trim($key));
    foreach (ha_menu_icons() as $icon) {
        if ($icon['key'] === $key) {
            return $key;
        }
    }
    return 'meal';
}

/**
 * Renders icon <select> + live Material Symbol preview.
 *
 * @param string $name Input name
 * @param string $selected Current key
 * @param string $selectClass Extra classes for <select>
 * @param string $wrapClass Wrapper classes
 */
function ha_render_icon_select(
    string $name = 'icon',
    string $selected = 'meal',
    string $selectClass = 'input !mb-0',
    string $wrapClass = ''
): void {
    $selected = ha_normalize_menu_icon($selected);
    $uid = 'iconPick_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $name) . '_' . substr(md5(uniqid('', true)), 0, 6);
    $sym = ha_menu_icon_symbol($selected);
    ?>
    <div class="ha-icon-pick flex items-center gap-2 <?= ha_h($wrapClass) ?>" data-icon-pick="<?= ha_h($uid) ?>">
      <span class="ha-icon-preview w-10 h-10 rounded-lg bg-primary-soft text-primary flex items-center justify-center shrink-0 border border-primary/10" aria-hidden="true">
        <span class="material-symbols-outlined text-[22px]" data-icon-symbol><?= ha_h($sym) ?></span>
      </span>
      <select name="<?= ha_h($name) ?>" id="<?= ha_h($uid) ?>" class="<?= ha_h($selectClass) ?> flex-1 min-w-0" data-icon-select>
        <?php foreach (ha_menu_icons() as $icon): ?>
          <option value="<?= ha_h($icon['key']) ?>"
                  data-symbol="<?= ha_h($icon['symbol']) ?>"
                  <?= $selected === $icon['key'] ? 'selected' : '' ?>>
            <?= ha_h($icon['label']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php
}

function ha_icon_pick_script(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    ?>
    <script>
    (function () {
      function bind(root) {
        var sel = root.querySelector('[data-icon-select]');
        var sym = root.querySelector('[data-icon-symbol]');
        if (!sel || !sym) return;
        function sync() {
          var opt = sel.options[sel.selectedIndex];
          if (opt && opt.getAttribute('data-symbol')) {
            sym.textContent = opt.getAttribute('data-symbol');
          }
        }
        sel.addEventListener('change', sync);
        sync();
      }
      document.querySelectorAll('[data-icon-pick]').forEach(bind);
    })();
    </script>
    <?php
}

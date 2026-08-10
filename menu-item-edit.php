<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
ha_require_login();

$hotelId = (int) $_SESSION['ha_hotel_id'];
$pdo = admin_db();
$id = isset($_GET['id']) && ctype_digit((string)$_GET['id']) ? (int) $_GET['id'] : 0;
$error = '';
$flash = '';
$item = null;

$cols = [
    'variants_json' => ha_col_exists('menu_items', 'variants_json', $pdo),
    'extras_json' => ha_col_exists('menu_items', 'extras_json', $pdo),
    'is_jain' => ha_col_exists('menu_items', 'is_jain', $pdo),
    'is_spicy' => ha_col_exists('menu_items', 'is_spicy', $pdo),
    'is_sugar_free' => ha_col_exists('menu_items', 'is_sugar_free', $pdo),
    'gst_inclusive' => ha_col_exists('menu_items', 'gst_inclusive', $pdo),
];

if ($id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM menu_items WHERE id=:id AND hotel_id=:h LIMIT 1');
    $stmt->execute([':id' => $id, ':h' => $hotelId]);
    $item = $stmt->fetch() ?: null;
    if (!$item) {
        header('Location: menu-items.php');
        exit;
    }
}

$cats = $pdo->prepare('SELECT id, name FROM menu_categories WHERE hotel_id=:h AND is_active=1 ORDER BY sort_order, name');
$cats->execute([':h' => $hotelId]);
$categories = $cats->fetchAll();

$parseJsonList = static function (?string $raw): array {
    if ($raw === null || $raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [];
    }
    $out = [];
    foreach ($decoded as $row) {
        if (!is_array($row)) {
            continue;
        }
        $name = trim((string) ($row['name'] ?? ''));
        if ($name === '') {
            continue;
        }
        $out[] = [
            'name' => $name,
            'price' => round((float) ($row['price'] ?? 0), 2),
        ];
    }
    return $out;
};

$variants = $parseJsonList($item['variants_json'] ?? null);
$extras = $parseJsonList($item['extras_json'] ?? null);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $desc = trim((string) ($_POST['description'] ?? ''));
    $price = (float) ($_POST['price'] ?? 0);
    $categoryId = (int) ($_POST['category_id'] ?? 0);
    $image = trim((string) ($_POST['image'] ?? ''));
    if ($image === '') {
        $image = 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=800&q=80';
    }
    $recommended = !empty($_POST['is_recommended']) ? 1 : 0;
    $available = !empty($_POST['is_available']) ? 1 : 0;
    $sort = (int) ($_POST['sort_order'] ?? 0);
    $isJain = !empty($_POST['is_jain']) ? 1 : 0;
    $isSpicy = !empty($_POST['is_spicy']) ? 1 : 0;
    $isSugarFree = !empty($_POST['is_sugar_free']) ? 1 : 0;
    $gstInclusive = isset($_POST['gst_inclusive']) ? ((int) $_POST['gst_inclusive'] === 1 ? 1 : 0) : 1;

    $variants = $parseJsonList((string) ($_POST['variants_json'] ?? '[]'));
    $extras = $parseJsonList((string) ($_POST['extras_json'] ?? '[]'));

    $catOk = false;
    foreach ($categories as $c) {
        if ((int) $c['id'] === $categoryId) {
            $catOk = true;
            break;
        }
    }

    if ($name === '' || $price <= 0 || !$catOk) {
        $error = 'Name, valid category, and price are required';
    } else {
        try {
            $sets = [
                'category_id=:c', 'name=:n', 'description=:d', 'price=:p', 'image=:img',
                'is_veg=1', 'is_recommended=:rec', 'is_available=:av', 'sort_order=:s',
            ];
            $params = [
                ':c' => $categoryId, ':n' => $name, ':d' => $desc !== '' ? $desc : null, ':p' => $price,
                ':img' => $image, ':rec' => $recommended, ':av' => $available, ':s' => $sort,
                ':h' => $hotelId,
            ];

            if ($cols['variants_json']) {
                $sets[] = 'variants_json=:variants';
                $params[':variants'] = $variants ? json_encode($variants) : null;
            }
            if ($cols['extras_json']) {
                $sets[] = 'extras_json=:extras';
                $params[':extras'] = $extras ? json_encode($extras) : null;
            }
            if ($cols['is_jain']) {
                $sets[] = 'is_jain=:jain';
                $params[':jain'] = $isJain;
            }
            if ($cols['is_spicy']) {
                $sets[] = 'is_spicy=:spicy';
                $params[':spicy'] = $isSpicy;
            }
            if ($cols['is_sugar_free']) {
                $sets[] = 'is_sugar_free=:sugar';
                $params[':sugar'] = $isSugarFree;
            }
            if ($cols['gst_inclusive']) {
                $sets[] = 'gst_inclusive=:gsti';
                $params[':gsti'] = $gstInclusive;
            }

            if ($item) {
                $params[':id'] = $id;
                $pdo->prepare(
                    'UPDATE menu_items SET ' . implode(', ', $sets) . ' WHERE id=:id AND hotel_id=:h'
                )->execute($params);
                $flash = 'Item updated';
                $stmt = $pdo->prepare('SELECT * FROM menu_items WHERE id=:id AND hotel_id=:h');
                $stmt->execute([':id' => $id, ':h' => $hotelId]);
                $item = $stmt->fetch();
                $variants = $parseJsonList($item['variants_json'] ?? null);
                $extras = $parseJsonList($item['extras_json'] ?? null);
            } else {
                $publicId = 'MI' . strtoupper(bin2hex(random_bytes(5)));
                $insertCols = ['public_id', 'hotel_id', 'category_id', 'name', 'description', 'price', 'image', 'is_veg', 'is_recommended', 'is_available', 'sort_order'];
                $insertVals = [':pid', ':h', ':c', ':n', ':d', ':p', ':img', '1', ':rec', ':av', ':s'];
                $insParams = [
                    ':pid' => $publicId, ':h' => $hotelId, ':c' => $categoryId, ':n' => $name,
                    ':d' => $desc !== '' ? $desc : null, ':p' => $price, ':img' => $image,
                    ':rec' => $recommended, ':av' => $available, ':s' => $sort,
                ];
                if ($cols['variants_json']) {
                    $insertCols[] = 'variants_json';
                    $insertVals[] = ':variants';
                    $insParams[':variants'] = $variants ? json_encode($variants) : null;
                }
                if ($cols['extras_json']) {
                    $insertCols[] = 'extras_json';
                    $insertVals[] = ':extras';
                    $insParams[':extras'] = $extras ? json_encode($extras) : null;
                }
                if ($cols['is_jain']) {
                    $insertCols[] = 'is_jain';
                    $insertVals[] = ':jain';
                    $insParams[':jain'] = $isJain;
                }
                if ($cols['is_spicy']) {
                    $insertCols[] = 'is_spicy';
                    $insertVals[] = ':spicy';
                    $insParams[':spicy'] = $isSpicy;
                }
                if ($cols['is_sugar_free']) {
                    $insertCols[] = 'is_sugar_free';
                    $insertVals[] = ':sugar';
                    $insParams[':sugar'] = $isSugarFree;
                }
                if ($cols['gst_inclusive']) {
                    $insertCols[] = 'gst_inclusive';
                    $insertVals[] = ':gsti';
                    $insParams[':gsti'] = $gstInclusive;
                }
                $pdo->prepare(
                    'INSERT INTO menu_items (' . implode(', ', $insertCols) . ') VALUES (' . implode(', ', $insertVals) . ')'
                )->execute($insParams);
                header('Location: menu-items.php');
                exit;
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$title = $item ? 'Edit menu item' : 'Add menu item';
$gstInclusiveVal = isset($item['gst_inclusive']) ? (int) $item['gst_inclusive'] : 1;

ha_layout_start($title, 'menu-items.php', 'Pure veg dishes for FoodMitra');
if ($flash): ?><div class="flash"><?= ha_h($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div style="background:#fef2f2;border:1px solid #fecaca;color:#991b1b;padding:0.75rem 1rem;border-radius:0.5rem;margin-bottom:1rem;font-size:0.875rem"><?= ha_h($error) ?></div><?php endif; ?>

<?php if (!$categories): ?>
  <div class="card">No categories yet. <a class="font-semibold text-primary underline" href="categories.php">Add a category</a> first.</div>
<?php else: ?>
<form method="post" class="card max-w-3xl" id="itemForm">
  <div class="flex items-center justify-between mb-4">
    <h3 class="!mb-0"><?= ha_h($title) ?></h3>
    <a href="menu-items.php" class="text-sm font-medium text-gray-500 hover:text-primary">← Back</a>
  </div>

  <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4">
    <div class="sm:col-span-2"><label>Name</label><input class="input" name="name" required maxlength="255" value="<?= ha_h($item['name'] ?? '') ?>"></div>
    <div class="sm:col-span-2"><label>Description</label><textarea class="input" name="description" rows="3" maxlength="1000"><?= ha_h($item['description'] ?? '') ?></textarea></div>
    <div>
      <label>Category</label>
      <select class="input" name="category_id" required>
        <?php foreach ($categories as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= (int)($item['category_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= ha_h($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div><label>Base price ₹</label><input class="input" type="number" step="0.01" min="1" name="price" required value="<?= ha_h((string)($item['price'] ?? '')) ?>"></div>
    <div class="sm:col-span-2"><label>Image URL</label><input class="input" name="image" value="<?= ha_h($item['image'] ?? '') ?>" placeholder="https://..."></div>
    <div><label>Sort order</label><input class="input" type="number" name="sort_order" value="<?= (int)($item['sort_order'] ?? 0) ?>"></div>
  </div>

  <p class="text-xs text-primary font-semibold mb-3 flex items-center gap-1">
    <span class="material-icons-outlined text-[16px]">eco</span> Always pure veg on FoodMitra
  </p>

  <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
    <?php if ($cols['is_jain']): ?>
      <div>
        <label>Jain option</label>
        <div class="flex gap-4 mt-1">
          <label class="!mb-0 flex items-center gap-2 text-sm"><input type="radio" name="is_jain" value="0" <?= empty($item['is_jain']) ? 'checked' : '' ?>> No</label>
          <label class="!mb-0 flex items-center gap-2 text-sm"><input type="radio" name="is_jain" value="1" <?= !empty($item['is_jain']) ? 'checked' : '' ?>> Yes</label>
        </div>
      </div>
    <?php endif; ?>
    <?php if ($cols['is_spicy']): ?>
      <div>
        <label>Spicy</label>
        <div class="flex gap-4 mt-1">
          <label class="!mb-0 flex items-center gap-2 text-sm"><input type="radio" name="is_spicy" value="0" <?= empty($item['is_spicy']) ? 'checked' : '' ?>> No</label>
          <label class="!mb-0 flex items-center gap-2 text-sm"><input type="radio" name="is_spicy" value="1" <?= !empty($item['is_spicy']) ? 'checked' : '' ?>> Yes</label>
        </div>
      </div>
    <?php endif; ?>
    <?php if ($cols['is_sugar_free']): ?>
      <div>
        <label>Sugar-free</label>
        <div class="flex gap-4 mt-1">
          <label class="!mb-0 flex items-center gap-2 text-sm"><input type="radio" name="is_sugar_free" value="0" <?= empty($item['is_sugar_free']) ? 'checked' : '' ?>> No</label>
          <label class="!mb-0 flex items-center gap-2 text-sm"><input type="radio" name="is_sugar_free" value="1" <?= !empty($item['is_sugar_free']) ? 'checked' : '' ?>> Yes</label>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($cols['gst_inclusive']): ?>
  <div class="mb-4">
    <label>GST treatment for this price</label>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
      <label class="!mb-0 flex items-start gap-2 p-3 border border-gray-200 rounded-lg cursor-pointer">
        <input type="radio" name="gst_inclusive" value="0" class="mt-0.5" <?= $gstInclusiveVal === 0 ? 'checked' : '' ?>>
        <span><span class="block text-sm font-semibold">GST excluded</span><span class="muted">Tax added at billing</span></span>
      </label>
      <label class="!mb-0 flex items-start gap-2 p-3 border border-gray-200 rounded-lg cursor-pointer">
        <input type="radio" name="gst_inclusive" value="1" class="mt-0.5" <?= $gstInclusiveVal === 1 ? 'checked' : '' ?>>
        <span><span class="block text-sm font-semibold">GST included</span><span class="muted">Price already includes tax</span></span>
      </label>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($cols['variants_json']): ?>
  <div class="mb-4">
    <label>Variants (optional)</label>
    <p class="muted !mt-0 mb-2">Price = base price + variant charge.</p>
    <div id="variantList" class="space-y-2 mb-2"></div>
    <div class="flex flex-col sm:flex-row gap-2">
      <input type="text" id="variantName" class="input !mb-0 flex-1" placeholder="Variant name (e.g. Full)">
      <input type="number" id="variantPrice" class="input !mb-0 sm:w-28" step="0.01" min="0" placeholder="₹">
      <button type="button" class="btn secondary" onclick="addVariant()">Add</button>
    </div>
    <input type="hidden" name="variants_json" id="variantsJson" value="<?= ha_h(json_encode($variants)) ?>">
  </div>
  <?php endif; ?>

  <?php if ($cols['extras_json']): ?>
  <div class="mb-4">
    <label>Extras / add-ons (optional)</label>
    <div id="extraList" class="space-y-2 mb-2"></div>
    <div class="flex flex-col sm:flex-row gap-2">
      <input type="text" id="extraName" class="input !mb-0 flex-1" placeholder="Extra name (e.g. Extra cheese)">
      <input type="number" id="extraPrice" class="input !mb-0 sm:w-28" step="0.01" min="0" placeholder="₹">
      <button type="button" class="btn secondary" onclick="addExtra()">Add</button>
    </div>
    <input type="hidden" name="extras_json" id="extrasJson" value="<?= ha_h(json_encode($extras)) ?>">
  </div>
  <?php endif; ?>

  <div class="flex flex-wrap gap-4 mb-4">
    <label class="!mb-0 flex items-center gap-2 text-sm font-medium"><input type="checkbox" name="is_available" value="1" <?= !isset($item) || !empty($item['is_available']) ? 'checked' : '' ?>> Available</label>
    <label class="!mb-0 flex items-center gap-2 text-sm font-medium"><input type="checkbox" name="is_recommended" value="1" <?= !empty($item['is_recommended']) ? 'checked' : '' ?>> Recommended</label>
  </div>
  <button class="btn" type="submit"><span class="material-icons-outlined text-[18px]">save</span> <?= $item ? 'Save changes' : 'Create item' ?></button>
</form>

<script>
let variants = [];
let extras = [];
try { variants = JSON.parse(document.getElementById('variantsJson')?.value || '[]') || []; } catch(e) { variants = []; }
try { extras = JSON.parse(document.getElementById('extrasJson')?.value || '[]') || []; } catch(e) { extras = []; }
if (!Array.isArray(variants)) variants = [];
if (!Array.isArray(extras)) extras = [];

function esc(s){ return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

function renderList(listEl, jsonEl, arr, removeFn){
  if (!listEl || !jsonEl) return;
  jsonEl.value = JSON.stringify(arr);
  if (!arr.length) {
    listEl.innerHTML = '<p class="text-sm text-gray-400">None yet</p>';
    return;
  }
  listEl.innerHTML = arr.map((v,i) => `
    <div class="flex items-center justify-between p-2.5 bg-gray-50 border border-gray-100 rounded-lg text-sm">
      <span><strong>${esc(v.name)}</strong> <span class="text-gray-500">+₹${Number(v.price||0).toFixed(2)}</span></span>
      <button type="button" class="text-red-600 text-xs font-semibold" onclick="${removeFn}(${i})">Remove</button>
    </div>`).join('');
}

function renderVariants(){ renderList(document.getElementById('variantList'), document.getElementById('variantsJson'), variants, 'removeVariant'); }
function renderExtras(){ renderList(document.getElementById('extraList'), document.getElementById('extrasJson'), extras, 'removeExtra'); }

function addVariant(){
  const name = (document.getElementById('variantName').value||'').trim();
  const price = parseFloat(document.getElementById('variantPrice').value||'0')||0;
  if (!name) return alert('Variant name required');
  if (variants.some(v => v.name.toLowerCase()===name.toLowerCase())) return alert('Variant already exists');
  variants.push({name, price});
  document.getElementById('variantName').value='';
  document.getElementById('variantPrice').value='';
  renderVariants();
}
function removeVariant(i){ variants.splice(i,1); renderVariants(); }

function addExtra(){
  const name = (document.getElementById('extraName').value||'').trim();
  const price = parseFloat(document.getElementById('extraPrice').value||'0')||0;
  if (!name) return alert('Extra name required');
  if (extras.some(v => v.name.toLowerCase()===name.toLowerCase())) return alert('Extra already exists');
  extras.push({name, price});
  document.getElementById('extraName').value='';
  document.getElementById('extraPrice').value='';
  renderExtras();
}
function removeExtra(i){ extras.splice(i,1); renderExtras(); }

renderVariants();
renderExtras();
</script>
<?php endif; ?>
<?php ha_layout_end(); ?>

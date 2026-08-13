<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
ha_require_login();

$hotelId = (int) $_SESSION['ha_hotel_id'];
$pdo = admin_db();
$flash = '';
$catFilter = (int) ($_GET['category'] ?? 0);

if (isset($_GET['toggle']) && ctype_digit((string)$_GET['toggle'])) {
    $id = (int) $_GET['toggle'];
    $pdo->prepare(
        'UPDATE menu_items SET is_available = IF(is_available=1,0,1) WHERE id=:id AND hotel_id=:h'
    )->execute([':id' => $id, ':h' => $hotelId]);
    $flash = 'Availability updated';
}
if (isset($_GET['del']) && ctype_digit((string)$_GET['del'])) {
    $pdo->prepare('DELETE FROM menu_items WHERE id=:id AND hotel_id=:h')
        ->execute([':id' => (int)$_GET['del'], ':h' => $hotelId]);
    $flash = 'Item deleted';
}

$cats = $pdo->prepare('SELECT id, name FROM menu_categories WHERE hotel_id=:h AND is_active=1 ORDER BY sort_order, name');
$cats->execute([':h' => $hotelId]);
$categories = $cats->fetchAll();

$sql = 'SELECT m.*, c.name AS category_name FROM menu_items m
        LEFT JOIN menu_categories c ON c.id = m.category_id
        WHERE m.hotel_id = :h';
$params = [':h' => $hotelId];
if ($catFilter > 0) {
    $sql .= ' AND m.category_id = :c';
    $params[':c'] = $catFilter;
}
$sql .= ' ORDER BY m.sort_order, m.id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

ha_layout_start('Menu items', 'menu-items.php', 'Add and manage dishes for the customer app');
if ($flash): ?><div class="flash"><?= ha_h($flash) ?></div><?php endif; ?>

<div class="page-header">
  <div>
    <h2>Menu items</h2>
    <p class="sub">Add and manage dishes for the customer app</p>
  </div>
  <a class="btn" href="menu-item-edit.php"><span class="material-symbols-outlined text-[18px]">add</span> Add item</a>
</div>

<div class="card flex flex-wrap items-center justify-between gap-3 !py-4">
  <form method="get" class="flex flex-wrap items-center gap-2">
    <select name="category" class="input !mb-0 !w-auto min-w-[180px]" onchange="this.form.submit()">
      <option value="0">All categories</option>
      <?php foreach ($categories as $c): ?>
        <option value="<?= (int)$c['id'] ?>" <?= $catFilter === (int)$c['id'] ? 'selected' : '' ?>><?= ha_h($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </form>
</div>

<?php if (!$categories): ?>
  <div class="flash-error">
    Create at least one <a class="font-semibold underline" href="categories.php">category</a> before adding dishes.
  </div>
<?php endif; ?>

<div class="card !p-0 overflow-hidden">
  <div class="overflow-x-auto">
    <table>
      <thead>
        <tr><th>Item</th><th>Category</th><th>Price</th><th>Status</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td>
              <div class="flex items-center gap-3">
                <?php if (!empty($r['image'])): ?>
                  <img src="<?= ha_h($r['image']) ?>" alt="" class="w-12 h-12 rounded-lg object-cover bg-gray-100 shrink-0" onerror="this.style.display='none'">
                <?php endif; ?>
                <div>
                  <p class="font-semibold text-gray-900"><?= ha_h($r['name']) ?></p>
                  <p class="muted line-clamp-1"><?= ha_h((string)($r['description'] ?? '')) ?></p>
                  <div class="flex flex-wrap gap-1 mt-1">
                    <?php if (!empty($r['is_recommended'])): ?>
                      <span class="badge badge-amber">Recommended</span>
                    <?php endif; ?>
                    <?php if (!empty($r['is_jain'])): ?>
                      <span class="badge badge-green">Jain</span>
                    <?php endif; ?>
                    <?php if (!empty($r['is_spicy'])): ?>
                      <span class="badge badge-red">Spicy</span>
                    <?php endif; ?>
                    <?php if (!empty($r['is_sugar_free'])): ?>
                      <span class="badge badge-blue">Sugar-free</span>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </td>
            <td><?= ha_h($r['category_name'] ?? '—') ?></td>
            <td class="font-semibold">₹<?= number_format((float)$r['price'], 2) ?></td>
            <td>
              <?php if (!empty($r['is_available'])): ?>
                <span class="badge badge-green">Available</span>
              <?php else: ?>
                <span class="badge badge-red">Sold out</span>
              <?php endif; ?>
            </td>
            <td>
              <div class="flex flex-wrap gap-2">
                <a class="btn secondary sm" href="menu-item-edit.php?id=<?= (int)$r['id'] ?>">Edit</a>
                <a class="btn secondary sm" href="?toggle=<?= (int)$r['id'] ?><?= $catFilter ? '&category='.$catFilter : '' ?>">Toggle</a>
                <a class="btn secondary sm" href="?del=<?= (int)$r['id'] ?>" onclick="return confirm('Delete this item?')">Delete</a>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
          <tr><td colspan="5">
            <div class="empty-state">
              <span class="material-symbols-outlined">restaurant_menu</span>
              <p>No menu items yet</p>
            </div>
          </td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php ha_layout_end(); ?>

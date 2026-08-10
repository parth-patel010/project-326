<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
ha_require_login();

$hotelId = (int) $_SESSION['ha_hotel_id'];
$pdo = admin_db();
$flash = '';
$error = '';

function ha_slugify(string $name): string
{
    $s = strtolower(trim($name));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? 'cat';
    $s = trim($s, '-');
    return $s !== '' ? substr($s, 0, 60) : 'cat';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? 'create');
    if ($action === 'create') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $icon = trim((string) ($_POST['icon'] ?? 'meal')) ?: 'meal';
        $sort = (int) ($_POST['sort_order'] ?? 0);
        if ($name === '') {
            $error = 'Category name required';
        } else {
            $slug = ha_slugify($name);
            try {
                $pdo->prepare(
                    'INSERT INTO menu_categories (hotel_id, slug, name, icon, sort_order, is_active)
                     VALUES (:h, :slug, :name, :icon, :sort, 1)'
                )->execute([
                    ':h' => $hotelId,
                    ':slug' => $slug . '-' . substr((string) time(), -4),
                    ':name' => $name,
                    ':icon' => $icon,
                    ':sort' => $sort,
                ]);
                $flash = 'Category added';
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }
    } elseif ($action === 'update') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        $icon = trim((string) ($_POST['icon'] ?? 'meal')) ?: 'meal';
        $sort = (int) ($_POST['sort_order'] ?? 0);
        $active = !empty($_POST['is_active']) ? 1 : 0;
        if ($id > 0 && $name !== '') {
            $pdo->prepare(
                'UPDATE menu_categories SET name=:n, icon=:i, sort_order=:s, is_active=:a
                 WHERE id=:id AND hotel_id=:h'
            )->execute([
                ':n' => $name, ':i' => $icon, ':s' => $sort, ':a' => $active,
                ':id' => $id, ':h' => $hotelId,
            ]);
            $flash = 'Category updated';
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $cnt = $pdo->prepare('SELECT COUNT(*) FROM menu_items WHERE category_id=:c AND hotel_id=:h');
            $cnt->execute([':c' => $id, ':h' => $hotelId]);
            if ((int) $cnt->fetchColumn() > 0) {
                $error = 'Remove or move items in this category first';
            } else {
                $pdo->prepare('DELETE FROM menu_categories WHERE id=:id AND hotel_id=:h')
                    ->execute([':id' => $id, ':h' => $hotelId]);
                $flash = 'Category deleted';
            }
        }
    }
}

$stmt = $pdo->prepare(
    'SELECT c.*, (SELECT COUNT(*) FROM menu_items m WHERE m.category_id = c.id) AS item_count
     FROM menu_categories c WHERE c.hotel_id = :h ORDER BY c.sort_order, c.id'
);
$stmt->execute([':h' => $hotelId]);
$rows = $stmt->fetchAll();

ha_layout_start('Categories', 'categories.php', 'Organize your menu sections');
if ($flash): ?><div class="flash"><?= ha_h($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="sa-alert-error" style="background:#fef2f2;border:1px solid #fecaca;color:#991b1b;padding:0.75rem 1rem;border-radius:0.5rem;margin-bottom:1rem;font-size:0.875rem"><?= ha_h($error) ?></div><?php endif; ?>

<form method="post" class="card max-w-xl">
  <h3>Add category</h3>
  <input type="hidden" name="action" value="create">
  <div class="grid grid-cols-1 sm:grid-cols-3 gap-x-4">
    <div class="sm:col-span-2"><label>Name</label><input class="input" name="name" required placeholder="e.g. Starters"></div>
    <div><label>Sort</label><input class="input" type="number" name="sort_order" value="0"></div>
    <div><label>Icon key</label><input class="input" name="icon" value="meal" placeholder="meal"></div>
  </div>
  <button class="btn" type="submit"><span class="material-icons-outlined text-[18px]">add</span> Add category</button>
</form>

<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
  <div class="overflow-x-auto">
    <table>
      <thead><tr><th>Name</th><th>Items</th><th>Sort</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td>
              <form method="post" class="flex flex-wrap items-end gap-2">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <div class="min-w-[140px]"><label class="!text-xs">Name</label><input class="input !mb-0" name="name" value="<?= ha_h($r['name']) ?>"></div>
                <div class="w-20"><label class="!text-xs">Sort</label><input class="input !mb-0" type="number" name="sort_order" value="<?= (int)$r['sort_order'] ?>"></div>
                <div class="w-24"><label class="!text-xs">Icon</label><input class="input !mb-0" name="icon" value="<?= ha_h($r['icon']) ?>"></div>
                <label class="!mb-0 flex items-center gap-1 text-xs font-medium"><input type="checkbox" name="is_active" value="1" <?= !empty($r['is_active']) ? 'checked' : '' ?>> Active</label>
                <button class="btn !py-1.5 !px-3 text-xs" type="submit">Save</button>
              </form>
            </td>
            <td><span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-primary-soft text-primary"><?= (int)$r['item_count'] ?></span></td>
            <td class="muted"><?= (int)$r['sort_order'] ?></td>
            <td>
              <?php if (!empty($r['is_active'])): ?>
                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Active</span>
              <?php else: ?>
                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">Hidden</span>
              <?php endif; ?>
            </td>
            <td>
              <form method="post" onsubmit="return confirm('Delete this category?')">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button class="btn secondary !py-1.5 !px-3 text-xs" type="submit">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
          <tr><td colspan="5" class="text-center text-gray-500 py-10">No categories yet — add your first section above</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php ha_layout_end(); ?>

<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
ha_require_login();
$hotelId = (int) $_SESSION['ha_hotel_id'];
$pdo = admin_db();
$flash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim((string) ($_POST['title'] ?? ''));
    $subtitle = trim((string) ($_POST['subtitle'] ?? ''));
    if ($title !== '') {
        $pdo->prepare(
            'INSERT INTO hotel_offers (hotel_id, title, subtitle, sort_order, is_active) VALUES (:h, :t, :s, 0, 1)'
        )->execute([':h' => $hotelId, ':t' => $title, ':s' => $subtitle]);
        $flash = 'Offer added';
    }
}
if (isset($_GET['del']) && ctype_digit((string)$_GET['del'])) {
    $pdo->prepare('DELETE FROM hotel_offers WHERE id=:id AND hotel_id=:h')
        ->execute([':id' => (int)$_GET['del'], ':h' => $hotelId]);
    $flash = 'Offer deleted';
}

$stmt = $pdo->prepare('SELECT * FROM hotel_offers WHERE hotel_id = :h ORDER BY sort_order, id DESC');
$stmt->execute([':h' => $hotelId]);
$rows = $stmt->fetchAll();

ha_layout_start('Offers', 'offers.php', 'Promo lines shown on your hotel card');
if ($flash): ?><div class="flash"><?= ha_h($flash) ?></div><?php endif; ?>

<div class="page-header">
  <div>
    <h2>Offers</h2>
    <p class="sub">Promo lines shown on your hotel card</p>
  </div>
</div>

<form method="post" class="card max-w-xl">
  <div class="card-header">
    <h3>Add offer</h3>
  </div>
  <label>Title</label>
  <input class="input" name="title" required placeholder="Items at ₹99">
  <label>Subtitle</label>
  <input class="input" name="subtitle" placeholder="On select items">
  <button class="btn" type="submit">
    <span class="material-symbols-outlined text-[18px]">local_offer</span> Add offer
  </button>
</form>

<div class="card !p-0 overflow-hidden">
  <div class="overflow-x-auto">
    <table>
      <thead><tr><th>Title</th><th>Subtitle</th><th>Active</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td class="font-semibold text-gray-900"><?= ha_h($r['title']) ?></td>
            <td class="muted"><?= ha_h($r['subtitle']) ?></td>
            <td>
              <?php if (!empty($r['is_active'])): ?>
                <span class="badge badge-green">Active</span>
              <?php else: ?>
                <span class="badge badge-gray">Off</span>
              <?php endif; ?>
            </td>
            <td><a class="btn secondary sm" href="?del=<?= (int)$r['id'] ?>" onclick="return confirm('Delete this offer?')">Delete</a></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
          <tr><td colspan="4">
            <div class="empty-state">
              <span class="material-symbols-outlined">local_offer</span>
              <p>No offers yet</p>
            </div>
          </td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php ha_layout_end(); ?>

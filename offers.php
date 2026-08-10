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

ha_layout_start('Offers', 'offers.php');
if ($flash): ?><div class="flash"><?= ha_h($flash) ?></div><?php endif; ?>
<form method="post" class="card">
  <label>Title</label>
  <input class="input" name="title" required placeholder="Items at ₹99">
  <label>Subtitle</label>
  <input class="input" name="subtitle" placeholder="On select items">
  <button class="btn" type="submit">Add offer</button>
</form>
<div class="card">
  <table>
    <thead><tr><th>Title</th><th>Subtitle</th><th>Active</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= ha_h($r['title']) ?></td>
          <td><?= ha_h($r['subtitle']) ?></td>
          <td><?= !empty($r['is_active']) ? 'Yes' : 'No' ?></td>
          <td><a class="btn secondary" href="?del=<?= (int)$r['id'] ?>">Delete</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php ha_layout_end(); ?>

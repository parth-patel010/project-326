<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (!empty($_SESSION['ha_user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $pass = (string) ($_POST['password'] ?? '');
    $stmt = admin_db()->prepare(
        'SELECT * FROM hotel_users WHERE email = :e AND is_active = 1 LIMIT 1'
    );
    $stmt->execute([':e' => $email]);
    $user = $stmt->fetch();
    if ($user && password_verify($pass, $user['password_hash'])) {
        $_SESSION['ha_user_id'] = (int) $user['id'];
        $_SESSION['ha_hotel_id'] = (int) $user['hotel_id'];
        header('Location: dashboard.php');
        exit;
    }
    $error = 'Invalid login';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Hotel Login · FoodMitra</title>
  <style>
    body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:linear-gradient(160deg,#12350c,#1F7A32);font-family:Segoe UI,system-ui,sans-serif}
    .box{width:100%;max-width:400px;background:#fff;border-radius:16px;padding:28px}
    h1{margin:0 0 8px;color:#195510}input{width:100%;padding:12px;margin:0 0 12px;border:1px solid #ddd;border-radius:10px;box-sizing:border-box}
    button{width:100%;padding:12px;border:0;border-radius:10px;background:#195510;color:#fff;font-weight:800}
    .err{background:#ffebee;color:#c62828;padding:10px;border-radius:8px;margin-bottom:12px}
    label{font-weight:700;font-size:13px}
  </style>
</head>
<body>
<form class="box" method="post">
  <h1>Hotel Admin Login</h1>
  <?php if ($error): ?><div class="err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <label>Email</label>
  <input type="email" name="email" required>
  <label>Password</label>
  <input type="password" name="password" required>
  <button type="submit">Login</button>
</form>
</body>
</html>

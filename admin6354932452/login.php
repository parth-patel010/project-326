<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (!empty($_SESSION['sa_user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $pass = (string) ($_POST['password'] ?? '');
    $stmt = admin_db()->prepare('SELECT * FROM admin_users WHERE email = :e AND is_active = 1 LIMIT 1');
    $stmt->execute([':e' => $email]);
    $user = $stmt->fetch();
    if ($user && password_verify($pass, $user['password_hash'])) {
        $_SESSION['sa_user_id'] = (int) $user['id'];
        header('Location: dashboard.php');
        exit;
    }
    $error = 'Invalid email or password';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Super Admin Login · FoodMitra</title>
  <style>
    body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:linear-gradient(160deg,#12350c,#1F7A32);font-family:Segoe UI,system-ui,sans-serif}
    .box{width:100%;max-width:400px;background:#fff;border-radius:16px;padding:28px;box-shadow:0 20px 50px rgba(0,0,0,.25)}
    h1{margin:0 0 6px;font-size:22px;color:#195510}p{margin:0 0 18px;color:#6b6b6b;font-size:14px}
    label{font-size:13px;font-weight:700;display:block;margin-bottom:4px}
    input{width:100%;padding:12px;border:1px solid #e5e5e5;border-radius:10px;margin-bottom:12px;font-size:14px;box-sizing:border-box}
    button{width:100%;padding:12px;border:0;border-radius:10px;background:#195510;color:#fff;font-weight:800;cursor:pointer}
    .err{background:#ffebee;color:#c62828;padding:10px;border-radius:8px;margin-bottom:12px;font-size:13px}
  </style>
</head>
<body>
  <form class="box" method="post">
    <h1>FoodMitra Super Admin</h1>
    <p>Sign in to manage platform settings</p>
    <?php if ($error): ?><div class="err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <label>Email</label>
    <input type="email" name="email" required value="admin@foodmitra.com">
    <label>Password</label>
    <input type="password" name="password" required placeholder="admin123">
    <button type="submit">Login</button>
  </form>
</body>
</html>

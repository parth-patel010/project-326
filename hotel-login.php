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
    try {
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
        $error = 'Invalid email or password';
    } catch (Throwable $e) {
        $error = 'Server error. Check database connection.';
        error_log('hotel login: ' . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Hotel Admin Login · FoodMitra</title>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: "#195510",
            "primary-hover": "#1F7A32",
            "primary-soft": "#e8f5e9",
            "content-bg": "#f9fafb",
            "text-main": "#1f2937",
            "text-muted": "#6b7280"
          },
          fontFamily: { sans: ["DM Sans", "system-ui", "sans-serif"] }
        }
      }
    };
  </script>
</head>
<body class="bg-content-bg font-sans antialiased min-h-screen flex items-center justify-center p-4">
  <div class="w-full max-w-md">
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-8 md:p-10">
      <div class="flex justify-center mb-6">
        <div class="w-16 h-16 bg-primary rounded-2xl flex items-center justify-center shadow-sm">
          <span class="material-symbols-outlined text-white text-3xl">storefront</span>
        </div>
      </div>
      <h1 class="text-2xl font-bold text-text-main text-center mb-2">Hotel Admin</h1>
      <p class="text-text-muted text-center mb-8 text-sm">Sign in to manage orders, menu, and POS</p>

      <?php if ($error): ?>
        <div class="flash-error mb-5"><?= ha_h($error) ?></div>
      <?php endif; ?>

      <form method="post" class="space-y-5">
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2" for="email">Email</label>
          <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
              <span class="material-symbols-outlined text-gray-400 text-xl">mail</span>
            </div>
            <input type="email" id="email" name="email" required
                   class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none text-text-main"
                   placeholder="Hotel login email" value="<?= ha_h((string)($_POST['email'] ?? '')) ?>">
          </div>
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2" for="password">Password</label>
          <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
              <span class="material-symbols-outlined text-gray-400 text-xl">lock</span>
            </div>
            <input type="password" id="password" name="password" required
                   class="block w-full pl-10 pr-10 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none text-text-main"
                   placeholder="Password">
            <button type="button" id="togglePassword" class="absolute inset-y-0 right-0 pr-3 flex items-center">
              <span id="toggleIcon" class="material-symbols-outlined text-gray-400 text-xl hover:text-gray-600">visibility</span>
            </button>
          </div>
        </div>
        <button type="submit" class="w-full py-3 bg-primary hover:bg-primary-hover text-white font-semibold rounded-lg transition-colors flex items-center justify-center gap-2 shadow-sm">
          <span>Sign In</span>
          <span class="material-symbols-outlined text-xl">arrow_forward</span>
        </button>
      </form>
    </div>
    <p class="text-center text-xs text-text-muted mt-6">FoodMitra · Hotel operations</p>
  </div>
  <style>
    .flash-error { background:#fef2f2; border:1px solid #fecaca; color:#991b1b; padding:0.75rem 1rem; border-radius:0.5rem; font-size:0.875rem; font-weight:500; }
  </style>
  <script>
    document.getElementById('togglePassword').addEventListener('click', function () {
      var p = document.getElementById('password');
      var i = document.getElementById('toggleIcon');
      if (p.type === 'password') { p.type = 'text'; i.textContent = 'visibility_off'; }
      else { p.type = 'password'; i.textContent = 'visibility'; }
    });
  </script>
</body>
</html>

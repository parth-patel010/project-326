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
    try {
        $stmt = admin_db()->prepare('SELECT * FROM admin_users WHERE email = :e AND is_active = 1 LIMIT 1');
        $stmt->execute([':e' => $email]);
        $user = $stmt->fetch();
        if ($user && password_verify($pass, $user['password_hash'])) {
            $_SESSION['sa_user_id'] = (int) $user['id'];
            header('Location: dashboard.php');
            exit;
        }
        $error = 'Invalid email or password';
    } catch (Throwable $e) {
        $error = 'Server error (DB). Check api/.env credentials.';
        error_log('admin login: ' . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Super Admin Login · FoodMitra</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: { primary: "#195510", "primary-hover": "#1F7A32" },
          fontFamily: { sans: ["Inter", "sans-serif"] }
        }
      }
    };
  </script>
</head>
<body class="bg-gray-100 font-sans antialiased min-h-screen flex items-center justify-center p-4">
  <div class="w-full max-w-md">
    <div class="bg-white rounded-2xl shadow-lg p-8 md:p-10">
      <div class="flex justify-center mb-6">
        <div class="w-16 h-16 bg-primary rounded-full flex items-center justify-center">
          <span class="material-icons-outlined text-white text-3xl">admin_panel_settings</span>
        </div>
      </div>
      <h1 class="text-2xl font-bold text-gray-900 text-center mb-2">Super Admin Login</h1>
      <p class="text-gray-500 text-center mb-8">Sign in to manage FoodMitra platform</p>

      <?php if ($error): ?>
        <div class="mb-5 text-sm text-red-600 bg-red-50 border border-red-100 p-3 rounded-lg"><?= sa_h($error) ?></div>
      <?php endif; ?>

      <form method="post" class="space-y-5">
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2" for="email">Email</label>
          <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
              <span class="material-icons-outlined text-gray-400 text-xl">email</span>
            </div>
            <input type="email" id="email" name="email" required
                   class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none"
                   placeholder="Admin email" value="<?= sa_h((string)($_POST['email'] ?? '')) ?>">
          </div>
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2" for="password">Password</label>
          <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
              <span class="material-icons-outlined text-gray-400 text-xl">vpn_key</span>
            </div>
            <input type="password" id="password" name="password" required
                   class="block w-full pl-10 pr-10 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none"
                   placeholder="Password">
            <button type="button" id="togglePassword" class="absolute inset-y-0 right-0 pr-3 flex items-center">
              <span id="toggleIcon" class="material-icons-outlined text-gray-400 text-xl hover:text-gray-600">visibility</span>
            </button>
          </div>
        </div>
        <button type="submit" class="w-full py-3 bg-primary hover:bg-primary-hover text-white font-semibold rounded-lg transition-colors flex items-center justify-center gap-2">
          <span class="material-icons-outlined text-xl">login</span> Sign In
        </button>
      </form>
    </div>
  </div>
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

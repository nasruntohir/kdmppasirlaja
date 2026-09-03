<?php
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/helpers.php';

if (isset($_SESSION['user'])) { header('Location: index.php'); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD']==='POST') {
  csrf_check();
  $u = trim(isset($_POST['username']) ? $_POST['username'] : '');
  $p = trim(isset($_POST['password']) ? $_POST['password'] : '');
  if (login($u, $p)) {
    header('Location: index.php'); exit;
  } else {
    $error = 'Login gagal. Periksa username/password.';
  }
}
$app_name = setting_get('app_name', 'KOPDES MERAH PUTIH PASIRLAJA');
$company = setting_get('company_name', 'Koperasi Simpan Pinjam');
?><!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login - <?php echo htmlspecialchars($app_name); ?></title>
  
  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  
  <!-- Bootstrap 5 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css" rel="stylesheet">
  
  <!-- Custom CSS -->
  <link href="public/css/app.css" rel="stylesheet">
</head>
<body>
  <div class="login-wrapper">

    <!-- Kiri -->
    <div class="login-left">
      <div class="brand">
        <h1>SimKopdes</h1>
        
        <div class="brand-subtitle">
          <div>Sistem Informasi Manajemen</div>
          <div>Kopdes Merah Putih Pasirlaja</div>
        </div>
      </div>
    </div>

    <!-- Kanan -->
    <div class="login-right">

      <div class="login-card">

        <!-- Header -->
        <div class="login-header">
          <div class="login-logo">
            <img src="member/assets/image/LOGO_KOPDES PASIRLAJA.png" alt="Logo">
          </div>
          <h2><?php echo htmlspecialchars($app_name); ?></h2>
          <p><?php echo htmlspecialchars($company); ?></p>
        </div>

        <!-- Error Alert -->
        <?php if ($error): ?>
        <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
          <i class="bi bi-exclamation-triangle-fill me-2"></i>
          <div><?php echo htmlspecialchars($error); ?></div>
        </div>
        <?php endif; ?>

        <!-- Login Form -->
        <form method="post" autocomplete="off">
          <?php echo csrf_input(); ?>

          <div class="mb-3">
            <label class="form-label">
              <i class="bi bi-person-fill"></i> Username
            </label>
            <div class="input-group">
              <span class="input-icon">
                <i class="bi bi-person"></i>
              </span>
              <input
                type="text"
                name="username"
                class="form-control"
                placeholder="Masukkan username"
                required
                autofocus
              >
            </div>
          </div>

          <div class="mb-4">
            <label class="form-label">
              <i class="bi bi-lock-fill"></i> Password
            </label>
            <div class="input-group">
              <span class="input-icon">
                <i class="bi bi-key"></i>
              </span>
              <input
                type="password"
                name="password"
                id="password"
                class="form-control"
                placeholder="Masukkan password"
                required
              >
            </div>
          </div>

          <div class="d-flex align-items-center justify-content-between mb-4">
            <div class="form-check">
              <input type="checkbox" class="form-check-input" id="remember">
              <label class="form-check-label small" for="remember">
                Ingat saya
              </label>
            </div>

            <a href="#" class="small text-decoration-none"
               style="color: var(--primary);">
              Lupa password?
            </a>
          </div>

          <button type="submit" class="btn btn-login">
            <i class="bi bi-box-arrow-in-right me-2"></i>
            Masuk
          </button>
        </form>

        <!-- Footer -->
        <div class="login-footer">
          <p class="mb-1">
            © <?php echo date('Y'); ?>
            <?php echo htmlspecialchars($app_name); ?>.
            All rights reserved.
          </p>

          <small class="text-muted">
            <i class="bi bi-info-circle me-1"></i>
            Version 0.1
          </small>
        </div>

      </div>

    </div>

  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

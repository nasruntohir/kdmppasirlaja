<?php
// partials/header.php
require_once __DIR__ . '/../lib/helpers.php';
require_login();
$app_name = setting_get('app_name', 'KSP Harian');
$company = setting_get('company_name', 'Koperasi Harian');
$u = user();
$u_name = 'Pengguna';
$u_role = 'User';
$u_initial = 'U';
if (is_array($u)) {
  if (isset($u['nama']) && $u['nama'] !== '') {
    $u_name = $u['nama'];
    $u_initial = strtoupper(substr($u['nama'], 0, 1));
  } elseif (isset($u['username']) && $u['username'] !== '') {
    $u_name = $u['username'];
    $u_initial = strtoupper(substr($u['username'], 0, 1));
  }
  if (isset($u['role'])) {
    $u_role = ucfirst($u['role']);
  }
}
$current_page = isset($_GET['m']) ? $_GET['m'] : 'dashboard';
$page_titles = array(
  'dashboard' => 'Dashboard',
  'anggota' => 'Data Anggota',
  'produk_simpanan' => 'Produk Simpanan',
  'simpanan' => 'Transaksi Simpanan',
  'produk_pinjaman' => 'Produk Pinjaman',
  'pinjaman_realisasi' => 'Realisasi Pinjaman',
  'angsuran' => 'Angsuran Harian',
  'kolektor' => 'Master Kolektor',
  'tagih' => 'Penagihan',
  'kas' => 'Kas',
  'laporan' => 'Laporan',
  'setting' => 'Pengaturan'
);
$page_title = isset($page_titles[$current_page]) ? $page_titles[$current_page] : 'Dashboard';
?><!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlspecialchars($page_title . ' - ' . $app_name); ?></title>
  
  <!-- Google Fonts - Inter -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  
  <!-- Bootstrap 5 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css" rel="stylesheet">
  
  <!-- DataTables -->
  <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
  
  <!-- Select2 -->
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
  
  <!-- SweetAlert2 -->
  <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
  
  <!-- Animate.css -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">
  
  <!-- Custom CSS -->
  <link href="public/css/app.css" rel="stylesheet">
</head>
<body>
<!-- Sidebar -->
<?php include __DIR__ . '/sidebar.php'; ?>

<!-- Main Content -->
<div class="main-wrapper">
  <!-- Top Navbar -->
  <header class="top-navbar">
    <div class="navbar-left">
      <button class="navbar-icon-btn toggle-sidebar d-lg-none" onclick="toggleSidebar()">
        <i class="bi bi-list"></i>
      </button>
      <div>
        <h4 class="mb-0"><?php echo htmlspecialchars($page_title); ?></h4>
        <nav aria-label="breadcrumb" class="d-none d-md-block">
          <ol class="breadcrumb mb-0" style="font-size:12px;">
            <li class="breadcrumb-item"><a href="index.php?m=dashboard" class="text-decoration-none"><i class="bi bi-house"></i></a></li>
            <li class="breadcrumb-item active"><?php echo htmlspecialchars($page_title); ?></li>
          </ol>
        </nav>
      </div>
    </div>
    
    <div class="navbar-right">
      <!-- Search -->
      <div class="navbar-search d-none d-lg-block">
        <i class="bi bi-search"></i>
        <input type="text" placeholder="Cari anggota, pinjaman..." id="globalSearch">
      </div>
      
      <!-- Launchpad Menu -->
      <button class="navbar-icon-btn" title="Menu Cepat" onclick="toggleLaunchpad()">
        <i class="bi bi-grid-3x3-gap-fill"></i>
      </button>
      
      <!-- Notifications -->
      <button class="navbar-icon-btn" title="Notifikasi">
        <i class="bi bi-bell"></i>
        <span class="badge">3</span>
      </button>
      
      <!-- Fullscreen -->
      <button class="navbar-icon-btn d-none d-md-flex" onclick="toggleFullscreen()" title="Fullscreen">
        <i class="bi bi-arrows-fullscreen"></i>
      </button>
      
      <!-- User Dropdown -->
      <div class="dropdown">
        <div class="user-dropdown" data-bs-toggle="dropdown">
          <div class="avatar"><?php echo $u_initial; ?></div>
          <div class="user-info">
            <div class="user-name"><?php echo htmlspecialchars($u_name); ?></div>
            <div class="user-role"><?php echo htmlspecialchars($u_role); ?></div>
          </div>
          <i class="bi bi-chevron-down text-muted ms-1"></i>
        </div>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><a class="dropdown-item" href="index.php?m=setting"><i class="bi bi-person-circle"></i> Profil</a></li>
          <li><a class="dropdown-item" href="index.php?m=setting"><i class="bi bi-gear"></i> Pengaturan</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
        </ul>
      </div>
    </div>
  </header>

  <!-- Content Area -->
  <div class="content-wrapper">
    <?php flash(); ?>

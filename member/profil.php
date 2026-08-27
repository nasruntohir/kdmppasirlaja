<?php
// member/profil.php - Profil Member (Mobile App Style)
require_once __DIR__ . '/../lib/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['member_id'])) {
  header('Location: index.php');
  exit;
}

$conn = db();
$member_id = $_SESSION['member_id'];

$member = $conn->query("SELECT * FROM ksp_anggota WHERE id = $member_id")->fetch_assoc();

// Stats
$stats = [
  'total_pinjaman' => $conn->query("SELECT COUNT(*) c FROM ksp_pinjaman WHERE anggota_id = $member_id")->fetch_assoc()['c'],
  'pinjaman_aktif' => $conn->query("SELECT COUNT(*) c FROM ksp_pinjaman WHERE anggota_id = $member_id AND status = 'aktif'")->fetch_assoc()['c'],
  'pinjaman_lunas' => $conn->query("SELECT COUNT(*) c FROM ksp_pinjaman WHERE anggota_id = $member_id AND status = 'lunas'")->fetch_assoc()['c'],
];

$simpanan = $conn->query("SELECT total_simpanan FROM ksp_simpanan_anggota WHERE anggota_id = $member_id")->fetch_assoc();
$total_simpanan = $simpanan ? $simpanan['total_simpanan'] : 0;

$company = setting_get('company_name', 'KSP Harian');
$page = 'profil';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <title>Profil - <?php echo htmlspecialchars($company); ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/mobile.css" rel="stylesheet">
</head>
<body>
  <!-- Profile Header -->
  <div style="background:linear-gradient(135deg,#6366f1,#8b5cf6);padding:50px 20px 80px;text-align:center;color:white;">
    <div style="width:90px;height:90px;background:rgba(255,255,255,0.2);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 15px;font-size:40px;">
      <i class="bi bi-person"></i>
    </div>
    <h2 style="margin:0 0 5px;font-size:22px;"><?php echo htmlspecialchars($member['nama']); ?></h2>
    <p style="margin:0;opacity:0.9;font-size:14px;"><?php echo $member['no_anggota']; ?></p>
    <div style="margin-top:15px;">
      <span style="background:rgba(255,255,255,0.2);padding:5px 15px;border-radius:20px;font-size:12px;">
        <i class="bi bi-check-circle me-1"></i>Anggota Aktif
      </span>
    </div>
  </div>

  <!-- Stats Cards -->
  <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin:-50px 20px 20px;position:relative;">
    <div class="card text-center" style="padding:15px 10px;">
      <div style="font-size:24px;font-weight:700;color:#6366f1;"><?php echo $stats['total_pinjaman']; ?></div>
      <div style="font-size:11px;color:#6b7280;">Total Pinjaman</div>
    </div>
    <div class="card text-center" style="padding:15px 10px;">
      <div style="font-size:24px;font-weight:700;color:#f59e0b;"><?php echo $stats['pinjaman_aktif']; ?></div>
      <div style="font-size:11px;color:#6b7280;">Aktif</div>
    </div>
    <div class="card text-center" style="padding:15px 10px;">
      <div style="font-size:24px;font-weight:700;color:#10b981;"><?php echo $stats['pinjaman_lunas']; ?></div>
      <div style="font-size:11px;color:#6b7280;">Lunas</div>
    </div>
  </div>

  <div style="padding:0 20px 100px;">
    <!-- Info Section -->
    <div class="card mb-3">
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:15px;">
        <div style="width:40px;height:40px;background:linear-gradient(135deg,#6366f1,#8b5cf6);border-radius:10px;display:flex;align-items:center;justify-content:center;color:white;">
          <i class="bi bi-person-vcard"></i>
        </div>
        <h3 style="margin:0;font-size:16px;">Informasi Pribadi</h3>
      </div>
      
      <div style="display:flex;flex-direction:column;gap:12px;">
        <div style="display:flex;justify-content:space-between;padding-bottom:12px;border-bottom:1px solid #f3f4f6;">
          <span class="text-gray">NIK</span>
          <span class="fw-bold"><?php echo htmlspecialchars($member['nik'] ?: '-'); ?></span>
        </div>
        <div style="display:flex;justify-content:space-between;padding-bottom:12px;border-bottom:1px solid #f3f4f6;">
          <span class="text-gray">No. HP</span>
          <span class="fw-bold"><?php echo htmlspecialchars($member['hp']); ?></span>
        </div>
        <div style="display:flex;justify-content:space-between;padding-bottom:12px;border-bottom:1px solid #f3f4f6;">
          <span class="text-gray">Alamat</span>
          <span class="fw-bold" style="text-align:right;max-width:60%;"><?php echo htmlspecialchars($member['alamat'] ?: '-'); ?></span>
        </div>
        <div style="display:flex;justify-content:space-between;">
          <span class="text-gray">Tanggal Gabung</span>
          <span class="fw-bold"><?php echo date('d M Y', strtotime($member['tanggal_gabung'])); ?></span>
        </div>
      </div>
    </div>

    <!-- Simpanan Info -->
    <div class="card mb-3">
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:15px;">
        <div style="width:40px;height:40px;background:linear-gradient(135deg,#10b981,#059669);border-radius:10px;display:flex;align-items:center;justify-content:center;color:white;">
          <i class="bi bi-wallet2"></i>
        </div>
        <h3 style="margin:0;font-size:16px;">Simpanan</h3>
      </div>
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <span class="text-gray">Total Simpanan</span>
        <span class="fw-bold text-success" style="font-size:18px;">Rp <?php echo number_format($total_simpanan, 0, ',', '.'); ?></span>
      </div>
    </div>

    <!-- Menu -->
    <div class="card">
      <a href="simpanan.php" style="display:flex;align-items:center;padding:15px 0;border-bottom:1px solid #f3f4f6;text-decoration:none;color:inherit;">
        <div style="width:40px;height:40px;background:#e0f2fe;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#0284c7;margin-right:15px;">
          <i class="bi bi-wallet2"></i>
        </div>
        <div style="flex:1;">
          <div class="fw-bold">Simpanan Saya</div>
          <div class="text-sm text-gray">Lihat saldo & mutasi simpanan</div>
        </div>
        <i class="bi bi-chevron-right text-gray"></i>
      </a>
      
      <a href="status_pengajuan.php" style="display:flex;align-items:center;padding:15px 0;border-bottom:1px solid #f3f4f6;text-decoration:none;color:inherit;">
        <div style="width:40px;height:40px;background:#fef3c7;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#d97706;margin-right:15px;">
          <i class="bi bi-clock-history"></i>
        </div>
        <div style="flex:1;">
          <div class="fw-bold">Riwayat Pengajuan</div>
          <div class="text-sm text-gray">Lihat status pengajuan pinjaman</div>
        </div>
        <i class="bi bi-chevron-right text-gray"></i>
      </a>
      
      <a href="dashboard.php" style="display:flex;align-items:center;padding:15px 0;text-decoration:none;color:inherit;">
        <div style="width:40px;height:40px;background:#e0e7ff;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#4f46e5;margin-right:15px;">
          <i class="bi bi-credit-card"></i>
        </div>
        <div style="flex:1;">
          <div class="fw-bold">Pinjaman Saya</div>
          <div class="text-sm text-gray">Lihat detail pinjaman aktif</div>
        </div>
        <i class="bi bi-chevron-right text-gray"></i>
      </a>
    </div>

    <!-- Logout -->
    <a href="index.php?logout=1" class="btn btn-outline btn-block" style="margin-top:20px;color:#ef4444;border-color:#ef4444;">
      <i class="bi bi-box-arrow-right"></i> Keluar
    </a>

    <p class="text-center text-gray text-sm" style="margin-top:20px;">
      <?php echo htmlspecialchars($company); ?><br>
      © <?php echo date('Y'); ?>
    </p>
  </div>

  <!-- Bottom Navigation -->
  <div class="bottom-nav">
    <a href="dashboard.php" class="nav-item"><i class="bi bi-house"></i><span>Home</span></a>
    <a href="status_pengajuan.php" class="nav-item"><i class="bi bi-clock-history"></i><span>Status</span></a>
    <a href="pengajuan.php" class="nav-item center-btn"><div class="icon-wrap"><i class="bi bi-plus-lg"></i></div><span>Ajukan</span></a>
    <a href="simpanan.php" class="nav-item"><i class="bi bi-wallet2"></i><span>Simpanan</span></a>
    <a href="profil.php" class="nav-item active"><i class="bi bi-person-fill"></i><span>Profil</span></a>
  </div>
</body>
</html>

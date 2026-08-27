<?php
// member/simpanan.php - Simpanan Saya (Mobile App Style)
require_once __DIR__ . '/../lib/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['member_id'])) {
  header('Location: index.php');
  exit;
}

$conn = db();
$member_id = $_SESSION['member_id'];

$simpanan = $conn->query("SELECT * FROM ksp_simpanan_anggota WHERE anggota_id = $member_id")->fetch_assoc();
if (!$simpanan) {
  $simpanan = ['simpanan_pokok' => 0, 'simpanan_wajib' => 0, 'simpanan_sukarela' => 0, 'total_simpanan' => 0];
}

$mutasi = $conn->query("SELECT * FROM ksp_mutasi_simpanan WHERE anggota_id = $member_id ORDER BY created_at DESC LIMIT 20");

$company = setting_get('company_name', 'KSP Harian');
$page = 'simpanan';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <title>Simpanan - <?php echo htmlspecialchars($company); ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/mobile.css" rel="stylesheet">
</head>
<body>
  <!-- App Bar -->
  <div class="app-bar app-bar-simple">
    <div class="app-bar-row">
      <a href="dashboard.php" style="color:white;text-decoration:none;font-size:24px;"><i class="bi bi-arrow-left"></i></a>
      <h1 style="flex:1;text-align:center;margin:0;">Simpanan Saya</h1>
      <div style="width:24px;"></div>
    </div>
  </div>

  <!-- Balance Card -->
  <div class="balance-card" style="margin:20px;margin-top:-10px;">
    <div style="text-align:center;margin-bottom:20px;">
      <div class="balance-label">Total Simpanan</div>
      <div class="balance-amount">Rp <?php echo number_format($simpanan['total_simpanan'], 0, ',', '.'); ?></div>
    </div>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;">
      <div style="background:rgba(255,255,255,0.15);border-radius:12px;padding:12px;text-align:center;">
        <div style="font-size:12px;opacity:0.8;">Pokok</div>
        <div style="font-size:16px;font-weight:700;">Rp <?php echo number_format($simpanan['simpanan_pokok']/1000, 0); ?>rb</div>
      </div>
      <div style="background:rgba(255,255,255,0.15);border-radius:12px;padding:12px;text-align:center;">
        <div style="font-size:12px;opacity:0.8;">Wajib</div>
        <div style="font-size:16px;font-weight:700;">Rp <?php echo number_format($simpanan['simpanan_wajib']/1000, 0); ?>rb</div>
      </div>
      <div style="background:rgba(255,255,255,0.15);border-radius:12px;padding:12px;text-align:center;">
        <div style="font-size:12px;opacity:0.8;">Sukarela</div>
        <div style="font-size:16px;font-weight:700;">Rp <?php echo number_format($simpanan['simpanan_sukarela']/1000, 0); ?>rb</div>
      </div>
    </div>
  </div>

  <div style="padding:0 20px 20px;">
    <!-- Section Header -->
    <div class="section-header">
      <h3><i class="bi bi-list-ul me-2"></i>Riwayat Mutasi</h3>
    </div>

    <?php if ($mutasi && $mutasi->num_rows > 0): ?>
      <?php while ($m = $mutasi->fetch_assoc()): ?>
      <div class="list-item">
        <div class="icon <?php echo $m['tipe'] == 'setor' ? 'bg-success' : 'bg-danger'; ?>">
          <i class="bi bi-<?php echo $m['tipe'] == 'setor' ? 'arrow-down-circle' : 'arrow-up-circle'; ?>" style="color:white;"></i>
        </div>
        <div class="content">
          <div class="title"><?php echo $m['tipe'] == 'setor' ? 'Setor' : 'Tarik'; ?> <?php echo ucfirst($m['jenis_simpanan']); ?></div>
          <div class="subtitle"><?php echo date('d M Y', strtotime($m['tanggal'])); ?></div>
        </div>
        <div>
          <div class="amount <?php echo $m['tipe'] == 'setor' ? 'text-success' : 'text-danger'; ?>">
            <?php echo $m['tipe'] == 'setor' ? '+' : '-'; ?>Rp <?php echo number_format($m['nominal'], 0, ',', '.'); ?>
          </div>
          <div class="text-sm text-gray text-right">Saldo: Rp <?php echo number_format($m['saldo_akhir']/1000, 0); ?>rb</div>
        </div>
      </div>
      <?php endwhile; ?>
    <?php else: ?>
      <div class="empty-state" style="padding:40px 0;">
        <i class="bi bi-inbox"></i>
        <h4>Belum Ada Riwayat</h4>
        <p>Belum ada transaksi simpanan</p>
      </div>
    <?php endif; ?>

    <div class="alert alert-info" style="margin-top:20px;">
      <i class="bi bi-info-circle"></i>
      <div>Untuk setor/tarik simpanan, silakan datang ke kantor koperasi.</div>
    </div>
  </div>

  <!-- Bottom Navigation -->
  <div class="bottom-nav">
    <a href="dashboard.php" class="nav-item"><i class="bi bi-house"></i><span>Home</span></a>
    <a href="status_pengajuan.php" class="nav-item"><i class="bi bi-clock-history"></i><span>Status</span></a>
    <a href="pengajuan.php" class="nav-item center-btn"><div class="icon-wrap"><i class="bi bi-plus-lg"></i></div><span>Ajukan</span></a>
    <a href="simpanan.php" class="nav-item active"><i class="bi bi-wallet2"></i><span>Simpanan</span></a>
    <a href="profil.php" class="nav-item"><i class="bi bi-person"></i><span>Profil</span></a>
  </div>
</body>
</html>

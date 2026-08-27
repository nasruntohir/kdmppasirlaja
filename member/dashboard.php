<?php
// member/dashboard.php - Member Dashboard (Mobile App Style)
require_once __DIR__ . '/../lib/helpers.php';
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

if (!isset($_SESSION['member_id'])) {
  header('Location: index.php');
  exit;
}

$conn = db();
$member_id = $_SESSION['member_id'];

// Get member info
$member = $conn->query("SELECT * FROM ksp_anggota WHERE id = $member_id")->fetch_assoc();

// Get simpanan
$simpanan = $conn->query("SELECT * FROM ksp_simpanan_anggota WHERE anggota_id = $member_id")->fetch_assoc();
$total_simpanan = $simpanan ? $simpanan['total_simpanan'] : 0;

// Get active loans
$loans = $conn->query("
  SELECT p.*, pr.nama as produk_nama,
    (SELECT COUNT(*) FROM ksp_jadwal_angsuran WHERE pinjaman_id=p.id AND status='lunas') as paid,
    (SELECT COUNT(*) FROM ksp_jadwal_angsuran WHERE pinjaman_id=p.id) as total,
    (SELECT IFNULL(SUM(bayar_nominal),0) FROM ksp_jadwal_angsuran WHERE pinjaman_id=p.id AND status='lunas') as terbayar
  FROM ksp_pinjaman p
  JOIN ksp_produk_pinjaman pr ON pr.id = p.produk_id
  WHERE p.anggota_id = $member_id
  ORDER BY p.status ASC, p.tanggal_realisasi DESC
  LIMIT 5
");

// Get upcoming payments
$upcoming = $conn->query("
  SELECT j.*, p.no_pinjaman
  FROM ksp_jadwal_angsuran j
  JOIN ksp_pinjaman p ON p.id = j.pinjaman_id
  WHERE p.anggota_id = $member_id AND j.status = 'belum'
  ORDER BY j.tanggal_jatuh_tempo ASC
  LIMIT 3
");

// Calculate totals
$total_pinjaman = 0;
$total_terbayar = 0;
$total_sisa = 0;
$loans_data = array();
while ($l = $loans->fetch_assoc()) {
  $loans_data[] = $l;
  if ($l['status'] == 'aktif') {
    $total_pinjaman += $l['total_pinjaman'] ?: ($l['pokok'] + ($l['pokok'] * $l['bunga_persen'] / 100));
    $total_terbayar += $l['terbayar'];
  }
}
$total_sisa = max(0, $total_pinjaman - $total_terbayar);

// Get pending pengajuan count
$pending_count = $conn->query("SELECT COUNT(*) c FROM ksp_pengajuan_online WHERE anggota_id = $member_id AND status IN ('pending','review')")->fetch_assoc()['c'];

$company = setting_get('company_name', 'KSP Harian');
$page = 'home';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <title><?php echo htmlspecialchars($company); ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/mobile.css" rel="stylesheet">
</head>
<body>
  <!-- App Bar -->
  <div class="app-bar">
    <div class="app-bar-row">
      <div class="user-info">
        <div class="avatar"><i class="bi bi-person"></i></div>
        <div>
          <h1>Halo, <?php echo htmlspecialchars(explode(' ', $member['nama'])[0]); ?>!</h1>
          <p><?php echo $member['no_anggota']; ?></p>
        </div>
      </div>
      <a href="index.php?logout=1" class="notif-btn"><i class="bi bi-box-arrow-right"></i></a>
    </div>
  </div>

  <!-- Balance Card -->
  <div class="balance-card">
    <div class="balance-label">Sisa Tagihan Pinjaman</div>
    <div class="balance-amount">Rp <?php echo number_format($total_sisa, 0, ',', '.'); ?></div>
    <div class="balance-row">
      <div class="balance-item">
        <span>Total Pinjaman</span>
        <strong>Rp <?php echo number_format($total_pinjaman/1000, 0); ?>rb</strong>
      </div>
      <div class="balance-item">
        <span>Terbayar</span>
        <strong>Rp <?php echo number_format($total_terbayar/1000, 0); ?>rb</strong>
      </div>
      <div class="balance-item">
        <span>Simpanan</span>
        <strong>Rp <?php echo number_format($total_simpanan/1000, 0); ?>rb</strong>
      </div>
    </div>
  </div>

  <!-- Quick Actions -->
  <div class="quick-actions">
    <a href="pengajuan.php" class="quick-action">
      <div class="icon bg-primary"><i class="bi bi-plus-lg"></i></div>
      <span>Ajukan</span>
    </a>
    <a href="status_pengajuan.php" class="quick-action">
      <div class="icon bg-warning"><i class="bi bi-clock-history"></i></div>
      <span>Status</span>
    </a>
    <a href="simpanan.php" class="quick-action">
      <div class="icon bg-success"><i class="bi bi-wallet2"></i></div>
      <span>Simpanan</span>
    </a>
    <a href="riwayat.php" class="quick-action">
      <div class="icon bg-info"><i class="bi bi-receipt"></i></div>
      <span>Riwayat</span>
    </a>
  </div>

  <!-- Tagihan Section -->
  <div class="section">
    <div class="section-header">
      <h3><i class="bi bi-calendar-check me-2"></i>Tagihan Terdekat</h3>
    </div>
    <?php if ($upcoming->num_rows > 0): ?>
      <?php while ($u = $upcoming->fetch_assoc()): 
        $is_overdue = strtotime($u['tanggal_jatuh_tempo']) < strtotime('today');
      ?>
      <div class="list-item">
        <div class="icon <?php echo $is_overdue ? 'bg-danger' : 'bg-warning'; ?>">
          <i class="bi bi-<?php echo $is_overdue ? 'exclamation-triangle' : 'calendar'; ?>" style="color:white;"></i>
        </div>
        <div class="content">
          <div class="title"><?php echo date('d M Y', strtotime($u['tanggal_jatuh_tempo'])); ?><?php if ($is_overdue): ?> <span class="badge badge-danger">Telat!</span><?php endif; ?></div>
          <div class="subtitle">Angsuran ke-<?php echo $u['hari_ke']; ?></div>
        </div>
        <div class="amount <?php echo $is_overdue ? 'text-danger' : ''; ?>">Rp <?php echo number_format($u['total_tagih'], 0, ',', '.'); ?></div>
      </div>
      <?php endwhile; ?>
    <?php else: ?>
      <div class="card text-center p-3">
        <i class="bi bi-check-circle text-success" style="font-size:40px;"></i>
        <p class="text-gray mt-2 mb-0">Tidak ada tagihan</p>
      </div>
    <?php endif; ?>
  </div>

  <!-- Pinjaman Aktif -->
  <div class="section">
    <div class="section-header">
      <h3><i class="bi bi-credit-card me-2"></i>Pinjaman Aktif</h3>
    </div>
    <?php if (count($loans_data) > 0): ?>
      <?php foreach ($loans_data as $l): 
        $pct = $l['total'] > 0 ? round(($l['paid'] / $l['total']) * 100) : 0;
        $total = $l['total_pinjaman'] ?: ($l['pokok'] + ($l['pokok'] * $l['bunga_persen'] / 100));
        $sisa = max(0, $total - $l['terbayar']);
      ?>
      <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
          <div>
            <div class="fw-bold"><?php echo $l['no_pinjaman']; ?></div>
            <div class="text-sm text-gray"><?php echo $l['produk_nama']; ?></div>
          </div>
          <span class="badge <?php echo $l['status']=='aktif' ? 'badge-primary' : 'badge-success'; ?>"><?php echo strtoupper($l['status']); ?></span>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:10px;">
          <div><span class="text-gray">Pokok</span><br><strong>Rp <?php echo number_format($l['pokok'],0,',','.'); ?></strong></div>
          <div><span class="text-gray">Total</span><br><strong>Rp <?php echo number_format($total,0,',','.'); ?></strong></div>
          <div><span class="text-gray">Sisa</span><br><strong class="text-danger">Rp <?php echo number_format($sisa,0,',','.'); ?></strong></div>
        </div>
        <div class="progress-bar"><div class="fill" style="width:<?php echo $pct; ?>%;"></div></div>
        <div style="display:flex;justify-content:space-between;font-size:12px;color:#6b7280;">
          <span><?php echo $l['paid']; ?>/<?php echo $l['total']; ?> angsuran</span>
          <span><?php echo $pct; ?>% lunas</span>
        </div>
      </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="card text-center p-3">
        <i class="bi bi-inbox text-gray" style="font-size:40px;"></i>
        <p class="text-gray mt-2 mb-0">Belum ada pinjaman</p>
        <a href="pengajuan.php" class="btn btn-primary btn-block mt-3">Ajukan Sekarang</a>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($pending_count > 0): ?>
  <div class="section">
    <div class="alert alert-info">
      <i class="bi bi-info-circle"></i>
      <div>Anda memiliki <strong><?php echo $pending_count; ?></strong> pengajuan yang sedang diproses. <a href="status_pengajuan.php">Lihat Status</a></div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Bottom Navigation -->
  <div class="bottom-nav">
    <a href="dashboard.php" class="nav-item active">
      <i class="bi bi-house-fill"></i>
      <span>Home</span>
    </a>
    <a href="status_pengajuan.php" class="nav-item">
      <i class="bi bi-clock-history"></i>
      <span>Status</span>
    </a>
    <a href="pengajuan.php" class="nav-item center-btn">
      <div class="icon-wrap"><i class="bi bi-plus-lg"></i></div>
      <span>Ajukan</span>
    </a>
    <a href="simpanan.php" class="nav-item">
      <i class="bi bi-wallet2"></i>
      <span>Simpanan</span>
    </a>
    <a href="profil.php" class="nav-item">
      <i class="bi bi-person"></i>
      <span>Profil</span>
    </a>
  </div>
</body>
</html>

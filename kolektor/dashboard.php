<?php
// kolektor/dashboard.php - Dashboard Kolektor Mobile
require_once __DIR__ . '/../lib/helpers.php';
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

if (!isset($_SESSION['kolektor_id'])) {
  header('Location: index.php');
  exit;
}

$conn = db();
$kolektor_id = $_SESSION['kolektor_id'];
$today = date('Y-m-d');

// Get kolektor info
$kolektor = $conn->query("SELECT * FROM ksp_kolektor WHERE id = $kolektor_id")->fetch_assoc();

// Get today's collection sheet
$tagihan = $conn->query("
  SELECT t.id as tagih_id, ti.id as item_id, j.*, p.no_pinjaman, a.nama, a.hp, a.alamat
  FROM ksp_tagih_harian t
  JOIN ksp_tagih_item ti ON ti.tagih_id = t.id
  JOIN ksp_jadwal_angsuran j ON j.id = ti.jadwal_id
  JOIN ksp_pinjaman p ON p.id = j.pinjaman_id
  JOIN ksp_anggota a ON a.id = p.anggota_id
  WHERE t.kolektor_id = $kolektor_id AND t.tanggal = '$today'
  ORDER BY a.nama
");

// Calculate stats
$total_target = 0;
$total_collected = 0;
$items = [];
while ($t = $tagihan->fetch_assoc()) {
  $items[] = $t;
  $total_target += $t['total_tagih'];
  if ($t['status'] == 'lunas') $total_collected += $t['bayar_nominal'];
}
$pending = count(array_filter($items, function($i) { return $i['status'] == 'belum'; }));
$collected = count(array_filter($items, function($i) { return $i['status'] == 'lunas'; }));

// Handle payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bayar'])) {
  $jadwal_id = (int)$_POST['jadwal_id'];
  $bayar = (float)$_POST['bayar_nominal'];
  
  $stmt = $conn->prepare("UPDATE ksp_jadwal_angsuran SET status='lunas', bayar_nominal=?, tanggal_bayar=CURDATE() WHERE id=? AND status='belum'");
  $stmt->bind_param("di", $bayar, $jadwal_id);
  if ($stmt->execute()) {
    // Check if all paid, update loan status
    $check = $conn->query("SELECT pinjaman_id FROM ksp_jadwal_angsuran WHERE id=$jadwal_id")->fetch_assoc();
    $pid = $check['pinjaman_id'];
    $unpaid = $conn->query("SELECT COUNT(*) c FROM ksp_jadwal_angsuran WHERE pinjaman_id=$pid AND status='belum'")->fetch_assoc()['c'];
    if ($unpaid == 0) {
      $conn->query("UPDATE ksp_pinjaman SET status='lunas' WHERE id=$pid");
    }
    log_activity('kolektor_payment', 'kolektor', 'Payment by kolektor: ' . $_SESSION['kolektor_nama']);
  }
  header('Location: dashboard.php?success=1');
  exit;
}

$company = setting_get('company_name', 'KSP Harian');
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard Kolektor - <?php echo htmlspecialchars($company); ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    body { background: #f5f5f5; }
    .navbar-kolektor { background: linear-gradient(135deg, #ea580c, #dc2626); }
    .stat-card { border-radius: 15px; border: none; }
    .stat-card .icon { width: 45px; height: 45px; border-radius: 12px; display: flex; align-items: center; justify-content: center; }
    .collection-item { background: white; border-radius: 12px; margin-bottom: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
    .collection-item.paid { border-left: 4px solid #22c55e; }
    .collection-item.pending { border-left: 4px solid #f59e0b; }
    .btn-wa { background: #25D366; color: white; }
    .btn-wa:hover { background: #128C7E; color: white; }
    .bottom-nav { position: fixed; bottom: 0; left: 0; right: 0; background: white; box-shadow: 0 -2px 10px rgba(0,0,0,0.1); z-index: 1000; }
  </style>
</head>
<body style="padding-bottom: 70px;">
  <!-- Navbar -->
  <nav class="navbar navbar-dark navbar-kolektor sticky-top">
    <div class="container">
      <span class="navbar-brand">
        <i class="bi bi-person-badge me-2"></i><?php echo htmlspecialchars($_SESSION['kolektor_nama']); ?>
      </span>
      <span class="text-white"><?php echo date('d M Y'); ?></span>
    </div>
  </nav>

  <div class="container py-3">
    <?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success py-2 mb-3"><i class="bi bi-check-circle me-2"></i>Pembayaran berhasil dicatat!</div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="row g-2 mb-3">
      <div class="col-6">
        <div class="stat-card card">
          <div class="card-body p-3 d-flex align-items-center">
            <div class="icon bg-warning bg-opacity-10 text-warning me-3">
              <i class="bi bi-hourglass-split"></i>
            </div>
            <div>
              <div class="text-muted small">Pending</div>
              <div class="fw-bold fs-5"><?php echo $pending; ?></div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-6">
        <div class="stat-card card">
          <div class="card-body p-3 d-flex align-items-center">
            <div class="icon bg-success bg-opacity-10 text-success me-3">
              <i class="bi bi-check-circle"></i>
            </div>
            <div>
              <div class="text-muted small">Terkumpul</div>
              <div class="fw-bold fs-5"><?php echo $collected; ?></div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-6">
        <div class="stat-card card">
          <div class="card-body p-3 d-flex align-items-center">
            <div class="icon bg-primary bg-opacity-10 text-primary me-3">
              <i class="bi bi-bullseye"></i>
            </div>
            <div>
              <div class="text-muted small">Target</div>
              <div class="fw-bold">Rp <?php echo number_format($total_target,0,',','.'); ?></div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-6">
        <div class="stat-card card">
          <div class="card-body p-3 d-flex align-items-center">
            <div class="icon bg-success bg-opacity-10 text-success me-3">
              <i class="bi bi-cash-stack"></i>
            </div>
            <div>
              <div class="text-muted small">Terkumpul</div>
              <div class="fw-bold">Rp <?php echo number_format($total_collected,0,',','.'); ?></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Collection List -->
    <h6 class="mb-2"><i class="bi bi-list-check me-2"></i>Daftar Tagihan Hari Ini</h6>
    
    <?php if (count($items) == 0): ?>
    <div class="text-center text-muted py-5">
      <i class="bi bi-inbox fs-1 mb-2 d-block"></i>
      <p>Belum ada lembar tagihan untuk hari ini.</p>
      <small>Hubungi admin untuk membuat lembar tagihan.</small>
    </div>
    <?php else: ?>
    
    <?php foreach ($items as $item): ?>
    <div class="collection-item p-3 <?php echo $item['status']=='lunas' ? 'paid' : 'pending'; ?>">
      <div class="d-flex justify-content-between align-items-start mb-2">
        <div>
          <strong><?php echo htmlspecialchars($item['nama']); ?></strong>
          <div class="text-muted small"><?php echo htmlspecialchars($item['no_pinjaman']); ?> - Ke <?php echo $item['hari_ke']; ?></div>
        </div>
        <?php if ($item['status'] == 'lunas'): ?>
        <span class="badge bg-success"><i class="bi bi-check"></i> Lunas</span>
        <?php else: ?>
        <span class="badge bg-warning text-dark">Pending</span>
        <?php endif; ?>
      </div>
      
      <div class="d-flex justify-content-between align-items-center mb-2">
        <div>
          <div class="text-muted small">Tagihan</div>
          <div class="fw-bold text-primary">Rp <?php echo number_format($item['total_tagih'],0,',','.'); ?></div>
        </div>
        <div class="text-end">
          <div class="text-muted small">Alamat</div>
          <div class="small"><?php echo htmlspecialchars(substr($item['alamat'], 0, 30)); ?></div>
        </div>
      </div>
      
      <?php if ($item['status'] == 'belum'): ?>
      <div class="d-flex gap-2">
        <?php 
        $wa = preg_replace('/\D+/', '', $item['hp']);
        if ($wa && strpos($wa,'0')===0) $wa = '62'.substr($wa,1);
        $msg = "Halo " . $item['nama'] . ", ini kolektor dari " . $company . ". Tagihan Rp " . number_format($item['total_tagih'],0,',','.') . " sudah jatuh tempo. Mohon disiapkan. Terima kasih.";
        ?>
        <a href="https://wa.me/<?php echo $wa; ?>?text=<?php echo urlencode($msg); ?>" target="_blank" class="btn btn-sm btn-wa flex-fill">
          <i class="bi bi-whatsapp"></i> WA
        </a>
        <a href="tel:<?php echo htmlspecialchars($item['hp']); ?>" class="btn btn-sm btn-outline-secondary">
          <i class="bi bi-telephone"></i>
        </a>
        <button class="btn btn-sm btn-success flex-fill" data-bs-toggle="modal" data-bs-target="#modalBayar" 
                onclick="setBayar(<?php echo $item['id']; ?>, <?php echo $item['total_tagih']; ?>, '<?php echo htmlspecialchars($item['nama']); ?>')">
          <i class="bi bi-cash"></i> Bayar
        </button>
      </div>
      <?php else: ?>
      <div class="text-success small">
        <i class="bi bi-check-circle me-1"></i>Dibayar: Rp <?php echo number_format($item['bayar_nominal'],0,',','.'); ?> (<?php echo $item['tanggal_bayar']; ?>)
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
    
    <?php endif; ?>
  </div>

  <!-- Bottom Navigation -->
  <nav class="bottom-nav">
    <div class="container">
      <div class="row text-center py-2">
        <div class="col-4">
          <a href="dashboard.php" class="text-danger text-decoration-none">
            <i class="bi bi-house-fill d-block fs-5"></i>
            <small>Home</small>
          </a>
        </div>
        <div class="col-4">
          <a href="riwayat.php" class="text-muted text-decoration-none">
            <i class="bi bi-clock-history d-block fs-5"></i>
            <small>Riwayat</small>
          </a>
        </div>
        <div class="col-4">
          <a href="index.php?logout=1" class="text-muted text-decoration-none">
            <i class="bi bi-box-arrow-right d-block fs-5"></i>
            <small>Keluar</small>
          </a>
        </div>
      </div>
    </div>
  </nav>

  <!-- Modal Bayar -->
  <div class="modal fade" id="modalBayar" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form method="post">
          <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-cash me-2"></i>Input Pembayaran</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="jadwal_id" id="bayar_jadwal_id">
            <p class="mb-3">Nasabah: <strong id="bayar_nama"></strong></p>
            <div class="mb-3">
              <label class="form-label">Nominal Bayar</label>
              <input type="number" name="bayar_nominal" id="bayar_nominal" class="form-control form-control-lg" required>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
            <button type="submit" name="bayar" class="btn btn-success">
              <i class="bi bi-check-lg me-1"></i>Simpan Pembayaran
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script>
  function setBayar(id, nominal, nama) {
    document.getElementById('bayar_jadwal_id').value = id;
    document.getElementById('bayar_nominal').value = nominal;
    document.getElementById('bayar_nama').textContent = nama;
  }
  </script>
</body>
</html>

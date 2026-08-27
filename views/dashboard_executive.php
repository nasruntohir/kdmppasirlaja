<?php
// views/dashboard_executive.php - Executive Dashboard with KPI & Charts
require_login();
$conn = db();

$tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : date('Y');

// KPI Data
$kpi = [];

// Total Anggota
$kpi['anggota'] = $conn->query("SELECT COUNT(*) c FROM ksp_anggota WHERE status = 'aktif'")->fetch_assoc()['c'];
$kpi['anggota_baru'] = $conn->query("SELECT COUNT(*) c FROM ksp_anggota WHERE YEAR(tanggal_gabung) = $tahun")->fetch_assoc()['c'];

// Pinjaman
$kpi['pinjaman_aktif'] = $conn->query("SELECT COUNT(*) c FROM ksp_pinjaman WHERE status = 'aktif'")->fetch_assoc()['c'];
$kpi['total_outstanding'] = $conn->query("
  SELECT SUM(j.total_tagih - j.bayar_nominal) as t 
  FROM ksp_jadwal_angsuran j 
  JOIN ksp_pinjaman p ON p.id = j.pinjaman_id 
  WHERE p.status = 'aktif' AND j.status = 'belum'
")->fetch_assoc()['t'] ?: 0;

$kpi['pinjaman_tahun'] = $conn->query("SELECT COUNT(*) c FROM ksp_pinjaman WHERE YEAR(tanggal_realisasi) = $tahun")->fetch_assoc()['c'];
$kpi['realisasi_tahun'] = $conn->query("SELECT SUM(pokok) s FROM ksp_pinjaman WHERE YEAR(tanggal_realisasi) = $tahun")->fetch_assoc()['s'] ?: 0;

// Simpanan
$kpi['total_simpanan'] = $conn->query("SELECT SUM(total_simpanan) s FROM ksp_simpanan_anggota")->fetch_assoc()['s'] ?: 0;

// NPL (Non Performing Loan)
$kpi['tunggakan'] = $conn->query("
  SELECT COUNT(DISTINCT p.id) c 
  FROM ksp_jadwal_angsuran j 
  JOIN ksp_pinjaman p ON p.id = j.pinjaman_id 
  WHERE p.status = 'aktif' AND j.status = 'belum' AND j.tanggal_jatuh_tempo < CURDATE()
")->fetch_assoc()['c'];

$kpi['npl_rate'] = $kpi['pinjaman_aktif'] > 0 ? round($kpi['tunggakan'] / $kpi['pinjaman_aktif'] * 100, 1) : 0;

// Kas
$kpi['saldo_kas'] = $conn->query("
  SELECT SUM(CASE WHEN jenis='masuk' THEN nominal ELSE -nominal END) s FROM ksp_kas
")->fetch_assoc()['s'] ?: 0;

// Pendapatan & Biaya YTD
$kpi['pendapatan_ytd'] = $conn->query("SELECT SUM(nominal) s FROM ksp_kas WHERE jenis = 'masuk' AND YEAR(tanggal) = $tahun")->fetch_assoc()['s'] ?: 0;
$kpi['biaya_ytd'] = $conn->query("SELECT SUM(nominal) s FROM ksp_kas WHERE jenis = 'keluar' AND YEAR(tanggal) = $tahun")->fetch_assoc()['s'] ?: 0;
$kpi['laba_ytd'] = $kpi['pendapatan_ytd'] - $kpi['biaya_ytd'];

// Monthly data for charts
$monthly_data = [];
for ($m = 1; $m <= 12; $m++) {
  $start = "$tahun-" . str_pad($m, 2, '0', STR_PAD_LEFT) . "-01";
  $end = date('Y-m-t', strtotime($start));
  
  $pendapatan = $conn->query("SELECT SUM(nominal) s FROM ksp_kas WHERE jenis = 'masuk' AND tanggal BETWEEN '$start' AND '$end'")->fetch_assoc()['s'] ?: 0;
  $biaya = $conn->query("SELECT SUM(nominal) s FROM ksp_kas WHERE jenis = 'keluar' AND tanggal BETWEEN '$start' AND '$end'")->fetch_assoc()['s'] ?: 0;
  $realisasi = $conn->query("SELECT SUM(pokok) s FROM ksp_pinjaman WHERE tanggal_realisasi BETWEEN '$start' AND '$end'")->fetch_assoc()['s'] ?: 0;
  $angsuran = $conn->query("SELECT SUM(bayar_nominal) s FROM ksp_jadwal_angsuran WHERE tanggal_bayar BETWEEN '$start' AND '$end'")->fetch_assoc()['s'] ?: 0;
  
  $monthly_data[] = [
    'bulan' => date('M', strtotime($start)),
    'pendapatan' => (float)$pendapatan,
    'biaya' => (float)$biaya,
    'realisasi' => (float)$realisasi,
    'angsuran' => (float)$angsuran
  ];
}

// Top performers (kolektor) - check if kolektor_id exists
$kolektor_col = $conn->query("SHOW COLUMNS FROM ksp_pinjaman LIKE 'kolektor_id'")->num_rows;
if ($kolektor_col > 0) {
  $top_kolektor = $conn->query("
    SELECT k.nama, COUNT(*) as total_tagih, SUM(j.bayar_nominal) as total_collect
    FROM ksp_jadwal_angsuran j
    JOIN ksp_pinjaman p ON p.id = j.pinjaman_id
    JOIN ksp_kolektor k ON k.id = p.kolektor_id
    WHERE j.status = 'lunas' AND YEAR(j.tanggal_bayar) = $tahun
    GROUP BY k.id
    ORDER BY total_collect DESC
    LIMIT 5
  ");
} else {
  // Fallback: top anggota by payment
  $top_kolektor = $conn->query("
    SELECT a.nama, COUNT(*) as total_tagih, SUM(j.bayar_nominal) as total_collect
    FROM ksp_jadwal_angsuran j
    JOIN ksp_pinjaman p ON p.id = j.pinjaman_id
    JOIN ksp_anggota a ON a.id = p.anggota_id
    WHERE j.status = 'lunas' AND YEAR(j.tanggal_bayar) = $tahun
    GROUP BY a.id
    ORDER BY total_collect DESC
    LIMIT 5
  ");
}

// Aging analysis
$aging = $conn->query("
  SELECT 
    COUNT(CASE WHEN DATEDIFF(CURDATE(), j.tanggal_jatuh_tempo) BETWEEN 1 AND 30 THEN 1 END) as days_1_30,
    COUNT(CASE WHEN DATEDIFF(CURDATE(), j.tanggal_jatuh_tempo) BETWEEN 31 AND 60 THEN 1 END) as days_31_60,
    COUNT(CASE WHEN DATEDIFF(CURDATE(), j.tanggal_jatuh_tempo) BETWEEN 61 AND 90 THEN 1 END) as days_61_90,
    COUNT(CASE WHEN DATEDIFF(CURDATE(), j.tanggal_jatuh_tempo) > 90 THEN 1 END) as days_over_90
  FROM ksp_jadwal_angsuran j
  JOIN ksp_pinjaman p ON p.id = j.pinjaman_id
  WHERE p.status = 'aktif' AND j.status = 'belum' AND j.tanggal_jatuh_tempo < CURDATE()
")->fetch_assoc();
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4><i class="bi bi-graph-up-arrow me-2"></i>Dashboard Executive</h4>
  <div class="d-flex gap-2">
    <select class="form-select form-select-sm" style="width:auto;" onchange="location.href='index.php?m=dashboard_executive&tahun='+this.value">
      <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
      <option value="<?php echo $y; ?>" <?php echo $y == $tahun ? 'selected' : ''; ?>><?php echo $y; ?></option>
      <?php endfor; ?>
    </select>
    <button class="btn btn-sm btn-outline-success" onclick="window.print()"><i class="bi bi-printer me-1"></i>Cetak</button>
  </div>
</div>

<!-- KPI Cards Row 1 -->
<div class="row g-3 mb-4">
  <div class="col-md-3">
    <div class="card card-widget h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between">
          <div>
            <h6 class="text-muted mb-1">Total Anggota</h6>
            <h3 class="mb-0"><?php echo number_format($kpi['anggota']); ?></h3>
            <small class="text-success">+<?php echo $kpi['anggota_baru']; ?> tahun ini</small>
          </div>
          <div class="bg-primary bg-opacity-10 rounded-circle p-3">
            <i class="bi bi-people fs-4 text-primary"></i>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card card-widget h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between">
          <div>
            <h6 class="text-muted mb-1">Pinjaman Aktif</h6>
            <h3 class="mb-0"><?php echo number_format($kpi['pinjaman_aktif']); ?></h3>
            <small class="text-info"><?php echo $kpi['pinjaman_tahun']; ?> baru tahun ini</small>
          </div>
          <div class="bg-info bg-opacity-10 rounded-circle p-3">
            <i class="bi bi-cash-stack fs-4 text-info"></i>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card card-widget h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between">
          <div>
            <h6 class="text-muted mb-1">Outstanding</h6>
            <h4 class="mb-0">Rp <?php echo number_format($kpi['total_outstanding']/1000000, 1); ?>jt</h4>
            <small class="text-muted">Piutang belum terbayar</small>
          </div>
          <div class="bg-warning bg-opacity-10 rounded-circle p-3">
            <i class="bi bi-wallet2 fs-4 text-warning"></i>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card card-widget h-100 <?php echo $kpi['npl_rate'] > 5 ? 'border-danger' : ''; ?>">
      <div class="card-body">
        <div class="d-flex justify-content-between">
          <div>
            <h6 class="text-muted mb-1">NPL Rate</h6>
            <h3 class="mb-0 <?php echo $kpi['npl_rate'] > 5 ? 'text-danger' : 'text-success'; ?>"><?php echo $kpi['npl_rate']; ?>%</h3>
            <small class="<?php echo $kpi['npl_rate'] > 5 ? 'text-danger' : 'text-success'; ?>"><?php echo $kpi['tunggakan']; ?> pinjaman bermasalah</small>
          </div>
          <div class="bg-<?php echo $kpi['npl_rate'] > 5 ? 'danger' : 'success'; ?> bg-opacity-10 rounded-circle p-3">
            <i class="bi bi-exclamation-triangle fs-4 text-<?php echo $kpi['npl_rate'] > 5 ? 'danger' : 'success'; ?>"></i>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- KPI Cards Row 2 -->
<div class="row g-3 mb-4">
  <div class="col-md-3">
    <div class="card card-widget bg-success bg-opacity-10 h-100">
      <div class="card-body text-center">
        <i class="bi bi-graph-up fs-2 text-success"></i>
        <h5 class="mt-2 mb-0">Rp <?php echo number_format($kpi['pendapatan_ytd']/1000000, 1); ?>jt</h5>
        <small class="text-muted">Pendapatan YTD</small>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card card-widget bg-danger bg-opacity-10 h-100">
      <div class="card-body text-center">
        <i class="bi bi-graph-down fs-2 text-danger"></i>
        <h5 class="mt-2 mb-0">Rp <?php echo number_format($kpi['biaya_ytd']/1000000, 1); ?>jt</h5>
        <small class="text-muted">Biaya YTD</small>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card card-widget bg-primary bg-opacity-10 h-100">
      <div class="card-body text-center">
        <i class="bi bi-trophy fs-2 text-primary"></i>
        <h5 class="mt-2 mb-0 <?php echo $kpi['laba_ytd'] >= 0 ? 'text-success' : 'text-danger'; ?>">Rp <?php echo number_format($kpi['laba_ytd']/1000000, 1); ?>jt</h5>
        <small class="text-muted"><?php echo $kpi['laba_ytd'] >= 0 ? 'Laba' : 'Rugi'; ?> YTD</small>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card card-widget bg-info bg-opacity-10 h-100">
      <div class="card-body text-center">
        <i class="bi bi-wallet2 fs-2 text-info"></i>
        <h5 class="mt-2 mb-0">Rp <?php echo number_format($kpi['total_simpanan']/1000000, 1); ?>jt</h5>
        <small class="text-muted">Total Simpanan</small>
      </div>
    </div>
  </div>
</div>

<!-- Charts Row -->
<div class="row g-3 mb-4">
  <div class="col-md-8">
    <div class="card card-widget h-100">
      <div class="card-header"><h6 class="mb-0"><i class="bi bi-bar-chart me-2"></i>Trend Pendapatan vs Biaya</h6></div>
      <div class="card-body">
        <canvas id="chartPendapatan" height="120"></canvas>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card card-widget h-100">
      <div class="card-header"><h6 class="mb-0"><i class="bi bi-pie-chart me-2"></i>Aging Analysis</h6></div>
      <div class="card-body">
        <canvas id="chartAging" height="180"></canvas>
        <div class="mt-3 small">
          <div class="d-flex justify-content-between"><span>1-30 hari</span><span class="badge bg-warning"><?php echo $aging['days_1_30']; ?></span></div>
          <div class="d-flex justify-content-between"><span>31-60 hari</span><span class="badge bg-orange"><?php echo $aging['days_31_60']; ?></span></div>
          <div class="d-flex justify-content-between"><span>61-90 hari</span><span class="badge bg-danger"><?php echo $aging['days_61_90']; ?></span></div>
          <div class="d-flex justify-content-between"><span>>90 hari</span><span class="badge bg-dark"><?php echo $aging['days_over_90']; ?></span></div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Row 3 -->
<div class="row g-3">
  <div class="col-md-6">
    <div class="card card-widget h-100">
      <div class="card-header"><h6 class="mb-0"><i class="bi bi-graph-up me-2"></i>Realisasi vs Pengembalian</h6></div>
      <div class="card-body">
        <canvas id="chartRealisasi" height="150"></canvas>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card card-widget h-100">
      <div class="card-header"><h6 class="mb-0"><i class="bi bi-award me-2"></i>Top Kolektor</h6></div>
      <div class="card-body p-0">
        <table class="table table-sm mb-0">
          <thead><tr><th>Nama</th><th class="text-end">Tagihan</th><th class="text-end">Total Collect</th></tr></thead>
          <tbody>
          <?php if ($top_kolektor && $top_kolektor->num_rows > 0): ?>
            <?php $rank = 1; while ($k = $top_kolektor->fetch_assoc()): ?>
            <tr>
              <td>
                <?php if ($rank == 1): ?><i class="bi bi-trophy-fill text-warning me-1"></i><?php endif; ?>
                <?php echo htmlspecialchars($k['nama']); ?>
              </td>
              <td class="text-end"><?php echo number_format($k['total_tagih']); ?></td>
              <td class="text-end fw-bold">Rp <?php echo number_format($k['total_collect']/1000000, 1); ?>jt</td>
            </tr>
            <?php $rank++; endwhile; ?>
          <?php else: ?>
            <tr><td colspan="3" class="text-center text-muted">Belum ada data</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
var monthlyData = <?php echo json_encode($monthly_data); ?>;
var labels = monthlyData.map(d => d.bulan);

// Chart Pendapatan vs Biaya
new Chart(document.getElementById('chartPendapatan'), {
  type: 'bar',
  data: {
    labels: labels,
    datasets: [
      { label: 'Pendapatan', data: monthlyData.map(d => d.pendapatan), backgroundColor: '#10b981' },
      { label: 'Biaya', data: monthlyData.map(d => d.biaya), backgroundColor: '#ef4444' }
    ]
  },
  options: { responsive: true, scales: { y: { beginAtZero: true } } }
});

// Chart Aging
new Chart(document.getElementById('chartAging'), {
  type: 'doughnut',
  data: {
    labels: ['1-30 hari', '31-60 hari', '61-90 hari', '>90 hari'],
    datasets: [{ data: [<?php echo $aging['days_1_30']; ?>, <?php echo $aging['days_31_60']; ?>, <?php echo $aging['days_61_90']; ?>, <?php echo $aging['days_over_90']; ?>], backgroundColor: ['#f59e0b', '#f97316', '#ef4444', '#1f2937'] }]
  },
  options: { responsive: true }
});

// Chart Realisasi vs Angsuran
new Chart(document.getElementById('chartRealisasi'), {
  type: 'line',
  data: {
    labels: labels,
    datasets: [
      { label: 'Realisasi', data: monthlyData.map(d => d.realisasi), borderColor: '#3b82f6', tension: 0.3, fill: false },
      { label: 'Angsuran Masuk', data: monthlyData.map(d => d.angsuran), borderColor: '#10b981', tension: 0.3, fill: false }
    ]
  },
  options: { responsive: true, scales: { y: { beginAtZero: true } } }
});
</script>

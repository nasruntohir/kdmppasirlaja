<?php
// views/dashboard_koperasi.php - Dashboard Executive
$conn = db();
$today = date('Y-m-d');
$bulan = date('m');
$tahun = date('Y');
$tgl_awal_bulan = "$tahun-$bulan-01";
$tgl_akhir_bulan = date('Y-m-t');

// === KPI UTAMA ===

// Total Aset
$kas_bank = $conn->query("SELECT COALESCE(SUM(saldo),0) as total FROM ksp_kas_bank WHERE status='aktif'")->fetch_assoc()['total'];
$piutang = $conn->query("SELECT COALESCE(SUM(j.pokok_harian),0) as total FROM ksp_jadwal_angsuran j JOIN ksp_pinjaman p ON p.id=j.pinjaman_id WHERE p.status='aktif' AND j.status='belum'")->fetch_assoc()['total'];
$persediaan = $conn->query("SELECT COALESCE(SUM(stok * harga_beli),0) as total FROM ksp_produk_barang")->fetch_assoc()['total'];
$total_aset = $kas_bank + $piutang + $persediaan;

// Total Anggota
$jml_anggota = $conn->query("SELECT COUNT(*) as cnt FROM ksp_anggota WHERE status='aktif'")->fetch_assoc()['cnt'];

// Total Simpanan
$total_simpanan = $conn->query("SELECT COALESCE(SUM(total_simpanan),0) as total FROM ksp_simpanan_anggota")->fetch_assoc()['total'];

// Pinjaman Aktif
$pinjaman_aktif_cnt = $conn->query("SELECT COUNT(*) as cnt FROM ksp_pinjaman WHERE status='aktif'")->fetch_assoc()['cnt'];
$pinjaman_aktif_total = $conn->query("SELECT COALESCE(SUM(j.pokok_harian),0) as total FROM ksp_jadwal_angsuran j JOIN ksp_pinjaman p ON p.id=j.pinjaman_id WHERE p.status='aktif' AND j.status='belum'")->fetch_assoc()['total'];
$pinjaman_aktif = ['cnt' => $pinjaman_aktif_cnt, 'total' => $pinjaman_aktif_total];

// NPL Ratio
$npl_amount = $conn->query("
  SELECT COALESCE(SUM(j.pokok_harian),0) as total
  FROM ksp_jadwal_angsuran j
  JOIN ksp_pinjaman p ON p.id = j.pinjaman_id
  WHERE p.status='aktif' AND j.status='belum' AND DATEDIFF('$today', j.tanggal_jatuh_tempo) > 90
")->fetch_assoc()['total'];
$npl_ratio = $pinjaman_aktif['total'] > 0 ? ($npl_amount / $pinjaman_aktif['total'] * 100) : 0;

// Pendapatan Bulan Ini
$pendapatan_bulan = $conn->query("
  SELECT COALESCE(SUM(bunga_harian + denda),0) as total 
  FROM ksp_jadwal_angsuran j JOIN ksp_pinjaman p ON p.id=j.pinjaman_id 
  WHERE j.status='lunas' AND j.tanggal_bayar BETWEEN '$tgl_awal_bulan' AND '$tgl_akhir_bulan'
")->fetch_assoc()['total'];

$penjualan_bulan = $conn->query("
  SELECT COALESCE(SUM(total_bayar),0) as total FROM ksp_penjualan 
  WHERE status='selesai' AND DATE(tanggal) BETWEEN '$tgl_awal_bulan' AND '$tgl_akhir_bulan'
")->fetch_assoc()['total'];

// === ALERT: Angsuran Jatuh Tempo ===
$jatuh_tempo = $conn->query("
  SELECT j.*, p.no_pinjaman, a.nama as anggota_nama, a.hp
  FROM ksp_jadwal_angsuran j
  JOIN ksp_pinjaman p ON p.id = j.pinjaman_id
  JOIN ksp_anggota a ON a.id = p.anggota_id
  WHERE j.status = 'belum' AND j.tanggal_jatuh_tempo BETWEEN '$today' AND DATE_ADD('$today', INTERVAL 7 DAY)
  ORDER BY j.tanggal_jatuh_tempo
  LIMIT 10
");

// === TREND BULANAN ===
$trend = $conn->query("
  SELECT 
    DATE_FORMAT(j.tanggal_bayar, '%Y-%m') as bulan,
    SUM(j.pokok_harian) as pokok,
    SUM(j.bunga_harian) as bunga
  FROM ksp_jadwal_angsuran j
  JOIN ksp_pinjaman p ON p.id = j.pinjaman_id
  WHERE j.status = 'lunas' AND j.tanggal_bayar >= DATE_SUB('$today', INTERVAL 6 MONTH)
  GROUP BY DATE_FORMAT(j.tanggal_bayar, '%Y-%m')
  ORDER BY bulan
");
$trend_data = [];
while ($t = $trend->fetch_assoc()) $trend_data[] = $t;

// === PINJAMAN BARU ===
$pinjaman_baru = $conn->query("
  SELECT DATE_FORMAT(tanggal_realisasi, '%Y-%m') as bulan, COUNT(*) as jml, SUM(pokok) as total
  FROM ksp_pinjaman
  WHERE tanggal_realisasi >= DATE_SUB('$today', INTERVAL 6 MONTH)
  GROUP BY DATE_FORMAT(tanggal_realisasi, '%Y-%m')
  ORDER BY bulan
");
$pinjaman_trend = [];
while ($pt = $pinjaman_baru->fetch_assoc()) $pinjaman_trend[] = $pt;

// Stok Rendah
$stok_alert = $conn->query("SELECT COUNT(*) as cnt FROM ksp_produk_barang WHERE stok <= stok_minimal")->fetch_assoc()['cnt'];
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-speedometer2 me-2"></i>Dashboard Executive</h4>
  <small class="text-muted">Data per <?php echo date('d F Y H:i'); ?></small>
</div>

<!-- KPI Cards Row 1 -->
<div class="row mb-3">
  <div class="col-md-3">
    <div class="card bg-primary text-white h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between">
          <div>
            <h6 class="text-white-50">Total Aset</h6>
            <h4 class="mb-0">Rp <?php echo rupiah($total_aset); ?></h4>
          </div>
          <div class="fs-1 opacity-50"><i class="bi bi-building"></i></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card bg-success text-white h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between">
          <div>
            <h6 class="text-white-50">Total Simpanan</h6>
            <h4 class="mb-0">Rp <?php echo rupiah($total_simpanan); ?></h4>
          </div>
          <div class="fs-1 opacity-50"><i class="bi bi-piggy-bank"></i></div>
        </div>
        <small><?php echo $jml_anggota; ?> anggota aktif</small>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card bg-info text-white h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between">
          <div>
            <h6 class="text-white-50">Pinjaman Outstanding</h6>
            <h4 class="mb-0">Rp <?php echo rupiah($pinjaman_aktif['total']); ?></h4>
          </div>
          <div class="fs-1 opacity-50"><i class="bi bi-credit-card"></i></div>
        </div>
        <small><?php echo $pinjaman_aktif['cnt']; ?> pinjaman aktif</small>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card bg-<?php echo $npl_ratio > 5 ? 'danger' : ($npl_ratio > 2 ? 'warning' : 'success'); ?> text-white h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between">
          <div>
            <h6 class="text-white-50">NPL Ratio</h6>
            <h4 class="mb-0"><?php echo number_format($npl_ratio, 2); ?>%</h4>
          </div>
          <div class="fs-1 opacity-50"><i class="bi bi-exclamation-triangle"></i></div>
        </div>
        <small>NPL: Rp <?php echo rupiah($npl_amount); ?></small>
      </div>
    </div>
  </div>
</div>

<!-- KPI Cards Row 2 -->
<div class="row mb-3">
  <div class="col-md-3">
    <div class="card bg-light h-100">
      <div class="card-body">
        <h6 class="text-muted">Kas & Bank</h6>
        <h5 class="text-primary mb-0">Rp <?php echo rupiah($kas_bank); ?></h5>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card bg-light h-100">
      <div class="card-body">
        <h6 class="text-muted">Piutang Pinjaman</h6>
        <h5 class="text-info mb-0">Rp <?php echo rupiah($piutang); ?></h5>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card bg-light h-100">
      <div class="card-body">
        <h6 class="text-muted">Pendapatan Bulan Ini</h6>
        <h5 class="text-success mb-0">Rp <?php echo rupiah($pendapatan_bulan); ?></h5>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card bg-light h-100">
      <div class="card-body">
        <h6 class="text-muted">Penjualan Bulan Ini</h6>
        <h5 class="text-warning mb-0">Rp <?php echo rupiah($penjualan_bulan); ?></h5>
      </div>
    </div>
  </div>
</div>

<div class="row">
  <!-- Trend Chart -->
  <div class="col-md-8">
    <div class="card card-widget mb-3">
      <div class="card-header bg-light"><strong>📊 Trend Penerimaan Angsuran (6 Bulan)</strong></div>
      <div class="card-body">
        <canvas id="chartTrend" height="120"></canvas>
      </div>
    </div>
  </div>
  
  <!-- Alerts -->
  <div class="col-md-4">
    <div class="card card-widget mb-3">
      <div class="card-header bg-warning text-dark"><strong>⚠️ Alert</strong></div>
      <div class="card-body p-0">
        <ul class="list-group list-group-flush">
          <?php if ($stok_alert > 0): ?>
          <li class="list-group-item d-flex justify-content-between">
            <span><i class="bi bi-box-seam me-2"></i>Stok Rendah</span>
            <span class="badge bg-warning text-dark"><?php echo $stok_alert; ?> item</span>
          </li>
          <?php endif; ?>
          <?php if ($npl_ratio > 2): ?>
          <li class="list-group-item d-flex justify-content-between text-danger">
            <span><i class="bi bi-exclamation-circle me-2"></i>NPL Tinggi</span>
            <span class="badge bg-danger"><?php echo number_format($npl_ratio, 1); ?>%</span>
          </li>
          <?php endif; ?>
          <?php if ($jatuh_tempo->num_rows == 0 && $stok_alert == 0 && $npl_ratio <= 2): ?>
          <li class="list-group-item text-success"><i class="bi bi-check-circle me-2"></i>Tidak ada alert</li>
          <?php endif; ?>
        </ul>
      </div>
    </div>
    
    <div class="card card-widget">
      <div class="card-header bg-danger text-white"><strong>📅 Jatuh Tempo 7 Hari</strong></div>
      <div class="card-body p-0" style="max-height:250px;overflow-y:auto">
        <table class="table table-sm table-striped mb-0">
          <thead class="sticky-top bg-white"><tr><th>Anggota</th><th>Tgl</th><th class="text-end">Nominal</th></tr></thead>
          <tbody>
          <?php while($jt = $jatuh_tempo->fetch_assoc()): ?>
            <tr>
              <td><small><?php echo htmlspecialchars($jt['anggota_nama']); ?></small></td>
              <td><small><?php echo date('d/m', strtotime($jt['tanggal_jatuh_tempo'])); ?></small></td>
              <td class="text-end"><small>Rp <?php echo rupiah($jt['pokok'] + $jt['bunga']); ?></small></td>
            </tr>
          <?php endwhile; ?>
          <?php if ($jatuh_tempo->num_rows == 0): ?>
            <tr><td colspan="3" class="text-center text-muted">Tidak ada</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Quick Links -->
<div class="row mt-3">
  <div class="col-12">
    <div class="card card-widget">
      <div class="card-header bg-light"><strong>🔗 Akses Cepat Laporan</strong></div>
      <div class="card-body">
        <div class="row g-2">
          <div class="col-md-2"><a href="?m=laporan_laba_rugi" class="btn btn-outline-primary w-100"><i class="bi bi-file-earmark-bar-graph me-1"></i> Laba Rugi</a></div>
          <div class="col-md-2"><a href="?m=laporan_neraca" class="btn btn-outline-success w-100"><i class="bi bi-journal-bookmark me-1"></i> Neraca</a></div>
          <div class="col-md-2"><a href="?m=laporan_arus_kas" class="btn btn-outline-info w-100"><i class="bi bi-arrow-left-right me-1"></i> Arus Kas</a></div>
          <div class="col-md-2"><a href="?m=laporan_pinjaman" class="btn btn-outline-warning w-100"><i class="bi bi-credit-card me-1"></i> Pinjaman</a></div>
          <div class="col-md-2"><a href="?m=laporan_simpanan" class="btn btn-outline-secondary w-100"><i class="bi bi-piggy-bank me-1"></i> Simpanan</a></div>
          <div class="col-md-2"><a href="?m=shu_generate" class="btn btn-outline-danger w-100"><i class="bi bi-pie-chart me-1"></i> SHU</a></div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const trendData = <?php echo json_encode($trend_data); ?>;
new Chart(document.getElementById('chartTrend'), {
  type: 'bar',
  data: {
    labels: trendData.map(t => t.bulan),
    datasets: [
      { label: 'Pokok', data: trendData.map(t => t.pokok), backgroundColor: 'rgba(54, 162, 235, 0.7)' },
      { label: 'Bunga', data: trendData.map(t => t.bunga), backgroundColor: 'rgba(75, 192, 192, 0.7)' }
    ]
  },
  options: {
    responsive: true,
    scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true } }
  }
});
</script>

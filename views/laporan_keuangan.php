<?php
// views/laporan_keuangan.php - Laporan Keuangan (Neraca & Laba/Rugi)
require_login();
$conn = db();

$tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : date('Y');
$bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : date('m');
$tipe = isset($_GET['tipe']) ? $_GET['tipe'] : 'neraca';

$start_date = "$tahun-01-01";
$end_date = "$tahun-$bulan-" . date('t', strtotime("$tahun-$bulan-01"));

// Get financial data
// AKTIVA
$kas = $conn->query("
  SELECT 
    SUM(CASE WHEN jenis = 'masuk' THEN nominal ELSE 0 END) - 
    SUM(CASE WHEN jenis = 'keluar' THEN nominal ELSE 0 END) as saldo
  FROM ksp_kas WHERE tanggal <= '$end_date'
")->fetch_assoc()['saldo'] ?: 0;

$piutang = $conn->query("
  SELECT SUM(j.total_tagih - j.bayar_nominal) as total
  FROM ksp_jadwal_angsuran j
  JOIN ksp_pinjaman p ON p.id = j.pinjaman_id
  WHERE p.status = 'aktif' AND j.status = 'belum'
")->fetch_assoc()['total'] ?: 0;

// PASIVA
$simpanan_anggota = $conn->query("SELECT SUM(total_simpanan) as total FROM ksp_simpanan_anggota")->fetch_assoc()['total'] ?: 0;

// PENDAPATAN
$pendapatan_bunga = $conn->query("
  SELECT SUM(nominal) as total FROM ksp_kas 
  WHERE jenis = 'masuk' AND sumber = 'angsuran' 
  AND tanggal BETWEEN '$start_date' AND '$end_date'
")->fetch_assoc()['total'] ?: 0;

$pendapatan_admin = $conn->query("
  SELECT SUM(admin_biaya) as total FROM ksp_pinjaman 
  WHERE tanggal_realisasi BETWEEN '$start_date' AND '$end_date'
")->fetch_assoc()['total'] ?: 0;

$pendapatan_denda = $conn->query("
  SELECT SUM(denda) as total FROM ksp_jadwal_angsuran 
  WHERE tanggal_bayar BETWEEN '$start_date' AND '$end_date'
")->fetch_assoc()['total'] ?: 0;

$total_pendapatan = $pendapatan_bunga + $pendapatan_admin + $pendapatan_denda;

// BIAYA
$biaya_operasional = $conn->query("
  SELECT SUM(nominal) as total FROM ksp_kas 
  WHERE jenis = 'keluar' 
  AND tanggal BETWEEN '$start_date' AND '$end_date'
")->fetch_assoc()['total'] ?: 0;

$laba_rugi = $total_pendapatan - $biaya_operasional;

$company = setting_get('company_name', 'KSP Harian');
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4><i class="bi bi-file-earmark-spreadsheet me-2"></i>Laporan Keuangan</h4>
  <button class="btn btn-outline-success" onclick="window.print()"><i class="bi bi-printer me-1"></i>Cetak</button>
</div>

<!-- Filter -->
<div class="card card-widget mb-3 no-print">
  <div class="card-body">
    <form class="row g-2" method="get">
      <input type="hidden" name="m" value="laporan_keuangan">
      <div class="col-md-2">
        <label class="form-label">Tipe Laporan</label>
        <select name="tipe" class="form-select">
          <option value="neraca" <?php echo $tipe == 'neraca' ? 'selected' : ''; ?>>Neraca</option>
          <option value="labarugi" <?php echo $tipe == 'labarugi' ? 'selected' : ''; ?>>Laba/Rugi</option>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label">Tahun</label>
        <select name="tahun" class="form-select">
          <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
          <option value="<?php echo $y; ?>" <?php echo $y == $tahun ? 'selected' : ''; ?>><?php echo $y; ?></option>
          <?php endfor; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label">Sampai Bulan</label>
        <select name="bulan" class="form-select">
          <?php for ($m = 1; $m <= 12; $m++): ?>
          <option value="<?php echo $m; ?>" <?php echo $m == $bulan ? 'selected' : ''; ?>><?php echo date('F', mktime(0,0,0,$m,1)); ?></option>
          <?php endfor; ?>
        </select>
      </div>
      <div class="col-md-2 align-self-end">
        <button class="btn btn-gradient w-100"><i class="bi bi-search me-1"></i>Tampilkan</button>
      </div>
    </form>
  </div>
</div>

<?php if ($tipe == 'neraca'): ?>
<!-- NERACA -->
<div class="card card-widget">
  <div class="card-header text-center bg-dark text-white">
    <h5 class="mb-0"><?php echo htmlspecialchars($company); ?></h5>
    <p class="mb-0">NERACA</p>
    <small>Per <?php echo date('d F Y', strtotime($end_date)); ?></small>
  </div>
  <div class="card-body">
    <div class="row">
      <!-- AKTIVA -->
      <div class="col-md-6">
        <h6 class="border-bottom pb-2 mb-3"><strong>AKTIVA</strong></h6>
        
        <h6 class="text-muted">Aktiva Lancar</h6>
        <table class="table table-sm">
          <tr>
            <td>Kas</td>
            <td class="text-end">Rp <?php echo number_format($kas, 0, ',', '.'); ?></td>
          </tr>
          <tr>
            <td>Piutang Anggota</td>
            <td class="text-end">Rp <?php echo number_format($piutang, 0, ',', '.'); ?></td>
          </tr>
          <tr class="table-secondary">
            <td><strong>Total Aktiva Lancar</strong></td>
            <td class="text-end"><strong>Rp <?php echo number_format($kas + $piutang, 0, ',', '.'); ?></strong></td>
          </tr>
        </table>
        
        <table class="table table-sm table-primary mt-3">
          <tr>
            <td><strong>TOTAL AKTIVA</strong></td>
            <td class="text-end"><strong>Rp <?php echo number_format($kas + $piutang, 0, ',', '.'); ?></strong></td>
          </tr>
        </table>
      </div>
      
      <!-- PASIVA -->
      <div class="col-md-6">
        <h6 class="border-bottom pb-2 mb-3"><strong>PASIVA</strong></h6>
        
        <h6 class="text-muted">Kewajiban</h6>
        <table class="table table-sm">
          <tr>
            <td>Simpanan Anggota</td>
            <td class="text-end">Rp <?php echo number_format($simpanan_anggota, 0, ',', '.'); ?></td>
          </tr>
          <tr class="table-secondary">
            <td><strong>Total Kewajiban</strong></td>
            <td class="text-end"><strong>Rp <?php echo number_format($simpanan_anggota, 0, ',', '.'); ?></strong></td>
          </tr>
        </table>
        
        <h6 class="text-muted">Modal</h6>
        <table class="table table-sm">
          <tr>
            <td>Laba/Rugi Berjalan</td>
            <td class="text-end">Rp <?php echo number_format($laba_rugi, 0, ',', '.'); ?></td>
          </tr>
          <tr>
            <td>Selisih</td>
            <td class="text-end">Rp <?php echo number_format(($kas + $piutang) - $simpanan_anggota - $laba_rugi, 0, ',', '.'); ?></td>
          </tr>
        </table>
        
        <table class="table table-sm table-primary mt-3">
          <tr>
            <td><strong>TOTAL PASIVA</strong></td>
            <td class="text-end"><strong>Rp <?php echo number_format($kas + $piutang, 0, ',', '.'); ?></strong></td>
          </tr>
        </table>
      </div>
    </div>
  </div>
</div>

<?php else: ?>
<!-- LABA RUGI -->
<div class="card card-widget">
  <div class="card-header text-center bg-dark text-white">
    <h5 class="mb-0"><?php echo htmlspecialchars($company); ?></h5>
    <p class="mb-0">LAPORAN LABA/RUGI</p>
    <small>Periode: <?php echo date('d M', strtotime($start_date)); ?> - <?php echo date('d M Y', strtotime($end_date)); ?></small>
  </div>
  <div class="card-body">
    <!-- Pendapatan -->
    <h6 class="border-bottom pb-2 mb-3"><strong>PENDAPATAN</strong></h6>
    <table class="table table-sm">
      <tr>
        <td>Pendapatan Bunga Pinjaman</td>
        <td class="text-end">Rp <?php echo number_format($pendapatan_bunga, 0, ',', '.'); ?></td>
      </tr>
      <tr>
        <td>Pendapatan Biaya Admin</td>
        <td class="text-end">Rp <?php echo number_format($pendapatan_admin, 0, ',', '.'); ?></td>
      </tr>
      <tr>
        <td>Pendapatan Denda</td>
        <td class="text-end">Rp <?php echo number_format($pendapatan_denda, 0, ',', '.'); ?></td>
      </tr>
      <tr class="table-success">
        <td><strong>TOTAL PENDAPATAN</strong></td>
        <td class="text-end"><strong>Rp <?php echo number_format($total_pendapatan, 0, ',', '.'); ?></strong></td>
      </tr>
    </table>
    
    <!-- Biaya -->
    <h6 class="border-bottom pb-2 mb-3 mt-4"><strong>BIAYA</strong></h6>
    <table class="table table-sm">
      <tr>
        <td>Biaya Operasional</td>
        <td class="text-end">Rp <?php echo number_format($biaya_operasional, 0, ',', '.'); ?></td>
      </tr>
      <tr class="table-danger">
        <td><strong>TOTAL BIAYA</strong></td>
        <td class="text-end"><strong>Rp <?php echo number_format($biaya_operasional, 0, ',', '.'); ?></strong></td>
      </tr>
    </table>
    
    <!-- Laba/Rugi -->
    <table class="table table-sm table-<?php echo $laba_rugi >= 0 ? 'primary' : 'danger'; ?> mt-4">
      <tr>
        <td><strong><?php echo $laba_rugi >= 0 ? 'LABA BERSIH' : 'RUGI BERSIH'; ?></strong></td>
        <td class="text-end"><strong class="fs-4">Rp <?php echo number_format(abs($laba_rugi), 0, ',', '.'); ?></strong></td>
      </tr>
    </table>
    
    <!-- Chart -->
    <div class="mt-4">
      <canvas id="chartLabaRugi" height="100"></canvas>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
new Chart(document.getElementById('chartLabaRugi'), {
  type: 'bar',
  data: {
    labels: ['Pendapatan', 'Biaya', '<?php echo $laba_rugi >= 0 ? 'Laba' : 'Rugi'; ?>'],
    datasets: [{
      data: [<?php echo $total_pendapatan; ?>, <?php echo $biaya_operasional; ?>, <?php echo abs($laba_rugi); ?>],
      backgroundColor: ['#10b981', '#ef4444', '<?php echo $laba_rugi >= 0 ? '#3b82f6' : '#f59e0b'; ?>']
    }]
  },
  options: {
    responsive: true,
    plugins: { legend: { display: false } },
    scales: { y: { beginAtZero: true } }
  }
});
</script>
<?php endif; ?>

<div class="text-center text-muted small mt-3 no-print">
  Dicetak pada <?php echo date('d/m/Y H:i'); ?>
</div>

<style>
@media print {
  .no-print { display: none !important; }
  .card { box-shadow: none !important; border: 1px solid #ddd !important; }
}
</style>

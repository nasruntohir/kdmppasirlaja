<?php
// views/rekap_angsuran.php - Rekap Angsuran
$conn = db();

$bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : date('m');
$tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : date('Y');

$tgl_awal = "$tahun-$bulan-01";
$tgl_akhir = date('Y-m-t', strtotime($tgl_awal));
$periode_label = date('F Y', strtotime($tgl_awal));

// List angsuran yang sudah dibayar
$list = $conn->query("
  SELECT j.*, p.no_pinjaman, a.nama as anggota_nama, a.no_anggota
  FROM ksp_jadwal_angsuran j
  JOIN ksp_pinjaman p ON p.id = j.pinjaman_id
  JOIN ksp_anggota a ON a.id = p.anggota_id
  WHERE j.status = 'lunas' AND j.tanggal_bayar BETWEEN '$tgl_awal' AND '$tgl_akhir'
  ORDER BY j.tanggal_bayar DESC
");

// Summary
$summary = $conn->query("
  SELECT 
    COUNT(*) as jml_angsuran,
    COALESCE(SUM(pokok_harian),0) as total_pokok,
    COALESCE(SUM(bunga_harian),0) as total_bunga,
    COALESCE(SUM(denda),0) as total_denda,
    COALESCE(SUM(pokok_harian + bunga_harian + denda),0) as total_all
  FROM ksp_jadwal_angsuran j
  JOIN ksp_pinjaman p ON p.id = j.pinjaman_id
  WHERE j.status = 'lunas' AND j.tanggal_bayar BETWEEN '$tgl_awal' AND '$tgl_akhir'
")->fetch_assoc();

// Daily summary
$daily = $conn->query("
  SELECT DATE(tanggal_bayar) as tanggal, 
         COUNT(*) as jml,
         SUM(pokok_harian + bunga_harian + denda) as total
  FROM ksp_jadwal_angsuran j
  JOIN ksp_pinjaman p ON p.id = j.pinjaman_id
  WHERE j.status = 'lunas' AND j.tanggal_bayar BETWEEN '$tgl_awal' AND '$tgl_akhir'
  GROUP BY DATE(tanggal_bayar)
  ORDER BY tanggal
");
$daily_data = [];
while ($d = $daily->fetch_assoc()) $daily_data[] = $d;
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-calendar-check me-2"></i>Rekap Angsuran</h4>
  <button class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer me-1"></i> Cetak</button>
</div>

<!-- Filter -->
<div class="card card-widget mb-3">
  <div class="card-body">
    <form method="get" class="row g-3 align-items-end">
      <input type="hidden" name="m" value="rekap_angsuran">
      <div class="col-md-2">
        <label class="form-label">Bulan</label>
        <select name="bulan" class="form-select">
          <?php for($i=1;$i<=12;$i++): ?>
          <option value="<?php echo $i; ?>" <?php echo $bulan==$i?'selected':''; ?>><?php echo date('F', mktime(0,0,0,$i,1)); ?></option>
          <?php endfor; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label">Tahun</label>
        <select name="tahun" class="form-select">
          <?php for($y=date('Y');$y>=date('Y')-5;$y--): ?>
          <option value="<?php echo $y; ?>" <?php echo $tahun==$y?'selected':''; ?>><?php echo $y; ?></option>
          <?php endfor; ?>
        </select>
      </div>
      <div class="col-md-2">
        <button class="btn btn-primary w-100"><i class="bi bi-funnel me-1"></i> Tampilkan</button>
      </div>
    </form>
  </div>
</div>

<!-- Summary Cards -->
<div class="row mb-3">
  <div class="col-md-3">
    <div class="card bg-primary text-white">
      <div class="card-body text-center">
        <h4 class="mb-0"><?php echo $summary['jml_angsuran']; ?></h4>
        <small>Jumlah Angsuran</small>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card bg-success text-white">
      <div class="card-body text-center">
        <h5 class="mb-0">Rp <?php echo rupiah($summary['total_pokok']); ?></h5>
        <small>Total Pokok</small>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card bg-info text-white">
      <div class="card-body text-center">
        <h5 class="mb-0">Rp <?php echo rupiah($summary['total_bunga']); ?></h5>
        <small>Total Bunga</small>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card bg-warning text-dark">
      <div class="card-body text-center">
        <h5 class="mb-0">Rp <?php echo rupiah($summary['total_all']); ?></h5>
        <small>Total Penerimaan</small>
      </div>
    </div>
  </div>
</div>

<div class="row">
  <!-- Daily Chart -->
  <div class="col-md-4">
    <div class="card card-widget mb-3">
      <div class="card-header bg-light"><strong>Penerimaan Harian</strong></div>
      <div class="card-body">
        <canvas id="chartDaily" height="250"></canvas>
      </div>
    </div>
  </div>
  
  <!-- List -->
  <div class="col-md-8">
    <div class="card card-widget">
      <div class="card-header bg-light"><strong>Detail Angsuran - <?php echo $periode_label; ?></strong></div>
      <div class="card-body">
        <div class="table-responsive" style="max-height:400px;overflow-y:auto">
          <table class="table table-striped table-sm">
            <thead class="sticky-top bg-white"><tr><th>#</th><th>Tanggal</th><th>No. Pinjaman</th><th>Anggota</th><th>Ke-</th><th class="text-end">Pokok</th><th class="text-end">Bunga</th><th class="text-end">Denda</th><th class="text-end">Total</th></tr></thead>
            <tbody>
            <?php $no=1; while($r=$list->fetch_assoc()): ?>
              <tr>
                <td><?php echo $no++; ?></td>
                <td><?php echo date('d/m/Y', strtotime($r['tanggal_bayar'])); ?></td>
                <td><strong><?php echo $r['no_pinjaman']; ?></strong></td>
                <td><?php echo $r['no_anggota']; ?> - <?php echo htmlspecialchars($r['anggota_nama']); ?></td>
                <td><?php echo $r['angsuran_ke']; ?></td>
                <td class="text-end">Rp <?php echo rupiah($r['pokok']); ?></td>
                <td class="text-end">Rp <?php echo rupiah($r['bunga']); ?></td>
                <td class="text-end"><?php echo $r['denda'] > 0 ? 'Rp '.rupiah($r['denda']) : '-'; ?></td>
                <td class="text-end"><strong>Rp <?php echo rupiah($r['pokok'] + $r['bunga'] + $r['denda']); ?></strong></td>
              </tr>
            <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const dailyData = <?php echo json_encode($daily_data); ?>;
new Chart(document.getElementById('chartDaily'), {
  type: 'bar',
  data: {
    labels: dailyData.map(d => d.tanggal.split('-')[2]),
    datasets: [{
      label: 'Total Penerimaan',
      data: dailyData.map(d => d.total),
      backgroundColor: 'rgba(54, 162, 235, 0.7)'
    }]
  },
  options: {
    scales: { y: { beginAtZero: true } },
    plugins: { legend: { display: false } }
  }
});
</script>
<style>
@media print { form, button, .btn, canvas { display: none !important; } }
</style>

<?php
// views/laporan_pinjaman.php - Laporan Pinjaman (Outstanding, NPL, Aging)
$conn = db();

$tipe_lap = isset($_GET['tipe']) ? $_GET['tipe'] : 'outstanding';
$tanggal = date('Y-m-d');

// Outstanding Loans
$outstanding = $conn->query("
  SELECT p.*, a.nama as anggota_nama, a.no_anggota, pp.nama as produk_nama,
         p.pokok as nominal,
         (SELECT COALESCE(SUM(j2.pokok_harian),0) FROM ksp_jadwal_angsuran j2 WHERE j2.pinjaman_id=p.id AND j2.status='belum') as sisa_pokok,
         (SELECT MIN(j.tanggal_jatuh_tempo) FROM ksp_jadwal_angsuran j WHERE j.pinjaman_id=p.id AND j.status='belum') as jatuh_tempo_terdekat,
         (SELECT COUNT(*) FROM ksp_jadwal_angsuran j WHERE j.pinjaman_id=p.id AND j.status='belum' AND j.tanggal_jatuh_tempo < '$tanggal') as angsuran_tunggak
  FROM ksp_pinjaman p
  JOIN ksp_anggota a ON a.id = p.anggota_id
  JOIN ksp_produk_pinjaman pp ON pp.id = p.produk_id
  WHERE p.status = 'aktif'
  ORDER BY p.tanggal_realisasi DESC
");

// NPL - tunggakan > 90 hari
$npl_list = $conn->query("
  SELECT p.*, a.nama as anggota_nama, a.no_anggota, pp.nama as produk_nama,
         (SELECT COALESCE(SUM(j2.pokok_harian),0) FROM ksp_jadwal_angsuran j2 WHERE j2.pinjaman_id=p.id AND j2.status='belum') as sisa_pokok,
         DATEDIFF('$tanggal', (SELECT MIN(j.tanggal_jatuh_tempo) FROM ksp_jadwal_angsuran j WHERE j.pinjaman_id=p.id AND j.status='belum')) as hari_tunggak,
         (SELECT SUM(j.pokok_harian + j.bunga_harian) FROM ksp_jadwal_angsuran j WHERE j.pinjaman_id=p.id AND j.status='belum' AND j.tanggal_jatuh_tempo < '$tanggal') as total_tunggakan
  FROM ksp_pinjaman p
  JOIN ksp_anggota a ON a.id = p.anggota_id
  JOIN ksp_produk_pinjaman pp ON pp.id = p.produk_id
  WHERE p.status = 'aktif'
  HAVING hari_tunggak > 90
  ORDER BY hari_tunggak DESC
");

// Aging Analysis
$aging = $conn->query("
  SELECT 
    CASE 
      WHEN DATEDIFF('$tanggal', j.tanggal_jatuh_tempo) <= 0 THEN 'current'
      WHEN DATEDIFF('$tanggal', j.tanggal_jatuh_tempo) BETWEEN 1 AND 30 THEN '1-30'
      WHEN DATEDIFF('$tanggal', j.tanggal_jatuh_tempo) BETWEEN 31 AND 60 THEN '31-60'
      WHEN DATEDIFF('$tanggal', j.tanggal_jatuh_tempo) BETWEEN 61 AND 90 THEN '61-90'
      ELSE '>90'
    END as kategori,
    COUNT(DISTINCT p.id) as jml_pinjaman,
    SUM(j.pokok_harian) as total_pokok,
    SUM(j.bunga_harian) as total_bunga,
    SUM(j.pokok_harian + j.bunga_harian) as total
  FROM ksp_jadwal_angsuran j
  JOIN ksp_pinjaman p ON p.id = j.pinjaman_id
  WHERE j.status = 'belum' AND p.status = 'aktif'
  GROUP BY kategori
  ORDER BY FIELD(kategori, 'current', '1-30', '31-60', '61-90', '>90')
");
$aging_data = [];
while ($ag = $aging->fetch_assoc()) $aging_data[$ag['kategori']] = $ag;

// Summary - hitung outstanding dari jadwal angsuran yang belum dibayar
$summary_cnt = $conn->query("SELECT COUNT(*) as total_pinjaman FROM ksp_pinjaman WHERE status='aktif'")->fetch_assoc()['total_pinjaman'];
$summary_outstanding = $conn->query("SELECT COALESCE(SUM(j.pokok_harian),0) as total FROM ksp_jadwal_angsuran j JOIN ksp_pinjaman p ON p.id=j.pinjaman_id WHERE p.status='aktif' AND j.status='belum'")->fetch_assoc()['total'];
$summary_npl = $conn->query("SELECT COALESCE(SUM(j.pokok_harian),0) as total FROM ksp_jadwal_angsuran j JOIN ksp_pinjaman p ON p.id=j.pinjaman_id WHERE p.status='aktif' AND j.status='belum' AND DATEDIFF('$tanggal', j.tanggal_jatuh_tempo) > 90")->fetch_assoc()['total'];
$summary = ['total_pinjaman' => $summary_cnt, 'total_outstanding' => $summary_outstanding, 'total_npl' => $summary_npl];

$npl_ratio = $summary['total_outstanding'] > 0 ? ($summary['total_npl'] / $summary['total_outstanding'] * 100) : 0;
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-credit-card me-2"></i>Laporan Pinjaman</h4>
  <button class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer me-1"></i> Cetak</button>
</div>

<!-- Summary Cards -->
<div class="row mb-3">
  <div class="col-md-3">
    <div class="card bg-primary text-white">
      <div class="card-body text-center">
        <h4 class="mb-0"><?php echo $summary['total_pinjaman']; ?></h4>
        <small>Pinjaman Aktif</small>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card bg-info text-white">
      <div class="card-body text-center">
        <h5 class="mb-0">Rp <?php echo rupiah($summary['total_outstanding']); ?></h5>
        <small>Total Outstanding</small>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card bg-danger text-white">
      <div class="card-body text-center">
        <h5 class="mb-0">Rp <?php echo rupiah($summary['total_npl']); ?></h5>
        <small>Total NPL (>90 hari)</small>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card bg-<?php echo $npl_ratio > 5 ? 'danger' : ($npl_ratio > 2 ? 'warning' : 'success'); ?> text-white">
      <div class="card-body text-center">
        <h4 class="mb-0"><?php echo number_format($npl_ratio, 2); ?>%</h4>
        <small>NPL Ratio</small>
      </div>
    </div>
  </div>
</div>

<!-- Tabs -->
<ul class="nav nav-tabs mb-3">
  <li class="nav-item"><a class="nav-link <?php echo $tipe_lap=='outstanding'?'active':''; ?>" href="?m=laporan_pinjaman&tipe=outstanding">Outstanding</a></li>
  <li class="nav-item"><a class="nav-link <?php echo $tipe_lap=='aging'?'active':''; ?>" href="?m=laporan_pinjaman&tipe=aging">Aging Piutang</a></li>
  <li class="nav-item"><a class="nav-link <?php echo $tipe_lap=='npl'?'active':''; ?>" href="?m=laporan_pinjaman&tipe=npl">NPL (>90 hari)</a></li>
</ul>

<?php if ($tipe_lap == 'outstanding'): ?>
<!-- Outstanding -->
<div class="card card-widget">
  <div class="card-header bg-light"><strong>Daftar Pinjaman Aktif (Outstanding)</strong></div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-striped table-sm datatable">
        <thead><tr><th>#</th><th>No. Pinjaman</th><th>Anggota</th><th>Produk</th><th>Tgl Realisasi</th><th class="text-end">Nominal</th><th class="text-end">Sisa Pokok</th><th>Jatuh Tempo</th><th>Status</th></tr></thead>
        <tbody>
        <?php $no=1; while($r=$outstanding->fetch_assoc()): ?>
          <tr class="<?php echo $r['angsuran_tunggak'] > 0 ? 'table-warning' : ''; ?>">
            <td><?php echo $no++; ?></td>
            <td><strong><?php echo $r['no_pinjaman']; ?></strong></td>
            <td><?php echo $r['no_anggota']; ?> - <?php echo htmlspecialchars($r['anggota_nama']); ?></td>
            <td><?php echo $r['produk_nama']; ?></td>
            <td><?php echo date('d/m/Y', strtotime($r['tanggal_realisasi'])); ?></td>
            <td class="text-end">Rp <?php echo rupiah($r['nominal']); ?></td>
            <td class="text-end"><strong>Rp <?php echo rupiah($r['sisa_pokok']); ?></strong></td>
            <td><?php echo $r['jatuh_tempo_terdekat'] ? date('d/m/Y', strtotime($r['jatuh_tempo_terdekat'])) : '-'; ?></td>
            <td>
              <?php if ($r['angsuran_tunggak'] > 0): ?>
              <span class="badge bg-danger"><?php echo $r['angsuran_tunggak']; ?> Tunggak</span>
              <?php else: ?>
              <span class="badge bg-success">Lancar</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php elseif ($tipe_lap == 'aging'): ?>
<!-- Aging -->
<div class="card card-widget">
  <div class="card-header bg-light"><strong>Aging Piutang</strong></div>
  <div class="card-body">
    <table class="table table-bordered">
      <thead class="table-dark">
        <tr><th>Kategori</th><th>Jml Pinjaman</th><th class="text-end">Total Pokok</th><th class="text-end">Total Bunga</th><th class="text-end">Total</th><th>Kolektibilitas</th></tr>
      </thead>
      <tbody>
        <tr class="table-success">
          <td><strong>Lancar (Current)</strong></td>
          <td><?php echo $aging_data['current']['jml_pinjaman'] ?? 0; ?></td>
          <td class="text-end">Rp <?php echo rupiah($aging_data['current']['total_pokok'] ?? 0); ?></td>
          <td class="text-end">Rp <?php echo rupiah($aging_data['current']['total_bunga'] ?? 0); ?></td>
          <td class="text-end"><strong>Rp <?php echo rupiah($aging_data['current']['total'] ?? 0); ?></strong></td>
          <td><span class="badge bg-success">Kol 1</span></td>
        </tr>
        <tr class="table-info">
          <td><strong>1-30 hari</strong></td>
          <td><?php echo $aging_data['1-30']['jml_pinjaman'] ?? 0; ?></td>
          <td class="text-end">Rp <?php echo rupiah($aging_data['1-30']['total_pokok'] ?? 0); ?></td>
          <td class="text-end">Rp <?php echo rupiah($aging_data['1-30']['total_bunga'] ?? 0); ?></td>
          <td class="text-end"><strong>Rp <?php echo rupiah($aging_data['1-30']['total'] ?? 0); ?></strong></td>
          <td><span class="badge bg-info">Kol 2 - DPK</span></td>
        </tr>
        <tr class="table-warning">
          <td><strong>31-60 hari</strong></td>
          <td><?php echo $aging_data['31-60']['jml_pinjaman'] ?? 0; ?></td>
          <td class="text-end">Rp <?php echo rupiah($aging_data['31-60']['total_pokok'] ?? 0); ?></td>
          <td class="text-end">Rp <?php echo rupiah($aging_data['31-60']['total_bunga'] ?? 0); ?></td>
          <td class="text-end"><strong>Rp <?php echo rupiah($aging_data['31-60']['total'] ?? 0); ?></strong></td>
          <td><span class="badge bg-warning text-dark">Kol 3 - Kurang Lancar</span></td>
        </tr>
        <tr class="table-orange" style="background:#ffe0b2">
          <td><strong>61-90 hari</strong></td>
          <td><?php echo $aging_data['61-90']['jml_pinjaman'] ?? 0; ?></td>
          <td class="text-end">Rp <?php echo rupiah($aging_data['61-90']['total_pokok'] ?? 0); ?></td>
          <td class="text-end">Rp <?php echo rupiah($aging_data['61-90']['total_bunga'] ?? 0); ?></td>
          <td class="text-end"><strong>Rp <?php echo rupiah($aging_data['61-90']['total'] ?? 0); ?></strong></td>
          <td><span class="badge bg-orange" style="background:#ff9800;color:#fff">Kol 4 - Diragukan</span></td>
        </tr>
        <tr class="table-danger">
          <td><strong>>90 hari (NPL)</strong></td>
          <td><?php echo $aging_data['>90']['jml_pinjaman'] ?? 0; ?></td>
          <td class="text-end">Rp <?php echo rupiah($aging_data['>90']['total_pokok'] ?? 0); ?></td>
          <td class="text-end">Rp <?php echo rupiah($aging_data['>90']['total_bunga'] ?? 0); ?></td>
          <td class="text-end"><strong>Rp <?php echo rupiah($aging_data['>90']['total'] ?? 0); ?></strong></td>
          <td><span class="badge bg-danger">Kol 5 - Macet</span></td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<?php else: ?>
<!-- NPL -->
<div class="card card-widget">
  <div class="card-header bg-danger text-white"><strong>Non-Performing Loan (NPL) - Tunggakan > 90 Hari</strong></div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-striped table-sm datatable">
        <thead><tr><th>#</th><th>No. Pinjaman</th><th>Anggota</th><th>Produk</th><th class="text-end">Sisa Pokok</th><th class="text-end">Total Tunggakan</th><th>Hari Tunggak</th></tr></thead>
        <tbody>
        <?php $no=1; while($r=$npl_list->fetch_assoc()): ?>
          <tr>
            <td><?php echo $no++; ?></td>
            <td><strong><?php echo $r['no_pinjaman']; ?></strong></td>
            <td><?php echo $r['no_anggota']; ?> - <?php echo htmlspecialchars($r['anggota_nama']); ?></td>
            <td><?php echo $r['produk_nama']; ?></td>
            <td class="text-end">Rp <?php echo rupiah($r['sisa_pokok']); ?></td>
            <td class="text-end text-danger"><strong>Rp <?php echo rupiah($r['total_tunggakan']); ?></strong></td>
            <td><span class="badge bg-danger"><?php echo $r['hari_tunggak']; ?> hari</span></td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>

<style>
@media print { form, button, .btn, .nav-tabs { display: none !important; } }
</style>

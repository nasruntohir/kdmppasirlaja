<?php
// views/laporan_laba_rugi.php - Laporan Laba Rugi
$conn = db();

// Filter periode
$bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : date('m');
$tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : date('Y');
$tipe = isset($_GET['tipe']) ? $_GET['tipe'] : 'bulanan';

if ($tipe == 'tahunan') {
  $tgl_awal = "$tahun-01-01";
  $tgl_akhir = "$tahun-12-31";
  $periode_label = "Tahun $tahun";
} else {
  $tgl_awal = "$tahun-$bulan-01";
  $tgl_akhir = date('Y-m-t', strtotime($tgl_awal));
  $periode_label = date('F Y', strtotime($tgl_awal));
}

// PENDAPATAN
// 1. Pendapatan Bunga Pinjaman
$bunga_pinjaman = $conn->query("SELECT COALESCE(SUM(bunga_harian),0) as total FROM ksp_jadwal_angsuran j JOIN ksp_pinjaman p ON p.id=j.pinjaman_id WHERE j.status='lunas' AND j.tanggal_bayar BETWEEN '$tgl_awal' AND '$tgl_akhir'")->fetch_assoc()['total'];

// 2. Pendapatan Admin Pinjaman
$admin_pinjaman = $conn->query("SELECT COALESCE(SUM(pp.admin_biaya),0) as total FROM ksp_pinjaman p JOIN ksp_produk_pinjaman pp ON pp.id=p.produk_id WHERE p.tanggal_realisasi BETWEEN '$tgl_awal' AND '$tgl_akhir'")->fetch_assoc()['total'];

// 3. Pendapatan Denda
$denda = $conn->query("SELECT COALESCE(SUM(denda),0) as total FROM ksp_jadwal_angsuran j JOIN ksp_pinjaman p ON p.id=j.pinjaman_id WHERE j.status='lunas' AND j.tanggal_bayar BETWEEN '$tgl_awal' AND '$tgl_akhir'")->fetch_assoc()['total'];

// 4. Pendapatan Penjualan
$penjualan = $conn->query("SELECT COALESCE(SUM(total_bayar),0) as total FROM ksp_penjualan WHERE status='selesai' AND DATE(tanggal) BETWEEN '$tgl_awal' AND '$tgl_akhir'")->fetch_assoc()['total'];

// 5. Pendapatan dari transaksi keuangan
$pendapatan_lain = $conn->query("SELECT COALESCE(SUM(nominal),0) as total FROM ksp_transaksi_keuangan WHERE tipe='pemasukan' AND tanggal BETWEEN '$tgl_awal' AND '$tgl_akhir'")->fetch_assoc()['total'];

// Detail pendapatan per kategori
$pendapatan_detail = $conn->query("
  SELECT mp.nama, COALESCE(SUM(t.nominal),0) as total
  FROM ksp_master_pendapatan mp
  LEFT JOIN ksp_transaksi_keuangan t ON t.pendapatan_id = mp.id AND t.tanggal BETWEEN '$tgl_awal' AND '$tgl_akhir'
  GROUP BY mp.id, mp.nama
  ORDER BY mp.kode
");

$total_pendapatan = $bunga_pinjaman + $admin_pinjaman + $denda + $penjualan + $pendapatan_lain;

// BIAYA/BEBAN
// 1. HPP Penjualan (harga beli barang yang terjual)
$hpp = $conn->query("
  SELECT COALESCE(SUM(pd.jumlah * pb.harga_beli),0) as total 
  FROM ksp_penjualan_detail pd 
  JOIN ksp_penjualan p ON p.id = pd.penjualan_id 
  JOIN ksp_produk_barang pb ON pb.id = pd.produk_id
  WHERE p.status='selesai' AND DATE(p.tanggal) BETWEEN '$tgl_awal' AND '$tgl_akhir'
")->fetch_assoc()['total'];

// 2. Biaya dari transaksi keuangan
$biaya_lain = $conn->query("SELECT COALESCE(SUM(nominal),0) as total FROM ksp_transaksi_keuangan WHERE tipe='pengeluaran' AND tanggal BETWEEN '$tgl_awal' AND '$tgl_akhir'")->fetch_assoc()['total'];

// Detail biaya per kategori
$biaya_detail = $conn->query("
  SELECT mb.nama, COALESCE(SUM(t.nominal),0) as total
  FROM ksp_master_biaya mb
  LEFT JOIN ksp_transaksi_keuangan t ON t.biaya_id = mb.id AND t.tanggal BETWEEN '$tgl_awal' AND '$tgl_akhir'
  GROUP BY mb.id, mb.nama
  ORDER BY mb.kode
");

$total_biaya = $hpp + $biaya_lain;

// LABA/RUGI
$laba_kotor = $penjualan - $hpp;
$laba_bersih = $total_pendapatan - $total_biaya;
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-file-earmark-bar-graph me-2"></i>Laporan Laba Rugi</h4>
  <button class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer me-1"></i> Cetak</button>
</div>

<!-- Filter -->
<div class="card card-widget mb-3">
  <div class="card-body">
    <form method="get" class="row g-3 align-items-end">
      <input type="hidden" name="m" value="laporan_laba_rugi">
      <div class="col-md-2">
        <label class="form-label">Tipe</label>
        <select name="tipe" class="form-select" onchange="togglePeriode(this.value)">
          <option value="bulanan" <?php echo $tipe=='bulanan'?'selected':''; ?>>Bulanan</option>
          <option value="tahunan" <?php echo $tipe=='tahunan'?'selected':''; ?>>Tahunan</option>
        </select>
      </div>
      <div class="col-md-2" id="divBulan" <?php echo $tipe=='tahunan'?'style="display:none"':''; ?>>
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

<!-- Report -->
<div class="card card-widget" id="printArea">
  <div class="card-header bg-light text-center">
    <h5 class="mb-0">LAPORAN LABA RUGI</h5>
    <small>Periode: <?php echo $periode_label; ?></small>
  </div>
  <div class="card-body">
    <table class="table table-sm">
      <!-- PENDAPATAN -->
      <thead class="table-success">
        <tr><th colspan="2">PENDAPATAN</th></tr>
      </thead>
      <tbody>
        <tr><td class="ps-4">Pendapatan Bunga Pinjaman</td><td class="text-end">Rp <?php echo rupiah($bunga_pinjaman); ?></td></tr>
        <tr><td class="ps-4">Pendapatan Admin Pinjaman</td><td class="text-end">Rp <?php echo rupiah($admin_pinjaman); ?></td></tr>
        <tr><td class="ps-4">Pendapatan Denda Keterlambatan</td><td class="text-end">Rp <?php echo rupiah($denda); ?></td></tr>
        <tr><td class="ps-4">Pendapatan Penjualan</td><td class="text-end">Rp <?php echo rupiah($penjualan); ?></td></tr>
        <?php while($pd = $pendapatan_detail->fetch_assoc()): if($pd['total'] > 0): ?>
        <tr><td class="ps-4"><?php echo $pd['nama']; ?></td><td class="text-end">Rp <?php echo rupiah($pd['total']); ?></td></tr>
        <?php endif; endwhile; ?>
        <tr class="table-success fw-bold"><td>Total Pendapatan</td><td class="text-end">Rp <?php echo rupiah($total_pendapatan); ?></td></tr>
      </tbody>
      
      <!-- BEBAN/BIAYA -->
      <thead class="table-danger">
        <tr><th colspan="2">BEBAN / BIAYA</th></tr>
      </thead>
      <tbody>
        <tr><td class="ps-4">Harga Pokok Penjualan (HPP)</td><td class="text-end">Rp <?php echo rupiah($hpp); ?></td></tr>
        <?php while($bd = $biaya_detail->fetch_assoc()): if($bd['total'] > 0): ?>
        <tr><td class="ps-4"><?php echo $bd['nama']; ?></td><td class="text-end">Rp <?php echo rupiah($bd['total']); ?></td></tr>
        <?php endif; endwhile; ?>
        <tr class="table-danger fw-bold"><td>Total Beban</td><td class="text-end">Rp <?php echo rupiah($total_biaya); ?></td></tr>
      </tbody>
      
      <!-- LABA/RUGI -->
      <thead class="table-primary">
        <tr><th colspan="2">LABA / RUGI</th></tr>
      </thead>
      <tbody>
        <tr><td class="ps-4">Laba Kotor (Penjualan - HPP)</td><td class="text-end">Rp <?php echo rupiah($laba_kotor); ?></td></tr>
        <tr class="table-<?php echo $laba_bersih >= 0 ? 'primary' : 'warning'; ?> fw-bold fs-5">
          <td>LABA/RUGI BERSIH</td>
          <td class="text-end"><?php echo $laba_bersih >= 0 ? '' : '('; ?>Rp <?php echo rupiah(abs($laba_bersih)); ?><?php echo $laba_bersih >= 0 ? '' : ')'; ?></td>
        </tr>
      </tbody>
    </table>
  </div>
  <div class="card-footer text-muted text-end">
    <small>Dicetak: <?php echo date('d/m/Y H:i'); ?> oleh <?php echo user()['nama']; ?></small>
  </div>
</div>

<script>
function togglePeriode(val) {
  document.getElementById('divBulan').style.display = val === 'tahunan' ? 'none' : '';
}
</script>
<style>
@media print {
  .card-widget { border: none !important; box-shadow: none !important; }
  form, button, .btn { display: none !important; }
}
</style>

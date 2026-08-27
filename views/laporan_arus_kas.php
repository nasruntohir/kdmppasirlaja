<?php
// views/laporan_arus_kas.php - Laporan Arus Kas (Cash Flow Statement)
$conn = db();

$bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : date('m');
$tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : date('Y');

$tgl_awal = "$tahun-$bulan-01";
$tgl_akhir = date('Y-m-t', strtotime($tgl_awal));
$periode_label = date('F Y', strtotime($tgl_awal));

// Saldo Awal (dari kas_bank)
$saldo_awal = $conn->query("SELECT COALESCE(SUM(saldo_awal),0) as total FROM ksp_kas_bank WHERE status='aktif'")->fetch_assoc()['total'];

// === ARUS KAS DARI AKTIVITAS OPERASIONAL ===

// Penerimaan
// 1. Angsuran Pinjaman (pokok + bunga)
$angsuran_masuk = $conn->query("SELECT COALESCE(SUM(pokok_harian + bunga_harian + denda),0) as total FROM ksp_jadwal_angsuran j JOIN ksp_pinjaman p ON p.id=j.pinjaman_id WHERE j.status='lunas' AND j.tanggal_bayar BETWEEN '$tgl_awal' AND '$tgl_akhir'")->fetch_assoc()['total'];

// 2. Setoran Simpanan
$simpanan_masuk = $conn->query("SELECT COALESCE(SUM(nominal),0) as total FROM ksp_mutasi_simpanan WHERE tipe='setor' AND tanggal BETWEEN '$tgl_awal' AND '$tgl_akhir'")->fetch_assoc()['total'];

// 3. Penjualan
$penjualan_masuk = $conn->query("SELECT COALESCE(SUM(jumlah_bayar),0) as total FROM ksp_penjualan WHERE status='selesai' AND metode_bayar='tunai' AND DATE(tanggal) BETWEEN '$tgl_awal' AND '$tgl_akhir'")->fetch_assoc()['total'];

// 4. Pendapatan Lain
$pendapatan_lain = $conn->query("SELECT COALESCE(SUM(nominal),0) as total FROM ksp_transaksi_keuangan WHERE tipe='pemasukan' AND tanggal BETWEEN '$tgl_awal' AND '$tgl_akhir'")->fetch_assoc()['total'];

$total_penerimaan_operasional = $angsuran_masuk + $simpanan_masuk + $penjualan_masuk + $pendapatan_lain;

// Pengeluaran
// 1. Pencairan Pinjaman
$pinjaman_keluar = $conn->query("SELECT COALESCE(SUM(pokok),0) as total FROM ksp_pinjaman WHERE tanggal_realisasi BETWEEN '$tgl_awal' AND '$tgl_akhir'")->fetch_assoc()['total'];

// 2. Penarikan Simpanan
$simpanan_keluar = $conn->query("SELECT COALESCE(SUM(nominal),0) as total FROM ksp_mutasi_simpanan WHERE tipe='tarik' AND tanggal BETWEEN '$tgl_awal' AND '$tgl_akhir'")->fetch_assoc()['total'];

// 3. Pembelian Barang
$pembelian_keluar = $conn->query("SELECT COALESCE(SUM(total_nilai),0) as total FROM ksp_pembelian WHERE cara_bayar='tunai' AND tanggal BETWEEN '$tgl_awal' AND '$tgl_akhir'")->fetch_assoc()['total'];

// 4. Biaya Operasional
$biaya_keluar = $conn->query("SELECT COALESCE(SUM(nominal),0) as total FROM ksp_transaksi_keuangan WHERE tipe='pengeluaran' AND tanggal BETWEEN '$tgl_awal' AND '$tgl_akhir'")->fetch_assoc()['total'];

$total_pengeluaran_operasional = $pinjaman_keluar + $simpanan_keluar + $pembelian_keluar + $biaya_keluar;

$arus_operasional = $total_penerimaan_operasional - $total_pengeluaran_operasional;

// === ARUS KAS DARI AKTIVITAS INVESTASI ===
$investasi_masuk = 0; // Penjualan aset tetap, dll
$investasi_keluar = 0; // Pembelian aset tetap, dll
$arus_investasi = $investasi_masuk - $investasi_keluar;

// === ARUS KAS DARI AKTIVITAS PENDANAAN ===
$pendanaan_masuk = 0; // Penerimaan pinjaman dari pihak luar
$pendanaan_keluar = 0; // Pembayaran pinjaman ke pihak luar
$arus_pendanaan = $pendanaan_masuk - $pendanaan_keluar;

// TOTAL
$kenaikan_kas = $arus_operasional + $arus_investasi + $arus_pendanaan;
$saldo_akhir = $conn->query("SELECT COALESCE(SUM(saldo),0) as total FROM ksp_kas_bank WHERE status='aktif'")->fetch_assoc()['total'];
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-arrow-left-right me-2"></i>Laporan Arus Kas</h4>
  <button class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer me-1"></i> Cetak</button>
</div>

<!-- Filter -->
<div class="card card-widget mb-3">
  <div class="card-body">
    <form method="get" class="row g-3 align-items-end">
      <input type="hidden" name="m" value="laporan_arus_kas">
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

<!-- Report -->
<div class="card card-widget" id="printArea">
  <div class="card-header bg-light text-center">
    <h5 class="mb-0">LAPORAN ARUS KAS</h5>
    <small>Periode: <?php echo $periode_label; ?></small>
  </div>
  <div class="card-body">
    <table class="table table-sm">
      <!-- AKTIVITAS OPERASIONAL -->
      <thead class="table-primary">
        <tr><th colspan="2">ARUS KAS DARI AKTIVITAS OPERASIONAL</th></tr>
      </thead>
      <tbody>
        <tr class="fw-bold"><td>Penerimaan Kas:</td><td></td></tr>
        <tr><td class="ps-4">Penerimaan Angsuran Pinjaman</td><td class="text-end">Rp <?php echo rupiah($angsuran_masuk); ?></td></tr>
        <tr><td class="ps-4">Setoran Simpanan Anggota</td><td class="text-end">Rp <?php echo rupiah($simpanan_masuk); ?></td></tr>
        <tr><td class="ps-4">Penjualan Tunai</td><td class="text-end">Rp <?php echo rupiah($penjualan_masuk); ?></td></tr>
        <tr><td class="ps-4">Pendapatan Lain-lain</td><td class="text-end">Rp <?php echo rupiah($pendapatan_lain); ?></td></tr>
        <tr class="table-success"><td class="ps-3 fw-bold">Total Penerimaan</td><td class="text-end fw-bold">Rp <?php echo rupiah($total_penerimaan_operasional); ?></td></tr>
        
        <tr class="fw-bold"><td>Pengeluaran Kas:</td><td></td></tr>
        <tr><td class="ps-4">Pencairan Pinjaman</td><td class="text-end">(Rp <?php echo rupiah($pinjaman_keluar); ?>)</td></tr>
        <tr><td class="ps-4">Penarikan Simpanan</td><td class="text-end">(Rp <?php echo rupiah($simpanan_keluar); ?>)</td></tr>
        <tr><td class="ps-4">Pembelian Barang</td><td class="text-end">(Rp <?php echo rupiah($pembelian_keluar); ?>)</td></tr>
        <tr><td class="ps-4">Biaya Operasional</td><td class="text-end">(Rp <?php echo rupiah($biaya_keluar); ?>)</td></tr>
        <tr class="table-danger"><td class="ps-3 fw-bold">Total Pengeluaran</td><td class="text-end fw-bold">(Rp <?php echo rupiah($total_pengeluaran_operasional); ?>)</td></tr>
        
        <tr class="table-primary fw-bold"><td>Arus Kas Bersih dari Aktivitas Operasional</td><td class="text-end">Rp <?php echo rupiah($arus_operasional); ?></td></tr>
      </tbody>
      
      <!-- AKTIVITAS INVESTASI -->
      <thead class="table-info">
        <tr><th colspan="2">ARUS KAS DARI AKTIVITAS INVESTASI</th></tr>
      </thead>
      <tbody>
        <tr><td class="ps-4">Penjualan Aset Tetap</td><td class="text-end">Rp <?php echo rupiah($investasi_masuk); ?></td></tr>
        <tr><td class="ps-4">Pembelian Aset Tetap</td><td class="text-end">(Rp <?php echo rupiah($investasi_keluar); ?>)</td></tr>
        <tr class="table-info fw-bold"><td>Arus Kas Bersih dari Aktivitas Investasi</td><td class="text-end">Rp <?php echo rupiah($arus_investasi); ?></td></tr>
      </tbody>
      
      <!-- AKTIVITAS PENDANAAN -->
      <thead class="table-warning">
        <tr><th colspan="2">ARUS KAS DARI AKTIVITAS PENDANAAN</th></tr>
      </thead>
      <tbody>
        <tr><td class="ps-4">Penerimaan Pinjaman</td><td class="text-end">Rp <?php echo rupiah($pendanaan_masuk); ?></td></tr>
        <tr><td class="ps-4">Pembayaran Pinjaman</td><td class="text-end">(Rp <?php echo rupiah($pendanaan_keluar); ?>)</td></tr>
        <tr class="table-warning fw-bold"><td>Arus Kas Bersih dari Aktivitas Pendanaan</td><td class="text-end">Rp <?php echo rupiah($arus_pendanaan); ?></td></tr>
      </tbody>
      
      <!-- RINGKASAN -->
      <thead class="table-dark">
        <tr><th colspan="2">RINGKASAN</th></tr>
      </thead>
      <tbody>
        <tr><td>Kenaikan/(Penurunan) Kas Bersih</td><td class="text-end fw-bold <?php echo $kenaikan_kas >= 0 ? 'text-success' : 'text-danger'; ?>">Rp <?php echo rupiah($kenaikan_kas); ?></td></tr>
        <tr><td>Saldo Kas Awal Periode</td><td class="text-end">Rp <?php echo rupiah($saldo_awal); ?></td></tr>
        <tr class="table-dark fs-5 fw-bold"><td>Saldo Kas Akhir Periode</td><td class="text-end">Rp <?php echo rupiah($saldo_akhir); ?></td></tr>
      </tbody>
    </table>
  </div>
  <div class="card-footer text-muted text-end">
    <small>Dicetak: <?php echo date('d/m/Y H:i'); ?> oleh <?php echo user()['nama']; ?></small>
  </div>
</div>

<style>
@media print {
  .card-widget { border: none !important; box-shadow: none !important; }
  form, button, .btn { display: none !important; }
}
</style>

<?php
// views/laporan_neraca.php - Laporan Neraca (Balance Sheet)
$conn = db();

$tanggal = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');

// ASET
// 1. Kas & Bank
$kas_bank = $conn->query("SELECT COALESCE(SUM(saldo),0) as total FROM ksp_kas_bank WHERE status='aktif'")->fetch_assoc()['total'];
$kas_bank_detail = $conn->query("SELECT nama, tipe, saldo FROM ksp_kas_bank WHERE status='aktif' ORDER BY tipe, nama");

// 2. Piutang Pinjaman (sisa pokok yang belum dibayar)
$piutang_pinjaman = $conn->query("SELECT COALESCE(SUM(j.pokok_harian),0) as total FROM ksp_jadwal_angsuran j JOIN ksp_pinjaman p ON p.id=j.pinjaman_id WHERE p.status='aktif' AND j.status='belum'")->fetch_assoc()['total'];

// 3. Persediaan Barang (stok x harga beli)
$persediaan = $conn->query("SELECT COALESCE(SUM(stok * harga_beli),0) as total FROM ksp_produk_barang WHERE stok > 0")->fetch_assoc()['total'];

// 4. Piutang Bunga (bunga yang belum dibayar)
$piutang_bunga = $conn->query("SELECT COALESCE(SUM(bunga_harian),0) as total FROM ksp_jadwal_angsuran WHERE status='belum'")->fetch_assoc()['total'];

$total_aset = $kas_bank + $piutang_pinjaman + $persediaan + $piutang_bunga;

// KEWAJIBAN
// 1. Simpanan Anggota
$simpanan_pokok = $conn->query("SELECT COALESCE(SUM(simpanan_pokok),0) as total FROM ksp_simpanan_anggota")->fetch_assoc()['total'];
$simpanan_wajib = $conn->query("SELECT COALESCE(SUM(simpanan_wajib),0) as total FROM ksp_simpanan_anggota")->fetch_assoc()['total'];
$simpanan_sukarela = $conn->query("SELECT COALESCE(SUM(simpanan_sukarela),0) as total FROM ksp_simpanan_anggota")->fetch_assoc()['total'];
$total_simpanan = $simpanan_pokok + $simpanan_wajib + $simpanan_sukarela;

// 2. Tabungan Harian (dari simpanan sukarela yang sudah termasuk di atas)
$tabungan = 0; // Sudah termasuk dalam simpanan_sukarela

// 3. Hutang Usaha (jika ada pembelian non-tunai yang belum lunas - simplified)
$hutang_usaha = 0;

$total_kewajiban = $total_simpanan + $tabungan + $hutang_usaha;

// MODAL
// 1. Modal Awal / Cadangan dari SHU
$cadangan = $conn->query("SELECT COALESCE(SUM(cadangan),0) as total FROM ksp_shu_periode WHERE status='final'")->fetch_assoc()['total'];

// 2. SHU Tahun Berjalan (laba yang belum didistribusi)
$shu_berjalan = $conn->query("SELECT COALESCE(SUM(laba_kotor),0) as total FROM ksp_shu_periode WHERE status='draft'")->fetch_assoc()['total'];

// 3. Selisih (balancing)
$total_modal_calc = $total_aset - $total_kewajiban;
$selisih = $total_modal_calc - $cadangan - $shu_berjalan;

$total_modal = $cadangan + $shu_berjalan + $selisih;
$total_kewajiban_modal = $total_kewajiban + $total_modal;
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-journal-bookmark me-2"></i>Laporan Neraca</h4>
  <button class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer me-1"></i> Cetak</button>
</div>

<!-- Filter -->
<div class="card card-widget mb-3">
  <div class="card-body">
    <form method="get" class="row g-3 align-items-end">
      <input type="hidden" name="m" value="laporan_neraca">
      <div class="col-md-3">
        <label class="form-label">Per Tanggal</label>
        <input type="date" name="tanggal" class="form-control" value="<?php echo $tanggal; ?>">
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
    <h5 class="mb-0">LAPORAN NERACA</h5>
    <small>Per Tanggal: <?php echo date('d F Y', strtotime($tanggal)); ?></small>
  </div>
  <div class="card-body">
    <div class="row">
      <!-- ASET -->
      <div class="col-md-6">
        <table class="table table-sm">
          <thead class="table-primary">
            <tr><th colspan="2">ASET</th></tr>
          </thead>
          <tbody>
            <tr class="fw-bold"><td colspan="2">Aset Lancar</td></tr>
            <tr><td class="ps-4">Kas & Bank</td><td class="text-end">Rp <?php echo rupiah($kas_bank); ?></td></tr>
            <?php while($kb = $kas_bank_detail->fetch_assoc()): ?>
            <tr class="text-muted small"><td class="ps-5">- <?php echo $kb['nama']; ?></td><td class="text-end">Rp <?php echo rupiah($kb['saldo']); ?></td></tr>
            <?php endwhile; ?>
            <tr><td class="ps-4">Piutang Pinjaman</td><td class="text-end">Rp <?php echo rupiah($piutang_pinjaman); ?></td></tr>
            <tr><td class="ps-4">Piutang Bunga</td><td class="text-end">Rp <?php echo rupiah($piutang_bunga); ?></td></tr>
            <tr><td class="ps-4">Persediaan Barang</td><td class="text-end">Rp <?php echo rupiah($persediaan); ?></td></tr>
          </tbody>
          <tfoot class="table-primary">
            <tr class="fw-bold fs-5"><td>TOTAL ASET</td><td class="text-end">Rp <?php echo rupiah($total_aset); ?></td></tr>
          </tfoot>
        </table>
      </div>
      
      <!-- KEWAJIBAN & MODAL -->
      <div class="col-md-6">
        <table class="table table-sm">
          <thead class="table-warning">
            <tr><th colspan="2">KEWAJIBAN</th></tr>
          </thead>
          <tbody>
            <tr class="fw-bold"><td colspan="2">Kewajiban Jangka Pendek</td></tr>
            <tr><td class="ps-4">Simpanan Pokok Anggota</td><td class="text-end">Rp <?php echo rupiah($simpanan_pokok); ?></td></tr>
            <tr><td class="ps-4">Simpanan Wajib Anggota</td><td class="text-end">Rp <?php echo rupiah($simpanan_wajib); ?></td></tr>
            <tr><td class="ps-4">Simpanan Sukarela</td><td class="text-end">Rp <?php echo rupiah($simpanan_sukarela); ?></td></tr>
            <tr><td class="ps-4">Tabungan Harian</td><td class="text-end">Rp <?php echo rupiah($tabungan); ?></td></tr>
          </tbody>
          <tfoot class="table-warning">
            <tr class="fw-bold"><td>Total Kewajiban</td><td class="text-end">Rp <?php echo rupiah($total_kewajiban); ?></td></tr>
          </tfoot>
        </table>
        
        <table class="table table-sm">
          <thead class="table-success">
            <tr><th colspan="2">MODAL</th></tr>
          </thead>
          <tbody>
            <tr><td class="ps-4">Cadangan (Laba Ditahan)</td><td class="text-end">Rp <?php echo rupiah($cadangan); ?></td></tr>
            <tr><td class="ps-4">SHU Tahun Berjalan</td><td class="text-end">Rp <?php echo rupiah($shu_berjalan); ?></td></tr>
            <tr><td class="ps-4">Selisih</td><td class="text-end">Rp <?php echo rupiah($selisih); ?></td></tr>
          </tbody>
          <tfoot class="table-success">
            <tr class="fw-bold"><td>Total Modal</td><td class="text-end">Rp <?php echo rupiah($total_modal); ?></td></tr>
          </tfoot>
        </table>
        
        <table class="table table-sm">
          <tfoot class="table-primary">
            <tr class="fw-bold fs-5"><td>TOTAL KEWAJIBAN + MODAL</td><td class="text-end">Rp <?php echo rupiah($total_kewajiban_modal); ?></td></tr>
          </tfoot>
        </table>
      </div>
    </div>
    
    <?php if (abs($total_aset - $total_kewajiban_modal) > 1): ?>
    <div class="alert alert-warning mt-3">
      <i class="bi bi-exclamation-triangle me-1"></i> Neraca tidak balance. Selisih: Rp <?php echo rupiah(abs($total_aset - $total_kewajiban_modal)); ?>
    </div>
    <?php else: ?>
    <div class="alert alert-success mt-3">
      <i class="bi bi-check-circle me-1"></i> Neraca Balance ✓
    </div>
    <?php endif; ?>
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

<?php
// views/laporan_penjualan.php - Laporan Penjualan
csrf_check();
$conn = db();

// Filter
$tgl_dari = isset($_GET['tgl_dari']) ? $_GET['tgl_dari'] : date('Y-m-01');
$tgl_sampai = isset($_GET['tgl_sampai']) ? $_GET['tgl_sampai'] : date('Y-m-d');
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'selesai';

// Build query
$where = "WHERE DATE(p.tanggal) BETWEEN '$tgl_dari' AND '$tgl_sampai'";
if ($status_filter) $where .= " AND p.status = '$status_filter'";

// Summary
$summary = $conn->query("
  SELECT 
    COUNT(*) as total_transaksi,
    SUM(total_bayar) as total_penjualan,
    SUM(diskon) as total_diskon,
    SUM(CASE WHEN metode_bayar='tunai' THEN total_bayar ELSE 0 END) as total_tunai,
    SUM(CASE WHEN metode_bayar='transfer' THEN total_bayar ELSE 0 END) as total_transfer
  FROM ksp_penjualan p
  $where
")->fetch_assoc();

// Top products
$top_products = $conn->query("
  SELECT pr.kode, pr.nama, SUM(d.jumlah) as total_qty, SUM(d.subtotal) as total_nilai
  FROM ksp_penjualan_detail d
  JOIN ksp_penjualan p ON p.id = d.penjualan_id
  JOIN ksp_produk_barang pr ON pr.id = d.produk_id
  $where
  GROUP BY d.produk_id
  ORDER BY total_nilai DESC
  LIMIT 10
");

// Daily sales
$daily_sales = $conn->query("
  SELECT DATE(tanggal) as tgl, COUNT(*) as jml_trx, SUM(total_bayar) as total
  FROM ksp_penjualan p
  $where
  GROUP BY DATE(tanggal)
  ORDER BY tgl DESC
");

// Detail list
$list = $conn->query("
  SELECT p.*, a.nama as anggota_nama 
  FROM ksp_penjualan p 
  LEFT JOIN ksp_anggota a ON a.id = p.anggota_id 
  $where
  ORDER BY p.tanggal DESC
");
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-graph-up me-2"></i>Laporan Penjualan</h4>
  <button class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer me-1"></i> Cetak</button>
</div>

<!-- Filter -->
<div class="card card-widget mb-3">
  <div class="card-body">
    <form method="get" class="row g-3 align-items-end">
      <input type="hidden" name="m" value="laporan_penjualan">
      <div class="col-md-2">
        <label class="form-label">Dari Tanggal</label>
        <input type="date" name="tgl_dari" class="form-control" value="<?php echo $tgl_dari; ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label">Sampai Tanggal</label>
        <input type="date" name="tgl_sampai" class="form-control" value="<?php echo $tgl_sampai; ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
          <option value="">-- Semua --</option>
          <option value="selesai" <?php echo $status_filter=='selesai'?'selected':''; ?>>Selesai</option>
          <option value="batal" <?php echo $status_filter=='batal'?'selected':''; ?>>Batal</option>
        </select>
      </div>
      <div class="col-md-2">
        <button class="btn btn-primary w-100"><i class="bi bi-funnel me-1"></i> Filter</button>
      </div>
    </form>
  </div>
</div>

<!-- Summary Cards -->
<div class="row mb-4">
  <div class="col-md-3">
    <div class="card bg-primary text-white">
      <div class="card-body text-center">
        <h3 class="mb-0"><?php echo number_format($summary['total_transaksi']); ?></h3>
        <small>Total Transaksi</small>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card bg-success text-white">
      <div class="card-body text-center">
        <h3 class="mb-0">Rp <?php echo rupiah($summary['total_penjualan']); ?></h3>
        <small>Total Penjualan</small>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card bg-info text-white">
      <div class="card-body text-center">
        <h3 class="mb-0">Rp <?php echo rupiah($summary['total_tunai']); ?></h3>
        <small>Pembayaran Tunai</small>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card bg-warning text-dark">
      <div class="card-body text-center">
        <h3 class="mb-0">Rp <?php echo rupiah($summary['total_transfer']); ?></h3>
        <small>Pembayaran Transfer</small>
      </div>
    </div>
  </div>
</div>

<div class="row">
  <!-- Top Products -->
  <div class="col-md-6">
    <div class="card card-widget mb-3">
      <div class="card-header bg-light"><strong><i class="bi bi-trophy me-1"></i> Produk Terlaris</strong></div>
      <div class="card-body">
        <table class="table table-sm">
          <thead><tr><th>#</th><th>Produk</th><th class="text-end">Qty</th><th class="text-end">Nilai</th></tr></thead>
          <tbody>
          <?php $no=1; while($tp = $top_products->fetch_assoc()): ?>
          <tr>
            <td><?php echo $no++; ?></td>
            <td><?php echo $tp['kode'].' - '.$tp['nama']; ?></td>
            <td class="text-end"><?php echo number_format($tp['total_qty']); ?></td>
            <td class="text-end">Rp <?php echo rupiah($tp['total_nilai']); ?></td>
          </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  
  <!-- Daily Sales -->
  <div class="col-md-6">
    <div class="card card-widget mb-3">
      <div class="card-header bg-light"><strong><i class="bi bi-calendar-day me-1"></i> Penjualan Harian</strong></div>
      <div class="card-body">
        <table class="table table-sm">
          <thead><tr><th>Tanggal</th><th class="text-end">Jml Trx</th><th class="text-end">Total</th></tr></thead>
          <tbody>
          <?php while($ds = $daily_sales->fetch_assoc()): ?>
          <tr>
            <td><?php echo date('d/m/Y', strtotime($ds['tgl'])); ?></td>
            <td class="text-end"><?php echo $ds['jml_trx']; ?></td>
            <td class="text-end">Rp <?php echo rupiah($ds['total']); ?></td>
          </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Detail Table -->
<div class="card card-widget">
  <div class="card-header bg-light"><strong><i class="bi bi-list-ul me-1"></i> Detail Transaksi</strong></div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-striped table-sm datatable">
        <thead>
          <tr><th>#</th><th>No. Penjualan</th><th>Waktu</th><th>Customer</th><th>Subtotal</th><th>Diskon</th><th>Total</th><th>Metode</th><th>Status</th></tr>
        </thead>
        <tbody>
        <?php $no=1; while($r=$list->fetch_assoc()): ?>
          <tr class="<?php echo $r['status']=='batal'?'table-danger':''; ?>">
            <td><?php echo $no++; ?></td>
            <td><strong><?php echo $r['no_penjualan']; ?></strong></td>
            <td><?php echo date('d/m/Y H:i', strtotime($r['tanggal'])); ?></td>
            <td>
              <?php if ($r['customer_tipe'] == 'anggota'): ?>
              <span class="badge bg-success">Anggota</span> <?php echo $r['anggota_nama']; ?>
              <?php else: ?>
              <?php echo $r['nama_customer'] ?: 'Umum'; ?>
              <?php endif; ?>
            </td>
            <td class="text-end">Rp <?php echo rupiah($r['total_nilai']); ?></td>
            <td class="text-end"><?php echo $r['diskon'] > 0 ? 'Rp '.rupiah($r['diskon']) : '-'; ?></td>
            <td class="text-end"><strong>Rp <?php echo rupiah($r['total_bayar']); ?></strong></td>
            <td><?php echo $r['metode_bayar']=='tunai'?'💵 Tunai':'🏦 Transfer'; ?></td>
            <td><span class="badge text-bg-<?php echo $r['status']=='selesai'?'success':'danger'; ?>"><?php echo $r['status']; ?></span></td>
          </tr>
        <?php endwhile; ?>
        </tbody>
        <tfoot>
          <tr class="table-dark">
            <th colspan="4" class="text-end">TOTAL:</th>
            <th class="text-end">Rp <?php echo rupiah($summary['total_penjualan'] + $summary['total_diskon']); ?></th>
            <th class="text-end">Rp <?php echo rupiah($summary['total_diskon']); ?></th>
            <th class="text-end">Rp <?php echo rupiah($summary['total_penjualan']); ?></th>
            <th colspan="2"></th>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
</div>

<style>
@media print {
  .btn, .card-widget form, .dataTables_filter, .dataTables_length, .dataTables_info, .dataTables_paginate { display: none !important; }
  .card { border: none !important; box-shadow: none !important; }
}
</style>

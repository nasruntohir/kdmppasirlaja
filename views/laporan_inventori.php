<?php
// views/laporan_inventori.php - Laporan Inventori (Nilai Persediaan & Analisa)
$conn = db();

$tipe_lap = isset($_GET['tipe']) ? $_GET['tipe'] : 'persediaan';

// Nilai Persediaan
$persediaan = $conn->query("
  SELECT pb.*, kb.nama as kategori_nama, s.nama as satuan_nama,
         (pb.stok * pb.harga_beli) as nilai_beli,
         (pb.stok * pb.harga_jual) as nilai_jual
  FROM ksp_produk_barang pb
  LEFT JOIN ksp_kategori_barang kb ON kb.id = pb.kategori_id
  LEFT JOIN ksp_satuan s ON s.id = pb.satuan_id
  ORDER BY kb.nama, pb.nama
");

// Summary
$summary = $conn->query("
  SELECT 
    COUNT(*) as jml_item,
    SUM(stok) as total_stok,
    SUM(stok * harga_beli) as total_nilai_beli,
    SUM(stok * harga_jual) as total_nilai_jual,
    SUM(stok * (harga_jual - harga_beli)) as potensi_margin
  FROM ksp_produk_barang
")->fetch_assoc();

// Per kategori
$per_kategori = $conn->query("
  SELECT kb.nama as kategori, COUNT(*) as jml_item, SUM(pb.stok) as total_stok,
         SUM(pb.stok * pb.harga_beli) as nilai_beli
  FROM ksp_produk_barang pb
  LEFT JOIN ksp_kategori_barang kb ON kb.id = pb.kategori_id
  GROUP BY kb.id, kb.nama
  ORDER BY nilai_beli DESC
");
$kategori_data = [];
while ($k = $per_kategori->fetch_assoc()) $kategori_data[] = $k;

// Stok Rendah (di bawah minimum)
$stok_rendah = $conn->query("
  SELECT pb.*, kb.nama as kategori_nama, s.nama as satuan_nama
  FROM ksp_produk_barang pb
  LEFT JOIN ksp_kategori_barang kb ON kb.id = pb.kategori_id
  LEFT JOIN ksp_satuan s ON s.id = pb.satuan_id
  WHERE pb.stok <= pb.stok_minimal
  ORDER BY pb.stok ASC
");

// Analisa Penjualan (Top Products)
$bulan = date('m');
$tahun = date('Y');
$top_products = $conn->query("
  SELECT pb.kode, pb.nama, SUM(pd.jumlah) as qty_terjual, SUM(pd.subtotal) as total_penjualan,
         SUM(pd.jumlah * pb.harga_beli) as hpp,
         SUM(pd.subtotal) - SUM(pd.jumlah * pb.harga_beli) as margin
  FROM ksp_penjualan_detail pd
  JOIN ksp_penjualan p ON p.id = pd.penjualan_id
  JOIN ksp_produk_barang pb ON pb.id = pd.produk_id
  WHERE p.status = 'selesai' AND MONTH(p.tanggal) = $bulan AND YEAR(p.tanggal) = $tahun
  GROUP BY pb.id, pb.kode, pb.nama
  ORDER BY qty_terjual DESC
  LIMIT 10
");
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-boxes me-2"></i>Laporan Inventori</h4>
  <button class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer me-1"></i> Cetak</button>
</div>

<!-- Summary Cards -->
<div class="row mb-3">
  <div class="col-md-3">
    <div class="card bg-primary text-white">
      <div class="card-body text-center">
        <h4 class="mb-0"><?php echo $summary['jml_item']; ?></h4>
        <small>Jenis Barang</small>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card bg-info text-white">
      <div class="card-body text-center">
        <h4 class="mb-0"><?php echo number_format($summary['total_stok']); ?></h4>
        <small>Total Stok</small>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card bg-success text-white">
      <div class="card-body text-center">
        <h5 class="mb-0">Rp <?php echo rupiah($summary['total_nilai_beli']); ?></h5>
        <small>Nilai Persediaan (HPP)</small>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card bg-warning text-dark">
      <div class="card-body text-center">
        <h5 class="mb-0">Rp <?php echo rupiah($summary['potensi_margin']); ?></h5>
        <small>Potensi Margin</small>
      </div>
    </div>
  </div>
</div>

<!-- Tabs -->
<ul class="nav nav-tabs mb-3">
  <li class="nav-item"><a class="nav-link <?php echo $tipe_lap=='persediaan'?'active':''; ?>" href="?m=laporan_inventori&tipe=persediaan">Nilai Persediaan</a></li>
  <li class="nav-item"><a class="nav-link <?php echo $tipe_lap=='kategori'?'active':''; ?>" href="?m=laporan_inventori&tipe=kategori">Per Kategori</a></li>
  <li class="nav-item"><a class="nav-link <?php echo $tipe_lap=='stok_rendah'?'active':''; ?>" href="?m=laporan_inventori&tipe=stok_rendah">Stok Rendah</a></li>
  <li class="nav-item"><a class="nav-link <?php echo $tipe_lap=='analisa'?'active':''; ?>" href="?m=laporan_inventori&tipe=analisa">Analisa Penjualan</a></li>
</ul>

<?php if ($tipe_lap == 'persediaan'): ?>
<!-- Nilai Persediaan -->
<div class="card card-widget">
  <div class="card-header bg-light"><strong>Nilai Persediaan Barang</strong></div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-striped table-sm datatable">
        <thead><tr><th>#</th><th>Kode</th><th>Nama Barang</th><th>Kategori</th><th>Satuan</th><th class="text-end">Stok</th><th class="text-end">Harga Beli</th><th class="text-end">Harga Jual</th><th class="text-end">Nilai (HPP)</th><th class="text-end">Nilai Jual</th></tr></thead>
        <tbody>
        <?php $no=1; while($r=$persediaan->fetch_assoc()): ?>
          <tr class="<?php echo $r['stok'] <= $r['stok_minimal'] ? 'table-warning' : ''; ?>">
            <td><?php echo $no++; ?></td>
            <td><strong><?php echo $r['kode']; ?></strong></td>
            <td><?php echo htmlspecialchars($r['nama']); ?></td>
            <td><?php echo $r['kategori_nama'] ?? '-'; ?></td>
            <td><?php echo $r['satuan_nama'] ?? $r['satuan']; ?></td>
            <td class="text-end"><?php echo number_format($r['stok']); ?></td>
            <td class="text-end">Rp <?php echo rupiah($r['harga_beli']); ?></td>
            <td class="text-end">Rp <?php echo rupiah($r['harga_jual']); ?></td>
            <td class="text-end">Rp <?php echo rupiah($r['nilai_beli']); ?></td>
            <td class="text-end"><strong>Rp <?php echo rupiah($r['nilai_jual']); ?></strong></td>
          </tr>
        <?php endwhile; ?>
        </tbody>
        <tfoot class="table-dark">
          <tr>
            <th colspan="8">TOTAL</th>
            <th class="text-end">Rp <?php echo rupiah($summary['total_nilai_beli']); ?></th>
            <th class="text-end">Rp <?php echo rupiah($summary['total_nilai_jual']); ?></th>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
</div>

<?php elseif ($tipe_lap == 'kategori'): ?>
<!-- Per Kategori -->
<div class="row">
  <div class="col-md-6">
    <div class="card card-widget">
      <div class="card-header bg-light"><strong>Nilai Persediaan per Kategori</strong></div>
      <div class="card-body">
        <canvas id="chartKategori" height="250"></canvas>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card card-widget">
      <div class="card-body">
        <table class="table table-striped">
          <thead><tr><th>Kategori</th><th class="text-end">Jml Item</th><th class="text-end">Stok</th><th class="text-end">Nilai HPP</th></tr></thead>
          <tbody>
          <?php foreach($kategori_data as $k): ?>
            <tr>
              <td><?php echo $k['kategori'] ?? 'Tanpa Kategori'; ?></td>
              <td class="text-end"><?php echo $k['jml_item']; ?></td>
              <td class="text-end"><?php echo number_format($k['total_stok']); ?></td>
              <td class="text-end"><strong>Rp <?php echo rupiah($k['nilai_beli']); ?></strong></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
new Chart(document.getElementById('chartKategori'), {
  type: 'doughnut',
  data: {
    labels: <?php echo json_encode(array_map(fn($k) => $k['kategori'] ?? 'Lainnya', $kategori_data)); ?>,
    datasets: [{
      data: <?php echo json_encode(array_map(fn($k) => $k['nilai_beli'], $kategori_data)); ?>,
      backgroundColor: ['#0d6efd','#198754','#0dcaf0','#ffc107','#dc3545','#6f42c1','#fd7e14']
    }]
  }
});
</script>

<?php elseif ($tipe_lap == 'stok_rendah'): ?>
<!-- Stok Rendah -->
<div class="card card-widget">
  <div class="card-header bg-warning text-dark"><strong>⚠️ Barang dengan Stok Rendah</strong></div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-striped table-sm datatable">
        <thead><tr><th>#</th><th>Kode</th><th>Nama Barang</th><th>Kategori</th><th class="text-end">Stok</th><th class="text-end">Stok Min</th><th>Status</th></tr></thead>
        <tbody>
        <?php $no=1; while($r=$stok_rendah->fetch_assoc()): ?>
          <tr class="<?php echo $r['stok'] == 0 ? 'table-danger' : 'table-warning'; ?>">
            <td><?php echo $no++; ?></td>
            <td><strong><?php echo $r['kode']; ?></strong></td>
            <td><?php echo htmlspecialchars($r['nama']); ?></td>
            <td><?php echo $r['kategori_nama'] ?? '-'; ?></td>
            <td class="text-end"><?php echo number_format($r['stok']); ?></td>
            <td class="text-end"><?php echo number_format($r['stok_minimal']); ?></td>
            <td>
              <?php if ($r['stok'] == 0): ?>
              <span class="badge bg-danger">HABIS</span>
              <?php else: ?>
              <span class="badge bg-warning text-dark">RENDAH</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php else: ?>
<!-- Analisa Penjualan -->
<div class="card card-widget">
  <div class="card-header bg-light"><strong>Top 10 Produk Terlaris - <?php echo date('F Y'); ?></strong></div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-striped">
        <thead><tr><th>#</th><th>Kode</th><th>Nama Barang</th><th class="text-end">Qty Terjual</th><th class="text-end">Total Penjualan</th><th class="text-end">HPP</th><th class="text-end">Margin</th><th class="text-end">%</th></tr></thead>
        <tbody>
        <?php $no=1; while($r=$top_products->fetch_assoc()): $margin_pct = $r['total_penjualan'] > 0 ? ($r['margin'] / $r['total_penjualan'] * 100) : 0; ?>
          <tr>
            <td><?php echo $no++; ?></td>
            <td><strong><?php echo $r['kode']; ?></strong></td>
            <td><?php echo htmlspecialchars($r['nama']); ?></td>
            <td class="text-end"><?php echo number_format($r['qty_terjual']); ?></td>
            <td class="text-end">Rp <?php echo rupiah($r['total_penjualan']); ?></td>
            <td class="text-end">Rp <?php echo rupiah($r['hpp']); ?></td>
            <td class="text-end text-success"><strong>Rp <?php echo rupiah($r['margin']); ?></strong></td>
            <td class="text-end"><?php echo number_format($margin_pct, 1); ?>%</td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>

<style>
@media print { form, button, .btn, .nav-tabs, canvas { display: none !important; } }
</style>

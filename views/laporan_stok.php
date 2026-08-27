<?php
// views/laporan_stok.php - Laporan Stok Barang
csrf_check();
$conn = db();

// Filter
$kategori_filter = isset($_GET['kategori']) ? (int)$_GET['kategori'] : 0;
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'aktif';

// Build query
$where = "WHERE 1=1";
if ($kategori_filter > 0) $where .= " AND p.kategori_id = $kategori_filter";
if ($status_filter) $where .= " AND p.status = '$status_filter'";

// Get data with konversi calculation
$sql = "
  SELECT 
    p.*,
    k.nama as kategori_nama,
    s.kode as satuan_kode,
    s.nama as satuan_nama,
    (p.stok * p.harga_beli) as nilai_stok_beli,
    (p.stok * p.harga_jual) as nilai_stok_jual
  FROM ksp_produk_barang p
  LEFT JOIN ksp_kategori_barang k ON k.id = p.kategori_id
  LEFT JOIN ksp_satuan s ON s.id = p.satuan_id
  $where
  ORDER BY k.nama, p.nama
";
$list = $conn->query($sql);

// Summary
$total_items = 0;
$total_stok = 0;
$total_nilai_beli = 0;
$total_nilai_jual = 0;
$stok_warning = 0;
$stok_habis = 0;

$data = [];
while ($r = $list->fetch_assoc()) {
  $data[] = $r;
  $total_items++;
  $total_stok += $r['stok'];
  $total_nilai_beli += $r['nilai_stok_beli'];
  $total_nilai_jual += $r['nilai_stok_jual'];
  $stok_min = $r['stok_minimal'] ?? 10;
  if ($r['stok'] <= 0) $stok_habis++;
  elseif ($r['stok'] <= $stok_min) $stok_warning++;
}

// Load kategori for filter
$kategori_list = $conn->query("SELECT id, nama FROM ksp_kategori_barang WHERE status='aktif' ORDER BY nama");

// Get konversi data for display
$konversi_list = $conn->query("
  SELECT k.*, sd.kode as kode_dari, sk.kode as kode_ke
  FROM ksp_konversi_satuan k
  JOIN ksp_satuan sd ON sd.id = k.satuan_dari_id
  JOIN ksp_satuan sk ON sk.id = k.satuan_ke_id
");
$konversi_map = [];
while ($kv = $konversi_list->fetch_assoc()) {
  $konversi_map[$kv['satuan_dari_id'].'_'.$kv['satuan_ke_id']] = $kv;
}
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-clipboard-data me-2"></i>Laporan Stok Barang</h4>
  <button class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer me-1"></i> Cetak</button>
</div>

<!-- Summary Cards -->
<div class="row mb-4">
  <div class="col-md-3">
    <div class="card bg-primary text-white">
      <div class="card-body text-center">
        <h3 class="mb-0"><?php echo number_format($total_items); ?></h3>
        <small>Total Jenis Barang</small>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card bg-success text-white">
      <div class="card-body text-center">
        <h3 class="mb-0"><?php echo number_format($total_stok); ?></h3>
        <small>Total Stok (unit)</small>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card bg-info text-white">
      <div class="card-body text-center">
        <h3 class="mb-0">Rp <?php echo rupiah($total_nilai_beli); ?></h3>
        <small>Nilai Stok (Harga Beli)</small>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card bg-warning text-dark">
      <div class="card-body text-center">
        <h3 class="mb-0">Rp <?php echo rupiah($total_nilai_jual); ?></h3>
        <small>Nilai Stok (Harga Jual)</small>
      </div>
    </div>
  </div>
</div>

<?php if ($stok_habis > 0 || $stok_warning > 0): ?>
<div class="alert alert-warning">
  <i class="bi bi-exclamation-triangle me-1"></i>
  <?php if ($stok_habis > 0): ?><strong><?php echo $stok_habis; ?> barang</strong> stok habis. <?php endif; ?>
  <?php if ($stok_warning > 0): ?><strong><?php echo $stok_warning; ?> barang</strong> stok di bawah minimal. <?php endif; ?>
</div>
<?php endif; ?>

<!-- Filter -->
<div class="card card-widget mb-3">
  <div class="card-body">
    <form method="get" class="row g-3 align-items-end">
      <input type="hidden" name="m" value="laporan_stok">
      <div class="col-md-3">
        <label class="form-label">Kategori</label>
        <select name="kategori" class="form-select">
          <option value="0">-- Semua Kategori --</option>
          <?php while($k = $kategori_list->fetch_assoc()): ?>
          <option value="<?php echo $k['id']; ?>" <?php echo $kategori_filter==$k['id']?'selected':''; ?>><?php echo $k['nama']; ?></option>
          <?php endwhile; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
          <option value="">-- Semua --</option>
          <option value="aktif" <?php echo $status_filter=='aktif'?'selected':''; ?>>Aktif</option>
          <option value="nonaktif" <?php echo $status_filter=='nonaktif'?'selected':''; ?>>Non-Aktif</option>
        </select>
      </div>
      <div class="col-md-2">
        <button class="btn btn-primary w-100"><i class="bi bi-funnel me-1"></i> Filter</button>
      </div>
    </form>
  </div>
</div>

<!-- Data Table -->
<div class="card card-widget">
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-striped table-sm datatable">
        <thead>
          <tr>
            <th>#</th>
            <th>Kode</th>
            <th>Nama Barang</th>
            <th>Kategori</th>
            <th>Satuan</th>
            <th class="text-end">Stok</th>
            <th class="text-end">Harga Beli</th>
            <th class="text-end">Harga Jual</th>
            <th class="text-end">Nilai (Beli)</th>
            <th class="text-end">Nilai (Jual)</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
        <?php $no=1; foreach($data as $r): 
          $stok_min = $r['stok_minimal'] ?? 10;
          $stok_class = '';
          if ($r['stok'] <= 0) $stok_class = 'table-danger';
          elseif ($r['stok'] <= $stok_min) $stok_class = 'table-warning';
        ?>
          <tr class="<?php echo $stok_class; ?>">
            <td><?php echo $no++; ?></td>
            <td><strong><?php echo htmlspecialchars($r['kode']); ?></strong></td>
            <td><?php echo htmlspecialchars($r['nama']); ?></td>
            <td><?php echo $r['kategori_nama'] ?? '-'; ?></td>
            <td><?php echo $r['satuan_kode'] ?? $r['satuan']; ?></td>
            <td class="text-end">
              <?php echo number_format($r['stok']); ?>
              <?php if ($r['stok'] <= 0): ?>
              <span class="badge bg-danger">Habis</span>
              <?php elseif ($r['stok'] <= $stok_min): ?>
              <span class="badge bg-warning text-dark">Low</span>
              <?php endif; ?>
            </td>
            <td class="text-end">Rp <?php echo rupiah($r['harga_beli']); ?></td>
            <td class="text-end">Rp <?php echo rupiah($r['harga_jual']); ?></td>
            <td class="text-end">Rp <?php echo rupiah($r['nilai_stok_beli']); ?></td>
            <td class="text-end">Rp <?php echo rupiah($r['nilai_stok_jual']); ?></td>
            <td><span class="badge text-bg-<?php echo $r['status']=='aktif'?'success':'secondary'; ?>"><?php echo $r['status']; ?></span></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr class="table-dark">
            <th colspan="5" class="text-end">TOTAL:</th>
            <th class="text-end"><?php echo number_format($total_stok); ?></th>
            <th></th>
            <th></th>
            <th class="text-end">Rp <?php echo rupiah($total_nilai_beli); ?></th>
            <th class="text-end">Rp <?php echo rupiah($total_nilai_jual); ?></th>
            <th></th>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
</div>

<!-- Kartu Stok Section -->
<div class="card card-widget mt-4">
  <div class="card-header bg-light">
    <strong><i class="bi bi-card-list me-1"></i> Kartu Stok (Mutasi Terakhir)</strong>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-sm table-striped">
        <thead>
          <tr><th>Tanggal</th><th>Produk</th><th>Tipe</th><th>Referensi</th><th>Jumlah</th><th>Saldo</th><th>Keterangan</th></tr>
        </thead>
        <tbody>
        <?php
        $kartu = $conn->query("
          SELECT ks.*, p.nama as produk_nama, p.kode as produk_kode, s.kode as satuan_kode
          FROM ksp_kartu_stok ks
          LEFT JOIN ksp_produk_barang p ON p.id = ks.produk_id
          LEFT JOIN ksp_satuan s ON s.id = ks.satuan_id
          ORDER BY ks.id DESC
          LIMIT 50
        ");
        while ($ks = $kartu->fetch_assoc()):
        ?>
        <tr>
          <td><?php echo date('d/m/Y', strtotime($ks['tanggal'])); ?></td>
          <td><?php echo $ks['produk_kode'].' - '.$ks['produk_nama']; ?></td>
          <td>
            <?php if ($ks['tipe'] == 'masuk'): ?>
            <span class="badge bg-success">📥 Masuk</span>
            <?php elseif ($ks['tipe'] == 'keluar'): ?>
            <span class="badge bg-danger">📤 Keluar</span>
            <?php else: ?>
            <span class="badge bg-info">🔄 Adjust</span>
            <?php endif; ?>
          </td>
          <td><?php echo $ks['referensi'] ?? '-'; ?></td>
          <td><?php echo number_format($ks['jumlah'],2).' '.($ks['satuan_kode'] ?? ''); ?></td>
          <td><?php echo number_format($ks['saldo_akhir']); ?></td>
          <td><?php echo htmlspecialchars($ks['keterangan'] ?? ''); ?></td>
        </tr>
        <?php endwhile; ?>
        </tbody>
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

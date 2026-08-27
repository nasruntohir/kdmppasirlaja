<?php
// views/laporan_simpanan.php - Laporan Simpanan per Anggota
$conn = db();

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$where = "";
if ($search) {
  $where = "AND (a.nama LIKE '%$search%' OR a.no_anggota LIKE '%$search%')";
}

$list = $conn->query("
  SELECT a.id, a.no_anggota, a.nama, a.hp, a.status,
         COALESCE(sa.simpanan_pokok,0) as simpanan_pokok,
         COALESCE(sa.simpanan_wajib,0) as simpanan_wajib,
         COALESCE(sa.simpanan_sukarela,0) as simpanan_sukarela,
         COALESCE(sa.total_simpanan,0) as total_simpanan
  FROM ksp_anggota a
  LEFT JOIN ksp_simpanan_anggota sa ON sa.anggota_id = a.id
  WHERE 1=1 $where
  ORDER BY a.nama
");

// Summary
$summary = $conn->query("
  SELECT 
    COUNT(DISTINCT a.id) as jml_anggota,
    COALESCE(SUM(sa.simpanan_pokok),0) as total_pokok,
    COALESCE(SUM(sa.simpanan_wajib),0) as total_wajib,
    COALESCE(SUM(sa.simpanan_sukarela),0) as total_sukarela,
    COALESCE(SUM(sa.total_simpanan),0) as total_all
  FROM ksp_anggota a
  LEFT JOIN ksp_simpanan_anggota sa ON sa.anggota_id = a.id
  WHERE a.status = 'aktif'
")->fetch_assoc();

// Tabungan Harian
$tabungan_summary = $conn->query("
  SELECT COUNT(DISTINCT anggota_id) as jml, COALESCE(SUM(saldo),0) as total
  FROM (
    SELECT anggota_id, SUM(CASE WHEN tipe='setor' THEN nominal ELSE -nominal END) as saldo
    FROM ksp_simpanan_tx
    GROUP BY anggota_id
    HAVING saldo > 0
  ) t
")->fetch_assoc();
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-piggy-bank me-2"></i>Laporan Simpanan Anggota</h4>
  <button class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer me-1"></i> Cetak</button>
</div>

<!-- Summary Cards -->
<div class="row mb-3">
  <div class="col-md-3">
    <div class="card bg-primary text-white">
      <div class="card-body text-center">
        <h5 class="mb-0">Rp <?php echo rupiah($summary['total_pokok']); ?></h5>
        <small>Simpanan Pokok</small>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card bg-success text-white">
      <div class="card-body text-center">
        <h5 class="mb-0">Rp <?php echo rupiah($summary['total_wajib']); ?></h5>
        <small>Simpanan Wajib</small>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card bg-info text-white">
      <div class="card-body text-center">
        <h5 class="mb-0">Rp <?php echo rupiah($summary['total_sukarela']); ?></h5>
        <small>Simpanan Sukarela</small>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card bg-warning text-dark">
      <div class="card-body text-center">
        <h5 class="mb-0">Rp <?php echo rupiah($summary['total_all']); ?></h5>
        <small>Total Semua Simpanan</small>
      </div>
    </div>
  </div>
</div>

<!-- Filter -->
<div class="card card-widget mb-3">
  <div class="card-body">
    <form method="get" class="row g-3 align-items-end">
      <input type="hidden" name="m" value="laporan_simpanan">
      <div class="col-md-4">
        <label class="form-label">Cari Anggota</label>
        <input type="text" name="search" class="form-control" value="<?php echo htmlspecialchars($search); ?>" placeholder="Nama atau No. Anggota">
      </div>
      <div class="col-md-2">
        <button class="btn btn-primary w-100"><i class="bi bi-search me-1"></i> Cari</button>
      </div>
    </form>
  </div>
</div>

<!-- List -->
<div class="card card-widget">
  <div class="card-header bg-light"><strong>Daftar Simpanan per Anggota</strong> (<?php echo $summary['jml_anggota']; ?> anggota aktif)</div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-striped table-sm datatable">
        <thead>
          <tr>
            <th>#</th>
            <th>No. Anggota</th>
            <th>Nama</th>
            <th class="text-end">Simpanan Pokok</th>
            <th class="text-end">Simpanan Wajib</th>
            <th class="text-end">Simpanan Sukarela</th>
            <th class="text-end">Total</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
        <?php $no=1; while($r=$list->fetch_assoc()): ?>
          <tr>
            <td><?php echo $no++; ?></td>
            <td><strong><?php echo $r['no_anggota']; ?></strong></td>
            <td><?php echo htmlspecialchars($r['nama']); ?></td>
            <td class="text-end">Rp <?php echo rupiah($r['simpanan_pokok']); ?></td>
            <td class="text-end">Rp <?php echo rupiah($r['simpanan_wajib']); ?></td>
            <td class="text-end">Rp <?php echo rupiah($r['simpanan_sukarela']); ?></td>
            <td class="text-end"><strong>Rp <?php echo rupiah($r['total_simpanan']); ?></strong></td>
            <td><span class="badge text-bg-<?php echo $r['status']=='aktif'?'success':'secondary'; ?>"><?php echo $r['status']; ?></span></td>
          </tr>
        <?php endwhile; ?>
        </tbody>
        <tfoot class="table-dark">
          <tr>
            <th colspan="3">TOTAL</th>
            <th class="text-end">Rp <?php echo rupiah($summary['total_pokok']); ?></th>
            <th class="text-end">Rp <?php echo rupiah($summary['total_wajib']); ?></th>
            <th class="text-end">Rp <?php echo rupiah($summary['total_sukarela']); ?></th>
            <th class="text-end">Rp <?php echo rupiah($summary['total_all']); ?></th>
            <th></th>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
</div>

<style>
@media print { form, button, .btn { display: none !important; } }
</style>

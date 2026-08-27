<?php
// views/transaksi_keuangan.php - Transaksi Keuangan (Pemasukan/Pengeluaran/Mutasi)
csrf_check();
$conn = db();

// Load master data
$kas_bank_list = $conn->query("SELECT id, kode, nama, tipe, saldo FROM ksp_kas_bank WHERE status='aktif' ORDER BY tipe, nama");
$kas_bank_arr = []; while($k = $kas_bank_list->fetch_assoc()) $kas_bank_arr[] = $k;

$pendapatan_list = $conn->query("SELECT id, kode, nama FROM ksp_master_pendapatan WHERE status='aktif' ORDER BY kode");
$pendapatan_arr = []; while($p = $pendapatan_list->fetch_assoc()) $pendapatan_arr[] = $p;

$biaya_list = $conn->query("SELECT id, kode, nama FROM ksp_master_biaya WHERE status='aktif' ORDER BY kode");
$biaya_arr = []; while($b = $biaya_list->fetch_assoc()) $biaya_arr[] = $b;

// Handle create
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='create') {
  $tipe = $_POST['tipe'];
  $tanggal = $_POST['tanggal'];
  $kas_bank_id = (int)$_POST['kas_bank_id'];
  $nominal = (float)$_POST['nominal'];
  $keterangan = trim($_POST['keterangan'] ?? '');
  $uid = user()['id'];
  
  $kas_bank_tujuan_id = null;
  $pendapatan_id = null;
  $biaya_id = null;
  
  // Generate no transaksi
  $prefix = $tipe == 'pemasukan' ? 'IN' : ($tipe == 'pengeluaran' ? 'OUT' : 'TRF');
  $no_transaksi = $prefix . '-' . date('Ymd') . '-' . str_pad(rand(1,9999), 4, '0', STR_PAD_LEFT);
  
  if ($tipe == 'pemasukan') {
    $pendapatan_id = (int)$_POST['pendapatan_id'];
  } elseif ($tipe == 'pengeluaran') {
    $biaya_id = (int)$_POST['biaya_id'];
  } else { // mutasi
    $kas_bank_tujuan_id = (int)$_POST['kas_bank_tujuan_id'];
    if ($kas_bank_id == $kas_bank_tujuan_id) {
      alert('danger', 'Kas/Bank asal dan tujuan tidak boleh sama.');
      header("Location: index.php?m=transaksi_keuangan"); exit;
    }
  }
  
  $stmt = $conn->prepare("INSERT INTO ksp_transaksi_keuangan(no_transaksi, tanggal, tipe, kas_bank_id, kas_bank_tujuan_id, pendapatan_id, biaya_id, nominal, keterangan, created_by) VALUES(?,?,?,?,?,?,?,?,?,?)");
  $stmt->bind_param("sssiiidssi", $no_transaksi, $tanggal, $tipe, $kas_bank_id, $kas_bank_tujuan_id, $pendapatan_id, $biaya_id, $nominal, $keterangan, $uid);
  
  if ($stmt->execute()) {
    // Update saldo kas/bank
    if ($tipe == 'pemasukan') {
      $conn->query("UPDATE ksp_kas_bank SET saldo = saldo + $nominal WHERE id = $kas_bank_id");
    } elseif ($tipe == 'pengeluaran') {
      $conn->query("UPDATE ksp_kas_bank SET saldo = saldo - $nominal WHERE id = $kas_bank_id");
    } else { // mutasi
      $conn->query("UPDATE ksp_kas_bank SET saldo = saldo - $nominal WHERE id = $kas_bank_id");
      $conn->query("UPDATE ksp_kas_bank SET saldo = saldo + $nominal WHERE id = $kas_bank_tujuan_id");
    }
    alert('success', "Transaksi $no_transaksi berhasil disimpan.");
  } else {
    alert('danger', 'Gagal: '.$conn->error);
  }
  header("Location: index.php?m=transaksi_keuangan"); exit;
}

// Handle delete
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='delete') {
  $id = (int)$_POST['id'];
  $trx = $conn->query("SELECT * FROM ksp_transaksi_keuangan WHERE id=$id")->fetch_assoc();
  
  if ($trx) {
    // Rollback saldo
    if ($trx['tipe'] == 'pemasukan') {
      $conn->query("UPDATE ksp_kas_bank SET saldo = saldo - ".$trx['nominal']." WHERE id = ".$trx['kas_bank_id']);
    } elseif ($trx['tipe'] == 'pengeluaran') {
      $conn->query("UPDATE ksp_kas_bank SET saldo = saldo + ".$trx['nominal']." WHERE id = ".$trx['kas_bank_id']);
    } else { // mutasi
      $conn->query("UPDATE ksp_kas_bank SET saldo = saldo + ".$trx['nominal']." WHERE id = ".$trx['kas_bank_id']);
      $conn->query("UPDATE ksp_kas_bank SET saldo = saldo - ".$trx['nominal']." WHERE id = ".$trx['kas_bank_tujuan_id']);
    }
    $conn->query("DELETE FROM ksp_transaksi_keuangan WHERE id=$id");
    alert('success', 'Transaksi dihapus.');
  }
  header("Location: index.php?m=transaksi_keuangan"); exit;
}

// Filter
$filter_tipe = isset($_GET['tipe']) ? $_GET['tipe'] : '';
$filter_tgl_dari = isset($_GET['tgl_dari']) ? $_GET['tgl_dari'] : date('Y-m-01');
$filter_tgl_sampai = isset($_GET['tgl_sampai']) ? $_GET['tgl_sampai'] : date('Y-m-d');

$where = "WHERE t.tanggal BETWEEN '$filter_tgl_dari' AND '$filter_tgl_sampai'";
if ($filter_tipe) $where .= " AND t.tipe = '$filter_tipe'";

$list = $conn->query("
  SELECT t.*, kb.nama as kas_bank_nama, kb.tipe as kas_bank_tipe,
         kbt.nama as kas_bank_tujuan_nama,
         mp.nama as pendapatan_nama, mb.nama as biaya_nama
  FROM ksp_transaksi_keuangan t
  LEFT JOIN ksp_kas_bank kb ON kb.id = t.kas_bank_id
  LEFT JOIN ksp_kas_bank kbt ON kbt.id = t.kas_bank_tujuan_id
  LEFT JOIN ksp_master_pendapatan mp ON mp.id = t.pendapatan_id
  LEFT JOIN ksp_master_biaya mb ON mb.id = t.biaya_id
  $where
  ORDER BY t.tanggal DESC, t.id DESC
");

// Summary
$sum = $conn->query("
  SELECT 
    SUM(CASE WHEN tipe='pemasukan' THEN nominal ELSE 0 END) as total_masuk,
    SUM(CASE WHEN tipe='pengeluaran' THEN nominal ELSE 0 END) as total_keluar
  FROM ksp_transaksi_keuangan t
  $where
")->fetch_assoc();
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-arrow-left-right me-2"></i>Transaksi Keuangan</h4>
  <div>
    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalPemasukan"><i class="bi bi-arrow-down-circle me-1"></i> Pemasukan</button>
    <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#modalPengeluaran"><i class="bi bi-arrow-up-circle me-1"></i> Pengeluaran</button>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalMutasi"><i class="bi bi-arrow-left-right me-1"></i> Mutasi</button>
  </div>
</div>

<!-- Summary -->
<div class="row mb-3">
  <div class="col-md-4">
    <div class="card bg-success text-white">
      <div class="card-body text-center">
        <h4 class="mb-0">Rp <?php echo rupiah($sum['total_masuk'] ?? 0); ?></h4>
        <small>Total Pemasukan</small>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card bg-danger text-white">
      <div class="card-body text-center">
        <h4 class="mb-0">Rp <?php echo rupiah($sum['total_keluar'] ?? 0); ?></h4>
        <small>Total Pengeluaran</small>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card bg-info text-white">
      <div class="card-body text-center">
        <h4 class="mb-0">Rp <?php echo rupiah(($sum['total_masuk'] ?? 0) - ($sum['total_keluar'] ?? 0)); ?></h4>
        <small>Selisih</small>
      </div>
    </div>
  </div>
</div>

<!-- Filter -->
<div class="card card-widget mb-3">
  <div class="card-body">
    <form method="get" class="row g-3 align-items-end">
      <input type="hidden" name="m" value="transaksi_keuangan">
      <div class="col-md-2">
        <label class="form-label">Dari</label>
        <input type="date" name="tgl_dari" class="form-control" value="<?php echo $filter_tgl_dari; ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label">Sampai</label>
        <input type="date" name="tgl_sampai" class="form-control" value="<?php echo $filter_tgl_sampai; ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label">Tipe</label>
        <select name="tipe" class="form-select">
          <option value="">-- Semua --</option>
          <option value="pemasukan" <?php echo $filter_tipe=='pemasukan'?'selected':''; ?>>Pemasukan</option>
          <option value="pengeluaran" <?php echo $filter_tipe=='pengeluaran'?'selected':''; ?>>Pengeluaran</option>
          <option value="mutasi" <?php echo $filter_tipe=='mutasi'?'selected':''; ?>>Mutasi</option>
        </select>
      </div>
      <div class="col-md-2">
        <button class="btn btn-primary w-100"><i class="bi bi-funnel me-1"></i> Filter</button>
      </div>
    </form>
  </div>
</div>

<!-- List -->
<div class="card card-widget">
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-striped datatable">
        <thead><tr><th>#</th><th>No. Transaksi</th><th>Tanggal</th><th>Tipe</th><th>Kas/Bank</th><th>Kategori</th><th class="text-end">Nominal</th><th>Keterangan</th><th>Aksi</th></tr></thead>
        <tbody>
        <?php $no=1; while($r=$list->fetch_assoc()): ?>
          <tr>
            <td><?php echo $no++; ?></td>
            <td><strong><?php echo $r['no_transaksi']; ?></strong></td>
            <td><?php echo date('d/m/Y', strtotime($r['tanggal'])); ?></td>
            <td>
              <?php if ($r['tipe'] == 'pemasukan'): ?>
              <span class="badge bg-success">📥 Pemasukan</span>
              <?php elseif ($r['tipe'] == 'pengeluaran'): ?>
              <span class="badge bg-danger">📤 Pengeluaran</span>
              <?php else: ?>
              <span class="badge bg-primary">🔄 Mutasi</span>
              <?php endif; ?>
            </td>
            <td>
              <?php echo $r['kas_bank_nama']; ?>
              <?php if ($r['tipe'] == 'mutasi'): ?>
              → <?php echo $r['kas_bank_tujuan_nama']; ?>
              <?php endif; ?>
            </td>
            <td><?php echo $r['pendapatan_nama'] ?? $r['biaya_nama'] ?? '-'; ?></td>
            <td class="text-end"><strong>Rp <?php echo rupiah($r['nominal']); ?></strong></td>
            <td><?php echo htmlspecialchars($r['keterangan'] ?? ''); ?></td>
            <td>
              <form method="post" style="display:inline" id="deleteForm<?php echo $r['id']; ?>">
                <?php echo csrf_input(); ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmHapus('Transaksi', '<?php echo $r['no_transaksi']; ?>', 'deleteForm<?php echo $r['id']; ?>')"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal Pemasukan -->
<div class="modal fade" id="modalPemasukan" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        <?php echo csrf_input(); ?>
        <input type="hidden" name="action" value="create">
        <input type="hidden" name="tipe" value="pemasukan">
        <div class="modal-header bg-success text-white"><h5 class="modal-title"><i class="bi bi-arrow-down-circle me-2"></i>Pemasukan</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-3"><label class="form-label">Tanggal</label><input type="date" name="tanggal" class="form-control" value="<?php echo date('Y-m-d'); ?>" required></div>
          <div class="mb-3">
            <label class="form-label">Kategori Pendapatan</label>
            <select name="pendapatan_id" class="form-select" required>
              <option value="">-- Pilih --</option>
              <?php foreach($pendapatan_arr as $p): ?>
              <option value="<?php echo $p['id']; ?>"><?php echo $p['kode'].' - '.$p['nama']; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Masuk ke Kas/Bank</label>
            <select name="kas_bank_id" class="form-select" required>
              <option value="">-- Pilih --</option>
              <?php foreach($kas_bank_arr as $k): ?>
              <option value="<?php echo $k['id']; ?>">[<?php echo strtoupper($k['tipe']); ?>] <?php echo $k['nama']; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3"><label class="form-label">Nominal</label><input type="number" name="nominal" class="form-control" min="1" step="1000" required></div>
          <div class="mb-3"><label class="form-label">Keterangan</label><input name="keterangan" class="form-control" placeholder="Opsional"></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button class="btn btn-success">Simpan</button></div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Pengeluaran -->
<div class="modal fade" id="modalPengeluaran" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        <?php echo csrf_input(); ?>
        <input type="hidden" name="action" value="create">
        <input type="hidden" name="tipe" value="pengeluaran">
        <div class="modal-header bg-danger text-white"><h5 class="modal-title"><i class="bi bi-arrow-up-circle me-2"></i>Pengeluaran</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-3"><label class="form-label">Tanggal</label><input type="date" name="tanggal" class="form-control" value="<?php echo date('Y-m-d'); ?>" required></div>
          <div class="mb-3">
            <label class="form-label">Kategori Biaya</label>
            <select name="biaya_id" class="form-select" required>
              <option value="">-- Pilih --</option>
              <?php foreach($biaya_arr as $b): ?>
              <option value="<?php echo $b['id']; ?>"><?php echo $b['kode'].' - '.$b['nama']; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Dari Kas/Bank</label>
            <select name="kas_bank_id" class="form-select" required>
              <option value="">-- Pilih --</option>
              <?php foreach($kas_bank_arr as $k): ?>
              <option value="<?php echo $k['id']; ?>">[<?php echo strtoupper($k['tipe']); ?>] <?php echo $k['nama']; ?> (Saldo: Rp <?php echo rupiah($k['saldo']); ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3"><label class="form-label">Nominal</label><input type="number" name="nominal" class="form-control" min="1" step="1000" required></div>
          <div class="mb-3"><label class="form-label">Keterangan</label><input name="keterangan" class="form-control" placeholder="Opsional"></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button class="btn btn-danger">Simpan</button></div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Mutasi -->
<div class="modal fade" id="modalMutasi" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        <?php echo csrf_input(); ?>
        <input type="hidden" name="action" value="create">
        <input type="hidden" name="tipe" value="mutasi">
        <div class="modal-header bg-primary text-white"><h5 class="modal-title"><i class="bi bi-arrow-left-right me-2"></i>Mutasi Kas/Bank</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-3"><label class="form-label">Tanggal</label><input type="date" name="tanggal" class="form-control" value="<?php echo date('Y-m-d'); ?>" required></div>
          <div class="mb-3">
            <label class="form-label">Dari Kas/Bank</label>
            <select name="kas_bank_id" class="form-select" required>
              <option value="">-- Pilih Asal --</option>
              <?php foreach($kas_bank_arr as $k): ?>
              <option value="<?php echo $k['id']; ?>">[<?php echo strtoupper($k['tipe']); ?>] <?php echo $k['nama']; ?> (Saldo: Rp <?php echo rupiah($k['saldo']); ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Ke Kas/Bank</label>
            <select name="kas_bank_tujuan_id" class="form-select" required>
              <option value="">-- Pilih Tujuan --</option>
              <?php foreach($kas_bank_arr as $k): ?>
              <option value="<?php echo $k['id']; ?>">[<?php echo strtoupper($k['tipe']); ?>] <?php echo $k['nama']; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3"><label class="form-label">Nominal</label><input type="number" name="nominal" class="form-control" min="1" step="1000" required></div>
          <div class="mb-3"><label class="form-label">Keterangan</label><input name="keterangan" class="form-control" placeholder="Opsional"></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button class="btn btn-primary">Simpan</button></div>
      </form>
    </div>
  </div>
</div>

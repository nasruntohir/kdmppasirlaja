<?php
// views/satuan.php - Master Satuan Barang & Konversi
csrf_check();
$conn = db();

// Handle create satuan
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='create') {
  $kode = strtoupper(trim($_POST['kode']));
  $nama = trim($_POST['nama']);
  $keterangan = trim($_POST['keterangan'] ?? '');
  
  $stmt = $conn->prepare("INSERT INTO ksp_satuan(kode,nama,keterangan) VALUES(?,?,?)");
  $stmt->bind_param("sss", $kode, $nama, $keterangan);
  if ($stmt->execute()) alert('success','Satuan ditambah.'); 
  else alert('danger','Gagal: '.$conn->error);
  header("Location: index.php?m=satuan"); exit;
}

// Handle update satuan
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='update') {
  $id = (int)$_POST['id'];
  $nama = trim($_POST['nama']);
  $keterangan = trim($_POST['keterangan'] ?? '');
  $status = trim($_POST['status'] ?? 'aktif');
  
  $stmt = $conn->prepare("UPDATE ksp_satuan SET nama=?, keterangan=?, status=? WHERE id=?");
  $stmt->bind_param("sssi", $nama, $keterangan, $status, $id);
  if ($stmt->execute()) alert('success', 'Satuan diperbarui.');
  else alert('danger', 'Gagal update: '.$conn->error);
  header("Location: index.php?m=satuan"); exit;
}

// Handle delete satuan
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='delete') {
  $id = (int)$_POST['id'];
  $stmt = $conn->prepare("DELETE FROM ksp_satuan WHERE id=?");
  $stmt->bind_param("i", $id);
  if ($stmt->execute()) alert('success', 'Satuan dihapus.');
  else alert('danger', 'Gagal menghapus: Satuan mungkin masih digunakan.');
  header("Location: index.php?m=satuan"); exit;
}

// Handle create konversi
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='create_konversi') {
  $dari = (int)$_POST['satuan_dari_id'];
  $ke = (int)$_POST['satuan_ke_id'];
  $nilai = (float)$_POST['nilai_konversi'];
  $ket = trim($_POST['keterangan'] ?? '');
  
  if ($dari == $ke) {
    alert('danger', 'Satuan asal dan tujuan tidak boleh sama.');
  } else {
    $stmt = $conn->prepare("INSERT INTO ksp_konversi_satuan(satuan_dari_id, satuan_ke_id, nilai_konversi, keterangan) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE nilai_konversi=VALUES(nilai_konversi), keterangan=VALUES(keterangan)");
    $stmt->bind_param("iids", $dari, $ke, $nilai, $ket);
    if ($stmt->execute()) alert('success','Konversi disimpan.'); 
    else alert('danger','Gagal: '.$conn->error);
  }
  header("Location: index.php?m=satuan"); exit;
}

// Handle delete konversi
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='delete_konversi') {
  $id = (int)$_POST['id'];
  $stmt = $conn->prepare("DELETE FROM ksp_konversi_satuan WHERE id=?");
  $stmt->bind_param("i", $id);
  if ($stmt->execute()) alert('success', 'Konversi dihapus.');
  else alert('danger', 'Gagal menghapus.');
  header("Location: index.php?m=satuan"); exit;
}

$list = $conn->query("SELECT * FROM ksp_satuan ORDER BY kode");
$konversi_list = $conn->query("
  SELECT k.*, sd.kode as kode_dari, sd.nama as nama_dari, sk.kode as kode_ke, sk.nama as nama_ke
  FROM ksp_konversi_satuan k
  JOIN ksp_satuan sd ON sd.id = k.satuan_dari_id
  JOIN ksp_satuan sk ON sk.id = k.satuan_ke_id
  ORDER BY sd.kode, sk.kode
");
$satuan_options = $conn->query("SELECT id, kode, nama FROM ksp_satuan WHERE status='aktif' ORDER BY kode");
$satuan_arr = [];
while ($s = $satuan_options->fetch_assoc()) $satuan_arr[] = $s;
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-rulers me-2"></i>Master Satuan & Konversi</h4>
  <div>
    <button class="btn btn-gradient me-1" data-bs-toggle="collapse" data-bs-target="#formAdd"><i class="bi bi-plus-lg me-1"></i> Tambah Satuan</button>
    <button class="btn btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#formKonversi"><i class="bi bi-arrow-left-right me-1"></i> Konversi</button>
  </div>
</div>

<div id="formAdd" class="collapse mb-3">
  <div class="card card-widget">
    <div class="card-header bg-light"><strong>Tambah Satuan Baru</strong></div>
    <div class="card-body">
      <form method="post">
        <?php echo csrf_input(); ?>
        <input type="hidden" name="action" value="create">
        <div class="row g-3">
          <div class="col-md-2"><label class="form-label">Kode</label><input name="kode" class="form-control text-uppercase" required placeholder="KG" maxlength="20"></div>
          <div class="col-md-4"><label class="form-label">Nama</label><input name="nama" class="form-control" required placeholder="Kilogram"></div>
          <div class="col-md-6"><label class="form-label">Keterangan</label><input name="keterangan" class="form-control" placeholder="Keterangan (opsional)"></div>
        </div>
        <div class="mt-3"><button class="btn btn-gradient">Simpan</button></div>
      </form>
    </div>
  </div>
</div>

<div id="formKonversi" class="collapse mb-3">
  <div class="card card-widget border-primary">
    <div class="card-header bg-primary text-white"><strong><i class="bi bi-arrow-left-right me-1"></i> Tambah/Edit Konversi Satuan</strong></div>
    <div class="card-body">
      <form method="post">
        <?php echo csrf_input(); ?>
        <input type="hidden" name="action" value="create_konversi">
        <div class="row g-3 align-items-end">
          <div class="col-md-1 text-center"><span class="fs-5">1</span></div>
          <div class="col-md-2">
            <label class="form-label">Satuan Besar</label>
            <select name="satuan_dari_id" class="form-select" required>
              <option value="">-- Pilih --</option>
              <?php foreach($satuan_arr as $s): ?>
              <option value="<?php echo $s['id']; ?>"><?php echo $s['kode'].' - '.$s['nama']; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-1 text-center"><span class="fs-5">=</span></div>
          <div class="col-md-2">
            <label class="form-label">Nilai Konversi</label>
            <input name="nilai_konversi" type="number" step="0.0001" class="form-control" required placeholder="50" min="0.0001">
          </div>
          <div class="col-md-2">
            <label class="form-label">Satuan Kecil</label>
            <select name="satuan_ke_id" class="form-select" required>
              <option value="">-- Pilih --</option>
              <?php foreach($satuan_arr as $s): ?>
              <option value="<?php echo $s['id']; ?>"><?php echo $s['kode'].' - '.$s['nama']; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Keterangan</label>
            <input name="keterangan" class="form-control" placeholder="Contoh: 1 SAK = 50 KG">
          </div>
          <div class="col-md-1"><button class="btn btn-primary w-100">Simpan</button></div>
        </div>
        <small class="text-muted mt-2 d-block">Contoh: 1 SAK = 50 KG, berarti satuan besar = SAK, nilai = 50, satuan kecil = KG</small>
      </form>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-md-6">
    <div class="card card-widget">
      <div class="card-header bg-light"><strong><i class="bi bi-rulers me-1"></i> Daftar Satuan</strong></div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-striped table-sm">
            <thead><tr><th>#</th><th>Kode</th><th>Nama</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
            <?php $no=1; while($r=$list->fetch_assoc()): ?>
              <tr>
                <td><?php echo $no++; ?></td>
                <td><strong><?php echo htmlspecialchars($r['kode']); ?></strong></td>
                <td><?php echo htmlspecialchars($r['nama']); ?></td>
                <td><span class="badge text-bg-<?php echo $r['status']=='aktif'?'success':'secondary'; ?>"><?php echo $r['status']; ?></span></td>
                <td>
                  <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editSat<?php echo $r['id']; ?>"><i class="bi bi-pencil"></i></button>
                  <form method="post" style="display:inline" id="deleteFormSat<?php echo $r['id']; ?>">
                    <?php echo csrf_input(); ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmHapus('<?php echo htmlspecialchars($r['nama'], ENT_QUOTES); ?>', '<?php echo $r['kode']; ?>', 'deleteFormSat<?php echo $r['id']; ?>')"><i class="bi bi-trash"></i></button>
                  </form>
                  
                  <!-- Edit Modal -->
                  <div class="modal fade" id="editSat<?php echo $r['id']; ?>" tabindex="-1">
                    <div class="modal-dialog modal-sm">
                      <div class="modal-content">
                        <form method="post">
                          <?php echo csrf_input(); ?>
                          <input type="hidden" name="action" value="update">
                          <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                          <div class="modal-header"><h5 class="modal-title">Edit Satuan</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                          <div class="modal-body">
                            <div class="mb-3"><label class="form-label">Kode</label><input class="form-control" value="<?php echo htmlspecialchars($r['kode']); ?>" disabled></div>
                            <div class="mb-3"><label class="form-label">Nama</label><input name="nama" class="form-control" value="<?php echo htmlspecialchars($r['nama']); ?>" required></div>
                            <div class="mb-3"><label class="form-label">Keterangan</label><input name="keterangan" class="form-control" value="<?php echo htmlspecialchars($r['keterangan'] ?? ''); ?>"></div>
                            <div class="mb-3"><label class="form-label">Status</label>
                              <select name="status" class="form-select">
                                <option value="aktif" <?php echo $r['status']=='aktif'?'selected':''; ?>>Aktif</option>
                                <option value="nonaktif" <?php echo $r['status']=='nonaktif'?'selected':''; ?>>Non-Aktif</option>
                              </select>
                            </div>
                          </div>
                          <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button class="btn btn-gradient">Simpan</button></div>
                        </form>
                      </div>
                    </div>
                  </div>
                </td>
              </tr>
            <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
  
  <div class="col-md-6">
    <div class="card card-widget">
      <div class="card-header bg-light"><strong><i class="bi bi-arrow-left-right me-1"></i> Tabel Konversi</strong></div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-striped table-sm">
            <thead><tr><th>Dari</th><th></th><th>Ke</th><th>Nilai</th><th>Aksi</th></tr></thead>
            <tbody>
            <?php while($k=$konversi_list->fetch_assoc()): ?>
              <tr>
                <td><strong>1 <?php echo $k['kode_dari']; ?></strong></td>
                <td>=</td>
                <td><strong><?php echo number_format($k['nilai_konversi'],2); ?> <?php echo $k['kode_ke']; ?></strong></td>
                <td><small class="text-muted"><?php echo $k['keterangan'] ?? ''; ?></small></td>
                <td>
                  <form method="post" style="display:inline" id="deleteFormKonv<?php echo $k['id']; ?>">
                    <?php echo csrf_input(); ?>
                    <input type="hidden" name="action" value="delete_konversi">
                    <input type="hidden" name="id" value="<?php echo $k['id']; ?>">
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="if(confirm('Hapus konversi ini?')) document.getElementById('deleteFormKonv<?php echo $k['id']; ?>').submit()"><i class="bi bi-trash"></i></button>
                  </form>
                </td>
              </tr>
            <?php endwhile; ?>
            <?php if ($konversi_list->num_rows == 0): ?>
              <tr><td colspan="5" class="text-center text-muted">Belum ada konversi. Klik tombol "Konversi" untuk menambahkan.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

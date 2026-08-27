<?php
// views/master_biaya.php - Master Biaya
csrf_check();
$conn = db();

// Handle create
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='create') {
  $kode = trim($_POST['kode']);
  $nama = trim($_POST['nama']);
  $keterangan = trim($_POST['keterangan'] ?? '');
  
  $stmt = $conn->prepare("INSERT INTO ksp_master_biaya(kode, nama, keterangan) VALUES(?,?,?)");
  $stmt->bind_param("sss", $kode, $nama, $keterangan);
  if ($stmt->execute()) alert('success', 'Biaya berhasil ditambahkan.');
  else alert('danger', 'Gagal: '.$conn->error);
  header("Location: index.php?m=master_biaya"); exit;
}

// Handle update
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='update') {
  $id = (int)$_POST['id'];
  $kode = trim($_POST['kode']);
  $nama = trim($_POST['nama']);
  $keterangan = trim($_POST['keterangan'] ?? '');
  $status = $_POST['status'];
  
  $stmt = $conn->prepare("UPDATE ksp_master_biaya SET kode=?, nama=?, keterangan=?, status=? WHERE id=?");
  $stmt->bind_param("ssssi", $kode, $nama, $keterangan, $status, $id);
  if ($stmt->execute()) alert('success', 'Biaya berhasil diupdate.');
  else alert('danger', 'Gagal: '.$conn->error);
  header("Location: index.php?m=master_biaya"); exit;
}

// Handle delete
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='delete') {
  $id = (int)$_POST['id'];
  $check = $conn->query("SELECT COUNT(*) as cnt FROM ksp_transaksi_keuangan WHERE biaya_id=$id")->fetch_assoc();
  if ($check['cnt'] > 0) {
    alert('danger', 'Tidak bisa dihapus, sudah ada transaksi terkait.');
  } else {
    $conn->query("DELETE FROM ksp_master_biaya WHERE id=$id");
    alert('success', 'Biaya berhasil dihapus.');
  }
  header("Location: index.php?m=master_biaya"); exit;
}

$list = $conn->query("SELECT * FROM ksp_master_biaya ORDER BY kode");
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-arrow-up-circle me-2"></i>Master Biaya</h4>
  <button class="btn btn-gradient" data-bs-toggle="collapse" data-bs-target="#formAdd"><i class="bi bi-plus-lg me-1"></i> Tambah</button>
</div>

<div id="formAdd" class="collapse mb-3">
  <div class="card card-widget">
    <div class="card-body">
      <form method="post">
        <?php echo csrf_input(); ?>
        <input type="hidden" name="action" value="create">
        <div class="row g-3">
          <div class="col-md-2"><label class="form-label">Kode</label><input name="kode" class="form-control" required placeholder="BYA001"></div>
          <div class="col-md-4"><label class="form-label">Nama Biaya</label><input name="nama" class="form-control" required placeholder="Biaya Gaji"></div>
          <div class="col-md-6"><label class="form-label">Keterangan</label><input name="keterangan" class="form-control" placeholder="Keterangan (opsional)"></div>
        </div>
        <div class="mt-3"><button class="btn btn-gradient">Simpan</button></div>
      </form>
    </div>
  </div>
</div>

<div class="card card-widget">
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-striped datatable">
        <thead><tr><th>#</th><th>Kode</th><th>Nama</th><th>Keterangan</th><th>Status</th><th>Aksi</th></tr></thead>
        <tbody>
        <?php $no=1; while($r=$list->fetch_assoc()): ?>
          <tr>
            <td><?php echo $no++; ?></td>
            <td><strong><?php echo htmlspecialchars($r['kode']); ?></strong></td>
            <td><?php echo htmlspecialchars($r['nama']); ?></td>
            <td><?php echo htmlspecialchars($r['keterangan'] ?? '-'); ?></td>
            <td><span class="badge text-bg-<?php echo $r['status']=='aktif'?'success':'secondary'; ?>"><?php echo $r['status']; ?></span></td>
            <td>
              <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $r['id']; ?>"><i class="bi bi-pencil"></i></button>
              <form method="post" style="display:inline" id="deleteForm<?php echo $r['id']; ?>">
                <?php echo csrf_input(); ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmHapus('Biaya', '<?php echo $r['nama']; ?>', 'deleteForm<?php echo $r['id']; ?>')"><i class="bi bi-trash"></i></button>
              </form>
              
              <div class="modal fade" id="editModal<?php echo $r['id']; ?>" tabindex="-1">
                <div class="modal-dialog">
                  <div class="modal-content">
                    <form method="post">
                      <?php echo csrf_input(); ?>
                      <input type="hidden" name="action" value="update">
                      <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                      <div class="modal-header"><h5 class="modal-title">Edit Biaya</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                      <div class="modal-body">
                        <div class="mb-3"><label class="form-label">Kode</label><input name="kode" class="form-control" value="<?php echo $r['kode']; ?>" required></div>
                        <div class="mb-3"><label class="form-label">Nama</label><input name="nama" class="form-control" value="<?php echo $r['nama']; ?>" required></div>
                        <div class="mb-3"><label class="form-label">Keterangan</label><input name="keterangan" class="form-control" value="<?php echo $r['keterangan']; ?>"></div>
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

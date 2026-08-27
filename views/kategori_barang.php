<?php
// views/kategori_barang.php - Master Kategori Barang
csrf_check();
$conn = db();

// Handle create
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='create') {
  $kode = trim($_POST['kode']);
  $nama = trim($_POST['nama']);
  $keterangan = trim($_POST['keterangan'] ?? '');
  
  $stmt = $conn->prepare("INSERT INTO ksp_kategori_barang(kode,nama,keterangan) VALUES(?,?,?)");
  $stmt->bind_param("sss", $kode, $nama, $keterangan);
  if ($stmt->execute()) alert('success','Kategori ditambah.'); 
  else alert('danger','Gagal: '.$conn->error);
  header("Location: index.php?m=kategori_barang"); exit;
}

// Handle update
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='update') {
  $id = (int)$_POST['id'];
  $nama = trim($_POST['nama']);
  $keterangan = trim($_POST['keterangan'] ?? '');
  $status = trim($_POST['status'] ?? 'aktif');
  
  $stmt = $conn->prepare("UPDATE ksp_kategori_barang SET nama=?, keterangan=?, status=? WHERE id=?");
  $stmt->bind_param("sssi", $nama, $keterangan, $status, $id);
  if ($stmt->execute()) alert('success', 'Kategori diperbarui.');
  else alert('danger', 'Gagal update: '.$conn->error);
  header("Location: index.php?m=kategori_barang"); exit;
}

// Handle delete
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='delete') {
  $id = (int)$_POST['id'];
  // Cek apakah kategori dipakai
  $check = $conn->query("SELECT COUNT(*) c FROM ksp_produk_barang WHERE kategori_id=$id")->fetch_assoc();
  if ($check['c'] > 0) {
    alert('danger', 'Kategori tidak dapat dihapus karena masih digunakan oleh produk.');
  } else {
    $stmt = $conn->prepare("DELETE FROM ksp_kategori_barang WHERE id=?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) alert('success', 'Kategori dihapus.');
    else alert('danger', 'Gagal menghapus.');
  }
  header("Location: index.php?m=kategori_barang"); exit;
}

$list = $conn->query("SELECT * FROM ksp_kategori_barang ORDER BY kode");
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-tags me-2"></i>Master Kategori Barang</h4>
  <button class="btn btn-gradient" data-bs-toggle="collapse" data-bs-target="#formAdd"><i class="bi bi-plus-lg me-1"></i> Tambah</button>
</div>

<div id="formAdd" class="collapse mb-3">
  <div class="card card-widget">
    <div class="card-body">
      <form method="post">
        <?php echo csrf_input(); ?>
        <input type="hidden" name="action" value="create">
        <div class="row g-3">
          <div class="col-md-2"><label class="form-label">Kode</label><input name="kode" class="form-control" required placeholder="KAT001"></div>
          <div class="col-md-4"><label class="form-label">Nama Kategori</label><input name="nama" class="form-control" required placeholder="Pupuk"></div>
          <div class="col-md-6"><label class="form-label">Keterangan</label><input name="keterangan" class="form-control" placeholder="Keterangan kategori (opsional)"></div>
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
            <td><?php echo htmlspecialchars($r['kode']); ?></td>
            <td><?php echo htmlspecialchars($r['nama']); ?></td>
            <td><?php echo htmlspecialchars($r['keterangan'] ?? '-'); ?></td>
            <td><span class="badge text-bg-<?php echo $r['status']=='aktif'?'success':'secondary'; ?>"><?php echo $r['status']; ?></span></td>
            <td>
              <button class="btn btn-sm btn-outline-primary me-1" data-bs-toggle="modal" data-bs-target="#editKat<?php echo $r['id']; ?>"><i class="bi bi-pencil"></i></button>
              <form method="post" style="display:inline" id="deleteFormKat<?php echo $r['id']; ?>">
                <?php echo csrf_input(); ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmHapus('<?php echo htmlspecialchars($r['nama'], ENT_QUOTES); ?>', '<?php echo $r['kode']; ?>', 'deleteFormKat<?php echo $r['id']; ?>')"><i class="bi bi-trash"></i></button>
              </form>
              
              <!-- Edit Modal -->
              <div class="modal fade" id="editKat<?php echo $r['id']; ?>" tabindex="-1">
                <div class="modal-dialog">
                  <div class="modal-content">
                    <form method="post">
                      <?php echo csrf_input(); ?>
                      <input type="hidden" name="action" value="update">
                      <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                      <div class="modal-header"><h5 class="modal-title">Edit Kategori</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
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

<?php
// views/supplier.php - Master Supplier
csrf_check();
$conn = db();

// Handle create
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='create') {
  $kode = trim($_POST['kode']);
  $nama = trim($_POST['nama']);
  $alamat = trim($_POST['alamat'] ?? '');
  $telepon = trim($_POST['telepon'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $kontak_person = trim($_POST['kontak_person'] ?? '');
  $keterangan = trim($_POST['keterangan'] ?? '');
  
  $stmt = $conn->prepare("INSERT INTO ksp_supplier(kode, nama, alamat, telepon, email, kontak_person, keterangan) VALUES(?,?,?,?,?,?,?)");
  $stmt->bind_param("sssssss", $kode, $nama, $alamat, $telepon, $email, $kontak_person, $keterangan);
  if ($stmt->execute()) alert('success', 'Supplier berhasil ditambahkan.');
  else alert('danger', 'Gagal: '.$conn->error);
  header("Location: index.php?m=supplier"); exit;
}

// Handle update
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='update') {
  $id = (int)$_POST['id'];
  $kode = trim($_POST['kode']);
  $nama = trim($_POST['nama']);
  $alamat = trim($_POST['alamat'] ?? '');
  $telepon = trim($_POST['telepon'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $kontak_person = trim($_POST['kontak_person'] ?? '');
  $keterangan = trim($_POST['keterangan'] ?? '');
  $status = $_POST['status'];
  
  $stmt = $conn->prepare("UPDATE ksp_supplier SET kode=?, nama=?, alamat=?, telepon=?, email=?, kontak_person=?, keterangan=?, status=? WHERE id=?");
  $stmt->bind_param("ssssssssi", $kode, $nama, $alamat, $telepon, $email, $kontak_person, $keterangan, $status, $id);
  if ($stmt->execute()) alert('success', 'Supplier berhasil diupdate.');
  else alert('danger', 'Gagal: '.$conn->error);
  header("Location: index.php?m=supplier"); exit;
}

// Handle delete
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='delete') {
  $id = (int)$_POST['id'];
  // Check if used in pembelian
  $check = $conn->query("SELECT COUNT(*) as cnt FROM ksp_pembelian WHERE supplier_id=$id")->fetch_assoc();
  if ($check['cnt'] > 0) {
    alert('danger', 'Supplier tidak bisa dihapus karena sudah ada transaksi pembelian.');
  } else {
    $conn->query("DELETE FROM ksp_supplier WHERE id=$id");
    alert('success', 'Supplier berhasil dihapus.');
  }
  header("Location: index.php?m=supplier"); exit;
}

$list = $conn->query("SELECT * FROM ksp_supplier ORDER BY nama");
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-truck me-2"></i>Master Supplier</h4>
  <button class="btn btn-gradient" data-bs-toggle="collapse" data-bs-target="#formAdd"><i class="bi bi-plus-lg me-1"></i> Tambah</button>
</div>

<div id="formAdd" class="collapse mb-3">
  <div class="card card-widget">
    <div class="card-body">
      <form method="post">
        <?php echo csrf_input(); ?>
        <input type="hidden" name="action" value="create">
        <div class="row g-3">
          <div class="col-md-2"><label class="form-label">Kode</label><input name="kode" class="form-control" required placeholder="SUP001"></div>
          <div class="col-md-4"><label class="form-label">Nama Supplier</label><input name="nama" class="form-control" required placeholder="PT Supplier ABC"></div>
          <div class="col-md-3"><label class="form-label">Telepon</label><input name="telepon" class="form-control" placeholder="08123456789"></div>
          <div class="col-md-3"><label class="form-label">Email</label><input name="email" type="email" class="form-control" placeholder="email@supplier.com"></div>
        </div>
        <div class="row g-3 mt-1">
          <div class="col-md-3"><label class="form-label">Kontak Person</label><input name="kontak_person" class="form-control" placeholder="Nama PIC"></div>
          <div class="col-md-4"><label class="form-label">Alamat</label><input name="alamat" class="form-control" placeholder="Alamat lengkap"></div>
          <div class="col-md-5"><label class="form-label">Keterangan</label><input name="keterangan" class="form-control" placeholder="Keterangan (opsional)"></div>
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
        <thead><tr><th>#</th><th>Kode</th><th>Nama</th><th>Telepon</th><th>Email</th><th>Kontak</th><th>Status</th><th>Aksi</th></tr></thead>
        <tbody>
        <?php $no=1; while($r=$list->fetch_assoc()): ?>
          <tr>
            <td><?php echo $no++; ?></td>
            <td><strong><?php echo htmlspecialchars($r['kode']); ?></strong></td>
            <td><?php echo htmlspecialchars($r['nama']); ?></td>
            <td><?php echo htmlspecialchars($r['telepon'] ?? '-'); ?></td>
            <td><?php echo htmlspecialchars($r['email'] ?? '-'); ?></td>
            <td><?php echo htmlspecialchars($r['kontak_person'] ?? '-'); ?></td>
            <td><span class="badge text-bg-<?php echo $r['status']=='aktif'?'success':'secondary'; ?>"><?php echo $r['status']; ?></span></td>
            <td>
              <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editSupplier<?php echo $r['id']; ?>"><i class="bi bi-pencil"></i></button>
              <form method="post" style="display:inline" id="deleteFormSup<?php echo $r['id']; ?>">
                <?php echo csrf_input(); ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmHapus('Supplier', '<?php echo $r['nama']; ?>', 'deleteFormSup<?php echo $r['id']; ?>')"><i class="bi bi-trash"></i></button>
              </form>
              
              <!-- Edit Modal -->
              <div class="modal fade" id="editSupplier<?php echo $r['id']; ?>" tabindex="-1">
                <div class="modal-dialog modal-lg">
                  <div class="modal-content">
                    <form method="post">
                      <?php echo csrf_input(); ?>
                      <input type="hidden" name="action" value="update">
                      <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                      <div class="modal-header"><h5 class="modal-title">Edit Supplier</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                      <div class="modal-body">
                        <div class="row g-3">
                          <div class="col-md-3"><label class="form-label">Kode</label><input name="kode" class="form-control" value="<?php echo $r['kode']; ?>" required></div>
                          <div class="col-md-5"><label class="form-label">Nama</label><input name="nama" class="form-control" value="<?php echo $r['nama']; ?>" required></div>
                          <div class="col-md-4"><label class="form-label">Status</label>
                            <select name="status" class="form-select">
                              <option value="aktif" <?php echo $r['status']=='aktif'?'selected':''; ?>>Aktif</option>
                              <option value="nonaktif" <?php echo $r['status']=='nonaktif'?'selected':''; ?>>Non-Aktif</option>
                            </select>
                          </div>
                        </div>
                        <div class="row g-3 mt-1">
                          <div class="col-md-4"><label class="form-label">Telepon</label><input name="telepon" class="form-control" value="<?php echo $r['telepon']; ?>"></div>
                          <div class="col-md-4"><label class="form-label">Email</label><input name="email" type="email" class="form-control" value="<?php echo $r['email']; ?>"></div>
                          <div class="col-md-4"><label class="form-label">Kontak Person</label><input name="kontak_person" class="form-control" value="<?php echo $r['kontak_person']; ?>"></div>
                        </div>
                        <div class="row g-3 mt-1">
                          <div class="col-md-6"><label class="form-label">Alamat</label><textarea name="alamat" class="form-control" rows="2"><?php echo $r['alamat']; ?></textarea></div>
                          <div class="col-md-6"><label class="form-label">Keterangan</label><textarea name="keterangan" class="form-control" rows="2"><?php echo $r['keterangan']; ?></textarea></div>
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

<?php
// views/kelompok.php - Master Kelompok Anggota
csrf_check();
$conn = db();

// Handle create
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='create') {
  $kode = trim($_POST['kode']);
  $nama = trim($_POST['nama']);
  $keterangan = trim($_POST['keterangan']);
  $stmt = $conn->prepare("INSERT INTO ksp_kelompok(kode, nama, keterangan) VALUES(?,?,?)");
  $stmt->bind_param("sss", $kode, $nama, $keterangan);
  if ($stmt->execute()) alert('success', 'Kelompok ditambahkan.');
  else alert('danger', 'Gagal: '.$conn->error);
  header("Location: index.php?m=kelompok"); exit;
}

// Handle update
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='update') {
  $id = (int)$_POST['id'];
  $nama = trim($_POST['nama']);
  $keterangan = trim($_POST['keterangan']);
  $status = in_array($_POST['status'], ['aktif','nonaktif']) ? $_POST['status'] : 'aktif';
  $stmt = $conn->prepare("UPDATE ksp_kelompok SET nama=?, keterangan=?, status=? WHERE id=?");
  $stmt->bind_param("sssi", $nama, $keterangan, $status, $id);
  if ($stmt->execute()) alert('success', 'Kelompok diperbarui.');
  else alert('danger', 'Gagal update: '.$conn->error);
  header("Location: index.php?m=kelompok"); exit;
}

// Handle delete
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='delete') {
  $id = (int)$_POST['id'];
  // Check if kelompok has members
  $check = $conn->query("SELECT COUNT(*) c FROM ksp_anggota WHERE kelompok_id=".$id);
  $has_anggota = $check ? (int)$check->fetch_assoc()['c'] : 0;
  if ($has_anggota > 0) {
    alert('danger', 'Tidak bisa hapus kelompok yang masih memiliki anggota.');
  } else {
    $stmt = $conn->prepare("DELETE FROM ksp_kelompok WHERE id=?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) alert('success', 'Kelompok dihapus.');
    else alert('danger', 'Gagal menghapus kelompok.');
  }
  header("Location: index.php?m=kelompok"); exit;
}

// Get kelompok list with member count and ketua info
$list = $conn->query("
  SELECT k.*,
    (SELECT COUNT(*) FROM ksp_anggota WHERE kelompok_id = k.id) as jumlah_anggota,
    (SELECT nama FROM ksp_anggota WHERE kelompok_id = k.id AND is_ketua = 1 LIMIT 1) as nama_ketua
  FROM ksp_kelompok k
  ORDER BY k.id DESC
");
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-people me-2"></i>Master Kelompok</h4>
  <button class="btn btn-gradient" data-bs-toggle="collapse" data-bs-target="#formAdd"><i class="bi bi-plus-lg me-1"></i> Tambah</button>
</div>

<div id="formAdd" class="collapse mb-3">
  <div class="card card-widget">
    <div class="card-body">
      <form method="post">
        <?php echo csrf_input(); ?>
        <input type="hidden" name="action" value="create">
        <div class="row g-3">
          <div class="col-md-3">
            <label class="form-label">Kode Kelompok</label>
            <input name="kode" class="form-control" required placeholder="e.g. KEL01">
          </div>
          <div class="col-md-4">
            <label class="form-label">Nama Kelompok</label>
            <input name="nama" class="form-control" required placeholder="e.g. Kelompok Mawar">
          </div>
          <div class="col-md-5">
            <label class="form-label">Keterangan</label>
            <input name="keterangan" class="form-control" placeholder="Opsional">
          </div>
        </div>
        <div class="mt-3">
          <button type="submit" class="btn btn-gradient" onclick="return confirmSimpan(this.form, 'Tambah Kelompok Baru')">Simpan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="card card-widget">
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-striped datatable">
        <thead>
          <tr>
            <th>#</th>
            <th>Kode</th>
            <th>Nama Kelompok</th>
            <th>Ketua</th>
            <th>Jml Anggota</th>
            <th>Keterangan</th>
            <th>Status</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
        <?php $no=1; while($r=$list->fetch_assoc()): ?>
          <tr>
            <td><?php echo $no++; ?></td>
            <td><strong><?php echo htmlspecialchars($r['kode']); ?></strong></td>
            <td><?php echo htmlspecialchars($r['nama']); ?></td>
            <td>
              <?php if ($r['nama_ketua']): ?>
                <span class="badge bg-primary"><i class="bi bi-star-fill me-1"></i><?php echo htmlspecialchars($r['nama_ketua']); ?></span>
              <?php else: ?>
                <span class="text-muted">Belum ada</span>
              <?php endif; ?>
            </td>
            <td><span class="badge bg-info"><?php echo $r['jumlah_anggota']; ?> orang</span></td>
            <td><small><?php echo htmlspecialchars($r['keterangan']); ?></small></td>
            <td>
              <span class="badge <?php echo $r['status']=='aktif' ? 'bg-success' : 'bg-secondary'; ?>">
                <?php echo strtoupper($r['status']); ?>
              </span>
            </td>
            <td>
              <button class="btn btn-sm btn-outline-primary me-1" data-bs-toggle="modal" data-bs-target="#editKelompok<?php echo $r['id']; ?>">
                <i class="bi bi-pencil"></i>
              </button>
              <form method="post" style="display:inline" id="deleteFormKel<?php echo $r['id']; ?>">
                <?php echo csrf_input(); ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmHapus('<?php echo htmlspecialchars($r['nama'], ENT_QUOTES); ?>', '<?php echo $r['kode']; ?>', 'deleteFormKel<?php echo $r['id']; ?>')">
                  <i class="bi bi-trash"></i>
                </button>
              </form>
              
              <!-- Edit Modal -->
              <div class="modal fade" id="editKelompok<?php echo $r['id']; ?>" tabindex="-1">
                <div class="modal-dialog">
                  <div class="modal-content">
                    <form method="post">
                      <?php echo csrf_input(); ?>
                      <input type="hidden" name="action" value="update">
                      <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                      <div class="modal-header">
                        <h5 class="modal-title">Edit Kelompok</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                      </div>
                      <div class="modal-body">
                        <div class="mb-3">
                          <label class="form-label">Kode</label>
                          <input class="form-control" value="<?php echo htmlspecialchars($r['kode']); ?>" disabled>
                        </div>
                        <div class="mb-3">
                          <label class="form-label">Nama Kelompok</label>
                          <input name="nama" class="form-control" value="<?php echo htmlspecialchars($r['nama']); ?>" required>
                        </div>
                        <div class="mb-3">
                          <label class="form-label">Keterangan</label>
                          <input name="keterangan" class="form-control" value="<?php echo htmlspecialchars($r['keterangan']); ?>">
                        </div>
                        <div class="mb-3">
                          <label class="form-label">Status</label>
                          <select name="status" class="form-select">
                            <option value="aktif" <?php echo $r['status']=='aktif'?'selected':''; ?>>Aktif</option>
                            <option value="nonaktif" <?php echo $r['status']=='nonaktif'?'selected':''; ?>>Nonaktif</option>
                          </select>
                        </div>
                      </div>
                      <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-gradient" onclick="return confirmSimpan(this.form, 'Update Kelompok')">Simpan</button>
                      </div>
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

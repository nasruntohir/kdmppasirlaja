<?php
// views/cabang.php - Multi-Cabang Management
require_login();

if (user()['role'] !== 'admin') {
  alert('danger', 'Akses ditolak.');
  header('Location: index.php?m=dashboard');
  exit;
}

$conn = db();

// Handle create
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah'])) {
  $kode = trim($_POST['kode']);
  $nama = trim($_POST['nama']);
  $alamat = trim($_POST['alamat']);
  $telp = trim($_POST['telp']);
  
  $stmt = $conn->prepare("INSERT INTO ksp_cabang (kode, nama, alamat, telp) VALUES (?, ?, ?, ?)");
  $stmt->bind_param("ssss", $kode, $nama, $alamat, $telp);
  if ($stmt->execute()) {
    log_activity('create_cabang', 'cabang', 'Created branch: ' . $nama);
    alert('success', 'Cabang berhasil ditambahkan.');
  } else {
    alert('danger', 'Gagal menambahkan cabang. Kode mungkin sudah ada.');
  }
  header('Location: index.php?m=cabang');
  exit;
}

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
  $id = (int)$_POST['id'];
  $kode = trim($_POST['kode']);
  $nama = trim($_POST['nama']);
  $alamat = trim($_POST['alamat']);
  $telp = trim($_POST['telp']);
  $status = $_POST['status'];
  
  $stmt = $conn->prepare("UPDATE ksp_cabang SET kode=?, nama=?, alamat=?, telp=?, status=? WHERE id=?");
  $stmt->bind_param("sssssi", $kode, $nama, $alamat, $telp, $status, $id);
  if ($stmt->execute()) {
    log_activity('update_cabang', 'cabang', 'Updated branch ID: ' . $id);
    alert('success', 'Cabang berhasil diupdate.');
  }
  header('Location: index.php?m=cabang');
  exit;
}

// Handle delete
if (isset($_GET['del'])) {
  $id = (int)$_GET['del'];
  // Check if branch has data
  $check = $conn->query("SELECT COUNT(*) c FROM ksp_pinjaman WHERE cabang_id = $id")->fetch_assoc();
  if ($check['c'] > 0) {
    alert('danger', 'Cabang tidak bisa dihapus karena masih memiliki data pinjaman.');
  } else {
    $conn->query("DELETE FROM ksp_cabang WHERE id = $id AND id != 1");
    log_activity('delete_cabang', 'cabang', 'Deleted branch ID: ' . $id);
    alert('success', 'Cabang berhasil dihapus.');
  }
  header('Location: index.php?m=cabang');
  exit;
}

// Get branches with stats
$branches = $conn->query("
  SELECT c.*,
    (SELECT COUNT(*) FROM ksp_anggota WHERE cabang_id = c.id) as total_anggota,
    (SELECT COUNT(*) FROM ksp_pinjaman WHERE cabang_id = c.id AND status = 'aktif') as pinjaman_aktif,
    (SELECT COUNT(*) FROM ksp_users WHERE cabang_id = c.id) as total_user
  FROM ksp_cabang c
  ORDER BY c.id
");
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4><i class="bi bi-building me-2"></i>Manajemen Cabang</h4>
  <button class="btn btn-gradient" data-bs-toggle="modal" data-bs-target="#modalTambah">
    <i class="bi bi-plus-lg me-1"></i>Tambah Cabang
  </button>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
  <?php 
  $branches_data = array();
  $total_cabang = 0;
  $total_aktif = 0;
  while ($b = $branches->fetch_assoc()) {
    $branches_data[] = $b;
    $total_cabang++;
    if ($b['status'] == 'aktif') $total_aktif++;
  }
  ?>
  <div class="col-md-4">
    <div class="card card-widget text-center">
      <div class="card-body">
        <i class="bi bi-building fs-1 text-primary"></i>
        <h3 class="mt-2"><?php echo $total_cabang; ?></h3>
        <div class="text-muted">Total Cabang</div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card card-widget text-center">
      <div class="card-body">
        <i class="bi bi-check-circle fs-1 text-success"></i>
        <h3 class="mt-2"><?php echo $total_aktif; ?></h3>
        <div class="text-muted">Cabang Aktif</div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card card-widget text-center">
      <div class="card-body">
        <i class="bi bi-x-circle fs-1 text-danger"></i>
        <h3 class="mt-2"><?php echo $total_cabang - $total_aktif; ?></h3>
        <div class="text-muted">Cabang Nonaktif</div>
      </div>
    </div>
  </div>
</div>

<!-- Branch List -->
<div class="card card-widget">
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-striped datatable">
        <thead>
          <tr>
            <th>Kode</th>
            <th>Nama Cabang</th>
            <th>Alamat</th>
            <th>Telp</th>
            <th>Anggota</th>
            <th>Pinjaman Aktif</th>
            <th>User</th>
            <th>Status</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($branches_data as $b): ?>
        <tr>
          <td><strong><?php echo htmlspecialchars($b['kode']); ?></strong></td>
          <td><?php echo htmlspecialchars($b['nama']); ?></td>
          <td><small><?php echo htmlspecialchars($b['alamat']); ?></small></td>
          <td><?php echo htmlspecialchars($b['telp']); ?></td>
          <td><span class="badge bg-primary"><?php echo $b['total_anggota']; ?></span></td>
          <td><span class="badge bg-info"><?php echo $b['pinjaman_aktif']; ?></span></td>
          <td><span class="badge bg-secondary"><?php echo $b['total_user']; ?></span></td>
          <td>
            <span class="badge <?php echo $b['status']=='aktif' ? 'bg-success' : 'bg-danger'; ?>">
              <?php echo strtoupper($b['status']); ?>
            </span>
          </td>
          <td>
            <button class="btn btn-sm btn-outline-primary" onclick="editCabang(<?php echo htmlspecialchars(json_encode($b)); ?>)">
              <i class="bi bi-pencil"></i>
            </button>
            <?php if ($b['id'] != 1): ?>
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmHapusCabang('<?php echo htmlspecialchars($b['nama'], ENT_QUOTES); ?>', '<?php echo $b['kode']; ?>', <?php echo $b['id']; ?>)">
              <i class="bi bi-trash"></i>
            </button>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal Tambah -->
<div class="modal fade" id="modalTambah" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Tambah Cabang</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Kode Cabang</label>
            <input type="text" name="kode" class="form-control" required maxlength="10" placeholder="e.g. CB001">
          </div>
          <div class="mb-3">
            <label class="form-label">Nama Cabang</label>
            <input type="text" name="nama" class="form-control" required placeholder="e.g. Cabang Jakarta">
          </div>
          <div class="mb-3">
            <label class="form-label">Alamat</label>
            <textarea name="alamat" class="form-control" rows="2"></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">Telp</label>
            <input type="text" name="telp" class="form-control">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" name="tambah" class="btn btn-gradient">Simpan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Edit -->
<div class="modal fade" id="modalEdit" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        <input type="hidden" name="id" id="edit_id">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Cabang</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Kode Cabang</label>
            <input type="text" name="kode" id="edit_kode" class="form-control" required maxlength="10">
          </div>
          <div class="mb-3">
            <label class="form-label">Nama Cabang</label>
            <input type="text" name="nama" id="edit_nama" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Alamat</label>
            <textarea name="alamat" id="edit_alamat" class="form-control" rows="2"></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">Telp</label>
            <input type="text" name="telp" id="edit_telp" class="form-control">
          </div>
          <div class="mb-3">
            <label class="form-label">Status</label>
            <select name="status" id="edit_status" class="form-select">
              <option value="aktif">Aktif</option>
              <option value="nonaktif">Nonaktif</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" name="update" class="btn btn-gradient">Update</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function editCabang(data) {
  document.getElementById('edit_id').value = data.id;
  document.getElementById('edit_kode').value = data.kode;
  document.getElementById('edit_nama').value = data.nama;
  document.getElementById('edit_alamat').value = data.alamat || '';
  document.getElementById('edit_telp').value = data.telp || '';
  document.getElementById('edit_status').value = data.status;
  new bootstrap.Modal(document.getElementById('modalEdit')).show();
}

function confirmHapusCabang(nama, kode, id) {
  Swal.fire({
    title: 'Hapus Cabang?',
    html: '<div class="text-start"><strong>Data yang akan dihapus:</strong><br>' +
          '<table class="table table-sm mt-2"><tr><td>Nama</td><td>: ' + nama + '</td></tr>' +
          '<tr><td>Kode</td><td>: ' + kode + '</td></tr></table>' +
          '<span class="text-danger"><i class="bi bi-exclamation-triangle me-1"></i>Data yang dihapus tidak dapat dikembalikan!</span></div>',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#dc3545',
    cancelButtonColor: '#6c757d',
    confirmButtonText: '<i class="bi bi-trash me-1"></i>Ya, Hapus!',
    cancelButtonText: 'Batal',
    reverseButtons: true
  }).then(function(result) {
    if (result.isConfirmed) {
      window.location.href = 'index.php?m=cabang&del=' + id;
    }
  });
}
</script>

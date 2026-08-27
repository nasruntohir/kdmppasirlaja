<?php
// views/anggota.php
csrf_check();
$conn = db();

// Handle create
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
  $no = gen_nomor_anggota();
  $nik = trim($_POST['nik'] ?? '');
  $nama = trim($_POST['nama'] ?? '');
  $hp = trim($_POST['hp'] ?? '');
  $alamat = trim($_POST['alamat'] ?? '');
  $rt = str_pad(trim($_POST['rt'] ?? ''), 2, '0', STR_PAD_LEFT);
  $rw = str_pad(trim($_POST['rw'] ?? ''), 2, '0', STR_PAD_LEFT);
  $tgl = trim($_POST['tanggal_gabung'] ?? '');
  $is_ketua = isset($_POST['is_ketua']) ? 1 : 0;

  $kodeCabang = 'RW-' . $rw;
  $stmt = $conn->prepare('SELECT id FROM ksp_cabang WHERE kode = ? LIMIT 1');
  $stmt->bind_param('s', $kodeCabang);
  $stmt->execute();
  $cabang = $stmt->get_result()->fetch_assoc();

  if ($cabang) {
    $cabang_id = (int) $cabang['id'];
  } else {
    $namaCabang = 'Cabang RW ' . $rw;
    $stmt = $conn->prepare("INSERT INTO ksp_cabang (kode, nama, status) VALUES (?, ?, 'aktif')");
    $stmt->bind_param('ss', $kodeCabang, $namaCabang);
    $stmt->execute();
    $cabang_id = $conn->insert_id;
  }

  $kodeKelompok = 'RW-' . $rw;
  $stmt = $conn->prepare('SELECT id FROM ksp_kelompok WHERE kode = ? LIMIT 1');
  $stmt->bind_param('s', $kodeKelompok);
  $stmt->execute();
  $kelompok = $stmt->get_result()->fetch_assoc();

  if ($kelompok) {
    $kelompok_id = (int) $kelompok['id'];
  } else {
    $namaKelompok = 'Kelompok RW ' . $rw;
    $stmt = $conn->prepare("INSERT INTO ksp_kelompok (kode, nama, cabang_id, rw, status) VALUES (?, ?, ?, ?, 'aktif')");
    $stmt->bind_param('ssis', $kodeKelompok, $namaKelompok, $cabang_id, $rw);
    $stmt->execute();
    $kelompok_id = $conn->insert_id;
  }

  if ($is_ketua) {
    $stmt = $conn->prepare('SELECT id FROM ksp_anggota WHERE kelompok_id = ? AND is_ketua = 1 LIMIT 1');
    $stmt->bind_param('i', $kelompok_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
      $is_ketua = 0;
      alert('warning', 'Kelompok sudah memiliki ketua, status ketua tidak diaktifkan.');
    }
  }

  $stmt = $conn->prepare("INSERT INTO ksp_anggota (no_anggota, nik, nama, hp, alamat, rt, rw, tanggal_gabung, status, cabang_id, kelompok_id, is_ketua) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'aktif', ?, ?, ?)");
  $stmt->bind_param('ssssssssiii', $no, $nik, $nama, $hp, $alamat, $rt, $rw, $tgl, $cabang_id, $kelompok_id, $is_ketua);

  if ($stmt->execute()) {
    alert('success', 'Anggota ditambahkan.');
  } else {
    alert('danger', 'Gagal: ' . $stmt->error);
  }

  header('Location: index.php?m=anggota');
  exit;
}
// Handle update
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='update') {
  $id = (int)$_POST['id'];
  $nik = trim($_POST['nik']);
  $nama = trim($_POST['nama']);
  $hp = trim($_POST['hp']);
  $alamat = trim($_POST['alamat']);
  $status = in_array($_POST['status'], array('aktif','nonaktif')) ? $_POST['status'] : 'aktif';
  $kelompok_id = !empty($_POST['kelompok_id']) ? (int)$_POST['kelompok_id'] : null;
  $is_ketua = isset($_POST['is_ketua']) ? 1 : 0;
  
  // Jika set sebagai ketua, pastikan kelompok belum punya ketua lain
  if ($is_ketua && $kelompok_id) {
    $cek_ketua = $conn->query("SELECT id FROM ksp_anggota WHERE kelompok_id=$kelompok_id AND is_ketua=1 AND id!=$id");
    if ($cek_ketua && $cek_ketua->num_rows > 0) {
      $is_ketua = 0;
      alert('warning', 'Kelompok sudah memiliki ketua lain.');
    }
  }
  
  // Jika kelompok kosong, reset is_ketua
  if (!$kelompok_id) $is_ketua = 0;
  
  $stmt = $conn->prepare("UPDATE ksp_anggota SET nik=?, nama=?, hp=?, alamat=?, status=?, kelompok_id=?, is_ketua=? WHERE id=?");
  $stmt->bind_param("sssssiii", $nik, $nama, $hp, $alamat, $status, $kelompok_id, $is_ketua, $id);
  if ($stmt->execute()) alert('success', 'Anggota diperbarui.');
  else alert('danger', 'Gagal update: '.$conn->error);
  header("Location: index.php?m=anggota"); exit;
}

// Handle delete (POST with CSRF)
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='delete') {
  $id = (int)$_POST['id'];
  // Check FK constraint - apakah anggota punya pinjaman aktif
  $check = $conn->query("SELECT COUNT(*) c FROM ksp_pinjaman WHERE anggota_id=".$id);
  $has_pinjaman = $check ? (int)$check->fetch_assoc()['c'] : 0;
  if ($has_pinjaman > 0) {
    alert('danger', 'Tidak bisa hapus anggota yang masih punya pinjaman.');
  } else {
    $stmt = $conn->prepare("DELETE FROM ksp_anggota WHERE id=?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
      alert('success', 'Anggota dihapus.');
    } else {
      alert('danger', 'Gagal menghapus anggota.');
    }
  }
  header("Location: index.php?m=anggota"); exit;
}

$list = $conn->query("
  SELECT a.*, 
    COALESCE(s.total_simpanan, 0) as total_simpanan,
    (SELECT COUNT(*) FROM ksp_pinjaman WHERE anggota_id = a.id AND status = 'aktif') as pinjaman_aktif,
    (SELECT COUNT(*) FROM ksp_blacklist WHERE anggota_id = a.id AND status = 'aktif') as is_blacklisted,
    c.nama as cabang_nama,
    k.nama as kelompok_nama
  FROM ksp_anggota a
  LEFT JOIN ksp_simpanan_anggota s ON s.anggota_id = a.id
  LEFT JOIN ksp_cabang c ON c.id = a.cabang_id
  LEFT JOIN ksp_kelompok k ON k.id = a.kelompok_id
  ORDER BY a.id 
");

// Get cabang list untuk form
$cabang_list = $conn->query("SELECT id, kode, nama FROM ksp_cabang WHERE status='aktif' ORDER BY id");

// Get kelompok list untuk form
$kelompok_list = $conn->query("SELECT id, kode, nama FROM ksp_kelompok WHERE status='aktif' ORDER BY nama");

// Function untuk cek apakah kelompok sudah punya ketua
if (!function_exists('getKelompokKetuaStatus')) {
  function getKelompokKetuaStatus($conn) {
    $result = [];
    $q = $conn->query("SELECT kelompok_id FROM ksp_anggota WHERE is_ketua=1 AND kelompok_id IS NOT NULL");
    while ($row = $q->fetch_assoc()) {
      $result[$row['kelompok_id']] = true;
    }
    return $result;
  }
}
$kelompok_has_ketua = getKelompokKetuaStatus($conn);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0">Anggota</h4>
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
            <label class="form-label">No. Anggota</label>
            <input name="no_anggota" class="form-control" value="<?php echo htmlspecialchars(gen_nomor_anggota()); ?>" readonly>
          </div>
          <div class="col-md-3">
            <label class="form-label">NIK</label>
            <input name="nik" class="form-control">
          </div>
          <div class="col-md-3">
            <label class="form-label">Nama</label>
            <input name="nama" class="form-control" required>
          </div>
          <div class="col-md-3">
            <label class="form-label">HP (WA)</label>
            <input name="hp" class="form-control">
          </div>
          <div class="col-md-12">
            <label class="form-label">Alamat</label>
            <input name="alamat" class="form-control">
          </div>
          <div class="col-md-2">
            <label class="form-label">RT</label>
            <input name="rt" class="form-control" maxlength="3" required>
          </div>
          <div class="col-md-2">
          <label class="form-label">RW</label>
          <input name="rw" class="form-control" maxlength="3" required>
          </div>
          <div class="col-md-3">
            <label class="form-label">Tanggal Gabung</label>
            <input type="date" name="tanggal_gabung" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Cabang & Kelompok</label>
            <input class="form-control" value="Otomatis berdasarkan RW" readonly>
          </div>
          <div class="col-md-3" id="ketua_container_add">
            <label class="form-label">Ketua Kelompok</label>
            <div class="form-check mt-2">
              <input type="checkbox" name="is_ketua" id="is_ketua_add" class="form-check-input" value="1">
              <label class="form-check-label" for="is_ketua_add"><i class="bi bi-star-fill text-warning me-1"></i>Jadikan Ketua Kelompok</label>
            </div>
          </div>
        </div>
        <div class="mt-3"><button type="submit" class="btn btn-gradient" onclick="return confirmSimpan(this.form, 'Tambah Anggota Baru')">Simpan</button></div>
      </form>
    </div>
  </div>
</div>

<div class="card card-widget">
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-striped datatable">
        <thead><tr>
          <th>#</th><th>No. Anggota</th><th>Nik</th><th>Nama</th><th>Alamat</th><th>RT</th><th>RW</th><th>Kelompok</th><th>HP</th><th>Simpanan</th><th>Pinjaman</th><th>Status</th><th>Aksi</th>
        </tr></thead>
        <tbody>
        <?php $no=1; while($r=$list->fetch_assoc()): ?>
          <tr class="<?php echo $r['is_blacklisted'] ? 'table-danger' : ''; ?>">
            <td><?php echo $no++; ?></td>
            <td><?php echo htmlspecialchars($r['no_anggota']); ?></td>
            <td><?php echo htmlspecialchars($r['nik']); ?></td>
            <td>
              <?php echo htmlspecialchars($r['nama']); ?>
              <?php if ($r['is_ketua']): ?><span class="badge bg-warning ms-1"><i class="bi bi-star-fill"></i> Ketua</span><?php endif; ?>
              <?php if ($r['is_blacklisted']): ?><span class="badge bg-danger ms-1">BLACKLIST</span><?php endif; ?>
            </td>
            <td><?php echo htmlspecialchars($r['alamat']); ?></td>
            <td><?php echo htmlspecialchars($r['rt']); ?></td>
            <td><?php echo htmlspecialchars($r['rw']); ?></td>
            <td>
              <?php if ($r['kelompok_nama']): ?>
              <span class="badge bg-info"><?php echo htmlspecialchars($r['kelompok_nama']); ?></span>
              <?php else: ?>
              <span class="text-muted">-</span>
              <?php endif; ?>
            </td>
            <td><?php echo htmlspecialchars($r['hp']); ?></td>
            <td>
              <a href="index.php?m=simpanan_anggota&id=<?php echo $r['id']; ?>" class="text-decoration-none">
                Rp <?php echo number_format($r['total_simpanan'], 0, ',', '.'); ?>
              </a>
            </td>
            <td>
              <?php if ($r['pinjaman_aktif'] > 0): ?>
              <span class="badge bg-warning"><?php echo $r['pinjaman_aktif']; ?> aktif</span>
              <?php else: ?>
              <span class="text-muted">-</span>
              <?php endif; ?>
            </td>
            <td><span class="badge text-bg-<?php echo $r['status']=='aktif'?'success':'secondary'; ?>"><?php echo htmlspecialchars($r['status']); ?></span></td>
            <td>
              <div class="btn-group">
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $r['id']; ?>" title="Edit"><i class="bi bi-pencil"></i></button>
                <a href="index.php?m=simpanan_anggota&id=<?php echo $r['id']; ?>" class="btn btn-sm btn-outline-success" title="Simpanan"><i class="bi bi-wallet2"></i></a>
                <a href="index.php?m=buku_tabungan&id=<?php echo $r['id']; ?>" class="btn btn-sm btn-outline-info" title="Buku Tabungan"><i class="bi bi-book"></i></a>
              </div>
              <form method="post" style="display:inline" id="deleteForm<?php echo $r['id']; ?>">
                <?php echo csrf_input(); ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                <button type="button" class="btn btn-sm btn-outline-danger" title="Hapus" onclick="confirmHapus('<?php echo htmlspecialchars($r['nama'], ENT_QUOTES); ?>', '<?php echo $r['no_anggota']; ?>', 'deleteForm<?php echo $r['id']; ?>')"><i class="bi bi-trash"></i></button>
              </form>
              <!-- Edit Modal -->
              <div class="modal fade" id="editModal<?php echo $r['id']; ?>" tabindex="-1">
                <div class="modal-dialog">
                  <div class="modal-content">
                    <form method="post">
                      <?php echo csrf_input(); ?>
                      <input type="hidden" name="action" value="update">
                      <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                      <div class="modal-header"><h5 class="modal-title">Edit Anggota</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                      <div class="modal-body">
                        <div class="mb-3"><label class="form-label">NIK</label><input name="nik" class="form-control" value="<?php echo htmlspecialchars($r['nik']); ?>"></div>
                        <div class="mb-3"><label class="form-label">Nama</label><input name="nama" class="form-control" value="<?php echo htmlspecialchars($r['nama']); ?>" required></div>
                        <div class="mb-3"><label class="form-label">HP</label><input name="hp" class="form-control" value="<?php echo htmlspecialchars($r['hp']); ?>"></div>
                        <div class="mb-3"><label class="form-label">Alamat</label><input name="alamat" class="form-control" value="<?php echo htmlspecialchars($r['alamat']); ?>"></div>
                        <div class="mb-3"><label class="form-label">Status</label><select name="status" class="form-select"><option value="aktif" <?php echo $r['status']=='aktif'?'selected':''; ?>>aktif</option><option value="nonaktif" <?php echo $r['status']=='nonaktif'?'selected':''; ?>>nonaktif</option></select></div>
                        <div class="mb-3">
                          <label class="form-label">Kelompok</label>
                          <select name="kelompok_id" id="kelompok_id_edit<?php echo $r['id']; ?>" class="form-select" onchange="toggleKetuaCheckbox('edit<?php echo $r['id']; ?>')">
                            <option value="">-- Tidak Ada --</option>
                            <?php 
                            $kelompok_list->data_seek(0);
                            while($kel = $kelompok_list->fetch_assoc()): 
                              $is_current = ($r['kelompok_id'] == $kel['id']);
                              $has_ketua = isset($kelompok_has_ketua[$kel['id']]) && !($is_current && $r['is_ketua']);
                            ?>
                            <option value="<?php echo $kel['id']; ?>" <?php echo $is_current ? 'selected' : ''; ?> data-has-ketua="<?php echo $has_ketua ? '1' : '0'; ?>"><?php echo htmlspecialchars($kel['nama']); ?></option>
                            <?php endwhile; ?>
                          </select>
                        </div>
                        <div class="mb-3" id="ketua_container_edit<?php echo $r['id']; ?>" style="<?php echo $r['kelompok_id'] ? '' : 'display:none;'; ?>">
                          <div class="form-check">
                            <input type="checkbox" name="is_ketua" id="is_ketua_edit<?php echo $r['id']; ?>" class="form-check-input" value="1" <?php echo $r['is_ketua'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="is_ketua_edit<?php echo $r['id']; ?>"><i class="bi bi-star-fill text-warning me-1"></i>Jadikan Ketua Kelompok</label>
                          </div>
                        </div>
                      </div>
                      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-gradient" onclick="return confirmSimpan(this.form, 'Update Data Anggota')">Simpan</button></div>
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

<script>
function toggleKetuaCheckbox(suffix) {
  var select = document.getElementById('kelompok_id_' + suffix);
  var container = document.getElementById('ketua_container_' + suffix);
  var checkbox = document.getElementById('is_ketua_' + suffix);
  
  if (!select || !container) return;
  
  var selectedOption = select.options[select.selectedIndex];
  var kelompokId = select.value;
  var hasKetua = selectedOption ? selectedOption.getAttribute('data-has-ketua') === '1' : false;
  
  if (kelompokId && kelompokId !== '') {
    container.style.display = '';
    if (hasKetua) {
      checkbox.disabled = true;
      checkbox.checked = false;
      checkbox.parentElement.innerHTML = '<span class="text-muted"><i class="bi bi-info-circle me-1"></i>Kelompok ini sudah memiliki ketua</span>';
    } else {
      checkbox.disabled = false;
    }
  } else {
    container.style.display = 'none';
    if (checkbox) {
      checkbox.checked = false;
    }
  }
}
</script>

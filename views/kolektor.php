<?php
csrf_check(); $conn = db();
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']=='create') {
  $kode = trim($_POST['kode']); $nama = trim($_POST['nama']); $rute = trim($_POST['rute']);
  $stmt = $conn->prepare("INSERT INTO ksp_kolektor(kode,nama,rute,status) VALUES(?,?,?,'aktif')");
  $stmt->bind_param("sss", $kode, $nama, $rute);
  if ($stmt->execute()) alert('success','Kolektor ditambahkan.'); else alert('danger','Gagal: '.$conn->error);
  header('Location: index.php?m=kolektor'); exit;
}
// Handle update
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='update') {
  $id = (int)$_POST['id'];
  $nama = trim($_POST['nama']);
  $rute = trim($_POST['rute']);
  $status = isset($_POST['status']) ? 'aktif' : 'nonaktif';
  $stmt = $conn->prepare("UPDATE ksp_kolektor SET nama=?, rute=?, status=? WHERE id=?");
  $stmt->bind_param("sssi", $nama, $rute, $status, $id);
  if ($stmt->execute()) alert('success', 'Kolektor diperbarui.');
  else alert('danger', 'Gagal update: '.$conn->error);
  header('Location: index.php?m=kolektor'); exit;
}
// Handle delete (POST with CSRF)
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='delete') {
  $id = (int)$_POST['id'];
  // Check FK constraint - apakah kolektor punya tagihan
  $check = $conn->query("SELECT COUNT(*) c FROM ksp_tagih_harian WHERE kolektor_id=".$id);
  $has_tagih = $check ? (int)$check->fetch_assoc()['c'] : 0;
  if ($has_tagih > 0) {
    alert('danger', 'Tidak bisa hapus kolektor yang sudah punya riwayat tagihan.');
  } else {
    $stmt = $conn->prepare("DELETE FROM ksp_kolektor WHERE id=?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
      alert('success', 'Kolektor dihapus.');
    } else {
      alert('danger', 'Gagal menghapus kolektor.');
    }
  }
  header('Location: index.php?m=kolektor'); exit;
}
$list = $conn->query("SELECT * FROM ksp_kolektor ORDER BY id DESC");
?>
<div class='d-flex justify-content-between align-items-center mb-3'>
  <h4 class='mb-0'>Master Kolektor</h4>
  <button class='btn btn-gradient' data-bs-toggle='collapse' data-bs-target='#formAdd'>Tambah</button>
</div>
<div id='formAdd' class='collapse mb-3'>
  <div class='card card-widget'><div class='card-body'>
    <form method='post'>
      <?php echo csrf_input(); ?><input type='hidden' name='action' value='create'>
      <div class='row g-3'>
        <div class='col-md-3'><label class='form-label'>Kode</label><input name='kode' class='form-control' required></div>
        <div class='col-md-4'><label class='form-label'>Nama</label><input name='nama' class='form-control' required></div>
        <div class='col-md-5'><label class='form-label'>Rute</label><input name='rute' class='form-control' placeholder='mis. RW 01-03'></div>
      </div>
      <div class='mt-3'><button class='btn btn-gradient'>Simpan</button></div>
    </form>
  </div></div>
</div>
<div class='card card-widget'><div class='card-body'>
  <div class='table-responsive'>
    <table class='table table-striped table-sm'>
      <thead><tr><th>#</th><th>Kode</th><th>Nama</th><th>Rute</th><th>Aksi</th></tr></thead>
      <tbody>
        <?php $no=1; while($r=$list->fetch_assoc()): ?>
        <tr>
          <td><?php echo $no++; ?></td>
          <td><?php echo htmlspecialchars($r['kode']); ?></td>
          <td><?php echo htmlspecialchars($r['nama']); ?></td>
          <td><?php echo htmlspecialchars($r['rute']); ?></td>
          <td>
            <button class='btn btn-sm btn-outline-primary me-1' data-bs-toggle='modal' data-bs-target='#editKolektor<?php echo $r['id']; ?>'><i class='bi bi-pencil'></i></button>
            <form method='post' style='display:inline' id='deleteFormKol<?php echo $r['id']; ?>'>
              <?php echo csrf_input(); ?>
              <input type='hidden' name='action' value='delete'>
              <input type='hidden' name='id' value='<?php echo $r['id']; ?>'>
              <button type='button' class='btn btn-sm btn-outline-danger' onclick="confirmHapus('<?php echo htmlspecialchars($r['nama'], ENT_QUOTES); ?>', '<?php echo $r['kode']; ?>', 'deleteFormKol<?php echo $r['id']; ?>')"><i class='bi bi-trash'></i></button>
            </form>
            <!-- Edit Modal -->
            <div class='modal fade' id='editKolektor<?php echo $r['id']; ?>' tabindex='-1'>
              <div class='modal-dialog'>
                <div class='modal-content'>
                  <form method='post'>
                    <?php echo csrf_input(); ?>
                    <input type='hidden' name='action' value='update'>
                    <input type='hidden' name='id' value='<?php echo $r['id']; ?>'>
                    <div class='modal-header'><h5 class='modal-title'>Edit Kolektor</h5><button type='button' class='btn-close' data-bs-dismiss='modal'></button></div>
                    <div class='modal-body'>
                      <div class='mb-3'><label class='form-label'>Kode</label><input class='form-control' value='<?php echo htmlspecialchars($r['kode']); ?>' disabled></div>
                      <div class='mb-3'><label class='form-label'>Nama</label><input name='nama' class='form-control' value='<?php echo htmlspecialchars($r['nama']); ?>' required></div>
                      <div class='mb-3'><label class='form-label'>Rute</label><input name='rute' class='form-control' value='<?php echo htmlspecialchars($r['rute']); ?>'></div>
                      <div class='mb-3 form-check'><input type='checkbox' name='status' class='form-check-input' <?php echo $r['status']=='aktif'?'checked':''; ?>><label class='form-check-label'>Aktif</label></div>
                    </div>
                    <div class='modal-footer'><button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>Batal</button><button class='btn btn-gradient'>Simpan</button></div>
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
</div></div>
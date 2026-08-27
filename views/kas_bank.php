<?php
// views/kas_bank.php - Master Kas & Bank
csrf_check();
$conn = db();

// Handle create
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='create') {
  $kode = trim($_POST['kode']);
  $nama = trim($_POST['nama']);
  $tipe = $_POST['tipe'];
  $no_rekening = trim($_POST['no_rekening'] ?? '');
  $nama_bank = trim($_POST['nama_bank'] ?? '');
  $saldo_awal = (float)($_POST['saldo_awal'] ?? 0);
  $keterangan = trim($_POST['keterangan'] ?? '');
  
  $stmt = $conn->prepare("INSERT INTO ksp_kas_bank(kode, nama, tipe, no_rekening, nama_bank, saldo_awal, saldo, keterangan) VALUES(?,?,?,?,?,?,?,?)");
  $stmt->bind_param("sssssdds", $kode, $nama, $tipe, $no_rekening, $nama_bank, $saldo_awal, $saldo_awal, $keterangan);
  if ($stmt->execute()) alert('success', 'Kas/Bank berhasil ditambahkan.');
  else alert('danger', 'Gagal: '.$conn->error);
  header("Location: index.php?m=kas_bank"); exit;
}

// Handle update
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='update') {
  $id = (int)$_POST['id'];
  $kode = trim($_POST['kode']);
  $nama = trim($_POST['nama']);
  $tipe = $_POST['tipe'];
  $no_rekening = trim($_POST['no_rekening'] ?? '');
  $nama_bank = trim($_POST['nama_bank'] ?? '');
  $keterangan = trim($_POST['keterangan'] ?? '');
  $status = $_POST['status'];
  
  $stmt = $conn->prepare("UPDATE ksp_kas_bank SET kode=?, nama=?, tipe=?, no_rekening=?, nama_bank=?, keterangan=?, status=? WHERE id=?");
  $stmt->bind_param("sssssssi", $kode, $nama, $tipe, $no_rekening, $nama_bank, $keterangan, $status, $id);
  if ($stmt->execute()) alert('success', 'Kas/Bank berhasil diupdate.');
  else alert('danger', 'Gagal: '.$conn->error);
  header("Location: index.php?m=kas_bank"); exit;
}

// Handle delete
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='delete') {
  $id = (int)$_POST['id'];
  $check = $conn->query("SELECT COUNT(*) as cnt FROM ksp_transaksi_keuangan WHERE kas_bank_id=$id OR kas_bank_tujuan_id=$id")->fetch_assoc();
  if ($check['cnt'] > 0) {
    alert('danger', 'Tidak bisa dihapus, sudah ada transaksi terkait.');
  } else {
    $conn->query("DELETE FROM ksp_kas_bank WHERE id=$id");
    alert('success', 'Kas/Bank berhasil dihapus.');
  }
  header("Location: index.php?m=kas_bank"); exit;
}

$list = $conn->query("SELECT * FROM ksp_kas_bank ORDER BY tipe, kode");
$total_kas = $conn->query("SELECT SUM(saldo) as total FROM ksp_kas_bank WHERE tipe='kas' AND status='aktif'")->fetch_assoc()['total'] ?? 0;
$total_bank = $conn->query("SELECT SUM(saldo) as total FROM ksp_kas_bank WHERE tipe='bank' AND status='aktif'")->fetch_assoc()['total'] ?? 0;
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-wallet2 me-2"></i>Master Kas & Bank</h4>
  <button class="btn btn-gradient" data-bs-toggle="collapse" data-bs-target="#formAdd"><i class="bi bi-plus-lg me-1"></i> Tambah</button>
</div>

<!-- Summary -->
<div class="row mb-3">
  <div class="col-md-4">
    <div class="card bg-success text-white">
      <div class="card-body text-center">
        <h4 class="mb-0">Rp <?php echo rupiah($total_kas); ?></h4>
        <small>Total Kas</small>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card bg-primary text-white">
      <div class="card-body text-center">
        <h4 class="mb-0">Rp <?php echo rupiah($total_bank); ?></h4>
        <small>Total Bank</small>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card bg-info text-white">
      <div class="card-body text-center">
        <h4 class="mb-0">Rp <?php echo rupiah($total_kas + $total_bank); ?></h4>
        <small>Total Keseluruhan</small>
      </div>
    </div>
  </div>
</div>

<div id="formAdd" class="collapse mb-3">
  <div class="card card-widget">
    <div class="card-body">
      <form method="post">
        <?php echo csrf_input(); ?>
        <input type="hidden" name="action" value="create">
        <div class="row g-3">
          <div class="col-md-2"><label class="form-label">Kode</label><input name="kode" class="form-control" required placeholder="KAS001"></div>
          <div class="col-md-3"><label class="form-label">Nama</label><input name="nama" class="form-control" required placeholder="Kas Utama"></div>
          <div class="col-md-2">
            <label class="form-label">Tipe</label>
            <select name="tipe" id="tipeKasBank" class="form-select">
              <option value="kas">Kas</option>
              <option value="bank">Bank</option>
            </select>
          </div>
          <div class="col-md-2 bank-field d-none"><label class="form-label">Nama Bank</label><input name="nama_bank" class="form-control" placeholder="BRI, BCA"></div>
          <div class="col-md-3 bank-field d-none"><label class="form-label">No. Rekening</label><input name="no_rekening" class="form-control" placeholder="1234567890"></div>
        </div>
        <div class="row g-3 mt-1">
          <div class="col-md-3"><label class="form-label">Saldo Awal</label><input name="saldo_awal" type="number" step="1000" class="form-control" value="0"></div>
          <div class="col-md-9"><label class="form-label">Keterangan</label><input name="keterangan" class="form-control" placeholder="Keterangan (opsional)"></div>
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
        <thead><tr><th>#</th><th>Kode</th><th>Nama</th><th>Tipe</th><th>Bank/Rekening</th><th class="text-end">Saldo</th><th>Status</th><th>Aksi</th></tr></thead>
        <tbody>
        <?php $no=1; while($r=$list->fetch_assoc()): ?>
          <tr>
            <td><?php echo $no++; ?></td>
            <td><strong><?php echo htmlspecialchars($r['kode']); ?></strong></td>
            <td><?php echo htmlspecialchars($r['nama']); ?></td>
            <td><span class="badge text-bg-<?php echo $r['tipe']=='kas'?'success':'primary'; ?>"><?php echo strtoupper($r['tipe']); ?></span></td>
            <td><?php echo $r['tipe']=='bank' ? $r['nama_bank'].' - '.$r['no_rekening'] : '-'; ?></td>
            <td class="text-end"><strong>Rp <?php echo rupiah($r['saldo']); ?></strong></td>
            <td><span class="badge text-bg-<?php echo $r['status']=='aktif'?'success':'secondary'; ?>"><?php echo $r['status']; ?></span></td>
            <td>
              <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $r['id']; ?>"><i class="bi bi-pencil"></i></button>
              <form method="post" style="display:inline" id="deleteForm<?php echo $r['id']; ?>">
                <?php echo csrf_input(); ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmHapus('Kas/Bank', '<?php echo $r['nama']; ?>', 'deleteForm<?php echo $r['id']; ?>')"><i class="bi bi-trash"></i></button>
              </form>
              
              <div class="modal fade" id="editModal<?php echo $r['id']; ?>" tabindex="-1">
                <div class="modal-dialog">
                  <div class="modal-content">
                    <form method="post">
                      <?php echo csrf_input(); ?>
                      <input type="hidden" name="action" value="update">
                      <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                      <div class="modal-header"><h5 class="modal-title">Edit Kas/Bank</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                      <div class="modal-body">
                        <div class="row g-3">
                          <div class="col-6"><label class="form-label">Kode</label><input name="kode" class="form-control" value="<?php echo $r['kode']; ?>" required></div>
                          <div class="col-6"><label class="form-label">Tipe</label>
                            <select name="tipe" class="form-select">
                              <option value="kas" <?php echo $r['tipe']=='kas'?'selected':''; ?>>Kas</option>
                              <option value="bank" <?php echo $r['tipe']=='bank'?'selected':''; ?>>Bank</option>
                            </select>
                          </div>
                        </div>
                        <div class="mb-3 mt-3"><label class="form-label">Nama</label><input name="nama" class="form-control" value="<?php echo $r['nama']; ?>" required></div>
                        <div class="row g-3">
                          <div class="col-6"><label class="form-label">Nama Bank</label><input name="nama_bank" class="form-control" value="<?php echo $r['nama_bank']; ?>"></div>
                          <div class="col-6"><label class="form-label">No. Rekening</label><input name="no_rekening" class="form-control" value="<?php echo $r['no_rekening']; ?>"></div>
                        </div>
                        <div class="mb-3 mt-3"><label class="form-label">Keterangan</label><input name="keterangan" class="form-control" value="<?php echo $r['keterangan']; ?>"></div>
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

<script>
document.getElementById('tipeKasBank').addEventListener('change', function() {
  document.querySelectorAll('.bank-field').forEach(el => {
    el.classList.toggle('d-none', this.value !== 'bank');
  });
});
</script>

<?php
// views/kas.php
csrf_check();
$conn = db();

// Handle input kas manual
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='create') {
  $jenis = in_array($_POST['jenis'], array('masuk','keluar')) ? $_POST['jenis'] : 'masuk';
  $sumber = trim($_POST['sumber']);
  $nominal = (float)$_POST['nominal'];
  $catatan = trim($_POST['catatan']);
  
  if ($nominal <= 0) {
    alert('danger', 'Nominal harus lebih dari 0.');
  } else {
    $no_kas = gen_number('prefix_no_kas');
    $stmt = $conn->prepare("INSERT INTO ksp_kas(no_kas,tanggal,jenis,sumber,nominal,user_id,catatan) VALUES(?,?,?,?,?,?,?)");
    $uid = user()['id'];
    $stmt->bind_param("ssssdis", $no_kas, now(), $jenis, $sumber, $nominal, $uid, $catatan);
    if ($stmt->execute()) {
      alert('success', 'Transaksi kas dicatat. No: '.$no_kas);
    } else {
      alert('danger', 'Gagal menyimpan: '.$conn->error);
    }
  }
  header("Location: index.php?m=kas"); exit;
}
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0">Kas</h4>
  <button class="btn btn-gradient" data-bs-toggle="collapse" data-bs-target="#formKas"><i class="bi bi-plus-lg me-1"></i> Input Manual</button>
</div>

<div id="formKas" class="collapse mb-3">
  <div class="card card-widget">
    <div class="card-body">
      <form method="post">
        <?php echo csrf_input(); ?>
        <input type="hidden" name="action" value="create">
        <div class="row g-3">
          <div class="col-md-2">
            <label class="form-label">Jenis</label>
            <select name="jenis" class="form-select" required>
              <option value="masuk">Masuk</option>
              <option value="keluar">Keluar</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Sumber</label>
            <select name="sumber" class="form-select" required>
              <option value="operasional">Operasional</option>
              <option value="biaya">Biaya</option>
              <option value="simpanan">Simpanan</option>
              <option value="penarikan">Penarikan</option>
              <option value="angsuran">Angsuran</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Nominal (Rp)</label>
            <input type="number" name="nominal" class="form-control" step="0.01" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Catatan</label>
            <input name="catatan" class="form-control" placeholder="Keterangan transaksi">
          </div>
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
        <thead><tr><th>No. Kas</th><th>Tanggal</th><th>Jenis</th><th>Sumber</th><th>Nominal</th><th>Ket</th></tr></thead>
        <tbody>
        <?php $q=$conn->query("SELECT * FROM ksp_kas ORDER BY id DESC");
        while($r=$q->fetch_assoc()): ?>
          <tr>
            <td><?php echo htmlspecialchars($r['no_kas']); ?></td>
            <td><?php echo htmlspecialchars($r['tanggal']); ?></td>
            <td><span class="badge <?php echo $r['jenis']=='masuk'?'text-bg-success':'text-bg-danger'; ?>"><?php echo $r['jenis']; ?></span></td>
            <td><?php echo htmlspecialchars($r['sumber']); ?></td>
            <td>Rp <?php echo rupiah($r['nominal']); ?></td>
            <td><?php echo htmlspecialchars($r['catatan']); ?></td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

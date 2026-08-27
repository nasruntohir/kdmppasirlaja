<?php
// views/produk_simpanan.php
csrf_check();
$conn = db();

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='create') {
  $kode = trim($_POST['kode']); 
  $nama = trim($_POST['nama']);
  $tipe = trim($_POST['tipe']); 
  $min = (float)$_POST['min_setor'];
  $setoran_awal = (float)($_POST['setoran_awal'] ?? 0);
  $setoran_minimal = (float)($_POST['setoran_minimal'] ?? 0);
  $saldo_minimal = (float)($_POST['saldo_minimal'] ?? 0);
  $setoran_per_bulan = (float)($_POST['setoran_per_bulan'] ?? 0);
  $biaya_admin_bulanan = (float)($_POST['biaya_admin_bulanan'] ?? 0);
  
  $stmt = $conn->prepare("INSERT INTO ksp_produk_simpanan(kode,nama,tipe,min_setor,setoran_awal,setoran_minimal,saldo_minimal,setoran_per_bulan,biaya_admin_bulanan) VALUES(?,?,?,?,?,?,?,?,?)");
  $stmt->bind_param("sssddddddd", $kode, $nama, $tipe, $min, $setoran_awal, $setoran_minimal, $saldo_minimal, $setoran_per_bulan, $biaya_admin_bulanan);
  if ($stmt->execute()) alert('success','Produk simpanan ditambah.'); else alert('danger','Gagal: '.$conn->error);
  header("Location: index.php?m=produk_simpanan"); exit;
}
// Handle update
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='update') {
  $id = (int)$_POST['id'];
  $nama = trim($_POST['nama']);
  $tipe = trim($_POST['tipe']);
  $min = (float)$_POST['min_setor'];
  $setoran_awal = (float)($_POST['setoran_awal'] ?? 0);
  $setoran_minimal = (float)($_POST['setoran_minimal'] ?? 0);
  $saldo_minimal = (float)($_POST['saldo_minimal'] ?? 0);
  $setoran_per_bulan = (float)($_POST['setoran_per_bulan'] ?? 0);
  $biaya_admin_bulanan = (float)($_POST['biaya_admin_bulanan'] ?? 0);
  
  $stmt = $conn->prepare("UPDATE ksp_produk_simpanan SET nama=?, tipe=?, min_setor=?, setoran_awal=?, setoran_minimal=?, saldo_minimal=?, setoran_per_bulan=?, biaya_admin_bulanan=? WHERE id=?");
  $stmt->bind_param("ssddddddi", $nama, $tipe, $min, $setoran_awal, $setoran_minimal, $saldo_minimal, $setoran_per_bulan, $biaya_admin_bulanan, $id);
  if ($stmt->execute()) alert('success', 'Produk diperbarui.');
  else alert('danger', 'Gagal update: '.$conn->error);
  header("Location: index.php?m=produk_simpanan"); exit;
}

// Handle delete (POST with CSRF)
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='delete') {
  $id = (int)$_POST['id'];
  // Check FK constraint - apakah produk dipakai di transaksi simpanan
  $check = $conn->query("SELECT COUNT(*) c FROM ksp_simpanan_tx WHERE produk_id=".$id);
  $has_tx = $check ? (int)$check->fetch_assoc()['c'] : 0;
  if ($has_tx > 0) {
    alert('danger', 'Tidak bisa hapus produk yang sudah dipakai di transaksi.');
  } else {
    $stmt = $conn->prepare("DELETE FROM ksp_produk_simpanan WHERE id=?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
      alert('success', 'Produk simpanan dihapus.');
    } else {
      alert('danger', 'Gagal menghapus produk simpanan.');
    }
  }
  header("Location: index.php?m=produk_simpanan"); exit;
}
$list = $conn->query("SELECT * FROM ksp_produk_simpanan ORDER BY id DESC");
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0">Produk Simpanan</h4>
  <button class="btn btn-gradient" data-bs-toggle="collapse" data-bs-target="#formAdd"><i class="bi bi-plus-lg me-1"></i> Tambah</button>
</div>

<div id="formAdd" class="collapse mb-3">
  <div class="card card-widget">
    <div class="card-body">
      <form method="post" id="formAddProduk">
        <?php echo csrf_input(); ?>
        <input type="hidden" name="action" value="create">
        <div class="row g-3">
          <div class="col-md-2"><label class="form-label">Kode</label><input name="kode" class="form-control" required></div>
          <div class="col-md-4"><label class="form-label">Nama</label><input name="nama" class="form-control" required></div>
          <div class="col-md-3">
            <label class="form-label">Tipe</label>
            <select name="tipe" id="tipeAdd" class="form-select" onchange="toggleFieldsByTipe('Add')">
              <option value="pokok">Pokok</option>
              <option value="wajib" selected>Wajib</option>
              <option value="sukarela">Sukarela</option>
              <option value="harian">Harian</option>
            </select>
          </div>
          <div class="col-md-3"><label class="form-label">Min Setor <small class="text-muted">(lama)</small></label><input name="min_setor" type="number" step="1000" class="form-control" value="0"></div>
        </div>
        <!-- Fields dinamis berdasarkan tipe -->
        <div class="row g-3 mt-2">
          <div class="col-12"><hr><strong class="text-primary"><i class="bi bi-gear me-1"></i>Pengaturan Berdasarkan Tipe</strong></div>
          <!-- Pokok & Wajib: Setoran Awal -->
          <div class="col-md-3 field-pokok field-wajib">
            <label class="form-label">Setoran Awal</label>
            <input name="setoran_awal" type="number" step="1000" class="form-control" value="0">
          </div>
          <!-- Pokok, Wajib, Sukarela: Setoran Minimal -->
          <div class="col-md-3 field-pokok field-wajib field-sukarela">
            <label class="form-label">Setoran Minimal</label>
            <input name="setoran_minimal" type="number" step="1000" class="form-control" value="0">
          </div>
          <!-- Pokok & Wajib: Saldo Minimal -->
          <div class="col-md-3 field-pokok field-wajib">
            <label class="form-label">Saldo Minimal</label>
            <input name="saldo_minimal" type="number" step="1000" class="form-control" value="0">
          </div>
          <!-- Wajib only: Setoran per Bulan -->
          <div class="col-md-3 field-wajib">
            <label class="form-label">Setoran per Bulan</label>
            <input name="setoran_per_bulan" type="number" step="1000" class="form-control" value="0">
            <small class="text-muted">Target akumulasi bulanan</small>
          </div>
          <!-- Sukarela only: Biaya Admin Bulanan -->
          <div class="col-md-3 field-sukarela">
            <label class="form-label">Biaya Admin/Bulan</label>
            <input name="biaya_admin_bulanan" type="number" step="1000" class="form-control" value="0">
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
        <thead><tr><th>#</th><th>Kode</th><th>Nama</th><th>Tipe</th><th>Min Setor</th><th>Aksi</th></tr></thead>
        <tbody>
        <?php $no=1; while($r=$list->fetch_assoc()): ?>
          <tr>
            <td><?php echo $no++; ?></td>
            <td><?php echo htmlspecialchars($r['kode']); ?></td>
            <td><?php echo htmlspecialchars($r['nama']); ?></td>
            <td><?php echo htmlspecialchars($r['tipe']); ?></td>
            <td>Rp <?php echo rupiah($r['min_setor']); ?></td>
            <td>
              <button class="btn btn-sm btn-outline-primary me-1" data-bs-toggle="modal" data-bs-target="#editSimpanan<?php echo $r['id']; ?>"><i class="bi bi-pencil"></i></button>
              <form method="post" style="display:inline" id="deleteFormPS<?php echo $r['id']; ?>">
                <?php echo csrf_input(); ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmHapus('<?php echo htmlspecialchars($r['nama'], ENT_QUOTES); ?>', '<?php echo $r['kode']; ?>', 'deleteFormPS<?php echo $r['id']; ?>')"><i class="bi bi-trash"></i></button>
              </form>
              <!-- Edit Modal -->
              <div class="modal fade" id="editSimpanan<?php echo $r['id']; ?>" tabindex="-1">
                <div class="modal-dialog modal-lg">
                  <div class="modal-content">
                    <form method="post">
                      <?php echo csrf_input(); ?>
                      <input type="hidden" name="action" value="update">
                      <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                      <div class="modal-header"><h5 class="modal-title">Edit Produk Simpanan</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                      <div class="modal-body">
                        <div class="row g-3">
                          <div class="col-md-4"><label class="form-label">Kode</label><input class="form-control" value="<?php echo htmlspecialchars($r['kode']); ?>" disabled></div>
                          <div class="col-md-4"><label class="form-label">Nama</label><input name="nama" class="form-control" value="<?php echo htmlspecialchars($r['nama']); ?>" required></div>
                          <div class="col-md-4">
                            <label class="form-label">Tipe</label>
                            <select name="tipe" id="tipeEdit<?php echo $r['id']; ?>" class="form-select" onchange="toggleFieldsByTipe('Edit<?php echo $r['id']; ?>')">
                              <option value="pokok" <?php echo $r['tipe']=='pokok'?'selected':''; ?>>Pokok</option>
                              <option value="wajib" <?php echo $r['tipe']=='wajib'?'selected':''; ?>>Wajib</option>
                              <option value="sukarela" <?php echo $r['tipe']=='sukarela'?'selected':''; ?>>Sukarela</option>
                              <option value="harian" <?php echo $r['tipe']=='harian'?'selected':''; ?>>Harian</option>
                            </select>
                          </div>
                        </div>
                        <hr>
                        <div class="row g-3" id="fieldsEdit<?php echo $r['id']; ?>">
                          <div class="col-md-4 field-pokok field-wajib">
                            <label class="form-label">Setoran Awal</label>
                            <input name="setoran_awal" type="number" step="1000" class="form-control" value="<?php echo $r['setoran_awal'] ?? 0; ?>">
                          </div>
                          <div class="col-md-4 field-pokok field-wajib field-sukarela">
                            <label class="form-label">Setoran Minimal</label>
                            <input name="setoran_minimal" type="number" step="1000" class="form-control" value="<?php echo $r['setoran_minimal'] ?? 0; ?>">
                          </div>
                          <div class="col-md-4 field-pokok field-wajib">
                            <label class="form-label">Saldo Minimal</label>
                            <input name="saldo_minimal" type="number" step="1000" class="form-control" value="<?php echo $r['saldo_minimal'] ?? 0; ?>">
                          </div>
                          <div class="col-md-4 field-wajib">
                            <label class="form-label">Setoran per Bulan</label>
                            <input name="setoran_per_bulan" type="number" step="1000" class="form-control" value="<?php echo $r['setoran_per_bulan'] ?? 0; ?>">
                          </div>
                          <div class="col-md-4 field-sukarela">
                            <label class="form-label">Biaya Admin/Bulan</label>
                            <input name="biaya_admin_bulanan" type="number" step="1000" class="form-control" value="<?php echo $r['biaya_admin_bulanan'] ?? 0; ?>">
                          </div>
                          <div class="col-md-4">
                            <label class="form-label">Min Setor <small class="text-muted">(lama)</small></label>
                            <input name="min_setor" type="number" step="1000" class="form-control" value="<?php echo $r['min_setor']; ?>">
                          </div>
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
function toggleFieldsByTipe(suffix) {
  var select = document.getElementById('tipe' + suffix);
  if (!select) return;
  
  var tipe = select.value;
  var container = suffix === 'Add' ? document.getElementById('formAddProduk') : document.getElementById('fieldsEdit' + suffix.replace('Edit', ''));
  if (!container) container = select.closest('form');
  
  // Hide all dynamic fields first
  var allFields = container.querySelectorAll('.field-pokok, .field-wajib, .field-sukarela, .field-harian');
  allFields.forEach(function(el) {
    el.style.display = 'none';
  });
  
  // Show fields based on tipe
  var fieldsToShow = container.querySelectorAll('.field-' + tipe);
  fieldsToShow.forEach(function(el) {
    el.style.display = '';
  });
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
  toggleFieldsByTipe('Add');
  
  // Initialize for edit modals when they open
  document.querySelectorAll('.modal').forEach(function(modal) {
    modal.addEventListener('shown.bs.modal', function() {
      var select = modal.querySelector('select[id^="tipeEdit"]');
      if (select) {
        var id = select.id.replace('tipeEdit', '');
        toggleFieldsByTipe('Edit' + id);
      }
    });
  });
});
</script>

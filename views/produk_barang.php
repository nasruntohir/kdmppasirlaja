<?php
// views/produk_barang.php - Master Produk Barang (untuk pinjaman barang)
csrf_check();
$conn = db();

// Load kategori dan satuan untuk dropdown
$kategori_list = $conn->query("SELECT id, kode, nama FROM ksp_kategori_barang WHERE status='aktif' ORDER BY nama");
$satuan_list = $conn->query("SELECT id, kode, nama FROM ksp_satuan WHERE status='aktif' ORDER BY nama");
$kat_arr = []; while($k = $kategori_list->fetch_assoc()) $kat_arr[] = $k;
$sat_arr = []; while($s = $satuan_list->fetch_assoc()) $sat_arr[] = $s;

// Handle create
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='create') {
  $kode = trim($_POST['kode']);
  $nama = trim($_POST['nama']);
  $kategori_id = !empty($_POST['kategori_id']) ? (int)$_POST['kategori_id'] : null;
  $satuan = trim($_POST['satuan'] ?? 'unit');
  $satuan_id = !empty($_POST['satuan_id']) ? (int)$_POST['satuan_id'] : null;
  $harga_beli = (float)($_POST['harga_beli'] ?? 0);
  $harga_jual = (float)($_POST['harga_jual'] ?? 0);
  $stok = (int)($_POST['stok'] ?? 0);
  $stok_minimal = (int)($_POST['stok_minimal'] ?? 0);
  $keterangan = trim($_POST['keterangan'] ?? '');
  
  $stmt = $conn->prepare("INSERT INTO ksp_produk_barang(kode,nama,kategori_id,satuan,satuan_id,harga_beli,harga_jual,stok,stok_minimal,keterangan) VALUES(?,?,?,?,?,?,?,?,?,?)");
  $stmt->bind_param("ssissddiis", $kode, $nama, $kategori_id, $satuan, $satuan_id, $harga_beli, $harga_jual, $stok, $stok_minimal, $keterangan);
  if ($stmt->execute()) alert('success','Produk barang ditambah.'); 
  else alert('danger','Gagal: '.$conn->error);
  header("Location: index.php?m=produk_barang"); exit;
}

// Handle update
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='update') {
  $id = (int)$_POST['id'];
  $nama = trim($_POST['nama']);
  $kategori_id = !empty($_POST['kategori_id']) ? (int)$_POST['kategori_id'] : null;
  $satuan = trim($_POST['satuan'] ?? 'unit');
  $satuan_id = !empty($_POST['satuan_id']) ? (int)$_POST['satuan_id'] : null;
  $harga_beli = (float)($_POST['harga_beli'] ?? 0);
  $harga_jual = (float)($_POST['harga_jual'] ?? 0);
  $stok = (int)($_POST['stok'] ?? 0);
  $stok_minimal = (int)($_POST['stok_minimal'] ?? 0);
  $keterangan = trim($_POST['keterangan'] ?? '');
  $status = trim($_POST['status'] ?? 'aktif');
  
  $stmt = $conn->prepare("UPDATE ksp_produk_barang SET nama=?, kategori_id=?, satuan=?, satuan_id=?, harga_beli=?, harga_jual=?, stok=?, stok_minimal=?, keterangan=?, status=? WHERE id=?");
  $stmt->bind_param("sisisddissi", $nama, $kategori_id, $satuan, $satuan_id, $harga_beli, $harga_jual, $stok, $stok_minimal, $keterangan, $status, $id);
  if ($stmt->execute()) alert('success', 'Produk diperbarui.');
  else alert('danger', 'Gagal update: '.$conn->error);
  header("Location: index.php?m=produk_barang"); exit;
}

// Handle delete
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='delete') {
  $id = (int)$_POST['id'];
  $stmt = $conn->prepare("DELETE FROM ksp_produk_barang WHERE id=?");
  $stmt->bind_param("i", $id);
  if ($stmt->execute()) {
    alert('success', 'Produk barang dihapus.');
  } else {
    alert('danger', 'Gagal menghapus produk barang.');
  }
  header("Location: index.php?m=produk_barang"); exit;
}

// Handle stok adjustment
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='adjust_stok') {
  $id = (int)$_POST['id'];
  $jumlah = (int)$_POST['jumlah'];
  $tipe = $_POST['tipe_adjust']; // 'masuk' or 'keluar'
  
  if ($tipe === 'masuk') {
    $stmt = $conn->prepare("UPDATE ksp_produk_barang SET stok = stok + ? WHERE id=?");
  } else {
    $stmt = $conn->prepare("UPDATE ksp_produk_barang SET stok = stok - ? WHERE id=?");
  }
  $stmt->bind_param("ii", $jumlah, $id);
  if ($stmt->execute()) alert('success', 'Stok berhasil diupdate.');
  else alert('danger', 'Gagal update stok: '.$conn->error);
  header("Location: index.php?m=produk_barang"); exit;
}

$list = $conn->query("
  SELECT p.*, k.nama as kategori_nama, s.kode as satuan_kode 
  FROM ksp_produk_barang p 
  LEFT JOIN ksp_kategori_barang k ON k.id = p.kategori_id 
  LEFT JOIN ksp_satuan s ON s.id = p.satuan_id 
  ORDER BY p.id DESC
");
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-box-seam me-2"></i>Produk Barang</h4>
  <button class="btn btn-gradient" data-bs-toggle="collapse" data-bs-target="#formAdd"><i class="bi bi-plus-lg me-1"></i> Tambah</button>
</div>

<div class="alert alert-info">
  <i class="bi bi-info-circle me-1"></i> Produk barang digunakan untuk <strong>Pinjaman Barang</strong> kepada kelompok tani. Nilai pinjaman diambil dari harga jual.
</div>

<div id="formAdd" class="collapse mb-3">
  <div class="card card-widget">
    <div class="card-body">
      <form method="post">
        <?php echo csrf_input(); ?>
        <input type="hidden" name="action" value="create">
        <div class="row g-3">
          <div class="col-md-2"><label class="form-label">Kode</label><input name="kode" class="form-control" required placeholder="BRG001"></div>
          <div class="col-md-4"><label class="form-label">Nama Barang</label><input name="nama" class="form-control" required placeholder="Pupuk Urea"></div>
          <div class="col-md-3">
            <label class="form-label">Kategori</label>
            <select name="kategori_id" class="form-select">
              <option value="">-- Pilih Kategori --</option>
              <?php foreach($kat_arr as $k): ?>
              <option value="<?php echo $k['id']; ?>"><?php echo $k['nama']; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Satuan</label>
            <select name="satuan_id" class="form-select">
              <option value="">-- Pilih Satuan --</option>
              <?php foreach($sat_arr as $s): ?>
              <option value="<?php echo $s['id']; ?>"><?php echo $s['kode'].' - '.$s['nama']; ?></option>
              <?php endforeach; ?>
            </select>
            <input type="hidden" name="satuan" value="unit">
          </div>
        </div>
        <div class="row g-3 mt-1">
          <div class="col-md-2"><label class="form-label">Harga Beli</label><input name="harga_beli_display" type="text" class="form-control rupiah-input" value="Rp 0"><input name="harga_beli" type="hidden"></div>
          <div class="col-md-2"><label class="form-label">Harga Jual</label><input name="harga_jual_display" type="text" class="form-control rupiah-input" value="0"><input name="harga_jual" type="hidden"></div>
          <div class="col-md-2"><label class="form-label">Stok Awal</label><input name="stok" type="number" class="form-control" value="0"></div>
          <div class="col-md-2"><label class="form-label">Stok Minimal</label><input name="stok_minimal" type="number" class="form-control" value="10" title="Warning jika stok di bawah nilai ini"></div>
          <div class="col-md-4"><label class="form-label">Keterangan</label><input name="keterangan" class="form-control" placeholder="Keterangan produk (opsional)"></div>
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
        <thead><tr>
          <th>#</th><th>Kode</th><th>Nama</th><th>Kategori</th><th>Satuan</th><th>Harga Beli</th><th>Harga Jual</th><th>Stok</th><th>Status</th><th>Aksi</th>
        </tr></thead>
        <tbody>
        <?php $no=1; while($r=$list->fetch_assoc()): 
          $stok_min = $r['stok_minimal'] ?? 10;
        ?>
          <tr>
            <td><?php echo $no++; ?></td>
            <td><?php echo htmlspecialchars($r['kode']); ?></td>
            <td><?php echo htmlspecialchars($r['nama']); ?></td>
            <td><?php echo $r['kategori_nama'] ? '<span class="badge bg-secondary">'.$r['kategori_nama'].'</span>' : '-'; ?></td>
            <td><?php echo $r['satuan_kode'] ?? $r['satuan']; ?></td>
            <td>Rp <?php echo rupiah($r['harga_beli']); ?></td>
            <td>Rp <?php echo rupiah($r['harga_jual']); ?></td>
            <td>
              <?php if ($r['stok'] <= 0): ?>
              <span class="badge bg-danger" title="Habis"><?php echo $r['stok']; ?></span>
              <?php elseif ($r['stok'] <= $stok_min): ?>
              <span class="badge bg-warning" title="Di bawah stok minimal (<?php echo $stok_min; ?>)"><?php echo $r['stok']; ?></span>
              <?php else: ?>
              <span class="badge bg-success"><?php echo $r['stok']; ?></span>
              <?php endif; ?>
            </td>
            <td><span class="badge text-bg-<?php echo $r['status']=='aktif'?'success':'secondary'; ?>"><?php echo $r['status']; ?></span></td>
            <td>
              <div class="btn-group">
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editBarang<?php echo $r['id']; ?>" title="Edit"><i class="bi bi-pencil"></i></button>
                <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#adjustStok<?php echo $r['id']; ?>" title="Adjust Stok"><i class="bi bi-box-arrow-in-down"></i></button>
              </div>
              <form method="post" style="display:inline" id="deleteFormPB<?php echo $r['id']; ?>">
                <?php echo csrf_input(); ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                <button type="button" class="btn btn-sm btn-outline-danger" title="Hapus" onclick="confirmHapus('<?php echo htmlspecialchars($r['nama'], ENT_QUOTES); ?>', '<?php echo $r['kode']; ?>', 'deleteFormPB<?php echo $r['id']; ?>')"><i class="bi bi-trash"></i></button>
              </form>
              
              <!-- Edit Modal -->
              <div class="modal fade" id="editBarang<?php echo $r['id']; ?>" tabindex="-1">
                <div class="modal-dialog">
                  <div class="modal-content">
                    <form method="post">
                      <?php echo csrf_input(); ?>
                      <input type="hidden" name="action" value="update">
                      <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                      <div class="modal-header"><h5 class="modal-title">Edit Produk Barang</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                      <div class="modal-body">
                        <div class="mb-3"><label class="form-label">Kode</label><input class="form-control" value="<?php echo htmlspecialchars($r['kode']); ?>" disabled></div>
                        <div class="mb-3"><label class="form-label">Nama</label><input name="nama" class="form-control" value="<?php echo htmlspecialchars($r['nama']); ?>" required></div>
                        <div class="mb-3"><label class="form-label">Satuan</label><input name="satuan" class="form-control" value="<?php echo htmlspecialchars($r['satuan']); ?>"></div>
                        <div class="row">
                          <div class="col-md-6 mb-3"><label class="form-label">Harga Beli</label><input name="harga_beli_display" type="text" class="form-control rupiah-input" value="Rp <?php echo rupiah($r['harga_beli']); ?>"><input name="harga_beli" type="hidden" value="<?php echo $r['harga_beli']; ?>"></div>
                          <div class="col-md-6 mb-3"><label class="form-label">Harga Jual</label><input name="harga_jual_display" type="text" class="form-control rupiah-input" value="Rp <?php echo rupiah($r['harga_jual']); ?>"><input name="harga_jual" type="hidden" value="<?php echo $r['harga_jual']; ?>"></div>
                        </div>
                        <div class="mb-3"><label class="form-label">Stok</label><input name="stok" type="number" class="form-control" value="<?php echo $r['stok']; ?>"></div>
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
              
              <!-- Adjust Stok Modal -->
              <div class="modal fade" id="adjustStok<?php echo $r['id']; ?>" tabindex="-1">
                <div class="modal-dialog modal-sm">
                  <div class="modal-content">
                    <form method="post">
                      <?php echo csrf_input(); ?>
                      <input type="hidden" name="action" value="adjust_stok">
                      <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                      <div class="modal-header"><h5 class="modal-title">Adjust Stok</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                      <div class="modal-body">
                        <p class="mb-2"><strong><?php echo htmlspecialchars($r['nama']); ?></strong></p>
                        <p class="mb-3">Stok saat ini: <span class="badge bg-info"><?php echo $r['stok']; ?> <?php echo $r['satuan']; ?></span></p>
                        <div class="mb-3">
                          <label class="form-label">Tipe</label>
                          <select name="tipe_adjust" class="form-select">
                            <option value="masuk">📥 Barang Masuk</option>
                            <option value="keluar">📤 Barang Keluar</option>
                          </select>
                        </div>
                        <div class="mb-3"><label class="form-label">Jumlah</label><input name="jumlah" type="number" class="form-control" min="1" value="1" required></div>
                      </div>
                      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button class="btn btn-gradient">Update Stok</button></div>
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

<?php
// views/produk_pinjaman.php
csrf_check();
$conn = db();

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='create') {
  $kode = trim($_POST['kode']); $nama = trim($_POST['nama']);
  $tenor = (int)$_POST['tenor_hari_default']; $btipe = trim($_POST['bunga_tipe']);
  $bp = (int)$_POST['bunga_persen']; $pot_tab = isset($_POST['potongan_tabungan']) ? (int)$_POST['potongan_tabungan'] : 0;
  $admin = (int)$_POST['admin_biaya'];
  $tipe_pinjaman = trim($_POST['tipe_pinjaman'] ?? 'uang');
  $jenis_peminjam = trim($_POST['jenis_peminjam'] ?? 'perorangan');
  $cara_pelunasan = trim($_POST['cara_pelunasan'] ?? 'tunai');
  
  $stmt = $conn->prepare("INSERT INTO ksp_produk_pinjaman(kode,nama,tenor_hari_default,bunga_tipe,bunga_persen,potongan_tabungan,admin_biaya,tipe_pinjaman,jenis_peminjam,cara_pelunasan) VALUES(?,?,?,?,?,?,?,?,?,?)");
  $stmt->bind_param("ssissiisss", $kode, $nama, $tenor, $btipe, $bp, $pot_tab, $admin, $tipe_pinjaman, $jenis_peminjam, $cara_pelunasan);
  if ($stmt->execute()) alert('success','Produk pinjaman ditambah.'); else alert('danger','Gagal: '.$conn->error);
  header("Location: index.php?m=produk_pinjaman"); exit;
}
// Handle update
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='update') {
  $id = (int)$_POST['id'];
  $nama = trim($_POST['nama']);
  $tenor = (int)$_POST['tenor_hari_default'];
  $btipe = trim($_POST['bunga_tipe']);
  $bp = (int)$_POST['bunga_persen'];
  $pot_tab = isset($_POST['potongan_tabungan']) ? (int)$_POST['potongan_tabungan'] : 0;
  $admin = (int)$_POST['admin_biaya'];
  $tipe_pinjaman = trim($_POST['tipe_pinjaman'] ?? 'uang');
  $jenis_peminjam = trim($_POST['jenis_peminjam'] ?? 'perorangan');
  $cara_pelunasan = trim($_POST['cara_pelunasan'] ?? 'tunai');
  
  $stmt = $conn->prepare("UPDATE ksp_produk_pinjaman SET nama=?, tenor_hari_default=?, bunga_tipe=?, bunga_persen=?, potongan_tabungan=?, admin_biaya=?, tipe_pinjaman=?, jenis_peminjam=?, cara_pelunasan=? WHERE id=?");
  $stmt->bind_param("sissiisssi", $nama, $tenor, $btipe, $bp, $pot_tab, $admin, $tipe_pinjaman, $jenis_peminjam, $cara_pelunasan, $id);
  if ($stmt->execute()) alert('success', 'Produk diperbarui.');
  else alert('danger', 'Gagal update: '.$conn->error);
  header("Location: index.php?m=produk_pinjaman"); exit;
}

// Handle delete (POST with CSRF)
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='delete') {
  $id = (int)$_POST['id'];
  // Check FK constraint - apakah produk dipakai di pinjaman
  $check = $conn->query("SELECT COUNT(*) c FROM ksp_pinjaman WHERE produk_id=".$id);
  $has_pinjaman = $check ? (int)$check->fetch_assoc()['c'] : 0;
  if ($has_pinjaman > 0) {
    alert('danger', 'Tidak bisa hapus produk yang sudah dipakai di pinjaman.');
  } else {
    $stmt = $conn->prepare("DELETE FROM ksp_produk_pinjaman WHERE id=?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
      alert('success', 'Produk pinjaman dihapus.');
    } else {
      alert('danger', 'Gagal menghapus produk pinjaman.');
    }
  }
  header("Location: index.php?m=produk_pinjaman"); exit;
}
$list = $conn->query("SELECT * FROM ksp_produk_pinjaman ORDER BY id DESC");
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0">Produk Pinjaman</h4>
  <button class="btn btn-gradient" data-bs-toggle="collapse" data-bs-target="#formAdd"><i class="bi bi-plus-lg me-1"></i> Tambah</button>
</div>

<div id="formAdd" class="collapse mb-3">
  <div class="card card-widget">
    <div class="card-body">
      <form method="post">
        <?php echo csrf_input(); ?>
        <input type="hidden" name="action" value="create">
        <div class="row g-3">
          <div class="col-md-2"><label class="form-label">Kode</label><input name="kode" class="form-control" required></div>
          <div class="col-md-4"><label class="form-label">Nama</label><input name="nama" class="form-control" required></div>
          <div class="col-md-2">
            <label class="form-label">Tipe Pinjaman</label>
            <select name="tipe_pinjaman" class="form-select">
              <option value="uang" selected>💵 Uang</option>
              <option value="barang">📦 Barang</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label">Jenis Peminjam</label>
            <select name="jenis_peminjam" class="form-select">
              <option value="perorangan" selected>👤 Perorangan</option>
              <option value="kelompok">👥 Kelompok</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label">Cara Pelunasan</label>
            <select name="cara_pelunasan" class="form-select">
              <option value="tunai" selected>💰 Tunai/Cicil</option>
              <option value="offset">🔄 Offset (Pembelian)</option>
            </select>
          </div>
        </div>
        <div class="row g-3 mt-1">
          <div class="col-md-2"><label class="form-label">Tenor Default</label><input name="tenor_hari_default" type="number" class="form-control" value="24"></div>
          <div class="col-md-2">
            <label class="form-label">Tipe Angsuran</label>
            <select name="bunga_tipe" class="form-select">
              <option value="bulanan" selected>Bulanan</option>
              <option value="tempo">Tempo/Jatuh Tempo</option>
            </select>
          </div>
          <div class="col-md-2"><label class="form-label">Bunga (%)</label><input name="bunga_persen" type="number" step="1" class="form-control" value="20" placeholder="cth: 20"></div>
          <div class="col-md-2"><label class="form-label">Pot. Tabungan (%)</label><input name="potongan_tabungan" type="number" step="1" class="form-control" value="5" placeholder="cth: 5"></div>
          <div class="col-md-2"><label class="form-label">Biaya Admin (Rp)</label><input name="admin_biaya" type="number" step="1000" class="form-control" value="25000"></div>
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
        <thead><tr><th>#</th><th>Kode</th><th>Nama</th><th>Tipe</th><th>Jenis</th><th>Pelunasan</th><th>Angsuran</th><th>Bunga</th><th>Aksi</th></tr></thead>
        <tbody>
        <?php $no=1; while($r=$list->fetch_assoc()): 
          $tipe_angsuran = $r['bunga_tipe'] == 'tempo' ? 'Tempo' : 'Bulanan';
          $tenor_label = ($r['bunga_tipe'] == 'tempo' ? '-' : (int)$r['tenor_hari_default'].' bulan');
          $pot_tab = isset($r['potongan_tabungan']) ? $r['potongan_tabungan'] : 0;
          $tipe_pinjaman = $r['tipe_pinjaman'] ?? 'uang';
          $jenis_peminjam = $r['jenis_peminjam'] ?? 'perorangan';
          $cara_pelunasan = $r['cara_pelunasan'] ?? 'tunai';
        ?>
          <tr>
            <td><?php echo $no++; ?></td>
            <td><?php echo htmlspecialchars($r['kode']); ?></td>
            <td><?php echo htmlspecialchars($r['nama']); ?></td>
            <td><span class="badge <?php echo $tipe_pinjaman=='barang'?'bg-warning':'bg-success'; ?>"><?php echo $tipe_pinjaman=='barang'?'📦 Barang':'💵 Uang'; ?></span></td>
            <td><span class="badge <?php echo $jenis_peminjam=='kelompok'?'bg-info':'bg-secondary'; ?>"><?php echo $jenis_peminjam=='kelompok'?'👥 Kelompok':'👤 Perorangan'; ?></span></td>
            <td><span class="badge <?php echo $cara_pelunasan=='offset'?'bg-primary':'bg-dark'; ?>"><?php echo $cara_pelunasan=='offset'?'🔄 Offset':'💰 Tunai'; ?></span></td>
            <td><?php echo $tipe_angsuran; ?> (<?php echo $tenor_label; ?>)</td>
            <td><?php echo (int)$r['bunga_persen']; ?>%</td>
            <td>
              <button class="btn btn-sm btn-outline-primary me-1" data-bs-toggle="modal" data-bs-target="#editProduk<?php echo $r['id']; ?>"><i class="bi bi-pencil"></i></button>
              <form method="post" style="display:inline" id="deleteFormPP<?php echo $r['id']; ?>">
                <?php echo csrf_input(); ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmHapus('<?php echo htmlspecialchars($r['nama'], ENT_QUOTES); ?>', '<?php echo $r['kode']; ?>', 'deleteFormPP<?php echo $r['id']; ?>')"><i class="bi bi-trash"></i></button>
              </form>
              <!-- Edit Modal -->
              <div class="modal fade" id="editProduk<?php echo $r['id']; ?>" tabindex="-1">
                <div class="modal-dialog modal-lg">
                  <div class="modal-content">
                    <form method="post">
                      <?php echo csrf_input(); ?>
                      <input type="hidden" name="action" value="update">
                      <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                      <div class="modal-header"><h5 class="modal-title">Edit Produk Pinjaman</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                      <div class="modal-body">
                        <div class="row g-3">
                          <div class="col-md-4"><label class="form-label">Kode</label><input class="form-control" value="<?php echo htmlspecialchars($r['kode']); ?>" disabled></div>
                          <div class="col-md-8"><label class="form-label">Nama</label><input name="nama" class="form-control" value="<?php echo htmlspecialchars($r['nama']); ?>" required></div>
                        </div>
                        <hr>
                        <div class="row g-3">
                          <div class="col-md-4">
                            <label class="form-label">Tipe Pinjaman</label>
                            <select name="tipe_pinjaman" class="form-select">
                              <option value="uang" <?php echo $tipe_pinjaman=='uang'?'selected':''; ?>>💵 Uang</option>
                              <option value="barang" <?php echo $tipe_pinjaman=='barang'?'selected':''; ?>>📦 Barang</option>
                            </select>
                          </div>
                          <div class="col-md-4">
                            <label class="form-label">Jenis Peminjam</label>
                            <select name="jenis_peminjam" class="form-select">
                              <option value="perorangan" <?php echo $jenis_peminjam=='perorangan'?'selected':''; ?>>👤 Perorangan</option>
                              <option value="kelompok" <?php echo $jenis_peminjam=='kelompok'?'selected':''; ?>>👥 Kelompok</option>
                            </select>
                          </div>
                          <div class="col-md-4">
                            <label class="form-label">Cara Pelunasan</label>
                            <select name="cara_pelunasan" class="form-select">
                              <option value="tunai" <?php echo $cara_pelunasan=='tunai'?'selected':''; ?>>💰 Tunai/Cicil</option>
                              <option value="offset" <?php echo $cara_pelunasan=='offset'?'selected':''; ?>>🔄 Offset (Pembelian)</option>
                            </select>
                          </div>
                        </div>
                        <hr>
                        <div class="row g-3">
                          <div class="col-md-4"><label class="form-label">Tenor Default</label><input name="tenor_hari_default" type="number" class="form-control" value="<?php echo $r['tenor_hari_default']; ?>"></div>
                          <div class="col-md-4"><label class="form-label">Tipe Angsuran</label><select name="bunga_tipe" class="form-select"><option value="bulanan" <?php echo $r['bunga_tipe']=='bulanan'?'selected':''; ?>>Bulanan</option><option value="tempo" <?php echo $r['bunga_tipe']=='tempo'?'selected':''; ?>>Tempo</option></select></div>
                          <div class="col-md-4"><label class="form-label">Bunga (%)</label><input name="bunga_persen" type="number" step="1" class="form-control" value="<?php echo (int)$r['bunga_persen']; ?>"></div>
                          <div class="col-md-4"><label class="form-label">Pot. Tabungan (%)</label><input name="potongan_tabungan" type="number" step="1" class="form-control" value="<?php echo $pot_tab; ?>"></div>
                          <div class="col-md-4"><label class="form-label">Biaya Admin (Rp)</label><input name="admin_biaya" type="number" step="1000" class="form-control" value="<?php echo (int)$r['admin_biaya']; ?>"></div>
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

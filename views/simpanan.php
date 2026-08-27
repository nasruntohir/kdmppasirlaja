<?php
// views/simpanan.php - Transaksi Simpanan (Setor/Tarik)
csrf_check();
$conn = db();

// Generate submission token untuk mencegah double submit
if (empty($_SESSION['form_token'])) {
  $_SESSION['form_token'] = bin2hex(random_bytes(16));
}

// Handle setor/tarik
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action'])) {
  // Cek submission token untuk mencegah double submit
  $submitted_token = isset($_POST['form_token']) ? $_POST['form_token'] : '';
  if (empty($submitted_token) || $submitted_token !== $_SESSION['form_token']) {
    alert('warning', 'Form sudah disubmit sebelumnya. Silakan cek data.');
    header("Location: index.php?m=simpanan"); exit;
  }
  // Regenerate token setelah submit berhasil diproses
  $_SESSION['form_token'] = bin2hex(random_bytes(16));
  $action = $_POST['action'];
  
  if (in_array($action, array('setor','tarik'))) {
    $anggota_id = (int)$_POST['anggota_id'];
    $produk_id = (int)$_POST['produk_id'];
    $nominal = (float)$_POST['nominal'];
    $metode = in_array($_POST['metode'], array('tunai','transfer')) ? $_POST['metode'] : 'tunai';
    $catatan = trim($_POST['catatan']);
    $tanggal = trim($_POST['tanggal'] ?? date('Y-m-d'));
    $tanggal_obj = DateTime::createFromFormat('Y-m-d', $tanggal);
    $tanggal_valid = $tanggal_obj && $tanggal_obj->format('Y-m-d') === $tanggal;
    
    // Validasi
    if ($anggota_id <= 0 || $produk_id <= 0 || !$tanggal_valid) {
      alert('danger', 'Pilih anggota, produk simpanan, dan tanggal yang valid.');
      header("Location: index.php?m=simpanan"); exit;
    }
    if ($nominal <= 0) {
      alert('danger', 'Nominal harus lebih dari 0.');
      header("Location: index.php?m=simpanan"); exit;
    }
    
    // Cek min setor untuk setor
    if ($action === 'setor') {
      $prod = $conn->query("SELECT min_setor, tipe FROM ksp_produk_simpanan WHERE id=$produk_id")->fetch_assoc();
      if ($prod && $nominal < (float)$prod['min_setor']) {
        alert('danger', 'Nominal kurang dari minimum setor (Rp '.rupiah($prod['min_setor']).').');
        header("Location: index.php?m=simpanan"); exit;
      }
    }
    
    // Cek saldo untuk tarik
    if ($action === 'tarik') {
      $saldo = $conn->query("SELECT IFNULL(SUM(CASE WHEN tipe='setor' THEN nominal ELSE -nominal END),0) s FROM ksp_simpanan_tx WHERE anggota_id=$anggota_id AND produk_id=$produk_id")->fetch_assoc();
      $current_saldo = (float)$saldo['s'];
      if ($nominal > $current_saldo) {
        alert('danger', 'Saldo tidak cukup. Saldo saat ini: Rp '.rupiah($current_saldo));
        header("Location: index.php?m=simpanan"); exit;
      }
    }
    
    // Insert transaksi
    $tipe = $action;
    $stmt = $conn->prepare("INSERT INTO ksp_simpanan_tx(tanggal, anggota_id, produk_id, tipe, nominal, metode, user_id, catatan) VALUES(?,?,?,?,?,?,?,?)");
    $uid = user()['id'];
    $stmt->bind_param("siisdsis", $tanggal, $anggota_id, $produk_id, $tipe, $nominal, $metode, $uid, $catatan);
    
    if ($stmt->execute()) {
      $ref_id = $stmt->insert_id;
      $produk_tipe = $prod['tipe'] ?? 'sukarela';
      $jenis_simpanan = ($produk_tipe === 'pokok') ? 'pokok' : (($produk_tipe === 'wajib') ? 'wajib' : 'sukarela');
      $current = $conn->query("SELECT * FROM ksp_simpanan_anggota WHERE anggota_id = $anggota_id")->fetch_assoc();
      if (!$current) {
        $conn->query("INSERT INTO ksp_simpanan_anggota (anggota_id) VALUES ($anggota_id)");
        $current = ['simpanan_pokok' => 0, 'simpanan_wajib' => 0, 'simpanan_sukarela' => 0];
      }

      $field = 'simpanan_' . $jenis_simpanan;
      $current_val = (float)$current[$field];
      $new_val = ($action === 'setor') ? $current_val + $nominal : $current_val - $nominal;

      $updated = [
        'simpanan_pokok' => (float)$current['simpanan_pokok'],
        'simpanan_wajib' => (float)$current['simpanan_wajib'],
        'simpanan_sukarela' => (float)$current['simpanan_sukarela'],
      ];
      $updated[$field] = $new_val;
      $total = $updated['simpanan_pokok'] + $updated['simpanan_wajib'] + $updated['simpanan_sukarela'];

      $conn->query("UPDATE ksp_simpanan_anggota SET $field = $new_val, total_simpanan = $total WHERE anggota_id = $anggota_id");

      $stmt3 = $conn->prepare("INSERT INTO ksp_mutasi_simpanan (anggota_id, tanggal, jenis_simpanan, tipe, nominal, saldo_akhir, keterangan, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
      $keterangan = $catatan !== '' ? $catatan : 'Transaksi simpanan';
      $stmt3->bind_param("isssddsi", $anggota_id, $tanggal, $jenis_simpanan, $action, $nominal, $new_val, $keterangan, $uid);
      $stmt3->execute();

      // Insert ke kas
      $jenis_kas = ($action === 'setor') ? 'masuk' : 'keluar';
      $sumber_kas = ($action === 'setor') ? 'simpanan' : 'penarikan';
      $no_kas = gen_number('prefix_no_kas');
      $anggota = $conn->query("SELECT nama FROM ksp_anggota WHERE id=$anggota_id")->fetch_assoc();
      $cat_kas = ucfirst($action).' simpanan '.$anggota['nama'];
      
      $stmt2 = $conn->prepare("INSERT INTO ksp_kas(no_kas,tanggal,jenis,sumber,ref_table,ref_id,nominal,user_id,catatan) VALUES(?,?,?,?,?,?,?,?,?)");
      $ref_table = 'ksp_simpanan_tx';
      $stmt2->bind_param("sssssidis", $no_kas, $tanggal, $jenis_kas, $sumber_kas, $ref_table, $ref_id, $nominal, $uid, $cat_kas);
      $stmt2->execute();
      
      alert('success', ucfirst($action).' simpanan berhasil dicatat.');
    } else {
      alert('danger', 'Gagal menyimpan: '.$conn->error);
      // Kembalikan token jika gagal agar bisa retry
      $_SESSION['form_token'] = $submitted_token;
    }
    header("Location: index.php?m=simpanan"); exit;
  }
}

// Data untuk form
$anggota_list = $conn->query("SELECT id, no_anggota, nama FROM ksp_anggota WHERE status='aktif' ORDER BY nama");
$produk_list = $conn->query("SELECT * FROM ksp_produk_simpanan ORDER BY id");

// handler hapus
if ($_SERVER['REQUEST_METHOD'] === 'POST'
&& ($_POST['action']?? '') === 'delete_mutasi') {
  csrf_check();

  $mutasi_id =  (int) ($_POST['mutasi_id'] ?? 0);

  $mutasi = $conn->query("SELECT * FROM ksp_mutasi_simpanan WHERE id = $mutasi_id") ->fetch_assoc();

  if(!$mutasi){
    alert('danger', 'Transaksi tidak ditemukan');
    header('Location: index.php?m=simpanan_anggota');
    exit;
  }

  $anggota_id = (int) $mutasi['anggota_id'];
  $jenis = $mutasi['jenis_simpanan']; // pokok/wajib/sukarela
  $field = 'simpanan_' . $jenis;
  $nominal = (float) $mutasi['nominal'];

  // Setor dihapus = saldo dikurangi, tarik dihapus = saldo dikembalikan
  $delta = $mutasi['tipe'] === 'setor' ? -$nominal : $nominal;

  $conn->begin_transaction();;

  try{
    $stmt = $conn->prepare("UPDATE ksp_simpanan_anggota SET $field = $field + ? WHERE anggota_id = ? ");
    $stmt->bind_param('di', $delta, $anggota_id);
    $stmt->execute();

    $conn->query(" UPDATE ksp_mutasi_simpanan SET total_simpanan =
    COALESCE(simpanan_pokok, 0) +
    COALESCE(simpanan_waajib, 0) +
    COALESCE(simpanan_sukarela, 0)
    WHERE anggota_id = $anggota_id ");

    $stmt = $conn->prepare("DELETE FROM ksp_mutasi_simpanan WHERE id = ?");
    $stmt->bind_param('i', $mutasi_id);
    $stmt->execute();

    $conn->commit();
    alert('success', 'Transaksi simpanan berhasil dihapus.');
  } catch(Throwable $e){
    $conn->rollback();
    alert('danger', 'Gagal menghapus transaksi simpanan.');
  }
  header('Location: index.php?m=simpanan_anggota&id=' . $anggota_id);
  exit;
}

// Cari saldo anggota
$selected_anggota = isset($_GET['anggota_id']) ? (int)$_GET['anggota_id'] : 0;
$saldo_list = null;
$saldo_from_simpanan_anggota = null;
if ($selected_anggota > 0) {
  // Saldo dari transaksi simpanan (ksp_simpanan_tx)
  $saldo_list = $conn->query("
    SELECT ps.id, ps.kode, ps.nama, ps.tipe,
           IFNULL(SUM(CASE WHEN st.tipe='setor' THEN st.nominal ELSE -st.nominal END),0) as saldo
    FROM ksp_produk_simpanan ps
    LEFT JOIN ksp_simpanan_tx st ON st.produk_id=ps.id AND st.anggota_id=$selected_anggota
    GROUP BY ps.id
    ORDER BY ps.id
  ");
  
  // Saldo dari simpanan anggota (ksp_simpanan_anggota) - data pokok/wajib/sukarela
  $saldo_from_simpanan_anggota = $conn->query("
    SELECT 
      COALESCE(simpanan_pokok, 0) as simpanan_pokok,
      COALESCE(simpanan_wajib, 0) as simpanan_wajib,
      COALESCE(simpanan_sukarela, 0) as simpanan_sukarela,
      COALESCE(total_simpanan, 0) as total_simpanan
    FROM ksp_simpanan_anggota 
    WHERE anggota_id = $selected_anggota
  ")->fetch_assoc();
}
?>
<h4>Transaksi Simpanan</h4>

<!-- Form Setor/Tarik -->
<div class="row g-3 mb-3">
  <div class="col-md-6">
    <div class="card card-widget h-100">
      <div class="card-body">
        <h6 class="text-success"><i class="bi bi-arrow-down-circle me-2"></i>Setor Simpanan</h6>
        <form method="post" onsubmit="return disableSubmitButton(this);">
          <?php echo csrf_input(); ?>
          <input type="hidden" name="form_token" value="<?php echo htmlspecialchars($_SESSION['form_token']); ?>">
          <input type="hidden" name="action" value="setor">
          <div class="mb-2">
            <label class="form-label">Anggota</label>
            <select name="anggota_id" class="form-select select2" required>
              <option value="">- pilih anggota -</option>
              <?php 
              $anggota_list->data_seek(0);
              while($a=$anggota_list->fetch_assoc()): ?>
                <option value="<?php echo $a['id']; ?>"><?php echo htmlspecialchars($a['nama'].' ('.$a['no_anggota'].')'); ?></option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="mb-2">
            <label class="form-label">Produk Simpanan</label>
            <select name="produk_id" class="form-select" required>
              <option value="">- pilih produk -</option>
              <?php 
              $produk_list->data_seek(0);
              while($p=$produk_list->fetch_assoc()): ?>
                <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['nama'].' - Min: Rp '.rupiah($p['min_setor'])); ?></option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="mb-2">
            <label class="form-label">Tanggal Transaksi</label>
            <input type="date" name="tanggal" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
          </div>
          <div class="mb-2">
            <label class="form-label">Nominal (Rp)</label>
            <input type="text" name="nominal_display" class="form-control rupiah-input" data-target="nominal" inputmode="numeric" autocomplete="off" required>
            <input type="hidden" name="nominal">
          </div>
          <div class="mb-2">
            <label class="form-label">Metode</label>
            <select name="metode" class="form-select">
              <option value="tunai">Tunai</option>
              <option value="transfer">Transfer</option>
            </select>
          </div>
          <div class="mb-2">
            <label class="form-label">Catatan</label>
            <input name="catatan" class="form-control" placeholder="Opsional">
          </div>
          <button type="submit" class="btn btn-success w-100" onclick="return confirmTransaksi(this.form, 'setor', this.form.nominal.value)"><i class="bi bi-check-lg me-1"></i>Setor</button>
        </form>
      </div>
    </div>
  </div>
  
  <div class="col-md-6">
    <div class="card card-widget h-100">
      <div class="card-body">
        <h6 class="text-danger"><i class="bi bi-arrow-up-circle me-2"></i>Tarik Simpanan</h6>
        <form method="post" onsubmit="return disableSubmitButton(this);">
          <?php echo csrf_input(); ?>
          <input type="hidden" name="form_token" value="<?php echo htmlspecialchars($_SESSION['form_token']); ?>">
          <input type="hidden" name="action" value="tarik">
          <div class="mb-2">
            <label class="form-label">Anggota</label>
            <select name="anggota_id" class="form-select select2" required>
              <option value="">- pilih anggota -</option>
              <?php 
              $anggota_list->data_seek(0);
              while($a=$anggota_list->fetch_assoc()): ?>
                <option value="<?php echo $a['id']; ?>"><?php echo htmlspecialchars($a['nama'].' ('.$a['no_anggota'].')'); ?></option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="mb-2">
            <label class="form-label">Produk Simpanan</label>
            <select name="produk_id" class="form-select" required>
              <option value="">- pilih produk -</option>
              <?php 
              $produk_list->data_seek(0);
              while($p=$produk_list->fetch_assoc()): ?>
                <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['nama']); ?></option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="mb-2">
            <label class="form-label">Tanggal Transaksi</label>
            <input type="date" name="tanggal" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
          </div>
          <div class="mb-2">
            <label class="form-label">Nominal (Rp)</label>
            <input type="text" name="nominal_display" class="form-control rupiah-input" data-target="nominal" inputmode="numeric" autocomplete="off" required>
            <input type="hidden" name="nominal">
          </div>
          <div class="mb-2">
            <label class="form-label">Metode</label>
            <select name="metode" class="form-select">
              <option value="tunai">Tunai</option>
              <option value="transfer">Transfer</option>
            </select>
          </div>
          <div class="mb-2">
            <label class="form-label">Catatan</label>
            <input name="catatan" class="form-control" placeholder="Opsional">
          </div>
          <button type="submit" class="btn btn-danger w-100" onclick="return confirmTransaksi(this.form, 'tarik', this.form.nominal.value)"><i class="bi bi-check-lg me-1"></i>Tarik</button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Cek Saldo -->
<div class="card card-widget mb-3">
  <div class="card-body">
    <h6>Cek Saldo Anggota</h6>
    <form class="row g-2" method="get">
      <input type="hidden" name="m" value="simpanan">
      <div class="col-md-4">
        <select name="anggota_id" class="form-select">
          <option value="">- pilih anggota -</option>
          <?php 
          $anggota_list->data_seek(0);
          while($a=$anggota_list->fetch_assoc()): ?>
            <option value="<?php echo $a['id']; ?>" <?php echo $selected_anggota==$a['id']?'selected':''; ?>><?php echo htmlspecialchars($a['nama'].' ('.$a['no_anggota'].')'); ?></option>
          <?php endwhile; ?>
        </select>
      </div>
      <div class="col-md-2"><button class="btn btn-gradient w-100">Cek Saldo</button></div>
    </form>
    <?php if ($saldo_list || $saldo_from_simpanan_anggota): ?>
    <div class="table-responsive mt-3">
      <table class="table table-striped table-sm">
        <thead><tr><th>Kode</th><th>Produk</th><th>Tipe</th><th class="text-end">Saldo</th></tr></thead>
        <tbody>
        <?php 
        $total = 0;
        
        // Tampilkan saldo dari ksp_simpanan_anggota (Pokok/Wajib/Sukarela)
        if ($saldo_from_simpanan_anggota): 
          $sa = $saldo_from_simpanan_anggota;
          if ((float)$sa['simpanan_pokok'] > 0 || (float)$sa['simpanan_wajib'] > 0 || (float)$sa['simpanan_sukarela'] > 0):
        ?>
          <tr class="table-info">
            <td colspan="4"><strong><i class="bi bi-wallet2 me-1"></i>Simpanan Anggota (Pokok/Wajib/Sukarela)</strong></td>
          </tr>
          <tr>
            <td>SPK</td>
            <td>Simpanan Pokok</td>
            <td>pokok</td>
            <td class="text-end">Rp <?php echo rupiah($sa['simpanan_pokok']); ?></td>
          </tr>
          <?php $total += (float)$sa['simpanan_pokok']; ?>
          <tr>
            <td>SWJ</td>
            <td>Simpanan Wajib</td>
            <td>wajib</td>
            <td class="text-end">Rp <?php echo rupiah($sa['simpanan_wajib']); ?></td>
          </tr>
          <?php $total += (float)$sa['simpanan_wajib']; ?>
          <tr>
            <td>SSK</td>
            <td>Simpanan Sukarela</td>
            <td>sukarela</td>
            <td class="text-end">Rp <?php echo rupiah($sa['simpanan_sukarela']); ?></td>
          </tr>
          <?php $total += (float)$sa['simpanan_sukarela']; ?>
        <?php endif; endif; ?>
        
        <?php 
        // Tampilkan saldo dari ksp_simpanan_tx (Transaksi Produk Simpanan)
        if ($saldo_list):
          $has_tx_saldo = false;
          $tx_rows = [];
          while($s=$saldo_list->fetch_assoc()) {
            if ((float)$s['saldo'] != 0) {
              $has_tx_saldo = true;
              $tx_rows[] = $s;
              $total += (float)$s['saldo'];
            }
          }
          if ($has_tx_saldo):
        ?>
          <tr class="table-warning">
            <td colspan="4"><strong><i class="bi bi-cash-stack me-1"></i>Transaksi Simpanan (Produk)</strong></td>
          </tr>
          <?php foreach($tx_rows as $s): ?>
          <tr>
            <td><?php echo htmlspecialchars($s['kode']); ?></td>
            <td><?php echo htmlspecialchars($s['nama']); ?></td>
            <td><?php echo htmlspecialchars($s['tipe']); ?></td>
            <td class="text-end">Rp <?php echo rupiah($s['saldo']); ?></td>
          </tr>
          <?php endforeach; ?>
        <?php endif; endif; ?>
        
          <tr class="table-dark">
            <td colspan="3"><strong>Total Semua Simpanan</strong></td>
            <td class="text-end"><strong>Rp <?php echo rupiah($total); ?></strong></td>
          </tr>
        </tbody>
      </table>
    </div>
    <?php elseif ($selected_anggota > 0): ?>
    <div class="alert alert-info mt-3"><i class="bi bi-info-circle me-2"></i>Anggota ini belum memiliki simpanan.</div>
    <?php endif; ?>
  </div>
</div>

<!-- Riwayat Transaksi -->
<div class="card card-widget">
  <div class="card-body">
    <h6>Riwayat Transaksi (30 Terbaru)</h6>
    <div class="table-responsive">
      <table class="table table-striped table-sm">
        <thead><tr><th>Tanggal</th><th>Anggota</th><th>Produk</th><th>Tipe</th><th>Metode</th><th class="text-end">Nominal</th></tr></thead>
        <tbody>
        <?php
        $tx = $conn->query("SELECT st.*, a.nama as anggota_nama, ps.nama as produk_nama 
                           FROM ksp_simpanan_tx st 
                           JOIN ksp_anggota a ON a.id=st.anggota_id 
                           JOIN ksp_produk_simpanan ps ON ps.id=st.produk_id 
                           ORDER BY st.id DESC LIMIT 30");
        while($r=$tx->fetch_assoc()): ?>
          <tr>
            <td><?php echo htmlspecialchars($r['tanggal']); ?></td>
            <td><?php echo htmlspecialchars($r['anggota_nama']); ?></td>
            <td><?php echo htmlspecialchars($r['produk_nama']); ?></td>
            <td><span class="badge <?php echo $r['tipe']=='setor'?'text-bg-success':'text-bg-danger'; ?>"><?php echo $r['tipe']; ?></span></td>
            <td><?php echo htmlspecialchars($r['metode']); ?></td>
            <td class="text-end">Rp <?php echo rupiah($r['nominal']); ?></td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

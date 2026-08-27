<?php
// views/penjualan.php - Transaksi Penjualan (POS Sederhana)
csrf_check();
$conn = db();

// Load data
$produk_list = $conn->query("SELECT id, kode, nama, satuan, satuan_id, harga_jual, stok FROM ksp_produk_barang WHERE status='aktif' AND stok > 0 ORDER BY nama");
$produk_arr = []; while($p = $produk_list->fetch_assoc()) $produk_arr[] = $p;

$anggota_list = $conn->query("SELECT id, no_anggota, nama FROM ksp_anggota WHERE status='aktif' ORDER BY nama");
$anggota_arr = []; while($a = $anggota_list->fetch_assoc()) $anggota_arr[] = $a;

$satuan_list = $conn->query("SELECT id, kode, nama FROM ksp_satuan WHERE status='aktif' ORDER BY kode");
$satuan_arr = []; while($s = $satuan_list->fetch_assoc()) $satuan_arr[] = $s;

// Handle create penjualan
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='create') {
  $customer_tipe = $_POST['customer_tipe'];
  $anggota_id = ($customer_tipe == 'anggota' && !empty($_POST['anggota_id'])) ? (int)$_POST['anggota_id'] : null;
  $nama_customer = ($customer_tipe == 'umum') ? trim($_POST['nama_customer'] ?? 'Umum') : null;
  $diskon = (float)($_POST['diskon'] ?? 0);
  $jumlah_bayar = (float)($_POST['jumlah_bayar'] ?? 0);
  $metode_bayar = $_POST['metode_bayar'] ?? 'tunai';
  $keterangan = trim($_POST['keterangan'] ?? '');
  $uid = user()['id'];
  
  $no_penjualan = 'POS-' . date('Ymd') . '-' . str_pad(rand(1,9999), 4, '0', STR_PAD_LEFT);
  $tanggal = date('Y-m-d H:i:s');
  
  $items = $_POST['items'] ?? [];
  if (empty($items)) {
    alert('danger', 'Minimal harus ada 1 item barang.');
    header("Location: index.php?m=penjualan"); exit;
  }
  
  // Calculate total
  $total_nilai = 0;
  foreach ($items as $item) {
    $subtotal = (float)$item['jumlah'] * (float)$item['harga'];
    $subtotal -= (float)($item['diskon_item'] ?? 0);
    $total_nilai += $subtotal;
  }
  $total_bayar = $total_nilai - $diskon;
  $kembalian = $jumlah_bayar - $total_bayar;
  if ($kembalian < 0) $kembalian = 0;
  
  // Validate stock
  $stok_error = false;
  foreach ($items as $item) {
    $produk_id = (int)$item['produk_id'];
    $jumlah = (float)$item['jumlah'];
    $stok = $conn->query("SELECT stok FROM ksp_produk_barang WHERE id=$produk_id")->fetch_assoc()['stok'] ?? 0;
    if ($jumlah > $stok) {
      alert('danger', 'Stok tidak cukup untuk salah satu produk.');
      $stok_error = true;
      break;
    }
  }
  if ($stok_error) { header("Location: index.php?m=penjualan"); exit; }
  
  // Insert header
  $stmt = $conn->prepare("INSERT INTO ksp_penjualan(no_penjualan, tanggal, customer_tipe, anggota_id, nama_customer, total_nilai, diskon, total_bayar, jumlah_bayar, kembalian, metode_bayar, keterangan, created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)");
  $stmt->bind_param("sssisdddddssi", $no_penjualan, $tanggal, $customer_tipe, $anggota_id, $nama_customer, $total_nilai, $diskon, $total_bayar, $jumlah_bayar, $kembalian, $metode_bayar, $keterangan, $uid);
  
  if ($stmt->execute()) {
    $penjualan_id = $conn->insert_id;
    
    foreach ($items as $item) {
      $produk_id = (int)$item['produk_id'];
      $jumlah = (float)$item['jumlah'];
      $satuan_id = !empty($item['satuan_id']) ? (int)$item['satuan_id'] : null;
      $harga = (float)($item['harga'] ?? 0);
      $diskon_item = (float)($item['diskon_item'] ?? 0);
      $subtotal = ($jumlah * $harga) - $diskon_item;
      
      // Calculate konversi
      $jumlah_konversi = $jumlah;
      if ($satuan_id) {
        $prod = $conn->query("SELECT satuan_id FROM ksp_produk_barang WHERE id=$produk_id")->fetch_assoc();
        if ($prod && $prod['satuan_id'] && $prod['satuan_id'] != $satuan_id) {
          $konv = $conn->query("SELECT nilai_konversi FROM ksp_konversi_satuan WHERE satuan_dari_id=$satuan_id AND satuan_ke_id=".$prod['satuan_id'])->fetch_assoc();
          if ($konv) $jumlah_konversi = $jumlah * $konv['nilai_konversi'];
        }
      }
      
      // Insert detail
      $stmt2 = $conn->prepare("INSERT INTO ksp_penjualan_detail(penjualan_id, produk_id, jumlah, satuan_id, harga_satuan, diskon_item, subtotal, jumlah_konversi) VALUES(?,?,?,?,?,?,?,?)");
      $stmt2->bind_param("iididddd", $penjualan_id, $produk_id, $jumlah, $satuan_id, $harga, $diskon_item, $subtotal, $jumlah_konversi);
      $stmt2->execute();
      
      // Update stok (kurangi)
      $conn->query("UPDATE ksp_produk_barang SET stok = stok - $jumlah_konversi WHERE id = $produk_id");
      
      // Insert kartu stok
      $saldo = $conn->query("SELECT stok FROM ksp_produk_barang WHERE id=$produk_id")->fetch_assoc()['stok'];
      $stmt3 = $conn->prepare("INSERT INTO ksp_kartu_stok(produk_id, tanggal, tipe, referensi, jumlah, satuan_id, jumlah_konversi, saldo_akhir, harga_satuan, keterangan, created_by) VALUES(?,?,'keluar',?,?,?,?,?,?,?,?)");
      $tgl_now = date('Y-m-d');
      $ket_stok = "Penjualan $no_penjualan";
      $stmt3->bind_param("issdiiddsi", $produk_id, $tgl_now, $no_penjualan, $jumlah, $satuan_id, $jumlah_konversi, $saldo, $harga, $ket_stok, $uid);
      $stmt3->execute();
    }
    
    // Record kas masuk
    $stmt_kas = $conn->prepare("INSERT INTO ksp_kas(no_kas, tanggal, jenis, sumber, ref_table, ref_id, nominal, user_id, catatan) VALUES(?,?,'masuk','penjualan','ksp_penjualan',?,?,?,?)");
    $no_kas = gen_number('prefix_no_kas');
    $tgl_now = date('Y-m-d');
    $catatan = "Penjualan $no_penjualan";
    $stmt_kas->bind_param("ssidis", $no_kas, $tgl_now, $penjualan_id, $total_bayar, $uid, $catatan);
    $stmt_kas->execute();
    
    // Set session for print
    $_SESSION['print_penjualan_id'] = $penjualan_id;
    
    alert('success', "Penjualan $no_penjualan berhasil. <a href='index.php?m=penjualan&print=$penjualan_id' class='alert-link'>Cetak Struk</a>");
  } else {
    alert('danger', 'Gagal: '.$conn->error);
  }
  header("Location: index.php?m=penjualan"); exit;
}

// Handle void/batal
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='void') {
  $id = (int)$_POST['id'];
  
  // Restore stok
  $details = $conn->query("SELECT produk_id, jumlah_konversi FROM ksp_penjualan_detail WHERE penjualan_id=$id");
  while ($d = $details->fetch_assoc()) {
    $conn->query("UPDATE ksp_produk_barang SET stok = stok + ".$d['jumlah_konversi']." WHERE id = ".$d['produk_id']);
  }
  
  // Get no_penjualan for kartu stok cleanup
  $pjl = $conn->query("SELECT no_penjualan FROM ksp_penjualan WHERE id=$id")->fetch_assoc();
  if ($pjl) {
    $conn->query("DELETE FROM ksp_kartu_stok WHERE referensi='".$pjl['no_penjualan']."'");
    $conn->query("DELETE FROM ksp_kas WHERE ref_table='ksp_penjualan' AND ref_id=$id");
  }
  
  $conn->query("UPDATE ksp_penjualan SET status='batal' WHERE id=$id");
  alert('success', 'Penjualan dibatalkan.');
  header("Location: index.php?m=penjualan"); exit;
}

// Print struk
if (isset($_GET['print'])) {
  $print_id = (int)$_GET['print'];
  $pjl = $conn->query("SELECT p.*, a.nama as anggota_nama FROM ksp_penjualan p LEFT JOIN ksp_anggota a ON a.id=p.anggota_id WHERE p.id=$print_id")->fetch_assoc();
  if ($pjl):
  $details = $conn->query("SELECT d.*, pr.nama as produk_nama, pr.kode as produk_kode FROM ksp_penjualan_detail d JOIN ksp_produk_barang pr ON pr.id=d.produk_id WHERE d.penjualan_id=$print_id");
?>
<!DOCTYPE html>
<html>
<head>
  <title>Struk <?php echo $pjl['no_penjualan']; ?></title>
  <style>
    body { font-family: 'Courier New', monospace; font-size: 12px; width: 280px; margin: 0 auto; }
    .center { text-align: center; }
    .right { text-align: right; }
    hr { border: none; border-top: 1px dashed #000; }
    table { width: 100%; }
    td { vertical-align: top; }
    .item-name { max-width: 150px; }
    @media print { body { margin: 0; } }
  </style>
</head>
<body onload="window.print()">
  <div class="center">
    <strong><?php echo setting_get('app_name') ?: 'KSP Harian'; ?></strong><br>
    <?php echo setting_get('alamat') ?: ''; ?><br>
    <?php echo setting_get('telepon') ?: ''; ?>
  </div>
  <hr>
  <table>
    <tr><td>No</td><td>: <?php echo $pjl['no_penjualan']; ?></td></tr>
    <tr><td>Tgl</td><td>: <?php echo date('d/m/Y H:i', strtotime($pjl['tanggal'])); ?></td></tr>
    <tr><td>Customer</td><td>: <?php echo $pjl['customer_tipe']=='anggota' ? $pjl['anggota_nama'] : ($pjl['nama_customer'] ?: 'Umum'); ?></td></tr>
  </table>
  <hr>
  <table>
    <?php while($d = $details->fetch_assoc()): ?>
    <tr>
      <td class="item-name"><?php echo $d['produk_nama']; ?></td>
      <td class="right"><?php echo number_format($d['jumlah']); ?> x <?php echo number_format($d['harga_satuan']); ?></td>
    </tr>
    <tr><td colspan="2" class="right"><?php echo number_format($d['subtotal']); ?></td></tr>
    <?php endwhile; ?>
  </table>
  <hr>
  <table>
    <tr><td>Subtotal</td><td class="right"><?php echo number_format($pjl['total_nilai']); ?></td></tr>
    <?php if ($pjl['diskon'] > 0): ?>
    <tr><td>Diskon</td><td class="right">-<?php echo number_format($pjl['diskon']); ?></td></tr>
    <?php endif; ?>
    <tr><td><strong>TOTAL</strong></td><td class="right"><strong><?php echo number_format($pjl['total_bayar']); ?></strong></td></tr>
    <tr><td>Bayar</td><td class="right"><?php echo number_format($pjl['jumlah_bayar']); ?></td></tr>
    <tr><td>Kembali</td><td class="right"><?php echo number_format($pjl['kembalian']); ?></td></tr>
  </table>
  <hr>
  <div class="center">
    Terima Kasih<br>
    Selamat Berbelanja Kembali
  </div>
</body>
</html>
<?php exit; endif; }

$list = $conn->query("SELECT p.*, a.nama as anggota_nama FROM ksp_penjualan p LEFT JOIN ksp_anggota a ON a.id=p.anggota_id ORDER BY p.id DESC LIMIT 100");
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-shop me-2"></i>Penjualan (POS)</h4>
  <button class="btn btn-gradient btn-lg" data-bs-toggle="modal" data-bs-target="#modalPOS"><i class="bi bi-plus-lg me-1"></i> Transaksi Baru</button>
</div>

<!-- POS Modal -->
<div class="modal fade" id="modalPOS" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <form method="post" id="formPOS">
        <?php echo csrf_input(); ?>
        <input type="hidden" name="action" value="create">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title"><i class="bi bi-shop me-2"></i>Transaksi Penjualan</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row">
            <!-- Left: Items -->
            <div class="col-md-8">
              <div class="row g-2 mb-3">
                <div class="col-md-4">
                  <label class="form-label">Customer</label>
                  <select name="customer_tipe" id="customerTipe" class="form-select">
                    <option value="umum">Umum</option>
                    <option value="anggota">Anggota Koperasi</option>
                  </select>
                </div>
                <div class="col-md-4 d-none" id="anggotaDiv">
                  <label class="form-label">Pilih Anggota</label>
                  <select name="anggota_id" id="anggotaId" class="form-select">
                    <option value="">-- Pilih --</option>
                    <?php foreach($anggota_arr as $a): ?>
                    <option value="<?php echo $a['id']; ?>"><?php echo $a['no_anggota'].' - '.$a['nama']; ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-4" id="namaCustomerDiv">
                  <label class="form-label">Nama Customer</label>
                  <input name="nama_customer" class="form-control" value="Umum">
                </div>
              </div>
              
              <div class="mb-2">
                <label class="form-label">Cari Produk</label>
                <select id="searchProduk" class="form-select" style="width:100%">
                  <option value="">-- Ketik untuk mencari produk --</option>
                  <?php foreach($produk_arr as $p): ?>
                  <option value="<?php echo $p['id']; ?>" data-harga="<?php echo $p['harga_jual']; ?>" data-stok="<?php echo $p['stok']; ?>" data-nama="<?php echo htmlspecialchars($p['nama']); ?>" data-kode="<?php echo $p['kode']; ?>"><?php echo $p['kode'].' - '.$p['nama'].' (Stok: '.$p['stok'].')'; ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              
              <table class="table table-bordered table-sm" id="tblPOS">
                <thead class="table-dark">
                  <tr><th>Produk</th><th width="80">Qty</th><th width="120">Harga</th><th width="100">Diskon</th><th width="120">Subtotal</th><th width="40"></th></tr>
                </thead>
                <tbody id="posBody"></tbody>
              </table>
            </div>
            
            <!-- Right: Summary -->
            <div class="col-md-4">
              <div class="card bg-light">
                <div class="card-body">
                  <h5 class="text-center mb-3">Ringkasan</h5>
                  <table class="table table-sm table-borderless mb-0">
                    <tr><td width="40%">Subtotal:</td><td class="text-end" id="posSubtotal"><strong>Rp 0</strong></td></tr>
                    <tr>
                      <td>Diskon:</td>
                      <td><input type="text" name="diskon_display" id="posDiskonDisplay" class="form-control form-control-sm text-end rupiah-input" data-target="diskon" value="0" inputmode="numeric" autocomplete="off">
                      <input type="hidden" name="diskon" id="posDiskon" value="0">  </td>
                    </tr>
                    <tr class="fs-4 table-primary"><td><strong>TOTAL:</strong></td><td class="text-end text-primary" id="posTotal"><strong>Rp 0</strong></td></tr>
                  </table>
                  <hr>
                  <div class="mb-2">
                    <label class="form-label">Metode Bayar</label>
                    <select name="metode_bayar" class="form-select">
                      <option value="tunai">Tunai</option>
                      <option value="transfer">Transfer</option>
                    </select>
                  </div>
                  <div class="mb-2">
                    <label class="form-label">Jumlah Bayar</label>
                    <input type="text" name="jumlah_bayar_display" id="posJumlahBayarDisplay" class="form-control form-control-lg text-end rupiah-input" data-target="jumlah_bayar" value="0" inputmode="numeric" autocomplete="off">
                    <input type="hidden" name="jumlah_bayar" id="posJumlahBayar" value="0">
                  </div>
                  <div class="mb-2">
                    <label class="form-label">Kembalian</label>
                    <input type="text" id="posKembalian" class="form-control form-control-lg text-end bg-success text-white" readonly value="Rp 0">
                  </div>
                  <div class="mb-2">
                    <label class="form-label">Keterangan</label>
                    <input name="keterangan" class="form-control" placeholder="Opsional">
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-success btn-lg"><i class="bi bi-check-lg me-1"></i> Bayar & Simpan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- History -->
<div class="card card-widget">
  <div class="card-header bg-light d-flex justify-content-between">
    <strong>Riwayat Penjualan</strong>
    <a href="index.php?m=laporan_penjualan" class="btn btn-sm btn-outline-primary"><i class="bi bi-graph-up me-1"></i> Laporan</a>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-striped datatable">
        <thead><tr><th>#</th><th>No. Penjualan</th><th>Waktu</th><th>Customer</th><th>Total</th><th>Bayar</th><th>Status</th><th>Aksi</th></tr></thead>
        <tbody>
        <?php $no=1; while($r=$list->fetch_assoc()): ?>
          <tr class="<?php echo $r['status']=='batal'?'table-danger':''; ?>">
            <td><?php echo $no++; ?></td>
            <td><strong><?php echo $r['no_penjualan']; ?></strong></td>
            <td><?php echo date('d/m/Y H:i', strtotime($r['tanggal'])); ?></td>
            <td>
              <?php if ($r['customer_tipe'] == 'anggota'): ?>
              <span class="badge bg-success">Anggota</span> <?php echo $r['anggota_nama']; ?>
              <?php else: ?>
              <?php echo $r['nama_customer'] ?: 'Umum'; ?>
              <?php endif; ?>
            </td>
            <td>Rp <?php echo rupiah($r['total_bayar']); ?></td>
            <td><?php echo $r['metode_bayar']=='tunai'?'💵':'🏦'; ?> Rp <?php echo rupiah($r['jumlah_bayar']); ?></td>
            <td><span class="badge text-bg-<?php echo $r['status']=='selesai'?'success':'danger'; ?>"><?php echo $r['status']; ?></span></td>
            <td>
              <?php if ($r['status'] == 'selesai'): ?>
              <a href="index.php?m=penjualan&print=<?php echo $r['id']; ?>" target="_blank" class="btn btn-sm btn-outline-info"><i class="bi bi-printer"></i></a>
              <form method="post" style="display:inline" id="voidForm<?php echo $r['id']; ?>">
                <?php echo csrf_input(); ?>
                <input type="hidden" name="action" value="void">
                <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmHapus('Void Penjualan', '<?php echo $r['no_penjualan']; ?>', 'voidForm<?php echo $r['id']; ?>')"><i class="bi bi-x-circle"></i></button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  let rowIndex = 0;
  const produkData = <?php echo json_encode($produk_arr); ?>;
  
  // Initialize Select2 inside modal with dropdownParent
  $('#modalPOS').on('shown.bs.modal', function() {
    $('#searchProduk').select2({
      dropdownParent: $('#modalPOS'),
      placeholder: '-- Ketik untuk mencari produk --',
      allowClear: true,
      width: '100%'
    });
    $('#anggotaId').select2({
      dropdownParent: $('#modalPOS'),
      placeholder: '-- Pilih Anggota --',
      allowClear: true,
      width: '100%'
    });
  });
  
  // Customer type toggle
  document.getElementById('customerTipe').addEventListener('change', function() {
    if (this.value === 'anggota') {
      document.getElementById('anggotaDiv').classList.remove('d-none');
      document.getElementById('namaCustomerDiv').classList.add('d-none');
    } else {
      document.getElementById('anggotaDiv').classList.add('d-none');
      document.getElementById('namaCustomerDiv').classList.remove('d-none');
    }
  });
  
  // Add product - use jQuery for Select2 compatibility
  $('#searchProduk').on('select2:select', function(e) {
    const data = e.params.data;
    const opt = data.element;
    if (!opt || !opt.value) return;
    
    const produkId = opt.value;
    const harga = opt.dataset.harga || 0;
    const nama = opt.dataset.nama || data.text.split(' - ')[1]?.split(' (')[0] || '';
    const kode = opt.dataset.kode || data.text.split(' - ')[0] || '';
    const stok = opt.dataset.stok || 0;
    
    // Check if already exists
    const existing = document.querySelector(`input[name="items[${produkId}][produk_id]"]`);
    if (existing) {
      const qtyInput = existing.closest('tr').querySelector('.qty-input');
      qtyInput.value = parseInt(qtyInput.value) + 1;
      calculateRow(existing.closest('tr'));
      $(this).val(null).trigger('change');
      return;
    }
    
    const tbody = document.getElementById('posBody');
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <input type="hidden" name="items[${produkId}][produk_id]" value="${produkId}">
      <input type="hidden" name="items[${produkId}][satuan_id]" value="">
      <td><strong>${kode}</strong> - ${nama}<br><small class="text-muted">Stok: ${stok}</small></td>
      <td><input type="number" name="items[${produkId}][jumlah]" class="form-control form-control-sm qty-input" value="1" min="1" max="${stok}"></td>
      <td><input type="number" name="items[${produkId}][harga]" class="form-control form-control-sm harga-input" value="${harga}" min="0"></td>
      <td><input type="number" name="items[${produkId}][diskon_item]" class="form-control form-control-sm diskon-input" value="0" min="0"></td>
      <td class="subtotal-cell text-end"><strong>Rp ${Number(harga).toLocaleString('id-ID')}</strong></td>
      <td><button type="button" class="btn btn-sm btn-danger btn-remove"><i class="bi bi-trash"></i></button></td>
    `;
    tbody.appendChild(tr);
    
    bindRowEvents(tr);
    calculateTotal();
    $(this).val(null).trigger('change');
  });
  
  // Remove row
  document.getElementById('posBody').addEventListener('click', function(e) {
    if (e.target.closest('.btn-remove')) {
      e.target.closest('tr').remove();
      calculateTotal();
    }
  });
  
  // Diskon change
  document.getElementById('posDiskon').addEventListener('input', calculateTotal);
  
  // Jumlah bayar change
  document.getElementById('posJumlahBayarDisplay').addEventListener('input', function() {
    syncRupiahInput(this);
    updateKembalian();
  });
  
  function bindRowEvents(tr) {
    tr.querySelectorAll('.qty-input, .harga-input, .diskon-input').forEach(inp => {
      inp.addEventListener('input', () => calculateRow(tr));
    });
  }
  
  function calculateRow(tr) {
    const qty = parseFloat(tr.querySelector('.qty-input').value) || 0;
    const harga = parseFloat(tr.querySelector('.harga-input').value) || 0;
    const diskon = parseFloat(tr.querySelector('.diskon-input').value) || 0;
    const subtotal = (qty * harga) - diskon;
    tr.querySelector('.subtotal-cell').textContent = 'Rp ' + subtotal.toLocaleString('id-ID');
    calculateTotal();
  }
  
  function calculateTotal() {
    let subtotal = 0;
    document.querySelectorAll('#posBody tr').forEach(tr => {
      const qty = parseFloat(tr.querySelector('.qty-input').value) || 0;
      const harga = parseFloat(tr.querySelector('.harga-input').value) || 0;
      const diskon = parseFloat(tr.querySelector('.diskon-input').value) || 0;
      subtotal += (qty * harga) - diskon;
    });
    
    const diskonTotal = parseFloat(document.getElementById('posDiskon').value) || 0;
    const total = subtotal - diskonTotal;
    
    document.getElementById('posSubtotal').textContent = 'Rp ' + subtotal.toLocaleString('id-ID');
    document.getElementById('posTotal').innerHTML = '<strong>Rp ' + total.toLocaleString('id-ID') + '</strong>';
    document.getElementById('posTotal').dataset.value = total;
    
    updateKembalian();
  }

  function updateKembalian() {
    const total = parseFloat(document.getElementById('posTotal').dataset.value) || 0;
    const bayar = parseFloat(document.getElementById('posJumlahBayar').value) || 0;
    const kembalian = bayar - total;
    document.getElementById('posKembalian').value = 'Rp ' + Math.max(0, kembalian).toLocaleString('id-ID');
  }
});
</script>

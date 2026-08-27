<?php
// views/pembelian.php - Transaksi Pembelian
csrf_check();
$conn = db();

// Load data
$supplier_list = $conn->query("SELECT id, kode, nama FROM ksp_supplier WHERE status='aktif' ORDER BY nama");
$supplier_arr = []; while($s = $supplier_list->fetch_assoc()) $supplier_arr[] = $s;

$kelompok_list = $conn->query("SELECT id, kode, nama FROM ksp_kelompok WHERE status='aktif' ORDER BY nama");
$kelompok_arr = []; while($k = $kelompok_list->fetch_assoc()) $kelompok_arr[] = $k;

$produk_list = $conn->query("SELECT id, kode, nama, satuan_id, harga_beli FROM ksp_produk_barang WHERE status='aktif' ORDER BY nama");
$produk_arr = []; while($p = $produk_list->fetch_assoc()) $produk_arr[] = $p;

$satuan_list = $conn->query("SELECT id, kode, nama FROM ksp_satuan WHERE status='aktif' ORDER BY kode");
$satuan_arr = []; while($s = $satuan_list->fetch_assoc()) $satuan_arr[] = $s;

// Handle create pembelian
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='create') {
  $tanggal = $_POST['tanggal'];
  $sumber_tipe = $_POST['sumber_tipe'];
  $supplier_id = !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;
  $kelompok_id = !empty($_POST['kelompok_id']) ? (int)$_POST['kelompok_id'] : null;
  $no_faktur = trim($_POST['no_faktur'] ?? '');
  $cara_bayar = $_POST['cara_bayar'];
  $pinjaman_id = ($cara_bayar == 'non_tunai' && !empty($_POST['pinjaman_id'])) ? (int)$_POST['pinjaman_id'] : null;
  $keterangan = trim($_POST['keterangan'] ?? '');
  $uid = user()['id'];
  
  $no_pembelian = 'PBL-' . date('Ymd') . '-' . str_pad(rand(1,9999), 4, '0', STR_PAD_LEFT);
  
  $items = $_POST['items'] ?? [];
  if (empty($items)) {
    alert('danger', 'Minimal harus ada 1 item barang.');
    header("Location: index.php?m=pembelian"); exit;
  }
  
  // Calculate total
  $total = 0;
  foreach ($items as $item) {
    $total += (float)$item['jumlah'] * (float)$item['harga'];
  }
  
  // Insert header
  $stmt = $conn->prepare("INSERT INTO ksp_pembelian(no_pembelian, tanggal, sumber_tipe, supplier_id, kelompok_id, no_faktur, cara_bayar, pinjaman_id, total_nilai, keterangan, created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?)");
  $stmt->bind_param("sssiisisssi", $no_pembelian, $tanggal, $sumber_tipe, $supplier_id, $kelompok_id, $no_faktur, $cara_bayar, $pinjaman_id, $total, $keterangan, $uid);
  
  if ($stmt->execute()) {
    $pembelian_id = $conn->insert_id;
    
    foreach ($items as $item) {
      $produk_id = (int)$item['produk_id'];
      $jumlah = (float)$item['jumlah'];
      $satuan_id = !empty($item['satuan_id']) ? (int)$item['satuan_id'] : null;
      $harga = (float)($item['harga'] ?? 0);
      $subtotal = $jumlah * $harga;
      
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
      $stmt2 = $conn->prepare("INSERT INTO ksp_pembelian_detail(pembelian_id, produk_id, jumlah, satuan_id, harga_satuan, subtotal, jumlah_konversi) VALUES(?,?,?,?,?,?,?)");
      $stmt2->bind_param("iididdd", $pembelian_id, $produk_id, $jumlah, $satuan_id, $harga, $subtotal, $jumlah_konversi);
      $stmt2->execute();
      
      // Update stok
      $conn->query("UPDATE ksp_produk_barang SET stok = stok + $jumlah_konversi WHERE id = $produk_id");
      
      // Insert kartu stok
      $saldo = $conn->query("SELECT stok FROM ksp_produk_barang WHERE id=$produk_id")->fetch_assoc()['stok'];
      $stmt3 = $conn->prepare("INSERT INTO ksp_kartu_stok(produk_id, tanggal, tipe, referensi, jumlah, satuan_id, jumlah_konversi, saldo_akhir, harga_satuan, keterangan, created_by) VALUES(?,?,'masuk',?,?,?,?,?,?,?,?)");
      $ket_stok = "Pembelian $no_pembelian";
      $stmt3->bind_param("issdiiddsi", $produk_id, $tanggal, $no_pembelian, $jumlah, $satuan_id, $jumlah_konversi, $saldo, $harga, $ket_stok, $uid);
      $stmt3->execute();
    }
    
    // Handle payment
    if ($cara_bayar == 'tunai') {
      // Record kas keluar
      $stmt_kas = $conn->prepare("INSERT INTO ksp_kas(no_kas, tanggal, jenis, sumber, ref_table, ref_id, nominal, user_id, catatan) VALUES(?,?,'keluar','pembelian','ksp_pembelian',?,?,?,?)");
      $no_kas = gen_number('prefix_no_kas');
      $catatan = "Pembelian $no_pembelian";
      $stmt_kas->bind_param("ssidis", $no_kas, $tanggal, $pembelian_id, $total, $uid, $catatan);
      $stmt_kas->execute();
    } else {
      // Non-tunai: offset pinjaman
      if ($pinjaman_id) {
        // Get pinjaman info
        $pinjaman = $conn->query("SELECT * FROM ksp_pinjaman WHERE id=$pinjaman_id")->fetch_assoc();
        if ($pinjaman) {
          // Update sisa pinjaman
          $sisa_baru = $pinjaman['sisa_pinjaman'] - $total;
          if ($sisa_baru < 0) $sisa_baru = 0;
          $conn->query("UPDATE ksp_pinjaman SET sisa_pinjaman = $sisa_baru WHERE id = $pinjaman_id");
          
          // Mark jadwal angsuran as paid (find unpaid schedules up to total amount)
          $remaining = $total;
          $jadwal = $conn->query("SELECT * FROM ksp_jadwal_angsuran WHERE pinjaman_id=$pinjaman_id AND status='belum' ORDER BY tanggal_jatuh_tempo");
          while ($j = $jadwal->fetch_assoc()) {
            if ($remaining <= 0) break;
            $bayar = min($remaining, $j['total_tagih']);
            $remaining -= $bayar;
            $conn->query("UPDATE ksp_jadwal_angsuran SET status='lunas', tanggal_bayar='$tanggal', jumlah_bayar=$bayar WHERE id=".$j['id']);
          }
          
          // Check if fully paid
          $cek_lunas = $conn->query("SELECT COUNT(*) as cnt FROM ksp_jadwal_angsuran WHERE pinjaman_id=$pinjaman_id AND status='belum'")->fetch_assoc();
          if ($cek_lunas['cnt'] == 0) {
            $conn->query("UPDATE ksp_pinjaman SET status='lunas' WHERE id=$pinjaman_id");
          }
        }
      }
    }
    
    alert('success', "Pembelian $no_pembelian berhasil disimpan.");
  } else {
    alert('danger', 'Gagal: '.$conn->error);
  }
  header("Location: index.php?m=pembelian"); exit;
}

// Handle delete
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='delete') {
  $id = (int)$_POST['id'];
  
  // Rollback stok
  $details = $conn->query("SELECT produk_id, jumlah_konversi FROM ksp_pembelian_detail WHERE pembelian_id=$id");
  while ($d = $details->fetch_assoc()) {
    $conn->query("UPDATE ksp_produk_barang SET stok = stok - ".$d['jumlah_konversi']." WHERE id = ".$d['produk_id']);
  }
  
  // Get no_pembelian for kartu stok cleanup
  $pbl = $conn->query("SELECT no_pembelian FROM ksp_pembelian WHERE id=$id")->fetch_assoc();
  if ($pbl) {
    $conn->query("DELETE FROM ksp_kartu_stok WHERE referensi='".$pbl['no_pembelian']."'");
  }
  
  $conn->query("DELETE FROM ksp_pembelian WHERE id=$id");
  alert('success', 'Pembelian dihapus.');
  header("Location: index.php?m=pembelian"); exit;
}

$list = $conn->query("
  SELECT p.*, s.nama as supplier_nama, k.nama as kelompok_nama 
  FROM ksp_pembelian p 
  LEFT JOIN ksp_supplier s ON s.id = p.supplier_id 
  LEFT JOIN ksp_kelompok k ON k.id = p.kelompok_id 
  ORDER BY p.id DESC LIMIT 100
");
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-cart-plus me-2"></i>Transaksi Pembelian</h4>
  <button class="btn btn-gradient" data-bs-toggle="collapse" data-bs-target="#formAdd"><i class="bi bi-plus-lg me-1"></i> Tambah Pembelian</button>
</div>

<div class="alert alert-info">
  <i class="bi bi-info-circle me-1"></i> Pembelian barang dari <strong>Supplier</strong> atau <strong>Kelompok Tani</strong>. 
  Jika cara bayar <strong>Non-Tunai</strong>, nilai pembelian akan di-offset ke pinjaman kelompok.
</div>

<div id="formAdd" class="collapse mb-3">
  <div class="card card-widget">
    <div class="card-header bg-light"><strong>Form Pembelian Baru</strong></div>
    <div class="card-body">
      <form method="post" id="formPembelian">
        <?php echo csrf_input(); ?>
        <input type="hidden" name="action" value="create">
        
        <div class="row g-3 mb-3">
          <div class="col-md-2">
            <label class="form-label">Tanggal</label>
            <input name="tanggal" type="date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
          </div>
          <div class="col-md-2">
            <label class="form-label">Sumber</label>
            <select name="sumber_tipe" id="sumberTipe" class="form-select" required>
              <option value="supplier">Supplier</option>
              <option value="kelompok">Kelompok Tani</option>
            </select>
          </div>
          <div class="col-md-3" id="supplierDiv">
            <label class="form-label">Supplier</label>
            <select name="supplier_id" id="supplierId" class="form-select">
              <option value="">-- Pilih Supplier --</option>
              <?php foreach($supplier_arr as $s): ?>
              <option value="<?php echo $s['id']; ?>"><?php echo $s['kode'].' - '.$s['nama']; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3 d-none" id="kelompokDiv">
            <label class="form-label">Kelompok Tani</label>
            <select name="kelompok_id" id="kelompokId" class="form-select">
              <option value="">-- Pilih Kelompok --</option>
              <?php foreach($kelompok_arr as $k): ?>
              <option value="<?php echo $k['id']; ?>"><?php echo $k['kode'].' - '.$k['nama']; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label">No. Faktur</label>
            <input name="no_faktur" class="form-control" placeholder="Opsional">
          </div>
          <div class="col-md-3">
            <label class="form-label">Keterangan</label>
            <input name="keterangan" class="form-control" placeholder="Opsional">
          </div>
        </div>
        
        <div class="row g-3 mb-3">
          <div class="col-md-2">
            <label class="form-label">Cara Bayar</label>
            <select name="cara_bayar" id="caraBayar" class="form-select" required>
              <option value="tunai">Tunai (Transfer)</option>
              <option value="non_tunai">Non-Tunai (Offset Pinjaman)</option>
            </select>
          </div>
          <div class="col-md-4 d-none" id="pinjamanDiv">
            <label class="form-label">Pinjaman untuk Offset</label>
            <select name="pinjaman_id" id="pinjamanId" class="form-select">
              <option value="">-- Pilih Pinjaman --</option>
            </select>
            <small class="text-muted">Hanya pinjaman dengan cara pelunasan Non-Tunai</small>
          </div>
        </div>
        
        <hr>
        <h6><i class="bi bi-list-ul me-1"></i> Detail Barang</h6>
        
        <table class="table table-bordered table-sm" id="tblItems">
          <thead class="table-light">
            <tr>
              <th width="30%">Produk</th>
              <th width="15%">Jumlah</th>
              <th width="15%">Satuan</th>
              <th width="20%">Harga Satuan</th>
              <th width="15%">Subtotal</th>
              <th width="5%"></th>
            </tr>
          </thead>
          <tbody id="itemsBody">
            <tr class="item-row">
              <td>
                <select name="items[0][produk_id]" class="form-select form-select-sm produk-select" required>
                  <option value="">-- Pilih Produk --</option>
                  <?php foreach($produk_arr as $p): ?>
                  <option value="<?php echo $p['id']; ?>" data-harga="<?php echo $p['harga_beli']; ?>"><?php echo $p['kode'].' - '.$p['nama']; ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td><input type="number" name="items[0][jumlah]" class="form-control form-control-sm jumlah-input" min="0.01" step="0.01" value="1" required></td>
              <td>
                <select name="items[0][satuan_id]" class="form-select form-select-sm">
                  <?php foreach($satuan_arr as $s): ?>
                  <option value="<?php echo $s['id']; ?>"><?php echo $s['kode']; ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td><input type="number" name="items[0][harga]" class="form-control form-control-sm harga-input" min="0" step="100" value="0"></td>
              <td><input type="text" class="form-control form-control-sm subtotal-display" readonly value="0"></td>
              <td><button type="button" class="btn btn-sm btn-outline-danger btn-remove-row"><i class="bi bi-trash"></i></button></td>
            </tr>
          </tbody>
          <tfoot>
            <tr>
              <td colspan="4" class="text-end"><strong>Total:</strong></td>
              <td><input type="text" id="grandTotal" class="form-control form-control-sm" readonly value="0"></td>
              <td></td>
            </tr>
          </tfoot>
        </table>
        
        <button type="button" class="btn btn-sm btn-outline-primary mb-3" id="btnAddRow"><i class="bi bi-plus-lg me-1"></i> Tambah Baris</button>
        
        <div class="mt-3">
          <button type="submit" class="btn btn-gradient"><i class="bi bi-save me-1"></i> Simpan Pembelian</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="card card-widget">
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-striped datatable">
        <thead><tr><th>#</th><th>No. Pembelian</th><th>Tanggal</th><th>Sumber</th><th>Cara Bayar</th><th>Total</th><th>Aksi</th></tr></thead>
        <tbody>
        <?php $no=1; while($r=$list->fetch_assoc()): ?>
          <tr>
            <td><?php echo $no++; ?></td>
            <td><strong><?php echo $r['no_pembelian']; ?></strong></td>
            <td><?php echo date('d/m/Y', strtotime($r['tanggal'])); ?></td>
            <td>
              <?php if ($r['sumber_tipe'] == 'supplier'): ?>
              <span class="badge bg-primary">Supplier</span> <?php echo $r['supplier_nama'] ?? '-'; ?>
              <?php else: ?>
              <span class="badge bg-success">Kelompok</span> <?php echo $r['kelompok_nama'] ?? '-'; ?>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($r['cara_bayar'] == 'tunai'): ?>
              <span class="badge bg-info">Tunai</span>
              <?php else: ?>
              <span class="badge bg-warning text-dark">Non-Tunai</span>
              <?php endif; ?>
            </td>
            <td>Rp <?php echo rupiah($r['total_nilai']); ?></td>
            <td>
              <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#detailPbl<?php echo $r['id']; ?>"><i class="bi bi-eye"></i></button>
              <form method="post" style="display:inline" id="deleteFormPbl<?php echo $r['id']; ?>">
                <?php echo csrf_input(); ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmHapus('Pembelian', '<?php echo $r['no_pembelian']; ?>', 'deleteFormPbl<?php echo $r['id']; ?>')"><i class="bi bi-trash"></i></button>
              </form>
              
              <!-- Detail Modal -->
              <div class="modal fade" id="detailPbl<?php echo $r['id']; ?>" tabindex="-1">
                <div class="modal-dialog modal-lg">
                  <div class="modal-content">
                    <div class="modal-header">
                      <h5 class="modal-title">Detail Pembelian: <?php echo $r['no_pembelian']; ?></h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                      <div class="row mb-3">
                        <div class="col-md-3"><strong>Tanggal:</strong> <?php echo date('d/m/Y', strtotime($r['tanggal'])); ?></div>
                        <div class="col-md-3"><strong>Sumber:</strong> <?php echo $r['sumber_tipe']=='supplier' ? ($r['supplier_nama'] ?? '-') : ($r['kelompok_nama'] ?? '-'); ?></div>
                        <div class="col-md-3"><strong>No. Faktur:</strong> <?php echo $r['no_faktur'] ?: '-'; ?></div>
                        <div class="col-md-3"><strong>Cara Bayar:</strong> <?php echo $r['cara_bayar']=='tunai' ? 'Tunai' : 'Non-Tunai (Offset)'; ?></div>
                      </div>
                      <table class="table table-sm table-bordered">
                        <thead><tr><th>Produk</th><th>Jumlah</th><th>Satuan</th><th>Harga</th><th>Subtotal</th></tr></thead>
                        <tbody>
                        <?php 
                        $details = $conn->query("SELECT d.*, p.nama as produk_nama, p.kode as produk_kode, s.kode as satuan_kode FROM ksp_pembelian_detail d LEFT JOIN ksp_produk_barang p ON p.id=d.produk_id LEFT JOIN ksp_satuan s ON s.id=d.satuan_id WHERE d.pembelian_id=".$r['id']);
                        while($d = $details->fetch_assoc()): 
                        ?>
                        <tr>
                          <td><?php echo $d['produk_kode'].' - '.$d['produk_nama']; ?></td>
                          <td><?php echo number_format($d['jumlah'],2); ?></td>
                          <td><?php echo $d['satuan_kode'] ?? '-'; ?></td>
                          <td>Rp <?php echo rupiah($d['harga_satuan']); ?></td>
                          <td>Rp <?php echo rupiah($d['subtotal']); ?></td>
                        </tr>
                        <?php endwhile; ?>
                        </tbody>
                        <tfoot>
                          <tr><th colspan="4" class="text-end">Total:</th><th>Rp <?php echo rupiah($r['total_nilai']); ?></th></tr>
                        </tfoot>
                      </table>
                    </div>
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
document.addEventListener('DOMContentLoaded', function() {
  let rowIndex = 1;
  const produkOptions = <?php echo json_encode($produk_arr); ?>;
  const satuanOptions = <?php echo json_encode($satuan_arr); ?>;
  
  // Toggle sumber
  document.getElementById('sumberTipe').addEventListener('change', function() {
    if (this.value === 'supplier') {
      document.getElementById('supplierDiv').classList.remove('d-none');
      document.getElementById('kelompokDiv').classList.add('d-none');
      document.getElementById('supplierId').required = true;
      document.getElementById('kelompokId').required = false;
    } else {
      document.getElementById('supplierDiv').classList.add('d-none');
      document.getElementById('kelompokDiv').classList.remove('d-none');
      document.getElementById('supplierId').required = false;
      document.getElementById('kelompokId').required = true;
    }
    // Load pinjaman if kelompok
    loadPinjaman();
  });
  
  // Toggle cara bayar
  document.getElementById('caraBayar').addEventListener('change', function() {
    if (this.value === 'non_tunai') {
      document.getElementById('pinjamanDiv').classList.remove('d-none');
      loadPinjaman();
    } else {
      document.getElementById('pinjamanDiv').classList.add('d-none');
    }
  });
  
  // Load pinjaman for offset
  document.getElementById('kelompokId').addEventListener('change', loadPinjaman);
  
  function loadPinjaman() {
    const kelompokId = document.getElementById('kelompokId').value;
    const caraBayar = document.getElementById('caraBayar').value;
    const pinjamanSelect = document.getElementById('pinjamanId');
    
    if (caraBayar !== 'non_tunai' || !kelompokId) {
      pinjamanSelect.innerHTML = '<option value="">-- Pilih Pinjaman --</option>';
      return;
    }
    
    // AJAX load pinjaman non-tunai for kelompok
    fetch('api/get_pinjaman_nontunai.php?kelompok_id=' + kelompokId)
      .then(r => r.json())
      .then(data => {
        let html = '<option value="">-- Pilih Pinjaman --</option>';
        data.forEach(p => {
          html += `<option value="${p.id}">${p.no_pinjaman} - Sisa: Rp ${Number(p.sisa_pinjaman).toLocaleString('id-ID')}</option>`;
        });
        pinjamanSelect.innerHTML = html;
      });
  }
  
  // Add row
  document.getElementById('btnAddRow').addEventListener('click', function() {
    const tbody = document.getElementById('itemsBody');
    const tr = document.createElement('tr');
    tr.className = 'item-row';
    tr.innerHTML = `
      <td>
        <select name="items[${rowIndex}][produk_id]" class="form-select form-select-sm produk-select" required>
          <option value="">-- Pilih Produk --</option>
          ${produkOptions.map(p => `<option value="${p.id}" data-harga="${p.harga_beli}">${p.kode} - ${p.nama}</option>`).join('')}
        </select>
      </td>
      <td><input type="number" name="items[${rowIndex}][jumlah]" class="form-control form-control-sm jumlah-input" min="0.01" step="0.01" value="1" required></td>
      <td>
        <select name="items[${rowIndex}][satuan_id]" class="form-select form-select-sm">
          ${satuanOptions.map(s => `<option value="${s.id}">${s.kode}</option>`).join('')}
        </select>
      </td>
      <td><input type="number" name="items[${rowIndex}][harga]" class="form-control form-control-sm harga-input" min="0" step="100" value="0"></td>
      <td><input type="text" class="form-control form-control-sm subtotal-display" readonly value="0"></td>
      <td><button type="button" class="btn btn-sm btn-outline-danger btn-remove-row"><i class="bi bi-trash"></i></button></td>
    `;
    tbody.appendChild(tr);
    rowIndex++;
    bindEvents();
  });
  
  // Remove row
  document.getElementById('itemsBody').addEventListener('click', function(e) {
    if (e.target.closest('.btn-remove-row')) {
      const rows = document.querySelectorAll('.item-row');
      if (rows.length > 1) {
        e.target.closest('tr').remove();
        calculateTotal();
      }
    }
  });
  
  function bindEvents() {
    document.querySelectorAll('.produk-select').forEach(sel => {
      sel.onchange = function() {
        const opt = this.options[this.selectedIndex];
        const harga = opt.dataset.harga || 0;
        const row = this.closest('tr');
        row.querySelector('.harga-input').value = harga;
        calculateRow(row);
      };
    });
    
    document.querySelectorAll('.jumlah-input, .harga-input').forEach(inp => {
      inp.oninput = function() { calculateRow(this.closest('tr')); };
    });
  }
  
  function calculateRow(row) {
    const jumlah = parseFloat(row.querySelector('.jumlah-input').value) || 0;
    const harga = parseFloat(row.querySelector('.harga-input').value) || 0;
    row.querySelector('.subtotal-display').value = (jumlah * harga).toLocaleString('id-ID');
    calculateTotal();
  }
  
  function calculateTotal() {
    let total = 0;
    document.querySelectorAll('.item-row').forEach(row => {
      const jumlah = parseFloat(row.querySelector('.jumlah-input').value) || 0;
      const harga = parseFloat(row.querySelector('.harga-input').value) || 0;
      total += jumlah * harga;
    });
    document.getElementById('grandTotal').value = total.toLocaleString('id-ID');
  }
  
  bindEvents();
});
</script>

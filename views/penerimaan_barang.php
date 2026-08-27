<?php
// views/penerimaan_barang.php - Transaksi Penerimaan Barang
csrf_check();
$conn = db();

// Load produk dan satuan
$produk_list = $conn->query("SELECT id, kode, nama, satuan, satuan_id, harga_beli FROM ksp_produk_barang WHERE status='aktif' ORDER BY nama");
$produk_arr = []; while($p = $produk_list->fetch_assoc()) $produk_arr[] = $p;
$satuan_list = $conn->query("SELECT id, kode, nama FROM ksp_satuan WHERE status='aktif' ORDER BY kode");
$satuan_arr = []; while($s = $satuan_list->fetch_assoc()) $satuan_arr[] = $s;

// Handle create penerimaan
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='create') {
  $tanggal = $_POST['tanggal'];
  $supplier = trim($_POST['supplier'] ?? '');
  $no_faktur = trim($_POST['no_faktur'] ?? '');
  $keterangan = trim($_POST['keterangan'] ?? '');
  $uid = user()['id'];
  
  // Generate nomor penerimaan
  $no_penerimaan = 'PNR-' . date('Ymd') . '-' . str_pad(rand(1,9999), 4, '0', STR_PAD_LEFT);
  
  // Hitung total
  $total = 0;
  $items = $_POST['items'] ?? [];
  
  if (empty($items)) {
    alert('danger', 'Minimal harus ada 1 item barang.');
    header("Location: index.php?m=penerimaan_barang"); exit;
  }
  
  // Insert header
  $stmt = $conn->prepare("INSERT INTO ksp_penerimaan_barang(no_penerimaan, tanggal, supplier, no_faktur, keterangan, created_by) VALUES(?,?,?,?,?,?)");
  $stmt->bind_param("sssssi", $no_penerimaan, $tanggal, $supplier, $no_faktur, $keterangan, $uid);
  
  if ($stmt->execute()) {
    $penerimaan_id = $conn->insert_id;
    
    foreach ($items as $item) {
      $produk_id = (int)$item['produk_id'];
      $jumlah = (float)$item['jumlah'];
      $satuan_id = !empty($item['satuan_id']) ? (int)$item['satuan_id'] : null;
      $harga = (float)($item['harga'] ?? 0);
      $subtotal = $jumlah * $harga;
      $total += $subtotal;
      
      // Cari konversi jika ada
      $jumlah_konversi = $jumlah;
      if ($satuan_id) {
        // Cek apakah ada konversi dari satuan pembelian ke satuan dasar produk
        $prod = $conn->query("SELECT satuan_id FROM ksp_produk_barang WHERE id=$produk_id")->fetch_assoc();
        if ($prod && $prod['satuan_id'] && $prod['satuan_id'] != $satuan_id) {
          $konv = $conn->query("SELECT nilai_konversi FROM ksp_konversi_satuan WHERE satuan_dari_id=$satuan_id AND satuan_ke_id=".$prod['satuan_id'])->fetch_assoc();
          if ($konv) {
            $jumlah_konversi = $jumlah * $konv['nilai_konversi'];
          }
        }
      }
      
      // Insert detail
      $stmt2 = $conn->prepare("INSERT INTO ksp_penerimaan_barang_detail(penerimaan_id, produk_id, jumlah, satuan_id, harga_satuan, subtotal, jumlah_konversi) VALUES(?,?,?,?,?,?,?)");
      $stmt2->bind_param("iididdd", $penerimaan_id, $produk_id, $jumlah, $satuan_id, $harga, $subtotal, $jumlah_konversi);
      $stmt2->execute();
      
      // Update stok produk
      $conn->query("UPDATE ksp_produk_barang SET stok = stok + $jumlah_konversi WHERE id = $produk_id");
      
      // Insert kartu stok
      $saldo = $conn->query("SELECT stok FROM ksp_produk_barang WHERE id=$produk_id")->fetch_assoc()['stok'];
      $stmt3 = $conn->prepare("INSERT INTO ksp_kartu_stok(produk_id, tanggal, tipe, referensi, jumlah, satuan_id, jumlah_konversi, saldo_akhir, harga_satuan, keterangan, created_by) VALUES(?,?,'masuk',?,?,?,?,?,?,?,?)");
      $ket_stok = "Penerimaan dari $supplier";
      $stmt3->bind_param("issdiiddsi", $produk_id, $tanggal, $no_penerimaan, $jumlah, $satuan_id, $jumlah_konversi, $saldo, $harga, $ket_stok, $uid);
      $stmt3->execute();
    }
    
    // Update total di header
    $conn->query("UPDATE ksp_penerimaan_barang SET total_nilai = $total WHERE id = $penerimaan_id");
    
    alert('success', "Penerimaan barang $no_penerimaan berhasil disimpan.");
  } else {
    alert('danger', 'Gagal: '.$conn->error);
  }
  header("Location: index.php?m=penerimaan_barang"); exit;
}

// Handle delete
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='delete') {
  $id = (int)$_POST['id'];
  
  // Rollback stok
  $details = $conn->query("SELECT produk_id, jumlah_konversi FROM ksp_penerimaan_barang_detail WHERE penerimaan_id=$id");
  while ($d = $details->fetch_assoc()) {
    $conn->query("UPDATE ksp_produk_barang SET stok = stok - ".$d['jumlah_konversi']." WHERE id = ".$d['produk_id']);
  }
  
  // Delete (cascade akan hapus detail)
  $conn->query("DELETE FROM ksp_penerimaan_barang WHERE id=$id");
  
  // Delete kartu stok terkait
  $no = $conn->query("SELECT no_penerimaan FROM ksp_penerimaan_barang WHERE id=$id")->fetch_assoc();
  if ($no) {
    $conn->query("DELETE FROM ksp_kartu_stok WHERE referensi='".$no['no_penerimaan']."'");
  }
  
  alert('success', 'Penerimaan barang dihapus.');
  header("Location: index.php?m=penerimaan_barang"); exit;
}

$list = $conn->query("SELECT * FROM ksp_penerimaan_barang ORDER BY id DESC LIMIT 100");
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-box-arrow-in-down me-2"></i>Penerimaan Barang</h4>
  <button class="btn btn-gradient" data-bs-toggle="collapse" data-bs-target="#formAdd"><i class="bi bi-plus-lg me-1"></i> Tambah Penerimaan</button>
</div>

<div id="formAdd" class="collapse mb-3">
  <div class="card card-widget">
    <div class="card-header bg-light"><strong>Form Penerimaan Barang Baru</strong></div>
    <div class="card-body">
      <form method="post" id="formPenerimaan">
        <?php echo csrf_input(); ?>
        <input type="hidden" name="action" value="create">
        
        <div class="row g-3 mb-3">
          <div class="col-md-2">
            <label class="form-label">Tanggal</label>
            <input name="tanggal" type="date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
          </div>
          <div class="col-md-3">
            <label class="form-label">Supplier</label>
            <input name="supplier" class="form-control" placeholder="Nama supplier">
          </div>
          <div class="col-md-2">
            <label class="form-label">No. Faktur</label>
            <input name="no_faktur" class="form-control" placeholder="Opsional">
          </div>
          <div class="col-md-5">
            <label class="form-label">Keterangan</label>
            <input name="keterangan" class="form-control" placeholder="Keterangan (opsional)">
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
                  <option value="<?php echo $p['id']; ?>" data-harga="<?php echo $p['harga_beli']; ?>" data-satuan="<?php echo $p['satuan_id']; ?>"><?php echo $p['kode'].' - '.$p['nama']; ?></option>
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
          <button type="submit" class="btn btn-gradient"><i class="bi bi-save me-1"></i> Simpan Penerimaan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="card card-widget">
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-striped datatable">
        <thead><tr><th>#</th><th>No. Penerimaan</th><th>Tanggal</th><th>Supplier</th><th>No. Faktur</th><th>Total Nilai</th><th>Aksi</th></tr></thead>
        <tbody>
        <?php $no=1; while($r=$list->fetch_assoc()): ?>
          <tr>
            <td><?php echo $no++; ?></td>
            <td><strong><?php echo $r['no_penerimaan']; ?></strong></td>
            <td><?php echo date('d/m/Y', strtotime($r['tanggal'])); ?></td>
            <td><?php echo htmlspecialchars($r['supplier'] ?? '-'); ?></td>
            <td><?php echo htmlspecialchars($r['no_faktur'] ?? '-'); ?></td>
            <td>Rp <?php echo rupiah($r['total_nilai']); ?></td>
            <td>
              <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#detailPnr<?php echo $r['id']; ?>"><i class="bi bi-eye"></i></button>
              <form method="post" style="display:inline" id="deleteFormPnr<?php echo $r['id']; ?>">
                <?php echo csrf_input(); ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmHapus('Penerimaan', '<?php echo $r['no_penerimaan']; ?>', 'deleteFormPnr<?php echo $r['id']; ?>')"><i class="bi bi-trash"></i></button>
              </form>
              
              <!-- Detail Modal -->
              <div class="modal fade" id="detailPnr<?php echo $r['id']; ?>" tabindex="-1">
                <div class="modal-dialog modal-lg">
                  <div class="modal-content">
                    <div class="modal-header">
                      <h5 class="modal-title">Detail Penerimaan: <?php echo $r['no_penerimaan']; ?></h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                      <div class="row mb-3">
                        <div class="col-md-4"><strong>Tanggal:</strong> <?php echo date('d/m/Y', strtotime($r['tanggal'])); ?></div>
                        <div class="col-md-4"><strong>Supplier:</strong> <?php echo htmlspecialchars($r['supplier'] ?? '-'); ?></div>
                        <div class="col-md-4"><strong>No. Faktur:</strong> <?php echo htmlspecialchars($r['no_faktur'] ?? '-'); ?></div>
                      </div>
                      <?php if ($r['keterangan']): ?>
                      <p><strong>Keterangan:</strong> <?php echo htmlspecialchars($r['keterangan']); ?></p>
                      <?php endif; ?>
                      <table class="table table-sm table-bordered">
                        <thead><tr><th>Produk</th><th>Jumlah</th><th>Satuan</th><th>Harga</th><th>Subtotal</th></tr></thead>
                        <tbody>
                        <?php 
                        $details = $conn->query("SELECT d.*, p.nama as produk_nama, p.kode as produk_kode, s.kode as satuan_kode FROM ksp_penerimaan_barang_detail d LEFT JOIN ksp_produk_barang p ON p.id=d.produk_id LEFT JOIN ksp_satuan s ON s.id=d.satuan_id WHERE d.penerimaan_id=".$r['id']);
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
  
  // Add row
  document.getElementById('btnAddRow').addEventListener('click', function() {
    const tbody = document.getElementById('itemsBody');
    const tr = document.createElement('tr');
    tr.className = 'item-row';
    tr.innerHTML = `
      <td>
        <select name="items[${rowIndex}][produk_id]" class="form-select form-select-sm produk-select" required>
          <option value="">-- Pilih Produk --</option>
          ${produkOptions.map(p => `<option value="${p.id}" data-harga="${p.harga_beli}" data-satuan="${p.satuan_id}">${p.kode} - ${p.nama}</option>`).join('')}
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
    // Produk change - auto fill harga
    document.querySelectorAll('.produk-select').forEach(sel => {
      sel.onchange = function() {
        const opt = this.options[this.selectedIndex];
        const harga = opt.dataset.harga || 0;
        const row = this.closest('tr');
        row.querySelector('.harga-input').value = harga;
        calculateRow(row);
      };
    });
    
    // Jumlah/harga change
    document.querySelectorAll('.jumlah-input, .harga-input').forEach(inp => {
      inp.oninput = function() {
        calculateRow(this.closest('tr'));
      };
    });
  }
  
  function calculateRow(row) {
    const jumlah = parseFloat(row.querySelector('.jumlah-input').value) || 0;
    const harga = parseFloat(row.querySelector('.harga-input').value) || 0;
    const subtotal = jumlah * harga;
    row.querySelector('.subtotal-display').value = subtotal.toLocaleString('id-ID');
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

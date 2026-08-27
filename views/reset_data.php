<?php
// views/reset_data.php - Reset/Clear All Data
require_login();

// Only admin can access
if (user()['role'] !== 'admin') {
  alert('danger', 'Akses ditolak! Hanya admin yang dapat mereset data.');
  header('Location: index.php?m=dashboard');
  exit;
}

$conn = db();

// Handle reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_reset'])) {
  csrf_check();
  
  $confirm_text = trim($_POST['confirm_text'] ?? '');
  $reset_type = $_POST['reset_type'] ?? '';
  
  if ($confirm_text !== 'RESET DATA') {
    alert('danger', 'Konfirmasi tidak valid! Ketik "RESET DATA" untuk melanjutkan.');
  } else {
    $success = true;
    $tables_reset = [];
    
    // Disable foreign key checks temporarily
    $conn->query("SET FOREIGN_KEY_CHECKS = 0");
    
    if ($reset_type === 'all' || $reset_type === 'transaksi') {
      // Reset transaction data
      $trans_tables = [
        'ksp_jadwal_angsuran',
        'ksp_pinjaman',
        'ksp_pengajuan_online',
        'ksp_simpanan_tx',
        'ksp_mutasi_simpanan',
        'ksp_simpanan_anggota',
        'ksp_kas',
        'ksp_tagih_item',
        'ksp_tagih_harian',
        'ksp_shu_anggota',
        'ksp_shu',
        'ksp_jaminan',
        'ksp_blacklist',
        'ksp_notifikasi',
        'ksp_activity_log',
        // B.4-B.5: Pembelian & Penjualan
        'ksp_pembelian_detail',
        'ksp_pembelian',
        'ksp_penjualan_detail',
        'ksp_penjualan',
        'ksp_penerimaan_barang_detail',
        'ksp_penerimaan_barang',
        // B.6: Keuangan
        'ksp_transaksi_keuangan',
        // B.7: SHU
        'ksp_shu_anggota_detail',
        'ksp_shu_periode'
      ];
      
      foreach ($trans_tables as $table) {
        if ($conn->query("TRUNCATE TABLE $table")) {
          $tables_reset[] = $table;
        }
      }
    }
    
    if ($reset_type === 'all' || $reset_type === 'master') {
      // Reset master data (except users and settings)
      $master_tables = [
        'ksp_anggota',
        'ksp_kolektor',
        'ksp_produk_pinjaman',
        'ksp_produk_simpanan',
        'ksp_nota_custom',
        // B.3: Inventori
        'ksp_produk_barang',
        'ksp_kategori_barang',
        'ksp_satuan',
        // B.4: Supplier
        'ksp_supplier',
        // B.6: Master Keuangan
        'ksp_master_pendapatan',
        'ksp_master_biaya',
        'ksp_kas_bank'
      ];
      
      foreach ($master_tables as $table) {
        if ($conn->query("TRUNCATE TABLE $table")) {
          $tables_reset[] = $table;
        }
      }
    }
    
    if ($reset_type === 'all') {
      // Reset auto increment for important tables
      $conn->query("ALTER TABLE ksp_anggota AUTO_INCREMENT = 1");
      $conn->query("ALTER TABLE ksp_pinjaman AUTO_INCREMENT = 1");
    }
    
    // Re-enable foreign key checks
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");
    
    // Log activity
    log_activity('reset_data', 'system', 'Reset data: ' . $reset_type . ' (' . count($tables_reset) . ' tables)');
    
    alert('success', 'Data berhasil direset! ' . count($tables_reset) . ' tabel dikosongkan.');
    header('Location: index.php?m=reset_data');
    exit;
  }
}

// Get current data counts
$counts = [
  'anggota' => $conn->query("SELECT COUNT(*) c FROM ksp_anggota")->fetch_assoc()['c'],
  'pinjaman' => $conn->query("SELECT COUNT(*) c FROM ksp_pinjaman")->fetch_assoc()['c'],
  'angsuran' => $conn->query("SELECT COUNT(*) c FROM ksp_jadwal_angsuran")->fetch_assoc()['c'],
  'simpanan' => $conn->query("SELECT COUNT(*) c FROM ksp_simpanan_tx")->fetch_assoc()['c'],
  'kas' => $conn->query("SELECT COUNT(*) c FROM ksp_kas")->fetch_assoc()['c'],
  'pengajuan' => $conn->query("SELECT COUNT(*) c FROM ksp_pengajuan_online")->fetch_assoc()['c'],
];
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-arrow-counterclockwise me-2"></i>Reset Data</h4>
</div>

<div class="row">
  <div class="col-lg-8">
    <!-- Warning -->
    <div class="alert alert-danger">
      <h5><i class="bi bi-exclamation-triangle-fill me-2"></i>PERINGATAN!</h5>
      <p class="mb-0">Fitur ini akan <strong>MENGHAPUS PERMANEN</strong> semua data yang dipilih. Data yang sudah dihapus <strong>TIDAK DAPAT DIKEMBALIKAN</strong>. Pastikan Anda sudah melakukan backup sebelum melanjutkan!</p>
    </div>

    <!-- Current Data Stats -->
    <div class="card card-widget mb-4">
      <div class="card-header"><h6 class="mb-0"><i class="bi bi-bar-chart me-2"></i>Data Saat Ini</h6></div>
      <div class="card-body">
        <div class="row g-3 text-center">
          <div class="col-4 col-md-2">
            <div class="border rounded p-2">
              <div class="fs-4 fw-bold text-primary"><?php echo number_format($counts['anggota']); ?></div>
              <small class="text-muted">Anggota</small>
            </div>
          </div>
          <div class="col-4 col-md-2">
            <div class="border rounded p-2">
              <div class="fs-4 fw-bold text-success"><?php echo number_format($counts['pinjaman']); ?></div>
              <small class="text-muted">Pinjaman</small>
            </div>
          </div>
          <div class="col-4 col-md-2">
            <div class="border rounded p-2">
              <div class="fs-4 fw-bold text-info"><?php echo number_format($counts['angsuran']); ?></div>
              <small class="text-muted">Angsuran</small>
            </div>
          </div>
          <div class="col-4 col-md-2">
            <div class="border rounded p-2">
              <div class="fs-4 fw-bold text-warning"><?php echo number_format($counts['simpanan']); ?></div>
              <small class="text-muted">Simpanan</small>
            </div>
          </div>
          <div class="col-4 col-md-2">
            <div class="border rounded p-2">
              <div class="fs-4 fw-bold text-secondary"><?php echo number_format($counts['kas']); ?></div>
              <small class="text-muted">Kas</small>
            </div>
          </div>
          <div class="col-4 col-md-2">
            <div class="border rounded p-2">
              <div class="fs-4 fw-bold text-danger"><?php echo number_format($counts['pengajuan']); ?></div>
              <small class="text-muted">Pengajuan</small>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Reset Options -->
    <div class="card card-widget">
      <div class="card-header bg-danger text-white">
        <h6 class="mb-0"><i class="bi bi-trash3 me-2"></i>Pilih Jenis Reset</h6>
      </div>
      <div class="card-body">
        <form method="post" id="resetForm">
          <?php echo csrf_input(); ?>
          
          <div class="mb-4">
            <div class="form-check mb-3">
              <input class="form-check-input" type="radio" name="reset_type" id="reset_transaksi" value="transaksi" checked>
              <label class="form-check-label" for="reset_transaksi">
                <strong class="text-warning">Reset Transaksi Saja</strong>
                <p class="text-muted small mb-0">Hapus: Pinjaman, Angsuran, Simpanan, Kas, Pengajuan Online, Tagihan, SHU, Blacklist, Jaminan<br>
                <span class="text-success">Tetap: Anggota, Produk, Kolektor, Users, Setting</span></p>
              </label>
            </div>
            
            <div class="form-check mb-3">
              <input class="form-check-input" type="radio" name="reset_type" id="reset_master" value="master">
              <label class="form-check-label" for="reset_master">
                <strong class="text-info">Reset Master Data Saja</strong>
                <p class="text-muted small mb-0">Hapus: Anggota, Kolektor, Produk Pinjaman, Produk Simpanan<br>
                <span class="text-success">Tetap: Users, Setting</span></p>
              </label>
            </div>
            
            <div class="form-check">
              <input class="form-check-input" type="radio" name="reset_type" id="reset_all" value="all">
              <label class="form-check-label" for="reset_all">
                <strong class="text-danger">Reset SEMUA Data</strong>
                <p class="text-muted small mb-0">Hapus semua data transaksi dan master data.<br>
                <span class="text-success">Tetap: Users, Setting (konfigurasi sistem)</span></p>
              </label>
            </div>
          </div>

          <hr>

          <div class="mb-3">
            <label class="form-label"><strong>Konfirmasi Reset</strong></label>
            <p class="text-muted small">Ketik <code>RESET DATA</code> untuk mengkonfirmasi penghapusan data.</p>
            <input type="text" name="confirm_text" class="form-control" placeholder="Ketik: RESET DATA" autocomplete="off" required>
          </div>

          <div class="d-grid gap-2">
            <button type="button" class="btn btn-danger btn-lg" onclick="confirmReset()">
              <i class="bi bi-trash3 me-2"></i>Reset Data Sekarang
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <!-- Backup Reminder -->
    <div class="card card-widget mb-3">
      <div class="card-header bg-success text-white">
        <h6 class="mb-0"><i class="bi bi-cloud-download me-2"></i>Backup Dulu!</h6>
      </div>
      <div class="card-body">
        <p class="small">Sebelum mereset data, sangat disarankan untuk melakukan backup terlebih dahulu.</p>
        <a href="index.php?m=backup" class="btn btn-success btn-sm w-100">
          <i class="bi bi-download me-1"></i>Backup Database
        </a>
      </div>
    </div>

    <!-- What Will Be Kept -->
    <div class="card card-widget">
      <div class="card-header">
        <h6 class="mb-0"><i class="bi bi-shield-check me-2"></i>Data yang TIDAK Dihapus</h6>
      </div>
      <div class="card-body">
        <ul class="list-unstyled mb-0 small">
          <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i><strong>Users</strong> - Akun admin tetap ada</li>
          <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i><strong>Setting</strong> - Konfigurasi sistem</li>
          <li><i class="bi bi-check-circle text-success me-2"></i><strong>Cabang</strong> - Data cabang</li>
        </ul>
      </div>
    </div>
  </div>
</div>

<script>
function confirmReset() {
  var confirmText = document.querySelector('input[name="confirm_text"]').value;
  var resetType = document.querySelector('input[name="reset_type"]:checked').value;
  
  var typeLabel = {
    'transaksi': 'TRANSAKSI',
    'master': 'MASTER DATA',
    'all': 'SEMUA DATA'
  };
  
  if (confirmText !== 'RESET DATA') {
    alert('Ketik "RESET DATA" untuk mengkonfirmasi!');
    return;
  }
  
  if (confirm('⚠️ PERINGATAN TERAKHIR!\n\nAnda akan menghapus ' + typeLabel[resetType] + '.\n\nData yang dihapus TIDAK DAPAT dikembalikan!\n\nLanjutkan?')) {
    document.getElementById('resetForm').submit();
  }
}
</script>

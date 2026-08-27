<?php
// views/buku_tabungan.php - Buku Tabungan Digital
require_login();
$conn = db();

$anggota_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$bulan = isset($_GET['bulan']) ? $_GET['bulan'] : date('Y-m');

// Get anggota if specified
$anggota = null;
$mutasi = null;
$saldo = null;

if ($anggota_id) {
  $anggota = $conn->query("
    SELECT a.*, 
      COALESCE(s.simpanan_pokok, 0) as simpanan_pokok,
      COALESCE(s.simpanan_wajib, 0) as simpanan_wajib,
      COALESCE(s.simpanan_sukarela, 0) as simpanan_sukarela,
      COALESCE(s.total_simpanan, 0) as total_simpanan
    FROM ksp_anggota a
    LEFT JOIN ksp_simpanan_anggota s ON s.anggota_id = a.id
    WHERE a.id = $anggota_id
  ")->fetch_assoc();
  
  if ($anggota) {
    $start_date = $bulan . '-01';
    $end_date = date('Y-m-t', strtotime($start_date));
    
    $mutasi = $conn->query("
      SELECT * FROM ksp_mutasi_simpanan 
      WHERE anggota_id = $anggota_id 
      AND tanggal BETWEEN '$start_date' AND '$end_date'
      ORDER BY tanggal, id
    ");
    
    // Get opening balance (saldo awal bulan)
    $saldo_awal = $conn->query("
      SELECT 
        SUM(CASE WHEN tipe = 'setor' THEN nominal ELSE -nominal END) as saldo
      FROM ksp_mutasi_simpanan 
      WHERE anggota_id = $anggota_id 
      AND tanggal < '$start_date'
    ")->fetch_assoc();
    
    $saldo = (float)($saldo_awal['saldo'] ?: 0);
  }
}

// Get all anggota for dropdown
$all_anggota = $conn->query("SELECT id, no_anggota, nama FROM ksp_anggota WHERE status = 'aktif' ORDER BY nama");

$company = setting_get('company_name', 'KSP Harian');
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4><i class="bi bi-book me-2"></i>Buku Tabungan Digital</h4>
  <a href="index.php?m=simpanan_anggota" class="btn btn-outline-primary"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
</div>

<!-- Filter -->
<div class="card card-widget mb-3">
  <div class="card-body">
    <form class="row g-2" method="get">
      <input type="hidden" name="m" value="buku_tabungan">
      <div class="col-md-5">
        <label class="form-label">Pilih Anggota</label>
        <select name="id" class="form-select select2" required>
          <option value="">-- Pilih Anggota --</option>
          <?php while ($a = $all_anggota->fetch_assoc()): ?>
          <option value="<?php echo $a['id']; ?>" <?php echo $a['id'] == $anggota_id ? 'selected' : ''; ?>>
            <?php echo $a['no_anggota'] . ' - ' . htmlspecialchars($a['nama']); ?>
          </option>
          <?php endwhile; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Periode</label>
        <input type="month" name="bulan" class="form-control" value="<?php echo $bulan; ?>">
      </div>
      <div class="col-md-2 align-self-end">
        <button class="btn btn-gradient w-100"><i class="bi bi-search me-1"></i>Tampilkan</button>
      </div>
      <div class="col-md-2 align-self-end">
        <?php if ($anggota): ?>
        <a href="print/buku_tabungan.php?id=<?php echo $anggota_id; ?>&bulan=<?php echo $bulan; ?>" target="_blank" class="btn btn-outline-success w-100">
          <i class="bi bi-printer me-1"></i>Cetak
        </a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<?php if ($anggota): ?>
<!-- Buku Tabungan -->
<div class="card card-widget">
  <div class="card-header bg-dark text-white">
    <div class="d-flex justify-content-between align-items-center">
      <div>
        <h5 class="mb-0"><?php echo htmlspecialchars($company); ?></h5>
        <small>BUKU TABUNGAN</small>
      </div>
      <div class="text-end">
        <div class="small">No. Rekening</div>
        <div class="fw-bold"><?php echo $anggota['no_anggota']; ?></div>
      </div>
    </div>
  </div>
  
  <div class="card-body">
    <!-- Info Pemilik -->
    <div class="row mb-4">
      <div class="col-md-6">
        <table class="table table-sm table-borderless">
          <tr><td width="120">Nama</td><td>: <strong><?php echo htmlspecialchars($anggota['nama']); ?></strong></td></tr>
          <tr><td>Alamat</td><td>: <?php echo htmlspecialchars($anggota['alamat']); ?></td></tr>
          <tr><td>No. HP</td><td>: <?php echo htmlspecialchars($anggota['hp']); ?></td></tr>
        </table>
      </div>
      <div class="col-md-6">
        <div class="row text-center">
          <div class="col-4">
            <div class="border rounded p-2">
              <small class="text-muted d-block">Pokok</small>
              <strong class="text-primary">Rp <?php echo number_format($anggota['simpanan_pokok'], 0, ',', '.'); ?></strong>
            </div>
          </div>
          <div class="col-4">
            <div class="border rounded p-2">
              <small class="text-muted d-block">Wajib</small>
              <strong class="text-success">Rp <?php echo number_format($anggota['simpanan_wajib'], 0, ',', '.'); ?></strong>
            </div>
          </div>
          <div class="col-4">
            <div class="border rounded p-2">
              <small class="text-muted d-block">Sukarela</small>
              <strong class="text-info">Rp <?php echo number_format($anggota['simpanan_sukarela'], 0, ',', '.'); ?></strong>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Periode -->
    <div class="alert alert-info py-2">
      <strong>Periode:</strong> <?php echo date('F Y', strtotime($bulan . '-01')); ?>
      | <strong>Saldo Awal:</strong> Rp <?php echo number_format($saldo, 0, ',', '.'); ?>
    </div>
    
    <!-- Mutasi Table -->
    <div class="table-responsive">
      <table class="table table-bordered">
        <thead class="table-light">
          <tr>
            <th width="100">Tanggal</th>
            <th>Keterangan</th>
            <th width="80" class="text-center">Jenis</th>
            <th width="130" class="text-end">Debet (Tarik)</th>
            <th width="130" class="text-end">Kredit (Setor)</th>
            <th width="150" class="text-end">Saldo</th>
          </tr>
        </thead>
        <tbody>
          <!-- Saldo Awal -->
          <tr class="table-secondary">
            <td><?php echo date('01/m/Y', strtotime($bulan . '-01')); ?></td>
            <td><em>Saldo Awal</em></td>
            <td></td>
            <td></td>
            <td></td>
            <td class="text-end fw-bold">Rp <?php echo number_format($saldo, 0, ',', '.'); ?></td>
          </tr>
          
          <?php 
          $running_saldo = $saldo;
          if ($mutasi && $mutasi->num_rows > 0): 
            while ($m = $mutasi->fetch_assoc()):
              if ($m['tipe'] == 'setor') {
                $running_saldo += $m['nominal'];
              } else {
                $running_saldo -= $m['nominal'];
              }
          ?>
          <tr>
            <td><?php echo date('d/m/Y', strtotime($m['tanggal'])); ?></td>
            <td>
              <?php echo ucfirst($m['jenis_simpanan']); ?>
              <?php if ($m['keterangan']): ?> - <?php echo htmlspecialchars($m['keterangan']); ?><?php endif; ?>
            </td>
            <td class="text-center">
              <span class="badge bg-<?php echo $m['jenis_simpanan'] == 'pokok' ? 'primary' : ($m['jenis_simpanan'] == 'wajib' ? 'success' : 'info'); ?>">
                <?php echo substr(ucfirst($m['jenis_simpanan']), 0, 1); ?>
              </span>
            </td>
            <td class="text-end text-danger">
              <?php echo $m['tipe'] == 'tarik' ? 'Rp ' . number_format($m['nominal'], 0, ',', '.') : ''; ?>
            </td>
            <td class="text-end text-success">
              <?php echo $m['tipe'] == 'setor' ? 'Rp ' . number_format($m['nominal'], 0, ',', '.') : ''; ?>
            </td>
            <td class="text-end fw-bold">Rp <?php echo number_format($running_saldo, 0, ',', '.'); ?></td>
          </tr>
          <?php 
            endwhile;
          else: 
          ?>
          <tr><td colspan="6" class="text-center text-muted py-3">Tidak ada transaksi pada periode ini</td></tr>
          <?php endif; ?>
          
          <!-- Saldo Akhir -->
          <tr class="table-warning">
            <td colspan="5" class="text-end"><strong>SALDO AKHIR</strong></td>
            <td class="text-end fw-bold fs-5">Rp <?php echo number_format($running_saldo, 0, ',', '.'); ?></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
  
  <div class="card-footer text-muted small text-center">
    Dicetak pada <?php echo date('d/m/Y H:i'); ?> | Dokumen ini sah dan dapat dipertanggungjawabkan
  </div>
</div>

<?php else: ?>
<div class="card card-widget">
  <div class="card-body text-center py-5">
    <i class="bi bi-book fs-1 text-muted"></i>
    <p class="mt-3 text-muted">Pilih anggota untuk melihat buku tabungan</p>
  </div>
</div>
<?php endif; ?>

<?php
// views/shu.php - SHU Calculator (Sisa Hasil Usaha)
require_login();
$conn = db();

$tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : date('Y');

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  
  // Hitung SHU
  if (isset($_POST['hitung_shu'])) {
    $tahun_hitung = (int)$_POST['tahun'];
    
    // Get pendapatan (kas masuk) tahun ini
    $pendapatan = $conn->query("
      SELECT SUM(nominal) as total FROM ksp_kas 
      WHERE jenis = 'masuk' AND YEAR(tanggal) = $tahun_hitung
    ")->fetch_assoc()['total'] ?: 0;
    
    // Get biaya (kas keluar) tahun ini
    $biaya = $conn->query("
      SELECT SUM(nominal) as total FROM ksp_kas 
      WHERE jenis = 'keluar' AND YEAR(tanggal) = $tahun_hitung
    ")->fetch_assoc()['total'] ?: 0;
    
    $shu_kotor = $pendapatan - $biaya;
    
    // Insert or update SHU
    $check = $conn->query("SELECT id FROM ksp_shu WHERE tahun = $tahun_hitung")->fetch_assoc();
    if ($check) {
      $conn->query("UPDATE ksp_shu SET total_pendapatan = $pendapatan, total_biaya = $biaya, shu_kotor = $shu_kotor WHERE tahun = $tahun_hitung");
    } else {
      $conn->query("INSERT INTO ksp_shu (tahun, total_pendapatan, total_biaya, shu_kotor) VALUES ($tahun_hitung, $pendapatan, $biaya, $shu_kotor)");
    }
    
    alert('success', 'SHU tahun ' . $tahun_hitung . ' berhasil dihitung.');
    header('Location: index.php?m=shu&tahun=' . $tahun_hitung);
    exit;
  }
  
  // Update persentase
  if (isset($_POST['update_persen'])) {
    $shu_id = (int)$_POST['shu_id'];
    $cadangan = (float)$_POST['cadangan_persen'];
    $pengurus = (float)$_POST['dana_pengurus_persen'];
    $karyawan = (float)$_POST['dana_karyawan_persen'];
    $pendidikan = (float)$_POST['dana_pendidikan_persen'];
    $sosial = (float)$_POST['dana_sosial_persen'];
    $jasa = (float)$_POST['jasa_anggota_persen'];
    
    $total = $cadangan + $pengurus + $karyawan + $pendidikan + $sosial + $jasa;
    if ($total != 100) {
      alert('danger', 'Total persentase harus 100%! (Sekarang: ' . $total . '%)');
    } else {
      $conn->query("UPDATE ksp_shu SET cadangan_persen=$cadangan, dana_pengurus_persen=$pengurus, dana_karyawan_persen=$karyawan, dana_pendidikan_persen=$pendidikan, dana_sosial_persen=$sosial, jasa_anggota_persen=$jasa WHERE id=$shu_id");
      alert('success', 'Persentase pembagian SHU diperbarui.');
    }
    header('Location: index.php?m=shu&tahun=' . $tahun);
    exit;
  }
  
  // Distribusi ke anggota
  if (isset($_POST['distribusi'])) {
    $shu_id = (int)$_POST['shu_id'];
    $shu = $conn->query("SELECT * FROM ksp_shu WHERE id = $shu_id")->fetch_assoc();
    
    if ($shu) {
      // Hapus distribusi lama
      $conn->query("DELETE FROM ksp_shu_anggota WHERE shu_id = $shu_id");
      
      $dana_jasa = $shu['shu_kotor'] * $shu['jasa_anggota_persen'] / 100;
      $tahun_shu = $shu['tahun'];
      
      // Get total simpanan dan total bunga pinjaman anggota
      $anggota_list = $conn->query("
        SELECT a.id, a.nama,
          COALESCE(s.total_simpanan, 0) as total_simpanan,
          COALESCE((SELECT SUM(total_bunga) FROM ksp_pinjaman WHERE anggota_id = a.id AND YEAR(tanggal_realisasi) = $tahun_shu), 0) as total_bunga_pinjaman
        FROM ksp_anggota a
        LEFT JOIN ksp_simpanan_anggota s ON s.anggota_id = a.id
        WHERE a.status = 'aktif'
      ");
      
      // Hitung total untuk proporsi
      $total_simpanan_all = 0;
      $total_bunga_all = 0;
      $anggota_data = [];
      while ($ag = $anggota_list->fetch_assoc()) {
        $total_simpanan_all += $ag['total_simpanan'];
        $total_bunga_all += $ag['total_bunga_pinjaman'];
        $anggota_data[] = $ag;
      }
      
      // 50% dari jasa anggota berdasarkan simpanan, 50% berdasarkan pinjaman
      $dana_simpanan = $dana_jasa * 0.5;
      $dana_pinjaman = $dana_jasa * 0.5;
      
      foreach ($anggota_data as $ag) {
        $jasa_simpanan = $total_simpanan_all > 0 ? ($ag['total_simpanan'] / $total_simpanan_all) * $dana_simpanan : 0;
        $jasa_pinjaman = $total_bunga_all > 0 ? ($ag['total_bunga_pinjaman'] / $total_bunga_all) * $dana_pinjaman : 0;
        $total_shu = $jasa_simpanan + $jasa_pinjaman;
        
        if ($total_shu > 0) {
          $stmt = $conn->prepare("INSERT INTO ksp_shu_anggota (shu_id, anggota_id, jasa_simpanan, jasa_pinjaman, total_shu) VALUES (?, ?, ?, ?, ?)");
          $stmt->bind_param("iiddd", $shu_id, $ag['id'], $jasa_simpanan, $jasa_pinjaman, $total_shu);
          $stmt->execute();
        }
      }
      
      $conn->query("UPDATE ksp_shu SET status = 'final' WHERE id = $shu_id");
      alert('success', 'SHU berhasil didistribusikan ke anggota.');
    }
    header('Location: index.php?m=shu&tahun=' . $tahun);
    exit;
  }
}

// Get SHU data
$shu = $conn->query("SELECT * FROM ksp_shu WHERE tahun = $tahun")->fetch_assoc();

// Get distribusi anggota
$distribusi = null;
if ($shu) {
  $distribusi = $conn->query("
    SELECT sa.*, a.nama, a.no_anggota 
    FROM ksp_shu_anggota sa 
    JOIN ksp_anggota a ON a.id = sa.anggota_id 
    WHERE sa.shu_id = {$shu['id']}
    ORDER BY sa.total_shu DESC
  ");
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4><i class="bi bi-calculator me-2"></i>SHU (Sisa Hasil Usaha)</h4>
</div>

<!-- Pilih Tahun & Hitung -->
<div class="card card-widget mb-3">
  <div class="card-body">
    <form class="row g-2" method="post">
      <?php echo csrf_input(); ?>
      <div class="col-md-3">
        <label class="form-label">Tahun</label>
        <select name="tahun" class="form-select" onchange="location.href='index.php?m=shu&tahun='+this.value">
          <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
          <option value="<?php echo $y; ?>" <?php echo $y == $tahun ? 'selected' : ''; ?>><?php echo $y; ?></option>
          <?php endfor; ?>
        </select>
      </div>
      <div class="col-md-3 align-self-end">
        <button type="submit" name="hitung_shu" class="btn btn-gradient w-100">
          <i class="bi bi-calculator me-1"></i>Hitung SHU <?php echo $tahun; ?>
        </button>
      </div>
    </form>
  </div>
</div>

<?php if ($shu): ?>
<!-- Ringkasan SHU -->
<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="card card-widget bg-success bg-opacity-10">
      <div class="card-body text-center">
        <i class="bi bi-graph-up-arrow fs-2 text-success"></i>
        <h5 class="mt-2 mb-0">Rp <?php echo number_format($shu['total_pendapatan'], 2, ',', '.'); ?></h5>
        <small class="text-muted">Total Pendapatan</small>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card card-widget bg-danger bg-opacity-10">
      <div class="card-body text-center">
        <i class="bi bi-graph-down-arrow fs-2 text-danger"></i>
        <h5 class="mt-2 mb-0">Rp <?php echo number_format($shu['total_biaya'], 2, ',', '.'); ?></h5>
        <small class="text-muted">Total Biaya</small>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card card-widget bg-primary bg-opacity-10">
      <div class="card-body text-center">
        <i class="bi bi-cash-stack fs-2 text-primary"></i>
        <h5 class="mt-2 mb-0">Rp <?php echo number_format($shu['shu_kotor'], 2, ',', '.'); ?></h5>
        <small class="text-muted">SHU Kotor</small>
        <span class="badge bg-<?php echo $shu['status'] == 'final' ? 'success' : 'warning'; ?> ms-2"><?php echo strtoupper($shu['status']); ?></span>
      </div>
    </div>
  </div>
</div>

<!-- Pembagian SHU -->
<div class="row">
  <div class="col-md-5">
    <div class="card card-widget">
      <div class="card-header"><h6 class="mb-0"><i class="bi bi-pie-chart me-2"></i>Pembagian SHU</h6></div>
      <div class="card-body">
        <form method="post">
          <?php echo csrf_input(); ?>
          <input type="hidden" name="shu_id" value="<?php echo $shu['id']; ?>">
          
          <?php
          $items = [
            ['cadangan_persen', 'Cadangan', 'primary'],
            ['dana_pengurus_persen', 'Dana Pengurus', 'success'],
            ['dana_karyawan_persen', 'Dana Karyawan', 'info'],
            ['dana_pendidikan_persen', 'Dana Pendidikan', 'warning'],
            ['dana_sosial_persen', 'Dana Sosial', 'secondary'],
            ['jasa_anggota_persen', 'Jasa Anggota', 'danger'],
          ];
          foreach ($items as $item):
            $nilai = $shu['shu_kotor'] * $shu[$item[0]] / 100;
          ?>
          <div class="mb-2">
            <div class="d-flex justify-content-between align-items-center">
              <label class="form-label mb-0"><?php echo $item[1]; ?></label>
              <span class="text-<?php echo $item[2]; ?> fw-bold">Rp <?php echo number_format($nilai, 2, ',', '.'); ?></span>
            </div>
            <div class="input-group input-group-sm">
              <input type="number" name="<?php echo $item[0]; ?>" class="form-control" value="<?php echo $shu[$item[0]]; ?>" step="0.01" min="0" max="100" <?php echo $shu['status'] == 'final' ? 'readonly' : ''; ?>>
              <span class="input-group-text">%</span>
            </div>
          </div>
          <?php endforeach; ?>
          
          <?php if ($shu['status'] != 'final'): ?>
          <hr>
          <button type="submit" name="update_persen" class="btn btn-outline-primary w-100 mb-2">
            <i class="bi bi-save me-1"></i>Simpan Persentase
          </button>
          <button type="submit" name="distribusi" class="btn btn-gradient w-100" onclick="return confirm('Distribusikan SHU ke anggota? Status akan menjadi FINAL.')">
            <i class="bi bi-people me-1"></i>Distribusi ke Anggota
          </button>
          <?php endif; ?>
        </form>
      </div>
    </div>
  </div>
  
  <div class="col-md-7">
    <div class="card card-widget">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="bi bi-people me-2"></i>Distribusi SHU Anggota</h6>
        <?php if ($distribusi && $distribusi->num_rows > 0): ?>
        <a href="print/shu_anggota.php?tahun=<?php echo $tahun; ?>" target="_blank" class="btn btn-sm btn-outline-success">
          <i class="bi bi-printer me-1"></i>Cetak
        </a>
        <?php endif; ?>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive" style="max-height:400px;">
          <table class="table table-sm table-striped mb-0">
            <thead class="sticky-top bg-light">
              <tr>
                <th>Anggota</th>
                <th class="text-end">Jasa Simpanan</th>
                <th class="text-end">Jasa Pinjaman</th>
                <th class="text-end">Total SHU</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
            <?php if ($distribusi && $distribusi->num_rows > 0): ?>
              <?php while ($d = $distribusi->fetch_assoc()): ?>
              <tr>
                <td>
                  <strong><?php echo htmlspecialchars($d['nama']); ?></strong><br>
                  <small class="text-muted"><?php echo $d['no_anggota']; ?></small>
                </td>
                <td class="text-end">Rp <?php echo number_format($d['jasa_simpanan'], 2, ',', '.'); ?></td>
                <td class="text-end">Rp <?php echo number_format($d['jasa_pinjaman'], 2, ',', '.'); ?></td>
                <td class="text-end fw-bold">Rp <?php echo number_format($d['total_shu'], 2, ',', '.'); ?></td>
                <td>
                  <span class="badge bg-<?php echo $d['status'] == 'dibayar' ? 'success' : 'warning'; ?>">
                    <?php echo $d['status']; ?>
                  </span>
                </td>
              </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="5" class="text-center text-muted py-4">Belum ada distribusi. Klik "Distribusi ke Anggota" untuk menghitung.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?php else: ?>
<div class="card card-widget">
  <div class="card-body text-center py-5">
    <i class="bi bi-calculator fs-1 text-muted"></i>
    <p class="mt-3 text-muted">Belum ada data SHU untuk tahun <?php echo $tahun; ?>.</p>
    <p>Klik tombol "Hitung SHU" untuk menghitung SHU berdasarkan data kas.</p>
  </div>
</div>
<?php endif; ?>

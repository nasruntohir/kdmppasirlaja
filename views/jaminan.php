<?php
// views/jaminan.php - Jaminan/Agunan Management
require_login();
$conn = db();

// Handle upload directory
$upload_dir = __DIR__ . '/../uploads/jaminan';
if (!is_dir($upload_dir)) {
  mkdir($upload_dir, 0755, true);
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  
  // Add jaminan
  if (isset($_POST['add_jaminan'])) {
    $pinjaman_id = (int)$_POST['pinjaman_id'];
    $jenis = $_POST['jenis_jaminan'];
    $nama = trim($_POST['nama_jaminan']);
    $nomor = trim($_POST['nomor_dokumen']);
    $nilai = (float)$_POST['nilai_taksiran'];
    $lokasi = trim($_POST['lokasi_penyimpanan']);
    $keterangan = trim($_POST['keterangan']);
    
    // Handle photo upload
    $foto = '';
    if (isset($_FILES['foto_jaminan']) && $_FILES['foto_jaminan']['size'] > 0) {
      $ext = strtolower(pathinfo($_FILES['foto_jaminan']['name'], PATHINFO_EXTENSION));
      if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
        $foto = 'jaminan_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
        move_uploaded_file($_FILES['foto_jaminan']['tmp_name'], $upload_dir . '/' . $foto);
      }
    }
    
    $stmt = $conn->prepare("INSERT INTO ksp_jaminan (pinjaman_id, jenis_jaminan, nama_jaminan, nomor_dokumen, nilai_taksiran, foto_jaminan, lokasi_penyimpanan, tanggal_terima, keterangan) VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), ?)");
    $stmt->bind_param("isssdsss", $pinjaman_id, $jenis, $nama, $nomor, $nilai, $foto, $lokasi, $keterangan);
    if ($stmt->execute()) {
      log_activity('add_jaminan', 'jaminan', 'Menambahkan jaminan: ' . $nama);
      alert('success', 'Jaminan berhasil ditambahkan.');
    }
    header('Location: index.php?m=jaminan');
    exit;
  }
  
  // Return jaminan
  if (isset($_POST['return_jaminan'])) {
    $id = (int)$_POST['id'];
    $conn->query("UPDATE ksp_jaminan SET status = 'dikembalikan', tanggal_kembali = CURDATE() WHERE id = $id");
    log_activity('return_jaminan', 'jaminan', 'Mengembalikan jaminan');
    alert('success', 'Jaminan ditandai telah dikembalikan.');
    header('Location: index.php?m=jaminan');
    exit;
  }
}

// Get jaminan list
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'disimpan';
$where = $status_filter ? "WHERE j.status = '$status_filter'" : '';

$jaminan_list = $conn->query("
  SELECT j.*, p.no_pinjaman, a.nama as anggota_nama, a.no_anggota
  FROM ksp_jaminan j
  JOIN ksp_pinjaman p ON p.id = j.pinjaman_id
  JOIN ksp_anggota a ON a.id = p.anggota_id
  $where
  ORDER BY j.created_at DESC
");

// Get pinjaman for dropdown (active loans)
$pinjaman_list = $conn->query("
  SELECT p.id, p.no_pinjaman, a.nama 
  FROM ksp_pinjaman p
  JOIN ksp_anggota a ON a.id = p.anggota_id
  WHERE p.status = 'aktif'
  ORDER BY p.tanggal_realisasi DESC
");

// Stats
$stats = $conn->query("
  SELECT 
    COUNT(CASE WHEN status = 'disimpan' THEN 1 END) as disimpan,
    COUNT(CASE WHEN status = 'dikembalikan' THEN 1 END) as dikembalikan,
    SUM(CASE WHEN status = 'disimpan' THEN nilai_taksiran ELSE 0 END) as total_nilai
  FROM ksp_jaminan
")->fetch_assoc();

$jenis_options = [
  'bpkb_motor' => 'BPKB Motor',
  'bpkb_mobil' => 'BPKB Mobil',
  'sertifikat' => 'Sertifikat Tanah/Rumah',
  'sk_kerja' => 'SK Kerja/Pegawai',
  'emas' => 'Emas/Perhiasan',
  'lainnya' => 'Lainnya'
];
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4><i class="bi bi-safe me-2"></i>Jaminan / Agunan</h4>
  <button class="btn btn-gradient" data-bs-toggle="modal" data-bs-target="#addJaminanModal">
    <i class="bi bi-plus-lg me-1"></i>Tambah Jaminan
  </button>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="card card-widget bg-primary bg-opacity-10">
      <div class="card-body text-center">
        <i class="bi bi-safe fs-2 text-primary"></i>
        <h3 class="mt-2 mb-0"><?php echo $stats['disimpan'] ?: 0; ?></h3>
        <small class="text-muted">Jaminan Disimpan</small>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card card-widget bg-success bg-opacity-10">
      <div class="card-body text-center">
        <i class="bi bi-check-circle fs-2 text-success"></i>
        <h3 class="mt-2 mb-0"><?php echo $stats['dikembalikan'] ?: 0; ?></h3>
        <small class="text-muted">Sudah Dikembalikan</small>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card card-widget bg-warning bg-opacity-10">
      <div class="card-body text-center">
        <i class="bi bi-cash-coin fs-2 text-warning"></i>
        <h5 class="mt-2 mb-0">Rp <?php echo number_format($stats['total_nilai'] ?: 0, 0, ',', '.'); ?></h5>
        <small class="text-muted">Total Nilai Taksiran</small>
      </div>
    </div>
  </div>
</div>

<!-- Filter -->
<div class="card card-widget mb-3">
  <div class="card-body py-2">
    <div class="btn-group">
      <a href="index.php?m=jaminan&status=disimpan" class="btn btn-sm <?php echo $status_filter == 'disimpan' ? 'btn-primary' : 'btn-outline-primary'; ?>">
        <i class="bi bi-safe me-1"></i>Disimpan
      </a>
      <a href="index.php?m=jaminan&status=dikembalikan" class="btn btn-sm <?php echo $status_filter == 'dikembalikan' ? 'btn-success' : 'btn-outline-success'; ?>">
        <i class="bi bi-check-circle me-1"></i>Dikembalikan
      </a>
      <a href="index.php?m=jaminan&status=" class="btn btn-sm <?php echo $status_filter == '' ? 'btn-secondary' : 'btn-outline-secondary'; ?>">
        Semua
      </a>
    </div>
  </div>
</div>

<!-- List -->
<div class="card card-widget">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-striped mb-0">
        <thead>
          <tr>
            <th>Foto</th>
            <th>No. Pinjaman</th>
            <th>Anggota</th>
            <th>Jenis</th>
            <th>Nama Jaminan</th>
            <th>Nilai Taksiran</th>
            <th>Status</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
        <?php if ($jaminan_list && $jaminan_list->num_rows > 0): ?>
          <?php while ($j = $jaminan_list->fetch_assoc()): ?>
          <tr>
            <td>
              <?php if ($j['foto_jaminan']): ?>
              <img src="uploads/jaminan/<?php echo $j['foto_jaminan']; ?>" alt="Foto" style="width:50px;height:50px;object-fit:cover;border-radius:8px;cursor:pointer;" onclick="window.open('uploads/jaminan/<?php echo $j['foto_jaminan']; ?>')">
              <?php else: ?>
              <div style="width:50px;height:50px;background:#eee;border-radius:8px;display:flex;align-items:center;justify-content:center;">
                <i class="bi bi-image text-muted"></i>
              </div>
              <?php endif; ?>
            </td>
            <td><a href="index.php?m=angsuran&no=<?php echo urlencode($j['no_pinjaman']); ?>"><?php echo $j['no_pinjaman']; ?></a></td>
            <td>
              <strong><?php echo htmlspecialchars($j['anggota_nama']); ?></strong><br>
              <small class="text-muted"><?php echo $j['no_anggota']; ?></small>
            </td>
            <td><span class="badge bg-secondary"><?php echo $jenis_options[$j['jenis_jaminan']] ?? $j['jenis_jaminan']; ?></span></td>
            <td>
              <strong><?php echo htmlspecialchars($j['nama_jaminan']); ?></strong>
              <?php if ($j['nomor_dokumen']): ?><br><small class="text-muted">No: <?php echo $j['nomor_dokumen']; ?></small><?php endif; ?>
            </td>
            <td>Rp <?php echo number_format($j['nilai_taksiran'], 0, ',', '.'); ?></td>
            <td>
              <span class="badge bg-<?php echo $j['status'] == 'disimpan' ? 'primary' : 'success'; ?>">
                <?php echo strtoupper($j['status']); ?>
              </span>
              <?php if ($j['tanggal_kembali']): ?><br><small><?php echo date('d/m/Y', strtotime($j['tanggal_kembali'])); ?></small><?php endif; ?>
            </td>
            <td>
              <?php if ($j['status'] == 'disimpan'): ?>
              <form method="post" style="display:inline;" onsubmit="return confirm('Tandai jaminan ini sudah dikembalikan?')">
                <?php echo csrf_input(); ?>
                <input type="hidden" name="id" value="<?php echo $j['id']; ?>">
                <button type="submit" name="return_jaminan" class="btn btn-sm btn-success">
                  <i class="bi bi-box-arrow-right"></i> Kembalikan
                </button>
              </form>
              <?php endif; ?>
              <button class="btn btn-sm btn-outline-info" onclick="viewDetail(<?php echo htmlspecialchars(json_encode($j)); ?>)">
                <i class="bi bi-eye"></i>
              </button>
            </td>
          </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="8" class="text-center text-muted py-4">Tidak ada data jaminan</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal Add -->
<div class="modal fade" id="addJaminanModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="post" enctype="multipart/form-data">
        <?php echo csrf_input(); ?>
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Tambah Jaminan</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Pinjaman</label>
              <select name="pinjaman_id" class="form-select" required>
                <option value="">-- Pilih Pinjaman --</option>
                <?php while ($p = $pinjaman_list->fetch_assoc()): ?>
                <option value="<?php echo $p['id']; ?>"><?php echo $p['no_pinjaman'] . ' - ' . htmlspecialchars($p['nama']); ?></option>
                <?php endwhile; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Jenis Jaminan</label>
              <select name="jenis_jaminan" class="form-select" required>
                <?php foreach ($jenis_options as $k => $v): ?>
                <option value="<?php echo $k; ?>"><?php echo $v; ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Nama/Deskripsi Jaminan</label>
              <input type="text" name="nama_jaminan" class="form-control" required placeholder="e.g. Honda Vario 2020">
            </div>
            <div class="col-md-6">
              <label class="form-label">Nomor Dokumen</label>
              <input type="text" name="nomor_dokumen" class="form-control" placeholder="e.g. B 1234 XYZ">
            </div>
            <div class="col-md-6">
              <label class="form-label">Nilai Taksiran (Rp)</label>
              <input type="number" name="nilai_taksiran" class="form-control" required min="0" step="100000">
            </div>
            <div class="col-md-6">
              <label class="form-label">Lokasi Penyimpanan</label>
              <input type="text" name="lokasi_penyimpanan" class="form-control" placeholder="e.g. Brankas Kantor">
            </div>
            <div class="col-md-6">
              <label class="form-label">Foto Jaminan</label>
              <input type="file" name="foto_jaminan" class="form-control" accept="image/*">
            </div>
            <div class="col-md-6">
              <label class="form-label">Keterangan</label>
              <input type="text" name="keterangan" class="form-control" placeholder="Opsional">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" name="add_jaminan" class="btn btn-gradient">Simpan Jaminan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Detail -->
<div class="modal fade" id="detailModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-info-circle me-2"></i>Detail Jaminan</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="detailContent"></div>
    </div>
  </div>
</div>

<script>
function viewDetail(data) {
  var jenis = <?php echo json_encode($jenis_options); ?>;
  var html = '<table class="table table-sm">';
  html += '<tr><td width="140">No. Pinjaman</td><td>: ' + data.no_pinjaman + '</td></tr>';
  html += '<tr><td>Jenis</td><td>: ' + (jenis[data.jenis_jaminan] || data.jenis_jaminan) + '</td></tr>';
  html += '<tr><td>Nama</td><td>: ' + data.nama_jaminan + '</td></tr>';
  html += '<tr><td>No. Dokumen</td><td>: ' + (data.nomor_dokumen || '-') + '</td></tr>';
  html += '<tr><td>Nilai Taksiran</td><td>: Rp ' + parseInt(data.nilai_taksiran).toLocaleString('id') + '</td></tr>';
  html += '<tr><td>Lokasi</td><td>: ' + (data.lokasi_penyimpanan || '-') + '</td></tr>';
  html += '<tr><td>Tgl Terima</td><td>: ' + data.tanggal_terima + '</td></tr>';
  html += '<tr><td>Tgl Kembali</td><td>: ' + (data.tanggal_kembali || '-') + '</td></tr>';
  html += '<tr><td>Keterangan</td><td>: ' + (data.keterangan || '-') + '</td></tr>';
  html += '</table>';
  if (data.foto_jaminan) {
    html += '<img src="uploads/jaminan/' + data.foto_jaminan + '" class="img-fluid rounded">';
  }
  document.getElementById('detailContent').innerHTML = html;
  new bootstrap.Modal(document.getElementById('detailModal')).show();
}
</script>

<?php
// views/blacklist.php - Blacklist System
require_login();
$conn = db();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  
  // Add to blacklist
  if (isset($_POST['add_blacklist'])) {
    $anggota_id = (int)$_POST['anggota_id'];
    $alasan = trim($_POST['alasan']);
    $tanggal_berakhir = !empty($_POST['tanggal_berakhir']) ? "'" . $conn->real_escape_string($_POST['tanggal_berakhir']) . "'" : 'NULL';
    
    // Check if already blacklisted
    $check = $conn->query("SELECT id FROM ksp_blacklist WHERE anggota_id = $anggota_id AND status = 'aktif'")->fetch_assoc();
    if ($check) {
      alert('warning', 'Anggota ini sudah dalam daftar blacklist.');
    } else {
      $stmt = $conn->prepare("INSERT INTO ksp_blacklist (anggota_id, alasan, tanggal_blacklist, tanggal_berakhir, created_by) VALUES (?, ?, CURDATE(), $tanggal_berakhir, ?)");
      $uid = user()['id'];
      $stmt->bind_param("isi", $anggota_id, $alasan, $uid);
      if ($stmt->execute()) {
        log_activity('add_blacklist', 'blacklist', 'Menambahkan anggota ke blacklist');
        alert('success', 'Anggota berhasil ditambahkan ke blacklist.');
      }
    }
    header('Location: index.php?m=blacklist');
    exit;
  }
  
  // Remove from blacklist
  if (isset($_POST['remove_blacklist'])) {
    $id = (int)$_POST['id'];
    $conn->query("UPDATE ksp_blacklist SET status = 'dicabut' WHERE id = $id");
    log_activity('remove_blacklist', 'blacklist', 'Mencabut blacklist anggota');
    alert('success', 'Status blacklist dicabut.');
    header('Location: index.php?m=blacklist');
    exit;
  }
}

// Get blacklist data
$blacklist = $conn->query("
  SELECT b.*, a.nama, a.no_anggota, a.hp, u.nama as created_by_name
  FROM ksp_blacklist b
  JOIN ksp_anggota a ON a.id = b.anggota_id
  LEFT JOIN ksp_users u ON u.id = b.created_by
  ORDER BY b.status ASC, b.tanggal_blacklist DESC
");

// Get anggota for dropdown (exclude already blacklisted)
$anggota_list = $conn->query("
  SELECT a.id, a.no_anggota, a.nama 
  FROM ksp_anggota a
  WHERE a.status = 'aktif'
  AND a.id NOT IN (SELECT anggota_id FROM ksp_blacklist WHERE status = 'aktif')
  ORDER BY a.nama
");

// Stats
$stats = $conn->query("
  SELECT 
    COUNT(CASE WHEN status = 'aktif' THEN 1 END) as total_aktif,
    COUNT(CASE WHEN status = 'dicabut' THEN 1 END) as total_dicabut
  FROM ksp_blacklist
")->fetch_assoc();
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4><i class="bi bi-person-x me-2"></i>Blacklist Anggota</h4>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="card card-widget bg-danger bg-opacity-10">
      <div class="card-body text-center">
        <i class="bi bi-person-x fs-2 text-danger"></i>
        <h3 class="mt-2 mb-0"><?php echo $stats['total_aktif'] ?: 0; ?></h3>
        <small class="text-muted">Blacklist Aktif</small>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card card-widget bg-success bg-opacity-10">
      <div class="card-body text-center">
        <i class="bi bi-person-check fs-2 text-success"></i>
        <h3 class="mt-2 mb-0"><?php echo $stats['total_dicabut'] ?: 0; ?></h3>
        <small class="text-muted">Dicabut</small>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card card-widget">
      <div class="card-body text-center">
        <i class="bi bi-info-circle fs-2 text-info"></i>
        <p class="mt-2 mb-0 small">Anggota blacklist tidak dapat mengajukan pinjaman baru</p>
      </div>
    </div>
  </div>
</div>

<div class="row">
  <!-- Form Add -->
  <div class="col-md-4">
    <div class="card card-widget">
      <div class="card-header bg-danger text-white">
        <h6 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Tambah ke Blacklist</h6>
      </div>
      <div class="card-body">
        <form method="post">
          <?php echo csrf_input(); ?>
          <div class="mb-3">
            <label class="form-label">Pilih Anggota</label>
            <select name="anggota_id" class="form-select select2" required>
              <option value="">-- Pilih --</option>
              <?php while ($a = $anggota_list->fetch_assoc()): ?>
              <option value="<?php echo $a['id']; ?>"><?php echo $a['no_anggota'] . ' - ' . htmlspecialchars($a['nama']); ?></option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Alasan Blacklist</label>
            <textarea name="alasan" class="form-control" rows="3" required placeholder="Jelaskan alasan blacklist..."></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">Tanggal Berakhir (Opsional)</label>
            <input type="date" name="tanggal_berakhir" class="form-control">
            <small class="text-muted">Kosongkan jika permanen</small>
          </div>
          <button type="submit" name="add_blacklist" class="btn btn-danger w-100">
            <i class="bi bi-person-x me-1"></i>Tambah ke Blacklist
          </button>
        </form>
      </div>
    </div>
  </div>
  
  <!-- List -->
  <div class="col-md-8">
    <div class="card card-widget">
      <div class="card-header">
        <h6 class="mb-0"><i class="bi bi-list me-2"></i>Daftar Blacklist</h6>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-striped mb-0">
            <thead>
              <tr>
                <th>Anggota</th>
                <th>Alasan</th>
                <th>Tanggal</th>
                <th>Berakhir</th>
                <th>Status</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody>
            <?php if ($blacklist && $blacklist->num_rows > 0): ?>
              <?php while ($b = $blacklist->fetch_assoc()): ?>
              <tr class="<?php echo $b['status'] == 'aktif' ? 'table-danger' : 'table-secondary'; ?>">
                <td>
                  <strong><?php echo htmlspecialchars($b['nama']); ?></strong><br>
                  <small class="text-muted"><?php echo $b['no_anggota']; ?> | <?php echo $b['hp']; ?></small>
                </td>
                <td><small><?php echo htmlspecialchars($b['alasan']); ?></small></td>
                <td><?php echo date('d/m/Y', strtotime($b['tanggal_blacklist'])); ?></td>
                <td><?php echo $b['tanggal_berakhir'] ? date('d/m/Y', strtotime($b['tanggal_berakhir'])) : '<span class="badge bg-dark">Permanen</span>'; ?></td>
                <td>
                  <span class="badge bg-<?php echo $b['status'] == 'aktif' ? 'danger' : 'success'; ?>">
                    <?php echo strtoupper($b['status']); ?>
                  </span>
                </td>
                <td>
                  <?php if ($b['status'] == 'aktif'): ?>
                  <form method="post" style="display:inline;" onsubmit="return confirm('Cabut blacklist anggota ini?')">
                    <?php echo csrf_input(); ?>
                    <input type="hidden" name="id" value="<?php echo $b['id']; ?>">
                    <button type="submit" name="remove_blacklist" class="btn btn-sm btn-success">
                      <i class="bi bi-check-lg"></i> Cabut
                    </button>
                  </form>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="6" class="text-center text-muted py-4">Tidak ada data blacklist</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

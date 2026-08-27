<?php
// views/backup.php - Database Backup Manager
require_login();

// Only admin
if (user()['role'] !== 'admin') {
  alert('danger', 'Akses ditolak.');
  header('Location: index.php?m=dashboard');
  exit;
}

$conn = db();
$backup_dir = __DIR__ . '/../backups';
if (!is_dir($backup_dir)) {
  mkdir($backup_dir, 0755, true);
}

// Handle backup request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  csrf_check();
  if ($_POST['action'] === 'backup') {
    $filename = 'backup_ksp_' . date('Y-m-d_His') . '.sql';
    $filepath = $backup_dir . '/' . $filename;
    
    // Get all tables
    $tables = array();
    $result = $conn->query("SHOW TABLES");
    while ($row = $result->fetch_row()) {
      $tables[] = $row[0];
    }
    
    $sql_dump = "-- KSP Harian Database Backup\n";
    $sql_dump .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $sql_dump .= "-- -------------------------------------------\n\n";
    $sql_dump .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
    
    foreach ($tables as $table) {
      // Get create table
      $create = $conn->query("SHOW CREATE TABLE `$table`")->fetch_row();
      $sql_dump .= "DROP TABLE IF EXISTS `$table`;\n";
      $sql_dump .= $create[1] . ";\n\n";
      
      // Get data
      $data = $conn->query("SELECT * FROM `$table`");
      if ($data->num_rows > 0) {
        while ($row = $data->fetch_assoc()) {
          $values = array();
          foreach ($row as $v) {
            $values[] = $v === null ? 'NULL' : "'" . $conn->real_escape_string($v) . "'";
          }
          $sql_dump .= "INSERT INTO `$table` VALUES (" . implode(',', $values) . ");\n";
        }
        $sql_dump .= "\n";
      }
    }
    
    $sql_dump .= "SET FOREIGN_KEY_CHECKS=1;\n";
    
    if (file_put_contents($filepath, $sql_dump)) {
      log_activity('backup_create', 'backup', 'Created backup: ' . $filename);
      alert('success', 'Backup berhasil dibuat: ' . $filename);
    } else {
      alert('danger', 'Gagal membuat backup.');
    }
    header('Location: index.php?m=backup');
    exit;
  }
  
  if ($_POST['action'] === 'delete' && isset($_POST['file'])) {
    $file = basename($_POST['file']);
    $filepath = $backup_dir . '/' . $file;
    if (file_exists($filepath) && unlink($filepath)) {
      log_activity('backup_delete', 'backup', 'Deleted backup: ' . $file);
      alert('success', 'Backup dihapus.');
    }
    header('Location: index.php?m=backup');
    exit;
  }
}

// Get existing backups
$backups = array();
if (is_dir($backup_dir)) {
  $files = scandir($backup_dir, SCANDIR_SORT_DESCENDING);
  foreach ($files as $file) {
    if (pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
      $filepath = $backup_dir . '/' . $file;
      $backups[] = array(
        'name' => $file,
        'size' => filesize($filepath),
        'date' => filemtime($filepath)
      );
    }
  }
}

// Get DB stats
$db_size = 0;
$table_count = 0;
$result = $conn->query("SHOW TABLE STATUS");
while ($row = $result->fetch_assoc()) {
  $db_size += $row['Data_length'] + $row['Index_length'];
  $table_count++;
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4><i class="bi bi-cloud-arrow-down me-2"></i>Backup Database</h4>
  <form method="post" style="display:inline;">
    <?php echo csrf_input(); ?>
    <input type="hidden" name="action" value="backup">
    <button type="submit" class="btn btn-success">
      <i class="bi bi-download me-2"></i>Buat Backup Sekarang
    </button>
  </form>
</div>

<!-- DB Info -->
<div class="row g-3 mb-4">
  <div class="col-md-3">
    <div class="card card-widget text-center">
      <div class="card-body">
        <i class="bi bi-database fs-1 text-primary"></i>
        <h4 class="mt-2"><?php echo $table_count; ?></h4>
        <div class="text-muted">Tabel</div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card card-widget text-center">
      <div class="card-body">
        <i class="bi bi-hdd fs-1 text-info"></i>
        <h4 class="mt-2"><?php echo round($db_size / 1024, 1); ?> KB</h4>
        <div class="text-muted">Ukuran Database</div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card card-widget text-center">
      <div class="card-body">
        <i class="bi bi-archive fs-1 text-success"></i>
        <h4 class="mt-2"><?php echo count($backups); ?></h4>
        <div class="text-muted">File Backup</div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card card-widget text-center">
      <div class="card-body">
        <i class="bi bi-clock-history fs-1 text-warning"></i>
        <h4 class="mt-2"><?php echo count($backups) > 0 ? date('d M', $backups[0]['date']) : '-'; ?></h4>
        <div class="text-muted">Backup Terakhir</div>
      </div>
    </div>
  </div>
</div>

<!-- Backup List -->
<div class="card card-widget">
  <div class="card-header">
    <h6 class="mb-0"><i class="bi bi-folder me-2"></i>Daftar Backup</h6>
  </div>
  <div class="card-body">
    <?php if (empty($backups)): ?>
    <div class="text-center text-muted py-4">
      <i class="bi bi-inbox fs-1 mb-2 d-block"></i>
      <p>Belum ada backup. Klik tombol "Buat Backup Sekarang" untuk membuat backup pertama.</p>
    </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-striped">
        <thead>
          <tr>
            <th>Nama File</th>
            <th>Ukuran</th>
            <th>Tanggal</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($backups as $b): ?>
        <tr>
          <td><i class="bi bi-file-earmark-code me-2 text-primary"></i><?php echo htmlspecialchars($b['name']); ?></td>
          <td><?php echo round($b['size'] / 1024, 1); ?> KB</td>
          <td><?php echo date('d M Y H:i', $b['date']); ?></td>
          <td>
            <a href="backups/<?php echo urlencode($b['name']); ?>" download class="btn btn-sm btn-outline-primary">
              <i class="bi bi-download"></i>
            </a>
            <form method="post" style="display:inline;" onsubmit="return confirm('Hapus backup ini?')">
              <?php echo csrf_input(); ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="file" value="<?php echo htmlspecialchars($b['name']); ?>">
              <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Tips -->
<div class="alert alert-info mt-3">
  <h6><i class="bi bi-lightbulb me-2"></i>Tips Backup:</h6>
  <ul class="mb-0 small">
    <li>Lakukan backup secara rutin, minimal seminggu sekali</li>
    <li>Download file backup dan simpan di tempat terpisah (cloud/external drive)</li>
    <li>Untuk restore, import file .sql melalui phpMyAdmin</li>
    <li>Hapus backup lama yang sudah tidak diperlukan</li>
  </ul>
</div>

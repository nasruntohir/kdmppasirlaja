<?php
// views/activity_log.php - Activity Log / Audit Trail
require_login();

// Only admin can view
if (user()['role'] !== 'admin') {
  alert('danger', 'Akses ditolak. Hanya admin yang bisa melihat log aktivitas.');
  header('Location: index.php?m=dashboard');
  exit;
}

$conn = db();

// Filter
$from = isset($_GET['from']) ? $_GET['from'] : date('Y-m-d', strtotime('-7 days'));
$to = isset($_GET['to']) ? $_GET['to'] : date('Y-m-d');
$user_filter = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$action_filter = isset($_GET['action']) ? $_GET['action'] : '';

$where = "WHERE DATE(l.created_at) BETWEEN '$from' AND '$to'";
if ($user_filter > 0) $where .= " AND l.user_id = $user_filter";
if ($action_filter) $where .= " AND l.action = '" . $conn->real_escape_string($action_filter) . "'";

// Get logs
$logs = $conn->query("
  SELECT l.*, u.nama as user_name 
  FROM ksp_activity_log l 
  LEFT JOIN ksp_users u ON u.id = l.user_id 
  $where 
  ORDER BY l.created_at DESC 
  LIMIT 500
");

// Get users for filter
$users = $conn->query("SELECT id, nama FROM ksp_users ORDER BY nama");

// Get distinct actions
$actions = $conn->query("SELECT DISTINCT action FROM ksp_activity_log ORDER BY action");
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4><i class="bi bi-clock-history me-2"></i>Activity Log</h4>
  <a href="export/export_activity_log.php?from=<?php echo $from; ?>&to=<?php echo $to; ?>" class="btn btn-success btn-sm">
    <i class="bi bi-file-earmark-excel me-1"></i> Export Excel
  </a>
</div>

<div class="card card-widget mb-3">
  <div class="card-body">
    <form method="get" class="row g-2">
      <input type="hidden" name="m" value="activity_log">
      <div class="col-md-2">
        <label class="form-label">Dari</label>
        <input type="date" name="from" class="form-control" value="<?php echo $from; ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label">Sampai</label>
        <input type="date" name="to" class="form-control" value="<?php echo $to; ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">User</label>
        <select name="user_id" class="form-select">
          <option value="">-- Semua User --</option>
          <?php while($u = $users->fetch_assoc()): ?>
          <option value="<?php echo $u['id']; ?>" <?php echo $user_filter==$u['id']?'selected':''; ?>><?php echo htmlspecialchars($u['nama']); ?></option>
          <?php endwhile; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Action</label>
        <select name="action" class="form-select">
          <option value="">-- Semua Action --</option>
          <?php while($a = $actions->fetch_assoc()): ?>
          <option value="<?php echo $a['action']; ?>" <?php echo $action_filter==$a['action']?'selected':''; ?>><?php echo $a['action']; ?></option>
          <?php endwhile; ?>
        </select>
      </div>
      <div class="col-md-2 align-self-end">
        <button class="btn btn-gradient w-100">Filter</button>
      </div>
    </form>
  </div>
</div>

<div class="card card-widget">
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-striped table-sm" id="activityTable">
        <thead>
          <tr>
            <th>Waktu</th>
            <th>User</th>
            <th>Action</th>
            <th>Module</th>
            <th>Detail</th>
            <th>IP Address</th>
          </tr>
        </thead>
        <tbody>
        <?php if ($logs && $logs->num_rows > 0): ?>
          <?php while($log = $logs->fetch_assoc()): 
            $badge_class = 'bg-secondary';
            if (strpos($log['action'], 'create') !== false || strpos($log['action'], 'add') !== false) $badge_class = 'bg-success';
            elseif (strpos($log['action'], 'update') !== false || strpos($log['action'], 'edit') !== false) $badge_class = 'bg-warning';
            elseif (strpos($log['action'], 'delete') !== false) $badge_class = 'bg-danger';
            elseif (strpos($log['action'], 'login') !== false) $badge_class = 'bg-info';
          ?>
          <tr>
            <td><?php echo date('d M Y H:i:s', strtotime($log['created_at'])); ?></td>
            <td><?php echo htmlspecialchars($log['user_name'] ?: 'System'); ?></td>
            <td><span class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($log['action']); ?></span></td>
            <td><?php echo htmlspecialchars($log['module']); ?></td>
            <td><small><?php echo htmlspecialchars(substr($log['description'], 0, 100)); ?></small></td>
            <td><small><?php echo htmlspecialchars($log['ip_address']); ?></small></td>
          </tr>
          <?php endwhile; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  if ($.fn.DataTable && $('#activityTable tbody tr').length > 0) {
    $('#activityTable').DataTable({
      order: [[0, 'desc']],
      pageLength: 25,
      language: {
        search: 'Cari:',
        lengthMenu: 'Tampilkan _MENU_',
        info: '_START_ - _END_ dari _TOTAL_',
        infoEmpty: 'Tidak ada data',
        zeroRecords: 'Data tidak ditemukan',
        paginate: { previous: '‹', next: '›' }
      }
    });
  }
});
</script>

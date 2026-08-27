<?php
// views/approval.php - Loan Approval Workflow
require_login();
$conn = db();

// Only admin/manager can access
if (!in_array(user()['role'], ['admin', 'manager'])) {
  alert('danger', 'Akses ditolak. Hanya admin/manager.');
  header('Location: index.php?m=dashboard');
  exit;
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  
  // Approve loan
  if (isset($_POST['approve'])) {
    $id = (int)$_POST['pinjaman_id'];
    $uid = user()['id'];
    $conn->query("UPDATE ksp_pinjaman SET approval_status = 'approved', approved_by = $uid, approved_at = NOW() WHERE id = $id");
    log_activity('approve_loan', 'approval', 'Menyetujui pinjaman ID: ' . $id);
    alert('success', 'Pinjaman berhasil disetujui.');
    header('Location: index.php?m=approval');
    exit;
  }
  
  // Reject loan
  if (isset($_POST['reject'])) {
    $id = (int)$_POST['pinjaman_id'];
    $reason = $conn->real_escape_string(trim($_POST['rejection_reason']));
    $uid = user()['id'];
    $conn->query("UPDATE ksp_pinjaman SET approval_status = 'rejected', approved_by = $uid, approved_at = NOW(), rejection_reason = '$reason', status = 'ditolak' WHERE id = $id");
    log_activity('reject_loan', 'approval', 'Menolak pinjaman ID: ' . $id);
    alert('success', 'Pinjaman ditolak.');
    header('Location: index.php?m=approval');
    exit;
  }
}

// Get pending approvals
$pending = $conn->query("
  SELECT p.*, a.nama, a.no_anggota, a.hp, a.alamat, pr.nama as produk_nama,
    COALESCE(s.total_simpanan, 0) as total_simpanan,
    (SELECT COUNT(*) FROM ksp_blacklist WHERE anggota_id = a.id AND status = 'aktif') as is_blacklisted,
    (SELECT COUNT(*) FROM ksp_pinjaman WHERE anggota_id = a.id AND status = 'aktif') as pinjaman_aktif
  FROM ksp_pinjaman p
  JOIN ksp_anggota a ON a.id = p.anggota_id
  JOIN ksp_produk_pinjaman pr ON pr.id = p.produk_id
  LEFT JOIN ksp_simpanan_anggota s ON s.anggota_id = a.id
  WHERE p.approval_status = 'pending'
  ORDER BY p.tanggal_realisasi DESC
");

// Get recent decisions
$recent = $conn->query("
  SELECT p.*, a.nama, a.no_anggota, u.nama as approved_by_name
  FROM ksp_pinjaman p
  JOIN ksp_anggota a ON a.id = p.anggota_id
  LEFT JOIN ksp_users u ON u.id = p.approved_by
  WHERE p.approval_status IN ('approved', 'rejected')
  ORDER BY p.approved_at DESC
  LIMIT 20
");

// Stats
$stats = $conn->query("
  SELECT 
    COUNT(CASE WHEN approval_status = 'pending' THEN 1 END) as pending,
    COUNT(CASE WHEN approval_status = 'approved' AND DATE(approved_at) = CURDATE() THEN 1 END) as approved_today,
    COUNT(CASE WHEN approval_status = 'rejected' AND DATE(approved_at) = CURDATE() THEN 1 END) as rejected_today
  FROM ksp_pinjaman
")->fetch_assoc();
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4><i class="bi bi-clipboard-check me-2"></i>Approval Pinjaman</h4>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="card card-widget bg-warning bg-opacity-10">
      <div class="card-body text-center">
        <i class="bi bi-hourglass-split fs-2 text-warning"></i>
        <h3 class="mt-2 mb-0"><?php echo $stats['pending'] ?: 0; ?></h3>
        <small class="text-muted">Menunggu Approval</small>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card card-widget bg-success bg-opacity-10">
      <div class="card-body text-center">
        <i class="bi bi-check-circle fs-2 text-success"></i>
        <h3 class="mt-2 mb-0"><?php echo $stats['approved_today'] ?: 0; ?></h3>
        <small class="text-muted">Disetujui Hari Ini</small>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card card-widget bg-danger bg-opacity-10">
      <div class="card-body text-center">
        <i class="bi bi-x-circle fs-2 text-danger"></i>
        <h3 class="mt-2 mb-0"><?php echo $stats['rejected_today'] ?: 0; ?></h3>
        <small class="text-muted">Ditolak Hari Ini</small>
      </div>
    </div>
  </div>
</div>

<!-- Pending Approvals -->
<div class="card card-widget mb-4">
  <div class="card-header bg-warning text-dark">
    <h6 class="mb-0"><i class="bi bi-hourglass-split me-2"></i>Pinjaman Menunggu Approval</h6>
  </div>
  <div class="card-body p-0">
    <?php if ($pending && $pending->num_rows > 0): ?>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>No. Pinjaman</th>
            <th>Anggota</th>
            <th>Produk</th>
            <th>Pokok</th>
            <th>Tenor</th>
            <th>Risk Check</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
        <?php while ($p = $pending->fetch_assoc()): ?>
        <tr>
          <td>
            <strong><?php echo $p['no_pinjaman']; ?></strong><br>
            <small class="text-muted"><?php echo date('d/m/Y', strtotime($p['tanggal_realisasi'])); ?></small>
          </td>
          <td>
            <strong><?php echo htmlspecialchars($p['nama']); ?></strong><br>
            <small class="text-muted"><?php echo $p['no_anggota']; ?> | <?php echo $p['hp']; ?></small>
          </td>
          <td><?php echo htmlspecialchars($p['produk_nama']); ?></td>
          <td><strong>Rp <?php echo number_format($p['pokok'], 0, ',', '.'); ?></strong></td>
          <td><?php echo $p['tenor_hari']; ?> hari</td>
          <td>
            <!-- Risk Indicators -->
            <?php if ($p['is_blacklisted'] > 0): ?>
            <span class="badge bg-danger" title="Anggota dalam blacklist"><i class="bi bi-exclamation-triangle"></i> BLACKLIST</span>
            <?php endif; ?>
            
            <?php if ($p['pinjaman_aktif'] > 0): ?>
            <span class="badge bg-warning" title="Masih punya pinjaman aktif"><i class="bi bi-info-circle"></i> <?php echo $p['pinjaman_aktif']; ?> Pinjaman Aktif</span>
            <?php endif; ?>
            
            <?php if ($p['total_simpanan'] > 0): ?>
            <span class="badge bg-success" title="Simpanan"><i class="bi bi-wallet2"></i> Rp <?php echo number_format($p['total_simpanan'], 0, ',', '.'); ?></span>
            <?php else: ?>
            <span class="badge bg-secondary"><i class="bi bi-wallet2"></i> Belum ada simpanan</span>
            <?php endif; ?>
            
            <?php 
            $ratio = $p['total_simpanan'] > 0 ? round($p['pokok'] / $p['total_simpanan'] * 100) : 0;
            if ($ratio > 300): ?>
            <span class="badge bg-danger" title="Pinjaman > 3x simpanan"><i class="bi bi-graph-up"></i> Ratio <?php echo $ratio; ?>%</span>
            <?php endif; ?>
          </td>
          <td>
            <div class="btn-group">
              <button class="btn btn-sm btn-success" onclick="approveModal(<?php echo $p['id']; ?>, '<?php echo $p['no_pinjaman']; ?>')">
                <i class="bi bi-check-lg"></i> Setuju
              </button>
              <button class="btn btn-sm btn-danger" onclick="rejectModal(<?php echo $p['id']; ?>, '<?php echo $p['no_pinjaman']; ?>')">
                <i class="bi bi-x-lg"></i> Tolak
              </button>
            </div>
          </td>
        </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <div class="text-center py-5">
      <i class="bi bi-check-circle fs-1 text-success"></i>
      <p class="mt-3 text-muted">Tidak ada pinjaman yang menunggu approval</p>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Recent Decisions -->
<div class="card card-widget">
  <div class="card-header">
    <h6 class="mb-0"><i class="bi bi-clock-history me-2"></i>Riwayat Approval Terbaru</h6>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-sm table-striped mb-0">
        <thead>
          <tr><th>Tanggal</th><th>No. Pinjaman</th><th>Anggota</th><th>Status</th><th>Diproses Oleh</th></tr>
        </thead>
        <tbody>
        <?php if ($recent && $recent->num_rows > 0): ?>
          <?php while ($r = $recent->fetch_assoc()): ?>
          <tr>
            <td><?php echo $r['approved_at'] ? date('d/m/Y H:i', strtotime($r['approved_at'])) : '-'; ?></td>
            <td><?php echo $r['no_pinjaman']; ?></td>
            <td><?php echo htmlspecialchars($r['nama']); ?></td>
            <td>
              <span class="badge bg-<?php echo $r['approval_status'] == 'approved' ? 'success' : 'danger'; ?>">
                <?php echo strtoupper($r['approval_status']); ?>
              </span>
              <?php if ($r['rejection_reason']): ?>
              <br><small class="text-muted"><?php echo htmlspecialchars($r['rejection_reason']); ?></small>
              <?php endif; ?>
            </td>
            <td><?php echo htmlspecialchars($r['approved_by_name']); ?></td>
          </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="5" class="text-center text-muted py-3">Belum ada riwayat</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Approve Modal -->
<div class="modal fade" id="approveModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        <?php echo csrf_input(); ?>
        <input type="hidden" name="pinjaman_id" id="approve_pinjaman_id">
        <div class="modal-header bg-success text-white">
          <h5 class="modal-title"><i class="bi bi-check-circle me-2"></i>Setujui Pinjaman</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p>Anda yakin ingin menyetujui pinjaman <strong id="approve_no"></strong>?</p>
          <p class="text-muted small">Pinjaman akan diaktifkan dan jadwal angsuran akan dibuat.</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" name="approve" class="btn btn-success"><i class="bi bi-check-lg me-1"></i>Ya, Setujui</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        <?php echo csrf_input(); ?>
        <input type="hidden" name="pinjaman_id" id="reject_pinjaman_id">
        <div class="modal-header bg-danger text-white">
          <h5 class="modal-title"><i class="bi bi-x-circle me-2"></i>Tolak Pinjaman</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p>Tolak pinjaman <strong id="reject_no"></strong>?</p>
          <div class="mb-3">
            <label class="form-label">Alasan Penolakan</label>
            <textarea name="rejection_reason" class="form-control" rows="3" required placeholder="Jelaskan alasan penolakan..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" name="reject" class="btn btn-danger"><i class="bi bi-x-lg me-1"></i>Ya, Tolak</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function approveModal(id, no) {
  document.getElementById('approve_pinjaman_id').value = id;
  document.getElementById('approve_no').textContent = no;
  new bootstrap.Modal(document.getElementById('approveModal')).show();
}
function rejectModal(id, no) {
  document.getElementById('reject_pinjaman_id').value = id;
  document.getElementById('reject_no').textContent = no;
  new bootstrap.Modal(document.getElementById('rejectModal')).show();
}
</script>

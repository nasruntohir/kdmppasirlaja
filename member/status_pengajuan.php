<?php
// member/status_pengajuan.php - Status Pengajuan (Mobile App Style)
require_once __DIR__ . '/../lib/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['member_id'])) {
  header('Location: index.php');
  exit;
}

$conn = db();
$member_id = $_SESSION['member_id'];

$pengajuan = $conn->query("SELECT * FROM ksp_pengajuan_online WHERE anggota_id = $member_id ORDER BY created_at DESC");

$company = setting_get('company_name', 'KSP Harian');
$page = 'status';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <title>Status Pengajuan - <?php echo htmlspecialchars($company); ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/mobile.css" rel="stylesheet">
</head>
<body>
  <!-- App Bar -->
  <div class="app-bar app-bar-simple">
    <div class="app-bar-row">
      <a href="dashboard.php" style="color:white;text-decoration:none;font-size:24px;"><i class="bi bi-arrow-left"></i></a>
      <h1 style="flex:1;text-align:center;margin:0;">Status Pengajuan</h1>
      <div style="width:24px;"></div>
    </div>
  </div>

  <div style="padding:20px;padding-top:30px;">
    <?php if ($pengajuan->num_rows > 0): ?>
      <?php while ($p = $pengajuan->fetch_assoc()): 
        $status_info = [
          'pending' => ['Menunggu Review', 'badge-warning', 'hourglass-split', '#f59e0b'],
          'review' => ['Sedang Direview', 'badge-info', 'search', '#06b6d4'],
          'approved' => ['Disetujui', 'badge-success', 'check-circle', '#10b981'],
          'rejected' => ['Ditolak', 'badge-danger', 'x-circle', '#ef4444'],
          'disbursed' => ['Dana Dicairkan', 'badge-primary', 'cash-stack', '#6366f1']
        ];
        $st = $status_info[$p['status']] ?? ['Unknown', 'badge-secondary', 'question', '#6b7280'];
      ?>
      <div class="status-card" style="border-left:4px solid <?php echo $st[3]; ?>;margin-bottom:20px;">
        <div class="status-card-header">
          <div>
            <div class="fw-bold"><?php echo $p['no_pengajuan']; ?></div>
            <div class="text-sm text-gray"><?php echo date('d M Y, H:i', strtotime($p['created_at'])); ?></div>
          </div>
          <span class="badge <?php echo $st[1]; ?>"><i class="bi bi-<?php echo $st[2]; ?>"></i> <?php echo $st[0]; ?></span>
        </div>
        <div class="status-card-body">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-bottom:15px;">
            <div>
              <div class="text-sm text-gray">Jumlah</div>
              <div class="fw-bold text-primary">Rp <?php echo number_format($p['jumlah_pinjaman'], 0, ',', '.'); ?></div>
            </div>
            <div>
              <div class="text-sm text-gray">Tenor</div>
              <div class="fw-bold"><?php echo $p['tenor_diinginkan']; ?> Hari</div>
            </div>
            <div>
              <div class="text-sm text-gray">Tipe</div>
              <div class="fw-bold"><?php echo ucfirst($p['tipe_angsuran']); ?></div>
            </div>
            <div>
              <div class="text-sm text-gray">Tujuan</div>
              <div class="fw-bold"><?php echo htmlspecialchars($p['tujuan_pinjaman']); ?></div>
            </div>
          </div>
          
          <?php if ($p['status'] == 'rejected' && $p['rejection_reason']): ?>
          <div class="alert alert-danger" style="margin:0;">
            <i class="bi bi-info-circle"></i>
            <div><strong>Alasan:</strong> <?php echo htmlspecialchars($p['rejection_reason']); ?></div>
          </div>
          <?php elseif ($p['status'] == 'approved'): ?>
          <div class="alert alert-success" style="margin:0;">
            <i class="bi bi-check-circle"></i>
            <div>Disetujui! Silakan datang ke kantor untuk pencairan.</div>
          </div>
          <?php elseif ($p['status'] == 'disbursed'): ?>
          <div class="alert alert-info" style="margin:0;">
            <i class="bi bi-cash-stack"></i>
            <div>Dana telah dicairkan. Cek di <a href="dashboard.php">Dashboard</a>.</div>
          </div>
          <?php endif; ?>
          
          <!-- Timeline -->
          <div class="timeline" style="margin-top:15px;">
            <div class="timeline-item done">
              <div class="text">Pengajuan Dikirim</div>
              <div class="time"><?php echo date('d M Y, H:i', strtotime($p['created_at'])); ?></div>
            </div>
            <?php if (in_array($p['status'], ['review', 'approved', 'rejected', 'disbursed'])): ?>
            <div class="timeline-item <?php echo $p['status'] == 'rejected' ? 'rejected' : 'done'; ?>">
              <div class="text">Review Admin</div>
              <div class="time"><?php echo $p['reviewed_at'] ? date('d M Y, H:i', strtotime($p['reviewed_at'])) : '-'; ?></div>
            </div>
            <?php endif; ?>
            <?php if (in_array($p['status'], ['approved', 'disbursed'])): ?>
            <div class="timeline-item done">
              <div class="text">Disetujui</div>
              <div class="time"><?php echo $p['approved_at'] ? date('d M Y, H:i', strtotime($p['approved_at'])) : '-'; ?></div>
            </div>
            <?php endif; ?>
            <?php if ($p['status'] == 'rejected'): ?>
            <div class="timeline-item rejected">
              <div class="text">Ditolak</div>
              <div class="time"><?php echo $p['reviewed_at'] ? date('d M Y, H:i', strtotime($p['reviewed_at'])) : '-'; ?></div>
            </div>
            <?php endif; ?>
            <?php if ($p['status'] == 'disbursed'): ?>
            <div class="timeline-item done">
              <div class="text">Dana Dicairkan</div>
              <div class="time">Pinjaman aktif</div>
            </div>
            <?php endif; ?>
            <?php if ($p['status'] == 'pending'): ?>
            <div class="timeline-item pending">
              <div class="text">Menunggu Review</div>
              <div class="time">Estimasi 1-2 hari kerja</div>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endwhile; ?>
    <?php else: ?>
      <div class="empty-state">
        <i class="bi bi-inbox"></i>
        <h4>Belum Ada Pengajuan</h4>
        <p>Anda belum pernah mengajukan pinjaman online.</p>
        <a href="pengajuan.php" class="btn btn-primary btn-block mt-3"><i class="bi bi-plus-lg"></i> Ajukan Sekarang</a>
      </div>
    <?php endif; ?>
  </div>

  <!-- Bottom Navigation -->
  <div class="bottom-nav">
    <a href="dashboard.php" class="nav-item"><i class="bi bi-house"></i><span>Home</span></a>
    <a href="status_pengajuan.php" class="nav-item active"><i class="bi bi-clock-history"></i><span>Status</span></a>
    <a href="pengajuan.php" class="nav-item center-btn"><div class="icon-wrap"><i class="bi bi-plus-lg"></i></div><span>Ajukan</span></a>
    <a href="simpanan.php" class="nav-item"><i class="bi bi-wallet2"></i><span>Simpanan</span></a>
    <a href="profil.php" class="nav-item"><i class="bi bi-person"></i><span>Profil</span></a>
  </div>
</body>
</html>

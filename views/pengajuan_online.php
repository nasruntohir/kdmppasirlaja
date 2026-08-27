<?php
// views/pengajuan_online.php - Admin: Kelola Pengajuan Pinjaman Online
require_login();
$conn = db();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $id = (int)$_POST['pengajuan_id'];
  $uid = user()['id'];
  
  // Review
  if (isset($_POST['review'])) {
    $conn->query("UPDATE ksp_pengajuan_online SET status = 'review', reviewed_by = $uid, reviewed_at = NOW() WHERE id = $id");
    alert('success', 'Pengajuan sedang direview.');
  }
  
  // Approve
  if (isset($_POST['approve'])) {
    $conn->query("UPDATE ksp_pengajuan_online SET status = 'approved', approved_by = $uid, approved_at = NOW() WHERE id = $id");
    
    // Create notification for member
    $peng = $conn->query("SELECT * FROM ksp_pengajuan_online WHERE id = $id")->fetch_assoc();
    $conn->query("INSERT INTO ksp_notifikasi (anggota_id, judul, pesan, tipe, link) VALUES ({$peng['anggota_id']}, 'Pengajuan Disetujui', 'Pengajuan pinjaman Anda no. {$peng['no_pengajuan']} telah disetujui. Silakan datang ke kantor untuk pencairan.', 'success', 'status_pengajuan.php')");
    
    log_activity('approve_pengajuan', 'pengajuan', 'Approve pengajuan: ' . $peng['no_pengajuan']);
    alert('success', 'Pengajuan disetujui!');
  }
  
  // Reject
  if (isset($_POST['reject'])) {
    $reason = $conn->real_escape_string(trim($_POST['rejection_reason']));
    $conn->query("UPDATE ksp_pengajuan_online SET status = 'rejected', rejection_reason = '$reason', reviewed_by = $uid, reviewed_at = NOW() WHERE id = $id");
    
    // Create notification
    $peng = $conn->query("SELECT * FROM ksp_pengajuan_online WHERE id = $id")->fetch_assoc();
    $conn->query("INSERT INTO ksp_notifikasi (anggota_id, judul, pesan, tipe, link) VALUES ({$peng['anggota_id']}, 'Pengajuan Ditolak', 'Maaf, pengajuan pinjaman Anda no. {$peng['no_pengajuan']} tidak dapat disetujui. Alasan: $reason', 'danger', 'status_pengajuan.php')");
    
    log_activity('reject_pengajuan', 'pengajuan', 'Reject pengajuan: ' . $peng['no_pengajuan']);
    alert('warning', 'Pengajuan ditolak.');
  }
  
  // Disburse (Cairkan) - Create actual loan
  if (isset($_POST['disburse'])) {
    $peng = $conn->query("SELECT po.*, a.no_anggota FROM ksp_pengajuan_online po JOIN ksp_anggota a ON a.id = po.anggota_id WHERE po.id = $id")->fetch_assoc();
    
    if ($peng && $peng['status'] == 'approved') {
      // Get default produk or first produk
      $produk = $conn->query("SELECT * FROM ksp_produk_pinjaman ORDER BY id LIMIT 1")->fetch_assoc();
      
      $anggota_id = $peng['anggota_id'];
      $pokok = $peng['jumlah_pinjaman'];
      $tenor = $peng['tenor_diinginkan'];
      $tipe = $peng['tipe_angsuran'];
      $bp = $produk ? $produk['bunga_persen'] : 20;
      $admin = $produk ? $produk['admin_biaya'] : 25000;
      $tgl = date('Y-m-d');
      
      $total_bunga = round($pokok * ($bp / 100));
      $total_pinjaman = $pokok + $total_bunga;
      $dana_diterima = $pokok - $admin;
      $angsuran_per_periode = round($total_pinjaman / max(1, $tenor));
      
      $no_pinjaman = gen_number('prefix_no_pinjaman');
      
      // Insert pinjaman
      $stmt = $conn->prepare("INSERT INTO ksp_pinjaman(no_pinjaman, anggota_id, produk_id, tanggal_realisasi, pokok, tenor_hari, bunga_tipe, bunga_persen, total_bunga, total_pinjaman, dana_diterima, angsuran_per_periode, tipe_angsuran, admin_biaya, created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
      $produk_id = $produk ? $produk['id'] : 1;
      $stmt->bind_param("siisisiiiiisiii", $no_pinjaman, $anggota_id, $produk_id, $tgl, $pokok, $tenor, $tipe, $bp, $total_bunga, $total_pinjaman, $dana_diterima, $angsuran_per_periode, $tipe, $admin, $uid);
      
      if ($stmt->execute()) {
        $pinjaman_id = $conn->insert_id;
        
        // Generate jadwal
        if ($tipe == 'harian') {
          for ($i=1; $i <= $tenor; $i++) {
            $jatuh = date('Y-m-d', strtotime($tgl . " +".($i-1)." day"));
            $pokok_per = round($pokok / $tenor);
            $bunga_per = round($total_bunga / $tenor);
            $total_per = $angsuran_per_periode;
            $conn->query("INSERT INTO ksp_jadwal_angsuran(pinjaman_id, hari_ke, tanggal_jatuh_tempo, pokok_harian, bunga_harian, total_tagih) VALUES($pinjaman_id, $i, '$jatuh', $pokok_per, $bunga_per, $total_per)");
          }
        } elseif ($tipe == 'mingguan') {
          for ($i=1; $i <= $tenor; $i++) {
            $jatuh = date('Y-m-d', strtotime($tgl . " +".($i * 7 - 7)." day"));
            $pokok_per = round($pokok / $tenor);
            $bunga_per = round($total_bunga / $tenor);
            $total_per = $angsuran_per_periode;
            $conn->query("INSERT INTO ksp_jadwal_angsuran(pinjaman_id, hari_ke, tanggal_jatuh_tempo, pokok_harian, bunga_harian, total_tagih) VALUES($pinjaman_id, $i, '$jatuh', $pokok_per, $bunga_per, $total_per)");
          }
        } else {
          $jatuh = date('Y-m-d', strtotime($tgl . " +$tenor day"));
          $conn->query("INSERT INTO ksp_jadwal_angsuran(pinjaman_id, hari_ke, tanggal_jatuh_tempo, pokok_harian, bunga_harian, total_tagih) VALUES($pinjaman_id, 1, '$jatuh', $pokok, $total_bunga, $total_pinjaman)");
        }
        
        // Update pengajuan
        $conn->query("UPDATE ksp_pengajuan_online SET status = 'disbursed', pinjaman_id = $pinjaman_id WHERE id = $id");
        
        // Notification
        $conn->query("INSERT INTO ksp_notifikasi (anggota_id, judul, pesan, tipe) VALUES ($anggota_id, 'Dana Dicairkan', 'Dana pinjaman Anda sebesar Rp ".number_format($dana_diterima,0,',','.')." telah dicairkan. No. Pinjaman: $no_pinjaman', 'success')");
        
        log_activity('disburse_pengajuan', 'pengajuan', 'Cairkan pengajuan: ' . $peng['no_pengajuan'] . ' -> ' . $no_pinjaman);
        alert('success', 'Dana berhasil dicairkan! No. Pinjaman: ' . $no_pinjaman);
      }
    }
  }
  
  header('Location: index.php?m=pengajuan_online');
  exit;
}

// Get data
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'pending';
$where = $status_filter ? "WHERE po.status = '$status_filter'" : '';

$list = $conn->query("
  SELECT po.*, a.nama, a.no_anggota, a.hp, a.alamat,
    (SELECT COUNT(*) FROM ksp_blacklist WHERE anggota_id = a.id AND status = 'aktif') as is_blacklisted,
    (SELECT COUNT(*) FROM ksp_pinjaman WHERE anggota_id = a.id AND status = 'aktif') as pinjaman_aktif,
    COALESCE((SELECT total_simpanan FROM ksp_simpanan_anggota WHERE anggota_id = a.id), 0) as total_simpanan
  FROM ksp_pengajuan_online po
  JOIN ksp_anggota a ON a.id = po.anggota_id
  $where
  ORDER BY po.created_at DESC
");

// Stats
$stats = $conn->query("
  SELECT 
    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
    COUNT(CASE WHEN status = 'review' THEN 1 END) as review,
    COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved,
    COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected,
    COUNT(CASE WHEN status = 'disbursed' THEN 1 END) as disbursed
  FROM ksp_pengajuan_online
")->fetch_assoc();
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4><i class="bi bi-cloud-arrow-down me-2"></i>Pengajuan Pinjaman Online</h4>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
  <div class="col">
    <a href="index.php?m=pengajuan_online&status=pending" class="card card-widget text-decoration-none <?php echo $status_filter=='pending'?'border-warning border-2':''; ?>">
      <div class="card-body text-center py-3">
        <i class="bi bi-hourglass-split fs-4 text-warning"></i>
        <h4 class="mb-0 mt-2"><?php echo $stats['pending']; ?></h4>
        <small class="text-muted">Pending</small>
      </div>
    </a>
  </div>
  <div class="col">
    <a href="index.php?m=pengajuan_online&status=review" class="card card-widget text-decoration-none <?php echo $status_filter=='review'?'border-info border-2':''; ?>">
      <div class="card-body text-center py-3">
        <i class="bi bi-search fs-4 text-info"></i>
        <h4 class="mb-0 mt-2"><?php echo $stats['review']; ?></h4>
        <small class="text-muted">Review</small>
      </div>
    </a>
  </div>
  <div class="col">
    <a href="index.php?m=pengajuan_online&status=approved" class="card card-widget text-decoration-none <?php echo $status_filter=='approved'?'border-success border-2':''; ?>">
      <div class="card-body text-center py-3">
        <i class="bi bi-check-circle fs-4 text-success"></i>
        <h4 class="mb-0 mt-2"><?php echo $stats['approved']; ?></h4>
        <small class="text-muted">Approved</small>
      </div>
    </a>
  </div>
  <div class="col">
    <a href="index.php?m=pengajuan_online&status=rejected" class="card card-widget text-decoration-none <?php echo $status_filter=='rejected'?'border-danger border-2':''; ?>">
      <div class="card-body text-center py-3">
        <i class="bi bi-x-circle fs-4 text-danger"></i>
        <h4 class="mb-0 mt-2"><?php echo $stats['rejected']; ?></h4>
        <small class="text-muted">Rejected</small>
      </div>
    </a>
  </div>
  <div class="col">
    <a href="index.php?m=pengajuan_online&status=disbursed" class="card card-widget text-decoration-none <?php echo $status_filter=='disbursed'?'border-primary border-2':''; ?>">
      <div class="card-body text-center py-3">
        <i class="bi bi-cash-stack fs-4 text-primary"></i>
        <h4 class="mb-0 mt-2"><?php echo $stats['disbursed']; ?></h4>
        <small class="text-muted">Dicairkan</small>
      </div>
    </a>
  </div>
</div>

<!-- List -->
<div class="card card-widget">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead class="bg-light">
          <tr>
            <th>No. Pengajuan</th>
            <th>Anggota</th>
            <th>Jumlah</th>
            <th>Tenor</th>
            <th>Risk Check</th>
            <th>Tanggal</th>
            <th>Status</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
        <?php if ($list && $list->num_rows > 0): ?>
          <?php while ($p = $list->fetch_assoc()): ?>
          <tr class="<?php echo $p['is_blacklisted'] ? 'table-danger' : ''; ?>">
            <td>
              <strong><?php echo $p['no_pengajuan']; ?></strong><br>
              <small class="text-muted"><?php echo ucfirst($p['tipe_angsuran']); ?></small>
            </td>
            <td>
              <strong><?php echo htmlspecialchars($p['nama']); ?></strong>
              <?php if ($p['is_blacklisted']): ?><span class="badge bg-danger">BLACKLIST</span><?php endif; ?>
              <br><small class="text-muted"><?php echo $p['no_anggota']; ?> | <?php echo $p['hp']; ?></small>
            </td>
            <td><strong>Rp <?php echo number_format($p['jumlah_pinjaman'], 0, ',', '.'); ?></strong></td>
            <td><?php echo $p['tenor_diinginkan']; ?> hari</td>
            <td>
              <?php if ($p['is_blacklisted']): ?>
              <span class="badge bg-danger"><i class="bi bi-x-circle"></i> Blacklist</span>
              <?php endif; ?>
              <?php if ($p['pinjaman_aktif'] > 0): ?>
              <span class="badge bg-warning"><?php echo $p['pinjaman_aktif']; ?> Aktif</span>
              <?php endif; ?>
              <span class="badge bg-info">Simpanan: Rp <?php echo number_format($p['total_simpanan'],0,',','.'); ?></span>
            </td>
            <td><?php echo date('d/m/Y H:i', strtotime($p['created_at'])); ?></td>
            <td>
              <?php
              $badges = ['pending'=>'warning','review'=>'info','approved'=>'success','rejected'=>'danger','disbursed'=>'primary'];
              ?>
              <span class="badge bg-<?php echo $badges[$p['status']]; ?>"><?php echo strtoupper($p['status']); ?></span>
            </td>
            <td>
              <div class="btn-group">
                <button class="btn btn-sm btn-outline-primary" onclick="viewDetail(<?php echo htmlspecialchars(json_encode($p)); ?>)">
                  <i class="bi bi-eye"></i>
                </button>
                
                <?php if ($p['status'] == 'pending'): ?>
                <form method="post" style="display:inline;">
                  <?php echo csrf_input(); ?>
                  <input type="hidden" name="pengajuan_id" value="<?php echo $p['id']; ?>">
                  <button type="submit" name="review" class="btn btn-sm btn-info"><i class="bi bi-search"></i> Review</button>
                </form>
                <?php endif; ?>
                
                <?php if ($p['status'] == 'review'): ?>
                <?php if (!$p['is_blacklisted']): ?>
                <form method="post" style="display:inline;">
                  <?php echo csrf_input(); ?>
                  <input type="hidden" name="pengajuan_id" value="<?php echo $p['id']; ?>">
                  <button type="submit" name="approve" class="btn btn-sm btn-success"><i class="bi bi-check"></i></button>
                </form>
                <?php endif; ?>
                <button class="btn btn-sm btn-danger" onclick="rejectModal(<?php echo $p['id']; ?>)"><i class="bi bi-x"></i></button>
                <?php endif; ?>
                
                <?php if ($p['status'] == 'approved'): ?>
                <form method="post" style="display:inline;" onsubmit="return confirm('Cairkan dana pinjaman ini?')">
                  <?php echo csrf_input(); ?>
                  <input type="hidden" name="pengajuan_id" value="<?php echo $p['id']; ?>">
                  <button type="submit" name="disburse" class="btn btn-sm btn-primary"><i class="bi bi-cash"></i> Cairkan</button>
                </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="8" class="text-center text-muted py-4">Tidak ada data pengajuan</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Detail Modal -->
<div class="modal fade" id="detailModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Detail Pengajuan</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body" id="detailContent"></div>
    </div>
  </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        <?php echo csrf_input(); ?>
        <input type="hidden" name="pengajuan_id" id="reject_id">
        <div class="modal-header bg-danger text-white"><h5 class="modal-title">Tolak Pengajuan</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <label class="form-label">Alasan Penolakan *</label>
          <textarea name="rejection_reason" class="form-control" rows="3" required placeholder="Jelaskan alasan penolakan..."></textarea>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" name="reject" class="btn btn-danger">Tolak Pengajuan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function viewDetail(data) {
  var html = '<div class="row">';
  html += '<div class="col-md-6">';
  html += '<h6>Data Pengajuan</h6>';
  html += '<table class="table table-sm"><tr><td>No</td><td>: '+data.no_pengajuan+'</td></tr>';
  html += '<tr><td>Jumlah</td><td>: <strong>Rp '+parseInt(data.jumlah_pinjaman).toLocaleString('id')+'</strong></td></tr>';
  html += '<tr><td>Tenor</td><td>: '+data.tenor_diinginkan+' hari</td></tr>';
  html += '<tr><td>Tipe</td><td>: '+data.tipe_angsuran+'</td></tr>';
  html += '<tr><td>Tujuan</td><td>: '+data.tujuan_pinjaman+'</td></tr>';
  html += '<tr><td>Pekerjaan</td><td>: '+(data.pekerjaan||'-')+'</td></tr>';
  html += '<tr><td>Penghasilan</td><td>: Rp '+(data.penghasilan_bulanan ? parseInt(data.penghasilan_bulanan).toLocaleString('id') : '-')+'</td></tr>';
  html += '</table></div>';
  
  html += '<div class="col-md-6">';
  html += '<h6>Data Anggota</h6>';
  html += '<table class="table table-sm"><tr><td>Nama</td><td>: '+data.nama+'</td></tr>';
  html += '<tr><td>No. Anggota</td><td>: '+data.no_anggota+'</td></tr>';
  html += '<tr><td>HP</td><td>: '+data.hp+'</td></tr>';
  html += '<tr><td>Alamat</td><td>: '+(data.alamat||'-')+'</td></tr>';
  html += '</table></div></div>';
  
  html += '<div class="row mt-3">';
  if (data.foto_ktp) {
    html += '<div class="col-md-6"><h6>Foto KTP</h6><img src="uploads/pengajuan/'+data.foto_ktp+'" class="img-fluid rounded" style="max-height:200px;"></div>';
  }
  if (data.foto_selfie) {
    html += '<div class="col-md-6"><h6>Selfie + KTP</h6><img src="uploads/pengajuan/'+data.foto_selfie+'" class="img-fluid rounded" style="max-height:200px;"></div>';
  }
  html += '</div>';
  
  document.getElementById('detailContent').innerHTML = html;
  new bootstrap.Modal(document.getElementById('detailModal')).show();
}

function rejectModal(id) {
  document.getElementById('reject_id').value = id;
  new bootstrap.Modal(document.getElementById('rejectModal')).show();
}
</script>

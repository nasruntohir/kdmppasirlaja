<?php
// views/credit_score.php - Credit Scoring Anggota
require_login();

$conn = db();

// Get member with credit score calculation
$sql = "SELECT 
  a.id, a.no_anggota, a.nama, a.hp, a.status, a.created_at,
  (SELECT COUNT(*) FROM ksp_pinjaman WHERE anggota_id=a.id) as total_pinjaman,
  (SELECT COUNT(*) FROM ksp_pinjaman WHERE anggota_id=a.id AND status='lunas') as pinjaman_lunas,
  (SELECT COUNT(*) FROM ksp_pinjaman WHERE anggota_id=a.id AND status='aktif') as pinjaman_aktif,
  (SELECT IFNULL(SUM(pokok),0) FROM ksp_pinjaman WHERE anggota_id=a.id) as total_pokok,
  (SELECT COUNT(*) FROM ksp_jadwal_angsuran j 
   JOIN ksp_pinjaman p ON p.id=j.pinjaman_id 
   WHERE p.anggota_id=a.id AND j.status='lunas') as angsuran_lunas,
  (SELECT COUNT(*) FROM ksp_jadwal_angsuran j 
   JOIN ksp_pinjaman p ON p.id=j.pinjaman_id 
   WHERE p.anggota_id=a.id) as total_angsuran,
  (SELECT COUNT(*) FROM ksp_jadwal_angsuran j 
   JOIN ksp_pinjaman p ON p.id=j.pinjaman_id 
   WHERE p.anggota_id=a.id AND j.status='lunas' AND j.tanggal_bayar <= j.tanggal_jatuh_tempo) as bayar_tepat_waktu,
  (SELECT COUNT(*) FROM ksp_jadwal_angsuran j 
   JOIN ksp_pinjaman p ON p.id=j.pinjaman_id 
   WHERE p.anggota_id=a.id AND j.status='belum' AND j.tanggal_jatuh_tempo < CURDATE()) as tunggakan
FROM ksp_anggota a
WHERE a.status = 'aktif'
ORDER BY a.nama";

$members = $conn->query($sql);

function calculate_credit_score($row) {
  $score = 500; // Base score
  
  // Factor 1: Payment history (40% weight)
  if ($row['total_angsuran'] > 0) {
    $payment_rate = $row['angsuran_lunas'] / $row['total_angsuran'];
    $score += $payment_rate * 200; // Max +200
  }
  
  // Factor 2: On-time payment (25% weight)
  if ($row['angsuran_lunas'] > 0) {
    $ontime_rate = $row['bayar_tepat_waktu'] / $row['angsuran_lunas'];
    $score += $ontime_rate * 125; // Max +125
  }
  
  // Factor 3: Loan completion (20% weight)
  if ($row['total_pinjaman'] > 0) {
    $completion_rate = $row['pinjaman_lunas'] / $row['total_pinjaman'];
    $score += $completion_rate * 100; // Max +100
  }
  
  // Factor 4: Current arrears penalty (15% weight)
  if ($row['tunggakan'] > 0) {
    $penalty = min($row['tunggakan'] * 10, 75); // Max -75
    $score -= $penalty;
  }
  
  // Bonus for loyalty (membership duration)
  $member_days = (strtotime('now') - strtotime($row['created_at'])) / 86400;
  if ($member_days > 365) $score += 25; // 1+ year bonus
  if ($member_days > 730) $score += 25; // 2+ year bonus
  
  return max(300, min(850, round($score))); // Clamp between 300-850
}

function get_score_grade($score) {
  if ($score >= 750) return ['grade' => 'A+', 'label' => 'Excellent', 'color' => 'success', 'desc' => 'Sangat layak kredit maksimal'];
  if ($score >= 700) return ['grade' => 'A', 'label' => 'Very Good', 'color' => 'success', 'desc' => 'Layak kredit tinggi'];
  if ($score >= 650) return ['grade' => 'B+', 'label' => 'Good', 'color' => 'info', 'desc' => 'Layak kredit normal'];
  if ($score >= 600) return ['grade' => 'B', 'label' => 'Fair', 'color' => 'primary', 'desc' => 'Layak kredit dengan syarat'];
  if ($score >= 550) return ['grade' => 'C', 'label' => 'Average', 'color' => 'warning', 'desc' => 'Perlu evaluasi lebih lanjut'];
  if ($score >= 500) return ['grade' => 'D', 'label' => 'Poor', 'color' => 'warning', 'desc' => 'Risiko tinggi'];
  return ['grade' => 'E', 'label' => 'Very Poor', 'color' => 'danger', 'desc' => 'Tidak direkomendasikan'];
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4><i class="bi bi-speedometer me-2"></i>Credit Scoring Anggota</h4>
  <a href="export/export_credit_score.php" class="btn btn-success btn-sm">
    <i class="bi bi-file-earmark-excel me-1"></i> Export Excel
  </a>
</div>

<!-- Legend -->
<div class="card card-widget mb-3">
  <div class="card-body py-2">
    <div class="d-flex flex-wrap gap-3 align-items-center small">
      <strong>Grade:</strong>
      <span class="badge bg-success">A+ (750-850)</span>
      <span class="badge bg-success">A (700-749)</span>
      <span class="badge bg-info">B+ (650-699)</span>
      <span class="badge bg-primary">B (600-649)</span>
      <span class="badge bg-warning text-dark">C (550-599)</span>
      <span class="badge bg-warning text-dark">D (500-549)</span>
      <span class="badge bg-danger">E (<500)</span>
    </div>
  </div>
</div>

<div class="card card-widget">
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-striped table-hover datatable">
        <thead>
          <tr>
            <th>No. Anggota</th>
            <th>Nama</th>
            <th>HP</th>
            <th>Score</th>
            <th>Grade</th>
            <th>Pinjaman</th>
            <th>Lunas</th>
            <th>Tepat Waktu</th>
            <th>Tunggakan</th>
            <th>Rekomendasi</th>
          </tr>
        </thead>
        <tbody>
        <?php while($m = $members->fetch_assoc()): 
          $score = calculate_credit_score($m);
          $grade = get_score_grade($score);
          $ontime_pct = $m['angsuran_lunas'] > 0 ? round(($m['bayar_tepat_waktu'] / $m['angsuran_lunas']) * 100) : 0;
        ?>
        <tr>
          <td><?php echo htmlspecialchars($m['no_anggota']); ?></td>
          <td><strong><?php echo htmlspecialchars($m['nama']); ?></strong></td>
          <td><?php echo htmlspecialchars($m['hp']); ?></td>
          <td>
            <div class="d-flex align-items-center gap-2">
              <div class="progress" style="width:60px;height:8px;">
                <div class="progress-bar bg-<?php echo $grade['color']; ?>" style="width:<?php echo ($score/850)*100; ?>%"></div>
              </div>
              <strong><?php echo $score; ?></strong>
            </div>
          </td>
          <td><span class="badge bg-<?php echo $grade['color']; ?> fs-6"><?php echo $grade['grade']; ?></span></td>
          <td><?php echo $m['total_pinjaman']; ?></td>
          <td><?php echo $m['pinjaman_lunas']; ?></td>
          <td><?php echo $ontime_pct; ?>%</td>
          <td>
            <?php if ($m['tunggakan'] > 0): ?>
            <span class="badge bg-danger"><?php echo $m['tunggakan']; ?> lembar</span>
            <?php else: ?>
            <span class="text-success">-</span>
            <?php endif; ?>
          </td>
          <td><small class="text-muted"><?php echo $grade['desc']; ?></small></td>
        </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Scoring Methodology -->
<div class="card card-widget mt-3">
  <div class="card-header">
    <h6 class="mb-0"><i class="bi bi-info-circle me-2"></i>Metodologi Scoring</h6>
  </div>
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-3">
        <div class="border rounded p-3 text-center">
          <div class="text-primary fw-bold fs-4">40%</div>
          <div class="small">Riwayat Pembayaran</div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="border rounded p-3 text-center">
          <div class="text-success fw-bold fs-4">25%</div>
          <div class="small">Ketepatan Waktu</div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="border rounded p-3 text-center">
          <div class="text-info fw-bold fs-4">20%</div>
          <div class="small">Penyelesaian Pinjaman</div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="border rounded p-3 text-center">
          <div class="text-warning fw-bold fs-4">15%</div>
          <div class="small">Bonus & Penalti</div>
        </div>
      </div>
    </div>
  </div>
</div>

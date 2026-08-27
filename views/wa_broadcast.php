<?php
// views/wa_broadcast.php - WhatsApp Broadcast & Reminder
require_login();

$conn = db();

// Get overdue members for reminder
$overdue = $conn->query("
  SELECT a.nama, a.hp, p.no_pinjaman, j.tanggal_jatuh_tempo, j.total_tagih,
         DATEDIFF(CURDATE(), j.tanggal_jatuh_tempo) as hari_telat,
         j.id as jadwal_id
  FROM ksp_jadwal_angsuran j
  JOIN ksp_pinjaman p ON p.id = j.pinjaman_id
  JOIN ksp_anggota a ON a.id = p.anggota_id
  WHERE j.status = 'belum' AND j.tanggal_jatuh_tempo < CURDATE()
  ORDER BY hari_telat DESC
  LIMIT 50
");

// Get due today
$due_today = $conn->query("
  SELECT a.nama, a.hp, p.no_pinjaman, j.tanggal_jatuh_tempo, j.total_tagih, j.id as jadwal_id
  FROM ksp_jadwal_angsuran j
  JOIN ksp_pinjaman p ON p.id = j.pinjaman_id
  JOIN ksp_anggota a ON a.id = p.anggota_id
  WHERE j.status = 'belum' AND j.tanggal_jatuh_tempo = CURDATE()
  ORDER BY a.nama
");

// Get due tomorrow (H-1 reminder)
$due_tomorrow = $conn->query("
  SELECT a.nama, a.hp, p.no_pinjaman, j.tanggal_jatuh_tempo, j.total_tagih, j.id as jadwal_id
  FROM ksp_jadwal_angsuran j
  JOIN ksp_pinjaman p ON p.id = j.pinjaman_id
  JOIN ksp_anggota a ON a.id = p.anggota_id
  WHERE j.status = 'belum' AND j.tanggal_jatuh_tempo = DATE_ADD(CURDATE(), INTERVAL 1 DAY)
  ORDER BY a.nama
");

$company = setting_get('company_name', 'Koperasi');

function format_wa_link($hp, $message) {
  $wa = preg_replace('/\D+/', '', $hp);
  if ($wa !== '' && strpos($wa, '0') === 0) $wa = '62' . substr($wa, 1);
  return 'https://web.whatsapp.com/send?phone=' . rawurlencode($wa) . '&text=' . rawurlencode($message);
}
?>

<h4><i class="bi bi-whatsapp me-2"></i>WhatsApp Broadcast & Reminder</h4>

<!-- Summary Cards -->
<div class="row g-3 mb-3">
  <div class="col-md-4">
    <div class="card card-widget border-warning">
      <div class="card-body text-center">
        <i class="bi bi-clock-history fs-1 text-warning"></i>
        <h3 class="mt-2"><?php echo $overdue->num_rows; ?></h3>
        <div class="text-muted">Tunggakan</div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card card-widget border-primary">
      <div class="card-body text-center">
        <i class="bi bi-calendar-check fs-1 text-primary"></i>
        <h3 class="mt-2"><?php echo $due_today->num_rows; ?></h3>
        <div class="text-muted">Jatuh Tempo Hari Ini</div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card card-widget border-info">
      <div class="card-body text-center">
        <i class="bi bi-bell fs-1 text-info"></i>
        <h3 class="mt-2"><?php echo $due_tomorrow->num_rows; ?></h3>
        <div class="text-muted">Jatuh Tempo Besok (H-1)</div>
      </div>
    </div>
  </div>
</div>

<!-- Template Messages -->
<div class="card card-widget mb-3">
  <div class="card-header">
    <h6 class="mb-0"><i class="bi bi-chat-left-text me-2"></i>Template Pesan</h6>
  </div>
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-4">
        <label class="form-label fw-bold text-warning">Pesan Tunggakan</label>
        <textarea class="form-control" id="msgOverdue" rows="4">Halo {nama}, ini dari <?php echo $company; ?>. Tagihan Anda no. {no_pinjaman} sebesar Rp {nominal} sudah telat {hari} hari sejak {jatuh_tempo}. Mohon segera dilunasi. Terima kasih.</textarea>
      </div>
      <div class="col-md-4">
        <label class="form-label fw-bold text-primary">Pesan Jatuh Tempo Hari Ini</label>
        <textarea class="form-control" id="msgToday" rows="4">Halo {nama}, ini dari <?php echo $company; ?>. Tagihan harian Anda no. {no_pinjaman} sebesar Rp {nominal} jatuh tempo HARI INI ({jatuh_tempo}). Mohon segera bayar. Terima kasih.</textarea>
      </div>
      <div class="col-md-4">
        <label class="form-label fw-bold text-info">Pesan Reminder H-1</label>
        <textarea class="form-control" id="msgReminder" rows="4">Halo {nama}, ini dari <?php echo $company; ?>. Ini adalah pengingat bahwa tagihan Anda no. {no_pinjaman} sebesar Rp {nominal} akan jatuh tempo BESOK ({jatuh_tempo}). Siapkan pembayaran ya. Terima kasih.</textarea>
      </div>
    </div>
  </div>
</div>

<!-- Tabs -->
<ul class="nav nav-tabs" role="tablist">
  <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tabOverdue">Tunggakan (<?php echo $overdue->num_rows; ?>)</a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabToday">Hari Ini (<?php echo $due_today->num_rows; ?>)</a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabTomorrow">Besok / H-1 (<?php echo $due_tomorrow->num_rows; ?>)</a></li>
</ul>

<div class="tab-content">
  <!-- Overdue Tab -->
  <div class="tab-pane fade show active" id="tabOverdue">
    <div class="card card-widget border-top-0 rounded-top-0">
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-sm table-striped">
            <thead><tr><th>Nama</th><th>HP</th><th>No. Pinjaman</th><th>Jatuh Tempo</th><th>Telat</th><th>Nominal</th><th>Kirim WA</th></tr></thead>
            <tbody>
            <?php 
            $overdue->data_seek(0);
            while($r = $overdue->fetch_assoc()): 
              $msg = str_replace(
                ['{nama}', '{no_pinjaman}', '{nominal}', '{jatuh_tempo}', '{hari}'],
                [$r['nama'], $r['no_pinjaman'], number_format($r['total_tagih'],0,',','.'), $r['tanggal_jatuh_tempo'], $r['hari_telat']],
                "Halo {nama}, ini dari $company. Tagihan Anda no. {no_pinjaman} sebesar Rp {nominal} sudah telat {hari} hari sejak {jatuh_tempo}. Mohon segera dilunasi. Terima kasih."
              );
            ?>
            <tr>
              <td><?php echo htmlspecialchars($r['nama']); ?></td>
              <td><?php echo htmlspecialchars($r['hp']); ?></td>
              <td><?php echo htmlspecialchars($r['no_pinjaman']); ?></td>
              <td><?php echo $r['tanggal_jatuh_tempo']; ?></td>
              <td><span class="badge bg-danger"><?php echo $r['hari_telat']; ?> hari</span></td>
              <td>Rp <?php echo number_format($r['total_tagih'],0,',','.'); ?></td>
              <td><a href="<?php echo format_wa_link($r['hp'], $msg); ?>" target="_blank" class="btn btn-sm btn-success"><i class="bi bi-whatsapp"></i></a></td>
            </tr>
            <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Today Tab -->
  <div class="tab-pane fade" id="tabToday">
    <div class="card card-widget border-top-0 rounded-top-0">
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-sm table-striped">
            <thead><tr><th>Nama</th><th>HP</th><th>No. Pinjaman</th><th>Jatuh Tempo</th><th>Nominal</th><th>Kirim WA</th></tr></thead>
            <tbody>
            <?php 
            $due_today->data_seek(0);
            while($r = $due_today->fetch_assoc()): 
              $msg = str_replace(
                ['{nama}', '{no_pinjaman}', '{nominal}', '{jatuh_tempo}'],
                [$r['nama'], $r['no_pinjaman'], number_format($r['total_tagih'],0,',','.'), $r['tanggal_jatuh_tempo']],
                "Halo {nama}, ini dari $company. Tagihan harian Anda no. {no_pinjaman} sebesar Rp {nominal} jatuh tempo HARI INI ({jatuh_tempo}). Mohon segera bayar. Terima kasih."
              );
            ?>
            <tr>
              <td><?php echo htmlspecialchars($r['nama']); ?></td>
              <td><?php echo htmlspecialchars($r['hp']); ?></td>
              <td><?php echo htmlspecialchars($r['no_pinjaman']); ?></td>
              <td><?php echo $r['tanggal_jatuh_tempo']; ?></td>
              <td>Rp <?php echo number_format($r['total_tagih'],0,',','.'); ?></td>
              <td><a href="<?php echo format_wa_link($r['hp'], $msg); ?>" target="_blank" class="btn btn-sm btn-success"><i class="bi bi-whatsapp"></i></a></td>
            </tr>
            <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Tomorrow Tab -->
  <div class="tab-pane fade" id="tabTomorrow">
    <div class="card card-widget border-top-0 rounded-top-0">
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-sm table-striped">
            <thead><tr><th>Nama</th><th>HP</th><th>No. Pinjaman</th><th>Jatuh Tempo</th><th>Nominal</th><th>Kirim WA</th></tr></thead>
            <tbody>
            <?php 
            $due_tomorrow->data_seek(0);
            while($r = $due_tomorrow->fetch_assoc()): 
              $msg = str_replace(
                ['{nama}', '{no_pinjaman}', '{nominal}', '{jatuh_tempo}'],
                [$r['nama'], $r['no_pinjaman'], number_format($r['total_tagih'],0,',','.'), $r['tanggal_jatuh_tempo']],
                "Halo {nama}, ini dari $company. Ini adalah pengingat bahwa tagihan Anda no. {no_pinjaman} sebesar Rp {nominal} akan jatuh tempo BESOK ({jatuh_tempo}). Siapkan pembayaran ya. Terima kasih."
              );
            ?>
            <tr>
              <td><?php echo htmlspecialchars($r['nama']); ?></td>
              <td><?php echo htmlspecialchars($r['hp']); ?></td>
              <td><?php echo htmlspecialchars($r['no_pinjaman']); ?></td>
              <td><?php echo $r['tanggal_jatuh_tempo']; ?></td>
              <td>Rp <?php echo number_format($r['total_tagih'],0,',','.'); ?></td>
              <td><a href="<?php echo format_wa_link($r['hp'], $msg); ?>" target="_blank" class="btn btn-sm btn-success"><i class="bi bi-whatsapp"></i></a></td>
            </tr>
            <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Tips -->
<div class="alert alert-info mt-3">
  <h6><i class="bi bi-lightbulb me-2"></i>Tips:</h6>
  <ul class="mb-0 small">
    <li>Klik tombol <i class="bi bi-whatsapp"></i> untuk membuka WhatsApp Web dan mengirim pesan ke nasabah</li>
    <li>Pastikan nomor HP nasabah sudah benar dan terdaftar di WhatsApp</li>
    <li>Kirim reminder H-1 untuk meningkatkan tingkat pembayaran tepat waktu</li>
    <li>Untuk integrasi WhatsApp API otomatis, hubungi developer</li>
  </ul>
</div>

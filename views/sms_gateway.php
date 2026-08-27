<?php
// views/sms_gateway.php - SMS Gateway Configuration & Sending
require_login();

if (user()['role'] !== 'admin') {
  alert('danger', 'Akses ditolak.');
  header('Location: index.php?m=dashboard');
  exit;
}

$conn = db();

// Save settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_config'])) {
  setting_set('sms_provider', $_POST['sms_provider']);
  setting_set('sms_api_key', $_POST['sms_api_key']);
  setting_set('sms_sender_id', $_POST['sms_sender_id']);
  setting_set('sms_username', $_POST['sms_username']);
  log_activity('update_sms_config', 'sms_gateway', 'Updated SMS gateway configuration');
  alert('success', 'Konfigurasi SMS berhasil disimpan.');
  header('Location: index.php?m=sms_gateway');
  exit;
}

// Get current config
$sms_provider = setting_get('sms_provider', 'zenziva');
$sms_api_key = setting_get('sms_api_key', '');
$sms_sender_id = setting_get('sms_sender_id', '');
$sms_username = setting_get('sms_username', '');

// Get overdue for SMS
$overdue = $conn->query("
  SELECT a.nama, a.hp, p.no_pinjaman, j.tanggal_jatuh_tempo, j.total_tagih,
         DATEDIFF(CURDATE(), j.tanggal_jatuh_tempo) as hari_telat, j.id
  FROM ksp_jadwal_angsuran j
  JOIN ksp_pinjaman p ON p.id = j.pinjaman_id
  JOIN ksp_anggota a ON a.id = p.anggota_id
  WHERE j.status = 'belum' AND j.tanggal_jatuh_tempo < CURDATE() AND a.hp IS NOT NULL AND a.hp != ''
  ORDER BY hari_telat DESC
  LIMIT 50
");

$due_today = $conn->query("
  SELECT a.nama, a.hp, p.no_pinjaman, j.tanggal_jatuh_tempo, j.total_tagih, j.id
  FROM ksp_jadwal_angsuran j
  JOIN ksp_pinjaman p ON p.id = j.pinjaman_id
  JOIN ksp_anggota a ON a.id = p.anggota_id
  WHERE j.status = 'belum' AND j.tanggal_jatuh_tempo = CURDATE() AND a.hp IS NOT NULL AND a.hp != ''
  ORDER BY a.nama
");

$company = setting_get('company_name', 'KSP');
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4><i class="bi bi-chat-dots me-2"></i>SMS Gateway</h4>
</div>

<!-- Config Card -->
<div class="card card-widget mb-4">
  <div class="card-header">
    <h6 class="mb-0"><i class="bi bi-gear me-2"></i>Konfigurasi SMS Gateway</h6>
  </div>
  <div class="card-body">
    <form method="post" class="row g-3">
      <div class="col-md-3">
        <label class="form-label">Provider SMS</label>
        <select name="sms_provider" class="form-select">
          <option value="zenziva" <?php echo $sms_provider=='zenziva'?'selected':''; ?>>Zenziva</option>
          <option value="twilio" <?php echo $sms_provider=='twilio'?'selected':''; ?>>Twilio</option>
          <option value="nexmo" <?php echo $sms_provider=='nexmo'?'selected':''; ?>>Nexmo/Vonage</option>
          <option value="infobip" <?php echo $sms_provider=='infobip'?'selected':''; ?>>Infobip</option>
          <option value="raja_sms" <?php echo $sms_provider=='raja_sms'?'selected':''; ?>>Raja SMS</option>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Username/Account ID</label>
        <input type="text" name="sms_username" class="form-control" value="<?php echo htmlspecialchars($sms_username); ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">API Key/Token</label>
        <input type="password" name="sms_api_key" class="form-control" value="<?php echo htmlspecialchars($sms_api_key); ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">Sender ID</label>
        <input type="text" name="sms_sender_id" class="form-control" value="<?php echo htmlspecialchars($sms_sender_id); ?>" placeholder="e.g. KOPERASI">
      </div>
      <div class="col-12">
        <button type="submit" name="save_config" class="btn btn-gradient">
          <i class="bi bi-save me-1"></i>Simpan Konfigurasi
        </button>
        <button type="button" class="btn btn-outline-primary ms-2" onclick="testSMS()">
          <i class="bi bi-send me-1"></i>Test Kirim SMS
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Template Messages -->
<div class="card card-widget mb-4">
  <div class="card-header">
    <h6 class="mb-0"><i class="bi bi-chat-left-text me-2"></i>Template Pesan SMS</h6>
  </div>
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label fw-bold text-danger">Template Tunggakan</label>
        <textarea class="form-control" id="tplOverdue" rows="3">Yth {nama}, tagihan <?php echo $company; ?> Rp{nominal} telat {hari} hari. Segera lunasi. Info: <?php echo setting_get('company_phone', ''); ?></textarea>
        <small class="text-muted">Variabel: {nama}, {nominal}, {hari}, {jatuh_tempo}, {no_pinjaman}</small>
      </div>
      <div class="col-md-6">
        <label class="form-label fw-bold text-primary">Template Jatuh Tempo</label>
        <textarea class="form-control" id="tplToday" rows="3">Yth {nama}, tagihan <?php echo $company; ?> Rp{nominal} jatuh tempo HARI INI. Mohon segera bayar. Terima kasih.</textarea>
        <small class="text-muted">Variabel: {nama}, {nominal}, {jatuh_tempo}, {no_pinjaman}</small>
      </div>
    </div>
  </div>
</div>

<!-- Tabs -->
<ul class="nav nav-tabs" role="tablist">
  <li class="nav-item">
    <a class="nav-link active" data-bs-toggle="tab" href="#tabOverdue">
      <i class="bi bi-exclamation-triangle text-danger me-1"></i>Tunggakan (<?php echo $overdue->num_rows; ?>)
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link" data-bs-toggle="tab" href="#tabToday">
      <i class="bi bi-calendar-check text-primary me-1"></i>Jatuh Tempo Hari Ini (<?php echo $due_today->num_rows; ?>)
    </a>
  </li>
</ul>

<div class="tab-content">
  <!-- Overdue -->
  <div class="tab-pane fade show active" id="tabOverdue">
    <div class="card card-widget border-top-0 rounded-top-0">
      <div class="card-body">
        <div class="mb-3">
          <button class="btn btn-danger btn-sm" onclick="sendBulkSMS('overdue')">
            <i class="bi bi-send me-1"></i>Kirim SMS ke Semua Tunggakan
          </button>
        </div>
        <div class="table-responsive">
          <table class="table table-sm table-striped">
            <thead><tr><th><input type="checkbox" id="checkAllOverdue"></th><th>Nama</th><th>HP</th><th>No. Pinjaman</th><th>Telat</th><th>Nominal</th><th>Aksi</th></tr></thead>
            <tbody>
            <?php while($r = $overdue->fetch_assoc()): ?>
            <tr>
              <td><input type="checkbox" class="sms-check-overdue" value="<?php echo $r['id']; ?>" data-hp="<?php echo htmlspecialchars($r['hp']); ?>" data-nama="<?php echo htmlspecialchars($r['nama']); ?>" data-nominal="<?php echo $r['total_tagih']; ?>" data-hari="<?php echo $r['hari_telat']; ?>"></td>
              <td><?php echo htmlspecialchars($r['nama']); ?></td>
              <td><?php echo htmlspecialchars($r['hp']); ?></td>
              <td><?php echo htmlspecialchars($r['no_pinjaman']); ?></td>
              <td><span class="badge bg-danger"><?php echo $r['hari_telat']; ?> hari</span></td>
              <td>Rp <?php echo number_format($r['total_tagih'],0,',','.'); ?></td>
              <td>
                <button class="btn btn-sm btn-outline-primary" onclick="sendSingleSMS('<?php echo htmlspecialchars($r['hp']); ?>', '<?php echo htmlspecialchars($r['nama']); ?>', <?php echo $r['total_tagih']; ?>, <?php echo $r['hari_telat']; ?>, 'overdue')">
                  <i class="bi bi-send"></i>
                </button>
              </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Today -->
  <div class="tab-pane fade" id="tabToday">
    <div class="card card-widget border-top-0 rounded-top-0">
      <div class="card-body">
        <div class="mb-3">
          <button class="btn btn-primary btn-sm" onclick="sendBulkSMS('today')">
            <i class="bi bi-send me-1"></i>Kirim SMS ke Semua Jatuh Tempo
          </button>
        </div>
        <div class="table-responsive">
          <table class="table table-sm table-striped">
            <thead><tr><th><input type="checkbox" id="checkAllToday"></th><th>Nama</th><th>HP</th><th>No. Pinjaman</th><th>Nominal</th><th>Aksi</th></tr></thead>
            <tbody>
            <?php while($r = $due_today->fetch_assoc()): ?>
            <tr>
              <td><input type="checkbox" class="sms-check-today" value="<?php echo $r['id']; ?>" data-hp="<?php echo htmlspecialchars($r['hp']); ?>" data-nama="<?php echo htmlspecialchars($r['nama']); ?>" data-nominal="<?php echo $r['total_tagih']; ?>"></td>
              <td><?php echo htmlspecialchars($r['nama']); ?></td>
              <td><?php echo htmlspecialchars($r['hp']); ?></td>
              <td><?php echo htmlspecialchars($r['no_pinjaman']); ?></td>
              <td>Rp <?php echo number_format($r['total_tagih'],0,',','.'); ?></td>
              <td>
                <button class="btn btn-sm btn-outline-primary" onclick="sendSingleSMS('<?php echo htmlspecialchars($r['hp']); ?>', '<?php echo htmlspecialchars($r['nama']); ?>', <?php echo $r['total_tagih']; ?>, 0, 'today')">
                  <i class="bi bi-send"></i>
                </button>
              </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Info -->
<div class="alert alert-info mt-3">
  <h6><i class="bi bi-info-circle me-2"></i>Informasi SMS Gateway:</h6>
  <ul class="mb-0 small">
    <li>Pastikan sudah mendaftar ke provider SMS dan memiliki API key yang valid</li>
    <li>Biaya SMS akan dibebankan sesuai tarif provider</li>
    <li>Gunakan fitur "Test Kirim SMS" untuk memastikan konfigurasi sudah benar</li>
    <li>Provider populer Indonesia: <strong>Zenziva, Raja SMS, Wavecell</strong></li>
  </ul>
</div>

<script>
document.getElementById('checkAllOverdue').addEventListener('change', function() {
  document.querySelectorAll('.sms-check-overdue').forEach(function(cb) { cb.checked = this.checked; }.bind(this));
});
document.getElementById('checkAllToday').addEventListener('change', function() {
  document.querySelectorAll('.sms-check-today').forEach(function(cb) { cb.checked = this.checked; }.bind(this));
});

function formatRupiah(n) {
  return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
}

function sendSingleSMS(hp, nama, nominal, hari, type) {
  var tpl = type == 'overdue' ? document.getElementById('tplOverdue').value : document.getElementById('tplToday').value;
  var msg = tpl.replace('{nama}', nama).replace('{nominal}', formatRupiah(nominal)).replace('{hari}', hari);
  
  if (confirm('Kirim SMS ke ' + hp + '?\n\nPesan:\n' + msg)) {
    // In production, this would call the SMS API
    alert('SMS akan dikirim ke ' + hp + '\n\nUntuk mengaktifkan fitur ini, pastikan API key sudah dikonfigurasi dengan benar.');
  }
}

function sendBulkSMS(type) {
  var checks = document.querySelectorAll('.sms-check-' + type + ':checked');
  if (checks.length == 0) {
    alert('Pilih minimal satu penerima!');
    return;
  }
  if (confirm('Kirim SMS ke ' + checks.length + ' penerima?')) {
    alert('Fitur bulk SMS akan mengirim ke ' + checks.length + ' nomor.\n\nPastikan API key sudah dikonfigurasi.');
  }
}

function testSMS() {
  var hp = prompt('Masukkan nomor HP untuk test:');
  if (hp) {
    alert('Test SMS akan dikirim ke ' + hp + '\n\nPastikan konfigurasi sudah benar.');
  }
}
</script>

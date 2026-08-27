<?php
// member/pengajuan.php - Form Pengajuan Pinjaman Online (Mobile App Style)
require_once __DIR__ . '/../lib/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['member_id'])) {
  header('Location: index.php');
  exit;
}

$conn = db();
$member_id = $_SESSION['member_id'];
$member = $conn->query("SELECT * FROM ksp_anggota WHERE id = $member_id")->fetch_assoc();

// Check blacklist
$is_blacklisted = $conn->query("SELECT id FROM ksp_blacklist WHERE anggota_id = $member_id AND status = 'aktif'")->num_rows;

// Check pending applications
$pending = $conn->query("SELECT id FROM ksp_pengajuan_online WHERE anggota_id = $member_id AND status IN ('pending','review')")->num_rows;

// Check active loans
$pinjaman_aktif = $conn->query("SELECT id FROM ksp_pinjaman WHERE anggota_id = $member_id AND status = 'aktif'")->num_rows;

// Handle submit
$success = false;
$error = '';
$no_pengajuan = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_pengajuan'])) {
  if ($is_blacklisted) {
    $error = 'Maaf, Anda tidak dapat mengajukan pinjaman karena terdaftar dalam blacklist.';
  } elseif ($pending > 0) {
    $error = 'Anda masih memiliki pengajuan yang sedang diproses.';
  } elseif ($pinjaman_aktif > 0) {
    $error = 'Anda masih memiliki pinjaman yang belum lunas. Silakan lunasi pinjaman sebelumnya terlebih dahulu sebelum mengajukan pinjaman baru.';
  } else {
    $jumlah = (float)preg_replace('/\D/', '', (string)($_POST['jumlah_pinjaman'] ?? 0));
    $batas_kali_simpanan = 3;
    $simpanan = $conn->query("
      SELECT
        COALESCE(simpanan_pokok, 0) AS simpanan_pokok,
        COALESCE(simpanan_wajib, 0) AS simpanan_wajib,
        COALESCE(simpanan_sukarela, 0) AS simpanan_sukarela
      FROM ksp_simpanan_anggota
      WHERE anggota_id = $member_id
    ")->fetch_assoc();
    $total_simpanan = $simpanan
      ? ((float)$simpanan['simpanan_pokok'] + (float)$simpanan['simpanan_wajib'] + (float)$simpanan['simpanan_sukarela'])
      : 0;
    $limit_pinjaman = $total_simpanan * $batas_kali_simpanan;
    $tujuan = trim($_POST['tujuan_pinjaman']);
    if ($tujuan === 'Lainnya') {
      $tujuan = trim($_POST['tujuan_pinjaman_lainnya'] ?? '');
    }
    $tenor = (int)$_POST['tenor'];
    $tipe = $_POST['tipe_angsuran'];
    $penghasilan = (float)preg_replace('/\D/', '', (string)($_POST['penghasilan_bulanan'] ?? 0));
    $pekerjaan = trim($_POST['pekerjaan']);
    if ($pekerjaan === 'Lainnya') {
      $pekerjaan = trim($_POST['pekerjaan_lainnya'] ?? '');
    }
    
    if ($jumlah < 500000) {
      $error = 'Jumlah pinjaman minimal Rp 500.000';
    } elseif($total_simpanan <= 0){
        $error = 'Anda belum memiliki simpanan pokok, wajib, atau sukarela.';
    } elseif ($jumlah > $limit_pinjaman){
      $error = 'Maksimal pinjaman Anda Rp '.number_format($limit_pinjaman, 0, ',', '.').' berdasarkan total simpanan Rp '.number_format($total_simpanan, 0, ',', '.').'.';
    }elseif ($jumlah > 50000000) {
      $error = 'Jumlah pinjaman maksimal Rp 50.000.000';
    } elseif (empty($tujuan)) {
      $error = 'Tujuan pinjaman harus diisi';
    } else {
      $upload_dir = __DIR__ . '/../uploads/pengajuan/';
      if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
      
      $foto_ktp = '';
      $foto_selfie = '';
      
      if (isset($_FILES['foto_ktp']) && $_FILES['foto_ktp']['size'] > 0) {
        $ext = strtolower(pathinfo($_FILES['foto_ktp']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png'])) {
          $foto_ktp = 'ktp_' . $member_id . '_' . time() . '.' . $ext;
          move_uploaded_file($_FILES['foto_ktp']['tmp_name'], $upload_dir . $foto_ktp);
        }
      }
      
      if (isset($_FILES['foto_selfie']) && $_FILES['foto_selfie']['size'] > 0) {
        $ext = strtolower(pathinfo($_FILES['foto_selfie']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png'])) {
          $foto_selfie = 'selfie_' . $member_id . '_' . time() . '.' . $ext;
          move_uploaded_file($_FILES['foto_selfie']['tmp_name'], $upload_dir . $foto_selfie);
        }
      }
      
      $no_pengajuan = 'PO-' . date('Ymd') . '-' . str_pad(rand(1,9999), 4, '0', STR_PAD_LEFT);
      
      $stmt = $conn->prepare("INSERT INTO ksp_pengajuan_online (no_pengajuan, anggota_id, jumlah_pinjaman, tujuan_pinjaman, tenor_diinginkan, tipe_angsuran, penghasilan_bulanan, pekerjaan, foto_ktp, foto_selfie) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
      $stmt->bind_param("sidsissdss", $no_pengajuan, $member_id, $jumlah, $tujuan, $tenor, $tipe, $penghasilan, $pekerjaan, $foto_ktp, $foto_selfie);
      
      if ($stmt->execute()) {
        log_activity('pengajuan_online', 'pengajuan', 'Pengajuan pinjaman online: ' . $no_pengajuan . ' dari ' . $member['nama']);
        $success = true;
      } else {
        $error = 'Gagal menyimpan pengajuan.';
      }
    }
  }
}

$company = setting_get('company_name', 'KSP Harian');
$page = 'pengajuan';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <title>Ajukan Pinjaman - <?php echo htmlspecialchars($company); ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/mobile.css" rel="stylesheet">
</head>
<body>
  <!-- App Bar -->
  <div class="app-bar app-bar-simple">
    <div class="app-bar-row">
      <a href="dashboard.php" style="color:white;text-decoration:none;font-size:24px;"><i class="bi bi-arrow-left"></i></a>
      <h1 style="flex:1;text-align:center;margin:0;">Ajukan Pinjaman</h1>
      <div style="width:24px;"></div>
    </div>
  </div>

  <div style="padding:20px;padding-top:30px;">
    
    <?php if ($success): ?>
    <!-- Success -->
    <div class="card text-center" style="padding:40px 20px;">
      <i class="bi bi-check-circle text-success" style="font-size:80px;"></i>
      <h3 style="margin:20px 0 10px;">Pengajuan Berhasil!</h3>
      <p class="text-gray">Pengajuan Anda sedang diproses</p>
      <div class="card" style="background:#f3f4f6;margin:20px 0;">
        <p class="text-gray text-sm mb-2">No. Pengajuan</p>
        <p class="fw-bold text-lg"><?php echo $no_pengajuan; ?></p>
      </div>
      <a href="status_pengajuan.php" class="btn btn-primary btn-block"><i class="bi bi-clock-history"></i> Lihat Status</a>
      <a href="dashboard.php" class="btn btn-outline btn-block mt-2">Kembali ke Home</a>
    </div>
    
    <?php elseif ($is_blacklisted): ?>
    <!-- Blacklisted -->
    <div class="card text-center" style="padding:40px 20px;">
      <i class="bi bi-shield-x text-danger" style="font-size:80px;"></i>
      <h3 style="margin:20px 0 10px;color:#dc2626;">Akses Ditolak</h3>
      <p class="text-gray">Maaf, Anda tidak dapat mengajukan pinjaman saat ini.</p>
      <p class="text-sm">Silakan hubungi kantor untuk informasi lebih lanjut.</p>
      <a href="dashboard.php" class="btn btn-outline btn-block mt-3">Kembali</a>
    </div>
    
    <?php elseif ($pending > 0): ?>
    <!-- Has Pending -->
    <div class="card text-center" style="padding:40px 20px;">
      <i class="bi bi-hourglass-split text-warning" style="font-size:80px;"></i>
      <h3 style="margin:20px 0 10px;">Masih Diproses</h3>
      <p class="text-gray">Anda masih memiliki pengajuan yang sedang dalam review.</p>
      <a href="status_pengajuan.php" class="btn btn-primary btn-block mt-3"><i class="bi bi-clock-history"></i> Lihat Status</a>
    </div>

    <?php elseif ($pinjaman_aktif > 0): ?>
    <!-- Has Active Loan -->
    <div class="card text-center" style="padding:40px 20px;">
      <i class="bi bi-wallet2 text-warning" style="font-size:80px;"></i>
      <h3 style="margin:20px 0 10px;">Pinjaman Belum Lunas</h3>
      <p class="text-gray">Anda masih memiliki pinjaman aktif yang belum lunas.</p>
      <p class="text-sm">Silakan lunasi pinjaman sebelumnya terlebih dahulu sebelum mengajukan pinjaman baru.</p>
      <a href="dashboard.php" class="btn btn-outline btn-block mt-3">Kembali ke Home</a>
    </div>
    
    <?php else: ?>
    <!-- Form -->
    <?php if ($error): ?>
    <div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i><div><?php echo $error; ?></div></div>
    <?php endif; ?>
    
    <form method="post" enctype="multipart/form-data" onsubmit="syncAllRupiahInputs()">
      <!-- Step 1: Jumlah -->
      <div class="card mb-3">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:15px;">
          <div style="width:35px;height:35px;background:linear-gradient(135deg,#6366f1,#8b5cf6);border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;font-weight:bold;">1</div>
          <h3 style="margin:0;font-size:16px;">Jumlah Pinjaman</h3>
        </div>
        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label">Berapa yang Anda butuhkan?</label>
          <div style="position:relative;">
            <span style="position:absolute;left:15px;top:50%;transform:translateY(-50%);color:#6b7280;font-weight:600;">Rp</span>
            <input type="text" name="jumlah_pinjaman_display" class="form-input rupiah-input" data-target="jumlah_pinjaman" style="padding-left:45px;" inputmode="numeric" autocomplete="off" required placeholder="5.000.000">
            <input type="hidden" name="jumlah_pinjaman">
          </div>
          <!-- <p class="text-sm text-gray mt-2">Min: Rp 500.000 | Max: Rp 50.000.000</p> -->
        </div>
      </div>

      <!-- Step 2: Detail -->
      <div class="card mb-3">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:15px;">
          <div style="width:35px;height:35px;background:linear-gradient(135deg,#6366f1,#8b5cf6);border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;font-weight:bold;">2</div>
          <h3 style="margin:0;font-size:16px;">Detail Pinjaman</h3>
        </div>
        
        <div class="form-group">
          <label class="form-label">Tenor (Lama Pinjaman)</label>
          <select name="tenor" class="form-select" required>
            <option value="7">7 Hari</option>
            <option value="14">14 Hari</option>
            <option value="21">21 Hari</option>
            <option value="30" selected>30 Hari</option>
            <option value="60">60 Hari</option>
            <option value="90">90 Hari</option>
          </select>
        </div>
        
        <div class="form-group">
          <label class="form-label">Tipe Angsuran</label>
          <select name="tipe_angsuran" class="form-select" required>
            <option value="harian">Harian</option>
            <option value="mingguan">Mingguan</option>
            <option value="tempo">Tempo (Bayar Sekaligus)</option>
          </select>
        </div>
        
        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label">Tujuan Pinjaman</label>
          <select name="tujuan_pinjaman" id="tujuan_pinjaman" class="form-select" required onchange="toggleManualInput('tujuan_pinjaman', 'tujuan_lainnya_wrap', 'tujuan_pinjaman_lainnya')">
            <option value="">Pilih Tujuan</option>
            <option value="Modal Usaha">Modal Usaha</option>
            <option value="Kebutuhan Rumah Tangga">Kebutuhan Rumah Tangga</option>
            <option value="Pendidikan">Pendidikan</option>
            <option value="Kesehatan">Kesehatan</option>
            <option value="Renovasi Rumah">Renovasi Rumah</option>
            <option value="Lainnya">Lainnya</option>
          </select>
          <div id="tujuan_lainnya_wrap" style="display:none;margin-top:10px;">
            <input type="text" name="tujuan_pinjaman_lainnya" id="tujuan_pinjaman_lainnya" class="form-input" placeholder="Tulis tujuan pinjaman">
          </div>
        </div>
      </div>

      <!-- Step 3: Pekerjaan -->
      <div class="card mb-3">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:15px;">
          <div style="width:35px;height:35px;background:linear-gradient(135deg,#6366f1,#8b5cf6);border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;font-weight:bold;">3</div>
          <h3 style="margin:0;font-size:16px;">Data Pekerjaan</h3>
        </div>
        
        <div class="form-group">
          <label class="form-label">Pekerjaan</label>
          <select name="pekerjaan" id="pekerjaan" class="form-select" required onchange="toggleManualInput('pekerjaan', 'pekerjaan_lainnya_wrap', 'pekerjaan_lainnya')">
            <option value="">Pilih Pekerjaan</option>
            <option value="Pedagang">Pedagang</option>
            <option value="Karyawan">Karyawan</option>
            <option value="Wiraswasta">Wiraswasta</option>
            <option value="Petani">Petani</option>
            <option value="Buruh">Buruh</option>
            <option value="PNS">PNS</option>
            <option value="Ibu Rumah Tangga">Ibu Rumah Tangga</option>
            <option value="Lainnya">Lainnya</option>
          </select>
          <div id="pekerjaan_lainnya_wrap" style="display:none;margin-top:10px;">
            <input type="text" name="pekerjaan_lainnya" id="pekerjaan_lainnya" class="form-input" placeholder="Tulis pekerjaan">
          </div>
        </div>
        
        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label">Penghasilan Bulanan</label>
          <div style="position:relative;">
            <span style="position:absolute;left:15px;top:50%;transform:translateY(-50%);color:#6b7280;font-weight:600;">Rp</span>
            <input type="text" name="penghasilan_bulanan_display" class="form-input rupiah-input" data-target="penghasilan_bulanan" style="padding-left:45px;" inputmode="numeric" autocomplete="off" required placeholder="3.000.000">
            <input type="hidden" name="penghasilan_bulanan">
          </div>
        </div>
      </div>

      <!-- Step 4: Upload -->
      <div class="card mb-3">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:15px;">
          <div style="width:35px;height:35px;background:linear-gradient(135deg,#6366f1,#8b5cf6);border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;font-weight:bold;">4</div>
          <h3 style="margin:0;font-size:16px;">Upload Dokumen</h3>
        </div>
        
        <div class="form-group">
          <label class="form-label">Foto KTP</label>
          <div class="upload-box" onclick="document.getElementById('foto_ktp').click()">
            <i class="bi bi-card-image"></i>
            <p>Tap untuk upload foto KTP</p>
            <img id="preview_ktp" style="display:none;">
          </div>
          <input type="file" id="foto_ktp" name="foto_ktp" style="display:none;" accept="image/*" required onchange="previewImg(this,'preview_ktp')">
        </div>
        
        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label">Selfie dengan KTP</label>
          <div class="upload-box" onclick="document.getElementById('foto_selfie').click()">
            <i class="bi bi-person-bounding-box"></i>
            <p>Tap untuk upload selfie + KTP</p>
            <img id="preview_selfie" style="display:none;">
          </div>
          <input type="file" id="foto_selfie" name="foto_selfie" style="display:none;" accept="image/*" required onchange="previewImg(this,'preview_selfie')">
        </div>
      </div>

      <!-- Agreement -->
      <div class="card mb-3">
        <label style="display:flex;align-items:flex-start;gap:12px;cursor:pointer;">
          <input type="checkbox" id="agree" required style="width:22px;height:22px;margin-top:2px;">
          <span class="text-sm">Saya menyatakan data yang saya isi adalah benar dan bersedia menerima konsekuensi jika terdapat ketidaksesuaian.</span>
        </label>
      </div>

      <button type="submit" name="submit_pengajuan" class="btn btn-primary btn-block btn-lg">
        <i class="bi bi-send"></i> Kirim Pengajuan
      </button>
    </form>
    <?php endif; ?>
  </div>

  <!-- Bottom Navigation -->
  <div class="bottom-nav">
    <a href="dashboard.php" class="nav-item"><i class="bi bi-house"></i><span>Home</span></a>
    <a href="status_pengajuan.php" class="nav-item"><i class="bi bi-clock-history"></i><span>Status</span></a>
    <a href="pengajuan.php" class="nav-item center-btn active"><div class="icon-wrap"><i class="bi bi-plus-lg"></i></div><span>Ajukan</span></a>
    <a href="simpanan.php" class="nav-item"><i class="bi bi-wallet2"></i><span>Simpanan</span></a>
    <a href="profil.php" class="nav-item"><i class="bi bi-person"></i><span>Profil</span></a>
  </div>

  <script>
  function onlyDigits(value) {
    return String(value || '').replace(/\D/g, '');
  }

  function formatRupiahInput(value) {
    var angka = onlyDigits(value);
    return angka.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  }

  function syncRupiahInput(input) {
    var angka = onlyDigits(input.value);
    input.value = formatRupiahInput(angka);

    var targetName = input.getAttribute('data-target');
    if (targetName && input.form) {
      var hidden = input.form.querySelector('input[name="' + targetName + '"]');
      if (hidden) hidden.value = angka;
    }
  }

  function syncAllRupiahInputs() {
    document.querySelectorAll('.rupiah-input').forEach(function(input) {
      syncRupiahInput(input);
    });
  }

  function toggleManualInput(selectId, wrapId, inputId) {
    var select = document.getElementById(selectId);
    var wrap = document.getElementById(wrapId);
    var input = document.getElementById(inputId);
    var show = select && select.value === 'Lainnya';

    if (!wrap || !input) return;
    wrap.style.display = show ? 'block' : 'none';
    input.required = show;
    if (!show) input.value = '';
  }

  document.querySelectorAll('.rupiah-input').forEach(function(input) {
    syncRupiahInput(input);
    input.addEventListener('input', function() {
      syncRupiahInput(this);
    });
    input.addEventListener('focus', function() {
      this.select();
    });
  });

  function previewImg(input, id) {
    var preview = document.getElementById(id);
    if (input.files && input.files[0]) {
      var reader = new FileReader();
      reader.onload = function(e) {
        preview.src = e.target.result;
        preview.style.display = 'block';
      }
      reader.readAsDataURL(input.files[0]);
    }
  }
  </script>
</body>
</html>

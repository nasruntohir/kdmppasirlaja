<?php
// views/setting.php — Pengaturan Aplikasi + Pengguna (PHP 5.6 friendly)
require_login();
csrf_check();
$conn = db();

// e() now defined globally in helpers.php

// Baca config password mode (plain/hash) dari config/app.php
$__CFG = @include __DIR__ . '/../config/app.php';
$use_plain = false;
if (is_array($__CFG) && isset($__CFG['USE_PLAIN_PASSWORD'])) {
  $use_plain = (bool)$__CFG['USE_PLAIN_PASSWORD'];
} elseif (defined('USE_PLAIN_PASSWORD')) {
  $use_plain = (bool)USE_PLAIN_PASSWORD;
}

// Tab
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'app';

// ====== Actions ======
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $action = isset($_POST['action']) ? $_POST['action'] : '';

  // --- Simpan pengaturan aplikasi ---
  if ($action === 'save_app') {
    setting_set('app_name', $_POST['app_name']);
    setting_set('company_name', $_POST['company_name']);
    setting_set('company_address', $_POST['company_address']);
    setting_set('company_phone', $_POST['company_phone']);
    setting_set('prefix_no_anggota', $_POST['prefix_no_anggota']);
    setting_set('prefix_no_pinjaman', $_POST['prefix_no_pinjaman']);
    setting_set('prefix_no_kas', $_POST['prefix_no_kas']);
    setting_set('denda_fixed_per_hari', $_POST['denda_fixed_per_hari']);
    alert('success', 'Pengaturan aplikasi disimpan.');
    header("Location: index.php?m=setting&tab=app"); exit;
  }

  // --- Simpan pengaturan cetak ---
  if ($action === 'save_print') {
    setting_set('print_paper_size', $_POST['print_paper_size']);
    setting_set('print_logo', $_POST['print_logo']);
    setting_set('print_header_text', $_POST['print_header_text']);
    setting_set('print_footer_text', $_POST['print_footer_text']);
    setting_set('print_signature_left', $_POST['print_signature_left']);
    setting_set('print_signature_right', $_POST['print_signature_right']);
    setting_set('print_show_logo', isset($_POST['print_show_logo']) ? '1' : '0');
    setting_set('print_show_qr', isset($_POST['print_show_qr']) ? '1' : '0');
    alert('success', 'Pengaturan cetak disimpan.');
    header("Location: index.php?m=setting&tab=print"); exit;
  }

  // --- Bagian Pengguna: hanya admin bisa kelola user lain ---
  $is_admin = is_role('admin');

  // Tambah user
  if ($action === 'user_create' && $is_admin) {
    $nama = trim($_POST['nama']);
    $username = trim($_POST['username']);
    $role = in_array($_POST['role'], array('admin','teller')) ? $_POST['role'] : 'teller';
    $pass1 = isset($_POST['password']) ? $_POST['password'] : '';
    $pass2 = isset($_POST['password2']) ? $_POST['password2'] : '';

    if ($username==='' || $pass1==='' || $pass2==='') {
      alert('danger','Username/Password tidak boleh kosong.');
      header("Location: index.php?m=setting&tab=users"); exit;
    }
    if ($pass1 !== $pass2) {
      alert('danger','Konfirmasi password tidak sama.');
      header("Location: index.php?m=setting&tab=users"); exit;
    }
    // cek duplikat username
    $st = $conn->prepare("SELECT id FROM ksp_users WHERE username=? LIMIT 1");
    $st->bind_param("s", $username); $st->execute(); $r=$st->get_result();
    if ($r && $r->fetch_assoc()) { alert('danger','Username sudah dipakai.'); header("Location: index.php?m=setting&tab=users"); exit; }

    $to_store = $use_plain ? $pass1 : (function_exists('password_hash') ? password_hash($pass1, PASSWORD_BCRYPT) : $pass1);
    $st = $conn->prepare("INSERT INTO ksp_users(nama,username,password,role,aktif) VALUES(?,?,?,?,1)");
    $st->bind_param("ssss", $nama, $username, $to_store, $role);
    if ($st->execute()) alert('success','User ditambahkan.');
    else alert('danger','Gagal menambah user: '.$conn->error);
    header("Location: index.php?m=setting&tab=users"); exit;
  }

  // Update nama/role
  if ($action === 'user_update' && $is_admin) {
    $id = (int)$_POST['id'];
    $nama = trim($_POST['nama']);
    $role = in_array($_POST['role'], array('admin','teller')) ? $_POST['role'] : 'teller';
    $st = $conn->prepare("UPDATE ksp_users SET nama=?, role=? WHERE id=?");
    $st->bind_param("ssi", $nama, $role, $id);
    if ($st->execute()) alert('success','User diperbarui.');
    else alert('danger','Gagal update user.');
    header("Location: index.php?m=setting&tab=users"); exit;
  }

  // Reset/Ubah password (admin bisa semua; non-admin hanya dirinya)
  if ($action === 'user_pw') {
    $id = (int)$_POST['id'];
    $me = user('id', 0);
    if (!$is_admin && $id !== (int)$me) {
      alert('danger','Tidak diizinkan.');
      header("Location: index.php?m=setting&tab=users"); exit;
    }
    $p1 = isset($_POST['password']) ? $_POST['password'] : '';
    $p2 = isset($_POST['password2']) ? $_POST['password2'] : '';
    if ($p1==='' || $p1!==$p2) {
      alert('danger','Password kosong / tidak sama.');
      header("Location: index.php?m=setting&tab=users"); exit;
    }
    $to_store = $use_plain ? $p1 : (function_exists('password_hash') ? password_hash($p1, PASSWORD_BCRYPT) : $p1);
    $st = $conn->prepare("UPDATE ksp_users SET password=? WHERE id=?");
    $st->bind_param("si", $to_store, $id);
    if ($st->execute()) alert('success','Password diperbarui.');
    else alert('danger','Gagal memperbarui password.');
    header("Location: index.php?m=setting&tab=users"); exit;
  }

  // Aktif/Nonaktif
  if ($action === 'user_toggle' && $is_admin) {
    $id = (int)$_POST['id'];
    if ($id === (int)user('id',0)) { alert('danger','Tidak bisa menonaktifkan akun sendiri.'); header("Location: index.php?m=setting&tab=users"); exit; }
    $st = $conn->prepare("UPDATE ksp_users SET aktif = IF(aktif=1,0,1) WHERE id=?");
    $st->bind_param("i", $id);
    if ($st->execute()) alert('success','Status user diubah.');
    else alert('danger','Gagal ubah status user.');
    header("Location: index.php?m=setting&tab=users"); exit;
  }

  // Hapus user (opsional; aman2an)
  if ($action === 'user_delete' && $is_admin) {
    $id = (int)$_POST['id'];
    if ($id === (int)user('id',0)) { alert('danger','Tidak bisa menghapus akun sendiri.'); header("Location: index.php?m=setting&tab=users"); exit; }
    $st = $conn->prepare("DELETE FROM ksp_users WHERE id=?");
    $st->bind_param("i", $id);
    if ($st->execute()) alert('success','User dihapus.');
    else alert('danger','Gagal menghapus user.');
    header("Location: index.php?m=setting&tab=users"); exit;
  }
}
?>

<h4>Pengaturan</h4>

<!-- Tabs kecil tanpa dependency -->
<div class="mb-2" style="display:flex;gap:8px;flex-wrap:wrap">
  <a class="btn <?php echo $tab=='app'?'btn-gradient':'btn-outline-primary'; ?>" href="index.php?m=setting&tab=app">Aplikasi</a>
  <a class="btn <?php echo $tab=='print'?'btn-gradient':'btn-outline-primary'; ?>" href="index.php?m=setting&tab=print">Cetak</a>
  <a class="btn <?php echo $tab=='users'?'btn-gradient':'btn-outline-primary'; ?>" href="index.php?m=setting&tab=users">Pengguna</a>
</div>

<?php flash(); ?>

<?php if ($tab=='app'): ?>

  <div class="card card-widget">
    <div class="card-body">
      <form method="post">
        <?php echo csrf_input(); ?>
        <input type="hidden" name="action" value="save_app">
        <div class="row g-3">
          <div class="col-md-4"><label class="form-label">App Name</label><input name="app_name" class="form-control" value="<?php echo e(setting_get('app_name','KSP Harian')); ?>"></div>
          <div class="col-md-4"><label class="form-label">Nama Koperasi</label><input name="company_name" class="form-control" value="<?php echo e(setting_get('company_name','Koperasi Harian')); ?>"></div>
          <div class="col-md-4"><label class="form-label">Telp/WA</label><input name="company_phone" class="form-control" value="<?php echo e(setting_get('company_phone','')); ?>"></div>
          <div class="col-md-12"><label class="form-label">Alamat</label><input name="company_address" class="form-control" value="<?php echo e(setting_get('company_address','')); ?>"></div>
          <div class="col-md-3"><label class="form-label">Prefix No. Anggota</label><input name="prefix_no_anggota" class="form-control" value="<?php echo e(setting_get('prefix_no_anggota','ANGG')); ?>"></div>
          <div class="col-md-3"><label class="form-label">Prefix No. Pinjaman</label><input name="prefix_no_pinjaman" class="form-control" value="<?php echo e(setting_get('prefix_no_pinjaman','PNJ')); ?>"></div>
          <div class="col-md-3"><label class="form-label">Prefix No. Kas</label><input name="prefix_no_kas" class="form-control" value="<?php echo e(setting_get('prefix_no_kas','KAS')); ?>"></div>
          <div class="col-md-3"><label class="form-label">Denda / Hari (Rp)</label><input type="number" name="denda_fixed_per_hari" class="form-control" value="<?php echo e(setting_get('denda_fixed_per_hari','1000')); ?>"></div>
        </div>
        <div class="mt-3"><button class="btn btn-gradient">Simpan</button></div>
      </form>
    </div>
  </div>

<?php elseif ($tab=='print'): ?>

  <div class="card card-widget">
    <div class="card-header">
      <h6 class="mb-0"><i class="bi bi-printer me-2"></i>Pengaturan Cetak</h6>
    </div>
    <div class="card-body">
      <form method="post">
        <?php echo csrf_input(); ?>
        <input type="hidden" name="action" value="save_print">
        
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Ukuran Kertas Default</label>
            <select name="print_paper_size" class="form-select">
              <option value="A4" <?php echo setting_get('print_paper_size','A4')=='A4'?'selected':''; ?>>A4</option>
              <option value="A5" <?php echo setting_get('print_paper_size','A4')=='A5'?'selected':''; ?>>A5</option>
              <option value="Letter" <?php echo setting_get('print_paper_size','A4')=='Letter'?'selected':''; ?>>Letter</option>
              <option value="58mm" <?php echo setting_get('print_paper_size','A4')=='58mm'?'selected':''; ?>>Thermal 58mm</option>
              <option value="80mm" <?php echo setting_get('print_paper_size','A4')=='80mm'?'selected':''; ?>>Thermal 80mm</option>
            </select>
          </div>
          <div class="col-md-8">
            <label class="form-label">URL Logo (opsional)</label>
            <input name="print_logo" class="form-control" value="<?php echo e(setting_get('print_logo','')); ?>" placeholder="https://... atau public/img/logo.png">
          </div>
          
          <div class="col-md-12">
            <label class="form-label">Teks Header Tambahan</label>
            <textarea name="print_header_text" class="form-control" rows="2" placeholder="Muncul di bawah nama koperasi"><?php echo e(setting_get('print_header_text','')); ?></textarea>
          </div>
          
          <div class="col-md-12">
            <label class="form-label">Teks Footer</label>
            <textarea name="print_footer_text" class="form-control" rows="2" placeholder="Muncul di bagian bawah dokumen"><?php echo e(setting_get('print_footer_text','Terima kasih atas kepercayaan Anda.')); ?></textarea>
          </div>
          
          <div class="col-md-6">
            <label class="form-label">Nama Tanda Tangan Kiri</label>
            <input name="print_signature_left" class="form-control" value="<?php echo e(setting_get('print_signature_left','Peminjam')); ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Nama Tanda Tangan Kanan</label>
            <input name="print_signature_right" class="form-control" value="<?php echo e(setting_get('print_signature_right','Petugas')); ?>">
          </div>
          
          <div class="col-md-6">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" name="print_show_logo" id="showLogo" <?php echo setting_get('print_show_logo','1')=='1'?'checked':''; ?>>
              <label class="form-check-label" for="showLogo">Tampilkan Logo di Dokumen</label>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" name="print_show_qr" id="showQR" <?php echo setting_get('print_show_qr','0')=='1'?'checked':''; ?>>
              <label class="form-check-label" for="showQR">Tampilkan QR Code Verifikasi</label>
            </div>
          </div>
        </div>
        
        <div class="mt-3">
          <button class="btn btn-gradient"><i class="bi bi-save me-1"></i>Simpan Pengaturan Cetak</button>
        </div>
      </form>
    </div>
  </div>
  
  <!-- Preview Templates -->
  <div class="card card-widget mt-3">
    <div class="card-header">
      <h6 class="mb-0"><i class="bi bi-file-earmark-text me-2"></i>Template Dokumen</h6>
    </div>
    <div class="card-body">
      <p class="text-muted small">Klik untuk preview template dokumen:</p>
      <div class="row g-2">
        <div class="col-md-3"><a href="print/bukti_pinjaman.php?id=1" target="_blank" class="btn btn-outline-primary w-100"><i class="bi bi-file-text me-1"></i>Bukti Pinjaman</a></div>
        <div class="col-md-3"><a href="print/kartu_angsuran.php?id=1" target="_blank" class="btn btn-outline-success w-100"><i class="bi bi-card-list me-1"></i>Kartu Angsuran</a></div>
        <div class="col-md-3"><a href="print/kwitansi.php?id=1" target="_blank" class="btn btn-outline-info w-100"><i class="bi bi-receipt me-1"></i>Kwitansi</a></div>
        <div class="col-md-3"><a href="print/bukti_lunas.php?id=1" target="_blank" class="btn btn-outline-warning w-100"><i class="bi bi-patch-check me-1"></i>Surat Lunas</a></div>
      </div>
    </div>
  </div>

<?php else: // tab users ?>

  <?php $is_admin = is_role('admin'); ?>

  <?php if ($is_admin): ?>
    <!-- Tambah user -->
    <div class="card card-widget mb-3">
      <div class="card-body">
        <h6 class="mb-2">Tambah Pengguna Baru</h6>
        <form method="post" class="row g-2">
          <?php echo csrf_input(); ?><input type="hidden" name="action" value="user_create">
          <div class="col-md-3"><label class="form-label">Nama</label><input name="nama" class="form-control" required></div>
          <div class="col-md-3"><label class="form-label">Username</label><input name="username" class="form-control" required></div>
          <div class="col-md-2"><label class="form-label">Role</label>
            <select name="role" class="form-select">
              <option value="teller">teller</option>
              <option value="admin">admin</option>
            </select>
          </div>
          <div class="col-md-2"><label class="form-label">Password</label><input name="password" type="password" class="form-control" required></div>
          <div class="col-md-2"><label class="form-label">Ulangi</label><input name="password2" type="password" class="form-control" required></div>
          <div class="col-12"><button class="btn btn-gradient">Tambah</button>
            <span class="small text-secondary ms-2">Mode password: <b><?php echo $use_plain?'PLAIN':'HASH'; ?></b></span>
          </div>
        </form>
      </div>
    </div>
  <?php endif; ?>

  <div class="row g-3">
    <div class="col-lg-8">
      <div class="card card-widget">
        <div class="card-body">
          <h6 class="mb-2">Daftar Pengguna</h6>
          <div class="table-responsive">
            <table class="table table-striped table-sm">
              <thead><tr><th>Nama</th><th>Username</th><th>Role</th><th>Status</th><th>Aksi</th></tr></thead>
              <tbody>
                <?php
                $u = $conn->query("SELECT id, nama, username, role, aktif FROM ksp_users ORDER BY id DESC");
                while($r=$u->fetch_assoc()):
                ?>
                <tr>
                  <td>
                    <?php if ($is_admin): ?>
                      <form method="post" class="d-flex gap-2">
                        <?php echo csrf_input(); ?>
                        <input type="hidden" name="action" value="user_update">
                        <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                        <input name="nama" class="form-control" value="<?php echo e($r['nama']); ?>" style="max-width:180px">
                  </td>
                  <td><?php echo e($r['username']); ?></td>
                  <td>
                        <select name="role" class="form-select" style="max-width:120px">
                          <option value="teller" <?php echo $r['role']=='teller'?'selected':''; ?>>teller</option>
                          <option value="admin"  <?php echo $r['role']=='admin'?'selected':''; ?>>admin</option>
                        </select>
                  </td>
                  <td>
                    <span class="badge <?php echo $r['aktif']?'text-bg-success':'text-bg-danger'; ?>">
                      <?php echo $r['aktif']?'aktif':'nonaktif'; ?>
                    </span>
                  </td>
                  <td class="d-flex gap-1">
                        <button class="btn btn-sm btn-outline-primary">Simpan</button>
                      </form>
                      <form method="post" onsubmit="return confirm('Toggle aktif/nonaktif user ini?');" style="display:inline">
                        <?php echo csrf_input(); ?>
                        <input type="hidden" name="action" value="user_toggle">
                        <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                        <button class="btn btn-sm btn-outline-secondary"><?php echo $r['aktif']?'Nonaktifkan':'Aktifkan'; ?></button>
                      </form>
                      <?php if ((int)$r['id'] !== (int)user('id',0)): ?>
                      <form method="post" onsubmit="return confirm('Hapus user?');" style="display:inline">
                        <?php echo csrf_input(); ?>
                        <input type="hidden" name="action" value="user_delete">
                        <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                        <button class="btn btn-sm btn-outline-danger">Hapus</button>
                      </form>
                      <?php endif; ?>
                  </td>
                    <?php else: // non admin ?>
                      <?php echo e($r['nama']); ?>
                  </td>
                  <td><?php echo e($r['username']); ?></td>
                  <td><?php echo e($r['role']); ?></td>
                  <td><span class="badge <?php echo $r['aktif']?'text-bg-success':'text-bg-danger'; ?>"><?php echo $r['aktif']?'aktif':'nonaktif'; ?></span></td>
                  <td><span class="text-secondary small">-</span></td>
                    <?php endif; ?>
                </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          </div>
          <div class="small text-secondary">Catatan: non-admin tidak bisa mengubah user lain.</div>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card card-widget">
        <div class="card-body">
          <h6 class="mb-2">Ubah Password Saya</h6>
          <form method="post" class="row g-2">
            <?php echo csrf_input(); ?>
            <input type="hidden" name="action" value="user_pw">
            <input type="hidden" name="id" value="<?php echo (int)user('id',0); ?>">
            <div class="col-12"><label class="form-label">Password Baru</label><input type="password" name="password" class="form-control" required></div>
            <div class="col-12"><label class="form-label">Ulangi Password</label><input type="password" name="password2" class="form-control" required></div>
            <div class="col-12"><button class="btn btn-gradient w-100">Simpan Password</button></div>
            <div class="small text-secondary">Mode password: <b><?php echo $use_plain?'PLAIN':'HASH'; ?></b></div>
          </form>
        </div>
      </div>
    </div>
  </div>

<?php endif; ?>

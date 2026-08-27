<?php
// views/nota.php - Edit Nota/Faktur/Invoice
require_login();
$conn = db();

// Handle save nota template
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_template'])) {
  csrf_check();
  $type = $_POST['template_type'];
  $content = $_POST['template_content'];
  setting_set('nota_template_' . $type, $content);
  log_activity('update_nota', 'nota', 'Updated nota template: ' . $type);
  alert('success', 'Format nota berhasil disimpan.');
  header('Location: index.php?m=nota&type=' . $type);
  exit;
}

// Handle create custom nota
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_nota'])) {
  csrf_check();
  $title = trim($_POST['nota_title']);
  $content = $_POST['nota_content'];
  $stmt = $conn->prepare("INSERT INTO ksp_nota_custom (title, content, created_by, created_at) VALUES (?, ?, ?, NOW())");
  $uid = user()['id'];
  $stmt->bind_param("ssi", $title, $content, $uid);
  if ($stmt->execute()) {
    log_activity('create_nota', 'nota', 'Created custom nota: ' . $title);
    alert('success', 'Nota custom berhasil dibuat.');
  }
  header('Location: index.php?m=nota&tab=custom');
  exit;
}

// Handle delete custom nota
if (isset($_GET['del'])) {
  $id = (int)$_GET['del'];
  $conn->query("DELETE FROM ksp_nota_custom WHERE id = $id");
  alert('success', 'Nota custom dihapus.');
  header('Location: index.php?m=nota&tab=custom');
  exit;
}

$tab = isset($_GET['tab']) ? $_GET['tab'] : 'templates';
$type = isset($_GET['type']) ? $_GET['type'] : 'kwitansi';
$type_labels = array(
  'kwitansi' => 'Kwitansi',
  'invoice' => 'Faktur',
  'bukti_setor' => 'Bukti Setor'
);
$type_label = isset($type_labels[$type]) ? $type_labels[$type] : ucfirst(str_replace('_', ' ', $type));

// Default templates
$default_templates = array(
  'kwitansi' => '<div style="text-align:center;margin-bottom:15px;">
<h2 style="margin:0;">{company_name}</h2>
<p style="margin:5px 0;font-size:12px;">{company_address}</p>
<p style="margin:5px 0;font-size:12px;">Telp: {company_phone}</p>
</div>
<hr>
<h3 style="text-align:center;">KWITANSI PEMBAYARAN</h3>
<p>No: {no_kwitansi}</p>
<p>Tanggal: {tanggal}</p>
<table style="width:100%;">
<tr><td>Diterima dari</td><td>: {nama_anggota}</td></tr>
<tr><td>Alamat</td><td>: {alamat}</td></tr>
<tr><td>Keperluan</td><td>: {keterangan}</td></tr>
</table>
<div style="text-align:center;margin:20px 0;padding:15px;background:#f0f0f0;border-radius:8px;">
<div style="font-size:12px;">Jumlah</div>
<div style="font-size:24px;font-weight:bold;">Rp {nominal}</div>
<div style="font-size:11px;font-style:italic;">({terbilang} rupiah)</div>
</div>
<table style="width:100%;margin-top:30px;">
<tr>
<td style="text-align:center;width:50%;">{signature_left}<br><br><br>(_____________)</td>
<td style="text-align:center;width:50%;">{signature_right}<br><br><br>(_____________)</td>
</tr>
</table>',

  'invoice' => '<div style="display:flex;justify-content:space-between;align-items:center;border-bottom:2px solid #000;padding-bottom:15px;margin-bottom:15px;">
<div>
<h2 style="margin:0;">{company_name}</h2>
<p style="margin:5px 0;font-size:12px;">{company_address}</p>
<p style="margin:5px 0;font-size:12px;">Telp: {company_phone}</p>
</div>
<div style="text-align:right;">
<h3 style="margin:0;">FAKTUR</h3>
<p style="margin:5px 0;">No: {no_invoice}</p>
<p style="margin:5px 0;">Tanggal: {tanggal}</p>
</div>
</div>
<table style="width:100%;margin-bottom:15px;">
<tr><td style="width:100px;">Kepada</td><td>: {nama_anggota}</td></tr>
<tr><td>Alamat</td><td>: {alamat}</td></tr>
<tr><td>No. HP</td><td>: {no_hp}</td></tr>
</table>
<table style="width:100%;border-collapse:collapse;">
<thead>
<tr style="background:#f0f0f0;">
<th style="border:1px solid #000;padding:8px;">No</th>
<th style="border:1px solid #000;padding:8px;">Keterangan</th>
<th style="border:1px solid #000;padding:8px;">Jumlah</th>
</tr>
</thead>
<tbody>
<tr>
<td style="border:1px solid #000;padding:8px;text-align:center;">1</td>
<td style="border:1px solid #000;padding:8px;">{keterangan}</td>
<td style="border:1px solid #000;padding:8px;text-align:right;">Rp {nominal}</td>
</tr>
<tr style="font-weight:bold;">
<td colspan="2" style="border:1px solid #000;padding:8px;text-align:right;">TOTAL</td>
<td style="border:1px solid #000;padding:8px;text-align:right;">Rp {nominal}</td>
</tr>
</tbody>
</table>
<p style="margin-top:20px;font-style:italic;font-size:12px;">Terbilang: {terbilang} rupiah</p>
<div style="text-align:right;margin-top:40px;">
<p>{tanggal}</p>
<p style="margin-top:50px;">(_____________)</p>
<p>{signature_right}</p>
</div>',

  'bukti_setor' => '<div style="text-align:center;border:2px solid #000;padding:15px;margin-bottom:15px;">
<h2 style="margin:0;">{company_name}</h2>
<h4 style="margin:10px 0;">BUKTI SETORAN</h4>
</div>
<table style="width:100%;">
<tr><td style="width:120px;">No. Bukti</td><td>: {no_bukti}</td></tr>
<tr><td>Tanggal</td><td>: {tanggal}</td></tr>
<tr><td>Nama</td><td>: {nama_anggota}</td></tr>
<tr><td>No. Anggota</td><td>: {no_anggota}</td></tr>
<tr><td>Jenis Setoran</td><td>: {jenis_setoran}</td></tr>
</table>
<div style="text-align:center;margin:20px 0;padding:15px;border:2px dashed #000;">
<div style="font-size:12px;">Jumlah Setoran</div>
<div style="font-size:28px;font-weight:bold;">Rp {nominal}</div>
</div>
<table style="width:100%;margin-top:20px;">
<tr>
<td style="text-align:center;">Penyetor<br><br><br>(_____________)</td>
<td style="text-align:center;">Penerima<br><br><br>(_____________)</td>
</tr>
</table>'
);

// Get custom notas
$custom_notas = $conn->query("SELECT * FROM ksp_nota_custom ORDER BY created_at DESC");
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4><i class="bi bi-receipt me-2"></i>Edit Nota / Faktur</h4>
</div>

<!-- Tabs -->
<ul class="nav nav-tabs mb-3">
  <li class="nav-item">
    <a class="nav-link <?php echo $tab=='templates'?'active':''; ?>" href="index.php?m=nota&tab=templates">
      <i class="bi bi-file-earmark-text me-1"></i>Format Standar
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?php echo $tab=='custom'?'active':''; ?>" href="index.php?m=nota&tab=custom">
      <i class="bi bi-plus-circle me-1"></i>Nota Buatan Sendiri
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?php echo $tab=='variables'?'active':''; ?>" href="index.php?m=nota&tab=variables">
      <i class="bi bi-code me-1"></i>Variabel
    </a>
  </li>
</ul>

<?php if ($tab == 'templates'): ?>
<!-- Template Editor -->
<div class="row">
  <div class="col-md-3">
    <div class="card card-widget">
      <div class="card-header"><h6 class="mb-0">Pilih Format</h6></div>
      <div class="list-group list-group-flush">
        <a href="index.php?m=nota&type=kwitansi" class="list-group-item list-group-item-action <?php echo $type=='kwitansi'?'active':''; ?>">
          <i class="bi bi-receipt me-2"></i>Kwitansi
        </a>
        <a href="index.php?m=nota&type=invoice" class="list-group-item list-group-item-action <?php echo $type=='invoice'?'active':''; ?>">
          <i class="bi bi-file-text me-2"></i>Faktur
        </a>
        <a href="index.php?m=nota&type=bukti_setor" class="list-group-item list-group-item-action <?php echo $type=='bukti_setor'?'active':''; ?>">
          <i class="bi bi-cash me-2"></i>Bukti Setor
        </a>
      </div>
    </div>
  </div>
  
  <div class="col-md-9">
    <div class="card card-widget">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="bi bi-pencil me-2"></i>Edit Format: <?php echo $type_label; ?></h6>
        <button class="btn btn-sm btn-outline-primary" onclick="resetTemplate()">
          <i class="bi bi-arrow-counterclockwise me-1"></i>Kembalikan Bawaan
        </button>
      </div>
      <div class="card-body">
        <form method="post" onsubmit="syncTemplateEditor()">
          <?php echo csrf_input(); ?>
          <input type="hidden" name="template_type" value="<?php echo $type; ?>">
          
          <?php 
          $current = setting_get('nota_template_' . $type, '');
          if (empty($current)) $current = $default_templates[$type];
          ?>
          
          <div class="mb-3">
            <label class="form-label">Isi Format</label>
            <div class="btn-toolbar gap-2 mb-2" role="toolbar" aria-label="Bilah alat editor format">
              <div class="btn-group btn-group-sm" role="group">
                <button type="button" class="btn btn-outline-secondary" onclick="formatEditor('bold')" title="Tebal"><i class="bi bi-type-bold"></i></button>
                <button type="button" class="btn btn-outline-secondary" onclick="formatEditor('italic')" title="Miring"><i class="bi bi-type-italic"></i></button>
                <button type="button" class="btn btn-outline-secondary" onclick="formatEditor('underline')" title="Garis bawah"><i class="bi bi-type-underline"></i></button>
              </div>
              <div class="btn-group btn-group-sm" role="group">
                <button type="button" class="btn btn-outline-secondary" onclick="formatEditor('justifyLeft')" title="Rata kiri"><i class="bi bi-text-left"></i></button>
                <button type="button" class="btn btn-outline-secondary" onclick="formatEditor('justifyCenter')" title="Rata tengah"><i class="bi bi-text-center"></i></button>
                <button type="button" class="btn btn-outline-secondary" onclick="formatEditor('justifyRight')" title="Rata kanan"><i class="bi bi-text-right"></i></button>
              </div>
              <select id="variablePicker" class="form-select form-select-sm" style="max-width:240px;" onchange="insertVariable(this.value); this.value='';">
                <option value="">Sisipkan variabel...</option>
                <option value="{company_name}">Nama Koperasi</option>
                <option value="{company_address}">Alamat Koperasi</option>
                <option value="{company_phone}">Telepon Koperasi</option>
                <option value="{tanggal}">Tanggal</option>
                <option value="{nama_anggota}">Nama Anggota</option>
                <option value="{no_anggota}">No. Anggota</option>
                <option value="{alamat}">Alamat Anggota</option>
                <option value="{nominal}">Nominal</option>
                <option value="{terbilang}">Terbilang</option>
                <option value="{keterangan}">Keterangan</option>
              </select>
            </div>
            <div id="templateVisualEditor" class="form-control bg-white" contenteditable="true" style="min-height:420px;overflow:auto;padding:20px;"><?php echo $current; ?></div>
            <textarea name="template_content" id="templateEditor" class="d-none"><?php echo htmlspecialchars($current); ?></textarea>
            <div class="form-text">Edit langsung seperti dokumen biasa. Variabel dalam kurung kurawal tetap boleh dipakai, misalnya {nama_anggota}.</div>
          </div>
          
          <div class="d-flex gap-2">
            <button type="submit" name="save_template" class="btn btn-gradient" onclick="syncTemplateEditor()">
              <i class="bi bi-save me-1"></i>Simpan Format
            </button>
            <button type="button" class="btn btn-outline-primary" onclick="previewTemplate()">
              <i class="bi bi-eye me-1"></i>Pratinjau
            </button>
          </div>
        </form>
      </div>
    </div>
    
    <!-- Pratinjau -->
    <div class="card card-widget mt-3">
      <div class="card-header"><h6 class="mb-0"><i class="bi bi-eye me-2"></i>Pratinjau</h6></div>
      <div class="card-body">
        <div id="previewArea" style="border:1px solid #ddd;padding:20px;background:white;min-height:200px;">
          <p class="text-muted text-center">Klik "Pratinjau" untuk melihat hasil</p>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
var defaultTemplates = <?php echo json_encode($default_templates); ?>;

function resetTemplate() {
  if (confirm('Kembalikan format ke bawaan?')) {
    document.getElementById('templateVisualEditor').innerHTML = defaultTemplates['<?php echo $type; ?>'];
    syncTemplateEditor();
  }
}

function syncTemplateEditor() {
  document.getElementById('templateEditor').value = document.getElementById('templateVisualEditor').innerHTML;
}

function formatEditor(command) {
  document.getElementById('templateVisualEditor').focus();
  document.execCommand(command, false, null);
  syncTemplateEditor();
}

function insertVariable(value) {
  if (!value) return;
  document.getElementById('templateVisualEditor').focus();
  document.execCommand('insertText', false, value);
  syncTemplateEditor();
}

function previewTemplate() {
  syncTemplateEditor();
  var content = document.getElementById('templateEditor').value;
  // Replace variables with sample data
  var samples = {
    '{company_name}': '<?php echo e(setting_get("company_name", "KSP Harian")); ?>',
    '{company_address}': '<?php echo e(setting_get("company_address", "Jl. Contoh No. 123")); ?>',
    '{company_phone}': '<?php echo e(setting_get("company_phone", "08123456789")); ?>',
    '{no_kwitansi}': 'KW-<?php echo date("Ymd"); ?>-001',
    '{no_invoice}': 'INV-<?php echo date("Ymd"); ?>-001',
    '{no_bukti}': 'BS-<?php echo date("Ymd"); ?>-001',
    '{tanggal}': '<?php echo date("d/m/Y"); ?>',
    '{nama_anggota}': 'Budi Santoso',
    '{no_anggota}': 'ANGG-001',
    '{alamat}': 'Jl. Merdeka No. 45',
    '{no_hp}': '081234567890',
    '{keterangan}': 'Pembayaran Angsuran ke-5',
    '{nominal}': '150.000',
    '{terbilang}': 'seratus lima puluh ribu',
    '{jenis_setoran}': 'Angsuran Pinjaman',
    '{signature_left}': '<?php echo e(setting_get("print_signature_left", "Peminjam")); ?>',
    '{signature_right}': '<?php echo e(setting_get("print_signature_right", "Petugas")); ?>'
  };
  
  for (var key in samples) {
    content = content.split(key).join(samples[key]);
  }
  
  document.getElementById('previewArea').innerHTML = content;
}

document.getElementById('templateVisualEditor').addEventListener('input', syncTemplateEditor);
</script>

<?php elseif ($tab == 'custom'): ?>
<!-- Nota Buatan Sendiri -->
<div class="row">
  <div class="col-md-5">
    <div class="card card-widget">
      <div class="card-header"><h6 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Buat Nota Sendiri</h6></div>
      <div class="card-body">
        <form method="post" onsubmit="syncCustomEditor()">
          <?php echo csrf_input(); ?>
          <div class="mb-3">
            <label class="form-label">Judul Nota</label>
            <input type="text" name="nota_title" class="form-control" required placeholder="Contoh: Nota Penjualan">
          </div>
          <div class="mb-3">
            <label class="form-label">Isi Nota</label>
            <div class="btn-toolbar gap-2 mb-2" role="toolbar" aria-label="Bilah alat editor nota">
              <div class="btn-group btn-group-sm" role="group">
                <button type="button" class="btn btn-outline-secondary" onclick="formatCustomEditor('bold')" title="Tebal"><i class="bi bi-type-bold"></i></button>
                <button type="button" class="btn btn-outline-secondary" onclick="formatCustomEditor('italic')" title="Miring"><i class="bi bi-type-italic"></i></button>
                <button type="button" class="btn btn-outline-secondary" onclick="formatCustomEditor('underline')" title="Garis bawah"><i class="bi bi-type-underline"></i></button>
              </div>
              <div class="btn-group btn-group-sm" role="group">
                <button type="button" class="btn btn-outline-secondary" onclick="formatCustomEditor('justifyLeft')" title="Rata kiri"><i class="bi bi-text-left"></i></button>
                <button type="button" class="btn btn-outline-secondary" onclick="formatCustomEditor('justifyCenter')" title="Rata tengah"><i class="bi bi-text-center"></i></button>
                <button type="button" class="btn btn-outline-secondary" onclick="formatCustomEditor('justifyRight')" title="Rata kanan"><i class="bi bi-text-right"></i></button>
              </div>
            </div>
            <div id="customVisualEditor" class="form-control bg-white" contenteditable="true" style="min-height:260px;overflow:auto;padding:16px;">
              <h3 style="text-align:center;">JUDUL NOTA</h3>
              <p>Tanggal: {tanggal}</p>
              <p>Nama: {nama_anggota}</p>
              <p>Keterangan: {keterangan}</p>
              <p style="text-align:right;font-weight:bold;">Total: Rp {nominal}</p>
            </div>
            <textarea name="nota_content" id="customNotaContent" class="d-none" required></textarea>
            <div class="form-text">Isi nota bisa diketik langsung tanpa menulis kode.</div>
          </div>
          <button type="submit" name="create_nota" class="btn btn-gradient" onclick="syncCustomEditor()">
            <i class="bi bi-save me-1"></i>Simpan Nota
          </button>
        </form>
      </div>
    </div>
  </div>
  
  <div class="col-md-7">
    <div class="card card-widget">
      <div class="card-header"><h6 class="mb-0"><i class="bi bi-list me-2"></i>Daftar Nota Buatan Sendiri</h6></div>
      <div class="card-body">
        <?php if ($custom_notas && $custom_notas->num_rows > 0): ?>
        <div class="table-responsive">
          <table class="table table-sm table-striped">
            <thead><tr><th>Judul</th><th>Dibuat</th><th>Aksi</th></tr></thead>
            <tbody>
            <?php while ($n = $custom_notas->fetch_assoc()): ?>
            <tr>
              <td><?php echo htmlspecialchars($n['title']); ?></td>
              <td><?php echo date('d/m/Y', strtotime($n['created_at'])); ?></td>
              <td>
                <button class="btn btn-sm btn-outline-primary" onclick="viewNota(<?php echo $n['id']; ?>, '<?php echo htmlspecialchars(addslashes($n['content'])); ?>')">
                  <i class="bi bi-eye"></i>
                </button>
                <a href="index.php?m=nota&tab=custom&del=<?php echo $n['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Hapus nota ini?')">
                  <i class="bi bi-trash"></i>
                </a>
              </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
          </table>
        </div>
        <?php else: ?>
        <p class="text-muted text-center py-4">Belum ada nota custom.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
function syncCustomEditor() {
  document.getElementById('customNotaContent').value = document.getElementById('customVisualEditor').innerHTML;
}

function formatCustomEditor(command) {
  document.getElementById('customVisualEditor').focus();
  document.execCommand(command, false, null);
  syncCustomEditor();
}

function viewNota(id, content) {
  var win = window.open('', '_blank', 'width=600,height=800');
  win.document.write('<html><head><title>Pratinjau Nota</title></head><body style="padding:20px;">' + content + '</body></html>');
}

document.getElementById('customVisualEditor').addEventListener('input', syncCustomEditor);
syncCustomEditor();
</script>

<?php else: // variables ?>
<!-- Variables Reference -->
<div class="card card-widget">
  <div class="card-header"><h6 class="mb-0"><i class="bi bi-code me-2"></i>Daftar Variabel yang Tersedia</h6></div>
  <div class="card-body">
    <p class="text-muted">Gunakan variabel berikut dalam template nota Anda. Variabel akan otomatis diganti dengan data sebenarnya saat dicetak.</p>
    
    <div class="table-responsive">
      <table class="table table-striped table-sm">
        <thead><tr><th>Variabel</th><th>Keterangan</th><th>Contoh Output</th></tr></thead>
        <tbody>
          <tr><td><code>{company_name}</code></td><td>Nama Koperasi</td><td><?php echo e(setting_get('company_name', 'KSP Harian')); ?></td></tr>
          <tr><td><code>{company_address}</code></td><td>Alamat Koperasi</td><td><?php echo e(setting_get('company_address', 'Jl. Contoh')); ?></td></tr>
          <tr><td><code>{company_phone}</code></td><td>Telp Koperasi</td><td><?php echo e(setting_get('company_phone', '08123456789')); ?></td></tr>
          <tr><td><code>{tanggal}</code></td><td>Tanggal Transaksi</td><td><?php echo date('d/m/Y'); ?></td></tr>
          <tr><td><code>{no_kwitansi}</code></td><td>Nomor Kwitansi</td><td>KW-<?php echo date('Ymd'); ?>-001</td></tr>
          <tr><td><code>{no_invoice}</code></td><td>Nomor Faktur</td><td>INV-<?php echo date('Ymd'); ?>-001</td></tr>
          <tr><td><code>{nama_anggota}</code></td><td>Nama Anggota</td><td>Budi Santoso</td></tr>
          <tr><td><code>{no_anggota}</code></td><td>No. Anggota</td><td>ANGG-001</td></tr>
          <tr><td><code>{alamat}</code></td><td>Alamat Anggota</td><td>Jl. Merdeka No. 45</td></tr>
          <tr><td><code>{no_hp}</code></td><td>No. HP Anggota</td><td>081234567890</td></tr>
          <tr><td><code>{nominal}</code></td><td>Jumlah Uang</td><td>150.000</td></tr>
          <tr><td><code>{terbilang}</code></td><td>Terbilang</td><td>seratus lima puluh ribu</td></tr>
          <tr><td><code>{keterangan}</code></td><td>Keterangan Transaksi</td><td>Pembayaran Angsuran</td></tr>
          <tr><td><code>{signature_left}</code></td><td>Tanda Tangan Kiri</td><td><?php echo e(setting_get('print_signature_left', 'Peminjam')); ?></td></tr>
          <tr><td><code>{signature_right}</code></td><td>Tanda Tangan Kanan</td><td><?php echo e(setting_get('print_signature_right', 'Petugas')); ?></td></tr>
        </tbody>
      </table>
    </div>
    
    <div class="alert alert-info mt-3">
      <h6><i class="bi bi-lightbulb me-2"></i>Tips:</h6>
      <ul class="mb-0 small">
        <li>Edit format langsung dari tampilan nota, tanpa perlu menulis kode</li>
        <li>Variabel harus ditulis persis seperti di atas, termasuk huruf besar dan kecil</li>
        <li>Gunakan tombol format untuk teks tebal, miring, garis bawah, dan perataan</li>
      </ul>
    </div>
  </div>
</div>
<?php endif; ?>

<?php
// views/template_cetak.php - Editor Template Cetak
require_login();
$conn = db();

// Default templates
$default_templates = [
  'struk_angsuran' => [
    'nama' => 'Struk Pembayaran Angsuran',
    'deskripsi' => 'Struk kecil untuk pembayaran angsuran (thermal 58mm)',
    'variabel' => '{company_name}, {tanggal}, {no_pinjaman}, {nama_anggota}, {angsuran_ke}, {nominal}, {kasir}'
  ],
  'kwitansi' => [
    'nama' => 'Kwitansi Pembayaran',
    'deskripsi' => 'Kwitansi standar A5/A4',
    'variabel' => '{company_name}, {no_kwitansi}, {tanggal}, {nama}, {nominal}, {terbilang}, {keterangan}, {kasir}'
  ],
  'bukti_pinjaman' => [
    'nama' => 'Bukti Realisasi Pinjaman',
    'deskripsi' => 'Bukti pencairan/realisasi pinjaman',
    'variabel' => '{company_name}, {no_pinjaman}, {tanggal}, {nama_anggota}, {no_anggota}, {pokok}, {bunga}, {total}, {tenor}, {angsuran}'
  ],
  'kartu_angsuran' => [
    'nama' => 'Kartu Angsuran',
    'deskripsi' => 'Kartu jadwal angsuran lengkap',
    'variabel' => '{company_name}, {no_pinjaman}, {nama_anggota}, {pokok}, {total}, {tenor}, {jadwal_table}'
  ],
  'buku_tabungan' => [
    'nama' => 'Buku Tabungan',
    'deskripsi' => 'Cetak buku tabungan/simpanan',
    'variabel' => '{company_name}, {nama_anggota}, {no_anggota}, {bulan}, {saldo_awal}, {mutasi_table}, {saldo_akhir}'
  ],
  'bukti_lunas' => [
    'nama' => 'Surat Keterangan Lunas',
    'deskripsi' => 'Surat bukti pelunasan pinjaman',
    'variabel' => '{company_name}, {no_pinjaman}, {nama_anggota}, {no_anggota}, {tanggal_realisasi}, {tanggal_lunas}, {total_pinjaman}'
  ],
  'perjanjian' => [
    'nama' => 'Surat Perjanjian Pinjaman',
    'deskripsi' => 'Surat perjanjian kredit A4',
    'variabel' => '{company_name}, {no_pinjaman}, {tanggal}, {nama_anggota}, {alamat}, {pokok}, {bunga}, {total}, {tenor}, {angsuran}'
  ],
  'lembar_tagih' => [
    'nama' => 'Lembar Penagihan',
    'deskripsi' => 'Lembar tagihan untuk kolektor',
    'variabel' => '{company_name}, {tanggal}, {nama_kolektor}, {daftar_tagihan}'
  ]
];

// Insert default templates if not exist
foreach ($default_templates as $kode => $tpl) {
  $check = $conn->query("SELECT id FROM ksp_print_template WHERE kode = '$kode'")->num_rows;
  if ($check == 0) {
    $stmt = $conn->prepare("INSERT INTO ksp_print_template (kode, nama, deskripsi, variabel_tersedia) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $kode, $tpl['nama'], $tpl['deskripsi'], $tpl['variabel']);
    $stmt->execute();
  }
}

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_template'])) {
  csrf_check();
  $id = (int)$_POST['template_id'];
  $html = $_POST['html_template'];
  $css = $_POST['css_style'];
  $uid = user()['id'];
  
  $stmt = $conn->prepare("UPDATE ksp_print_template SET html_template = ?, css_style = ?, updated_by = ? WHERE id = ?");
  $stmt->bind_param("ssii", $html, $css, $uid, $id);
  
  if ($stmt->execute()) {
    log_activity('update_template', 'print_template', 'Update template cetak ID: ' . $id);
    alert('success', 'Template berhasil disimpan!');
  } else {
    alert('danger', 'Gagal menyimpan template.');
  }
  header('Location: index.php?m=template_cetak&id=' . $id);
  exit;
}

// Handle reset to default
if (isset($_GET['reset']) && isset($_GET['id'])) {
  csrf_check();
  $id = (int)$_GET['id'];
  $conn->query("UPDATE ksp_print_template SET html_template = NULL, css_style = NULL WHERE id = $id");
  alert('info', 'Template direset ke default.');
  header('Location: index.php?m=template_cetak&id=' . $id);
  exit;
}

// Get all templates
$templates = $conn->query("SELECT * FROM ksp_print_template ORDER BY nama");

// Get selected template
$selected = null;
if (isset($_GET['id'])) {
  $id = (int)$_GET['id'];
  $selected = $conn->query("SELECT * FROM ksp_print_template WHERE id = $id")->fetch_assoc();
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-brush me-2"></i>Editor Template Cetak</h4>
</div>

<div class="row">
  <!-- Template List -->
  <div class="col-md-3">
    <div class="card card-widget">
      <div class="card-header"><h6 class="mb-0"><i class="bi bi-file-earmark-text me-2"></i>Daftar Template</h6></div>
      <div class="list-group list-group-flush">
        <?php while ($t = $templates->fetch_assoc()): ?>
        <a href="index.php?m=template_cetak&id=<?php echo $t['id']; ?>" 
           class="list-group-item list-group-item-action <?php echo ($selected && $selected['id'] == $t['id']) ? 'active' : ''; ?>">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <strong><?php echo htmlspecialchars($t['nama']); ?></strong>
              <br><small class="<?php echo ($selected && $selected['id'] == $t['id']) ? '' : 'text-muted'; ?>"><?php echo $t['kode']; ?></small>
            </div>
            <?php if ($t['html_template']): ?>
            <span class="badge bg-success">Custom</span>
            <?php else: ?>
            <span class="badge bg-secondary">Default</span>
            <?php endif; ?>
          </div>
        </a>
        <?php endwhile; ?>
      </div>
    </div>
  </div>

  <!-- Editor -->
  <div class="col-md-9">
    <?php if ($selected): ?>
    <div class="card card-widget">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="bi bi-code-slash me-2"></i><?php echo htmlspecialchars($selected['nama']); ?></h6>
        <div>
          <a href="print/preview_template.php?id=<?php echo $selected['id']; ?>" target="_blank" class="btn btn-sm btn-outline-info">
            <i class="bi bi-eye me-1"></i>Preview
          </a>
          <?php if ($selected['html_template']): ?>
          <a href="index.php?m=template_cetak&reset=1&id=<?php echo $selected['id']; ?>&<?php echo csrf_input(true); ?>" 
             class="btn btn-sm btn-outline-warning" onclick="return confirm('Reset template ke default?')">
            <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
          </a>
          <?php endif; ?>
        </div>
      </div>
      <div class="card-body">
        <p class="text-muted small mb-3"><?php echo htmlspecialchars($selected['deskripsi']); ?></p>
        
        <!-- Variable Info -->
        <div class="alert alert-info py-2 small">
          <strong><i class="bi bi-info-circle me-1"></i>Variabel yang tersedia:</strong><br>
          <code><?php echo htmlspecialchars($selected['variabel_tersedia']); ?></code>
        </div>

        <form method="post">
          <?php echo csrf_input(); ?>
          <input type="hidden" name="template_id" value="<?php echo $selected['id']; ?>">
          
          <!-- Tabs -->
          <ul class="nav nav-tabs mb-3" role="tablist">
            <li class="nav-item">
              <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabHtml" type="button">HTML Template</button>
            </li>
            <li class="nav-item">
              <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabCss" type="button">CSS Style</button>
            </li>
            <li class="nav-item">
              <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabHelp" type="button">Bantuan</button>
            </li>
          </ul>

          <div class="tab-content">
            <!-- HTML Tab -->
            <div class="tab-pane fade show active" id="tabHtml">
              <textarea name="html_template" id="htmlEditor" class="form-control font-monospace" rows="20" 
                style="font-size:13px;line-height:1.5;"><?php echo htmlspecialchars($selected['html_template'] ?: get_default_template($selected['kode'])); ?></textarea>
            </div>

            <!-- CSS Tab -->
            <div class="tab-pane fade" id="tabCss">
              <textarea name="css_style" id="cssEditor" class="form-control font-monospace" rows="20"
                style="font-size:13px;line-height:1.5;"><?php echo htmlspecialchars($selected['css_style'] ?: get_default_css($selected['kode'])); ?></textarea>
            </div>

            <!-- Help Tab -->
            <div class="tab-pane fade" id="tabHelp">
              <div class="p-3 bg-light rounded">
                <h6>Cara Penggunaan</h6>
                <ol class="small">
                  <li>Edit HTML template sesuai kebutuhan</li>
                  <li>Gunakan variabel dengan format <code>{nama_variabel}</code></li>
                  <li>Tambahkan CSS custom di tab CSS Style</li>
                  <li>Klik Preview untuk melihat hasil</li>
                  <li>Simpan jika sudah sesuai</li>
                </ol>
                
                <h6 class="mt-3">Variabel Umum</h6>
                <table class="table table-sm small">
                  <tr><td><code>{company_name}</code></td><td>Nama koperasi</td></tr>
                  <tr><td><code>{company_address}</code></td><td>Alamat koperasi</td></tr>
                  <tr><td><code>{company_phone}</code></td><td>Telepon koperasi</td></tr>
                  <tr><td><code>{tanggal}</code></td><td>Tanggal cetak</td></tr>
                  <tr><td><code>{waktu}</code></td><td>Waktu cetak</td></tr>
                </table>

                <h6 class="mt-3">Tips</h6>
                <ul class="small">
                  <li>Gunakan <code>@media print</code> untuk style khusus cetak</li>
                  <li>Ukuran kertas: A4 (210x297mm), A5 (148x210mm), Thermal (58mm/80mm)</li>
                  <li>Untuk struk thermal, gunakan lebar max 58mm atau 80mm</li>
                </ul>
              </div>
            </div>
          </div>

          <div class="mt-3 d-flex justify-content-between">
            <button type="submit" name="save_template" class="btn btn-primary">
              <i class="bi bi-save me-1"></i>Simpan Template
            </button>
            <a href="print/preview_template.php?id=<?php echo $selected['id']; ?>" target="_blank" class="btn btn-outline-secondary">
              <i class="bi bi-printer me-1"></i>Test Print
            </a>
          </div>
        </form>
      </div>
    </div>
    <?php else: ?>
    <div class="card card-widget">
      <div class="card-body text-center py-5">
        <i class="bi bi-brush text-muted" style="font-size:60px;"></i>
        <h5 class="mt-3">Pilih Template</h5>
        <p class="text-muted">Pilih template dari daftar di sebelah kiri untuk mulai mengedit.</p>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php
// Helper functions for default templates
function get_default_template($kode) {
  $templates = [
    'struk_angsuran' => '<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Struk Pembayaran</title>
</head>
<body>
  <div class="struk">
    <div class="header">
      <h3>{company_name}</h3>
      <p>{company_address}</p>
    </div>
    <div class="divider">================================</div>
    <table>
      <tr><td>Tanggal</td><td>: {tanggal}</td></tr>
      <tr><td>No. Pinjaman</td><td>: {no_pinjaman}</td></tr>
      <tr><td>Nama</td><td>: {nama_anggota}</td></tr>
      <tr><td>Angsuran ke</td><td>: {angsuran_ke}</td></tr>
    </table>
    <div class="divider">--------------------------------</div>
    <div class="total">
      <strong>TOTAL: Rp {nominal}</strong>
    </div>
    <div class="divider">================================</div>
    <div class="footer">
      <p>Kasir: {kasir}</p>
      <p>Terima Kasih</p>
    </div>
  </div>
</body>
</html>',

    'kwitansi' => '<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Kwitansi</title>
</head>
<body>
  <div class="kwitansi">
    <div class="header">
      <h2>{company_name}</h2>
      <p>{company_address} | Telp: {company_phone}</p>
    </div>
    <h3 class="title">KWITANSI</h3>
    <p class="nomor">No: {no_kwitansi}</p>
    
    <table class="detail">
      <tr>
        <td width="150">Telah Terima Dari</td>
        <td>: <strong>{nama}</strong></td>
      </tr>
      <tr>
        <td>Uang Sejumlah</td>
        <td>: <em>{terbilang}</em></td>
      </tr>
      <tr>
        <td>Untuk Pembayaran</td>
        <td>: {keterangan}</td>
      </tr>
    </table>
    
    <div class="amount-box">
      <strong>Rp {nominal}</strong>
    </div>
    
    <div class="footer">
      <div class="date">{tanggal}</div>
      <div class="sign">
        <p>Kasir,</p>
        <br><br><br>
        <p><u>{kasir}</u></p>
      </div>
    </div>
  </div>
</body>
</html>',

    'bukti_pinjaman' => '<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Bukti Pinjaman</title>
</head>
<body>
  <div class="bukti">
    <div class="header">
      <h2>{company_name}</h2>
      <p>{company_address}</p>
    </div>
    <h3>BUKTI REALISASI PINJAMAN</h3>
    <p>No: {no_pinjaman}</p>
    
    <table class="info">
      <tr><td>Tanggal</td><td>: {tanggal}</td></tr>
      <tr><td>Nama Anggota</td><td>: {nama_anggota}</td></tr>
      <tr><td>No. Anggota</td><td>: {no_anggota}</td></tr>
    </table>
    
    <table class="detail">
      <tr><td>Pokok Pinjaman</td><td class="right">Rp {pokok}</td></tr>
      <tr><td>Bunga</td><td class="right">Rp {bunga}</td></tr>
      <tr class="total"><td>Total Pinjaman</td><td class="right">Rp {total}</td></tr>
    </table>
    
    <p>Tenor: {tenor} | Angsuran: Rp {angsuran}</p>
    
    <div class="sign-area">
      <div class="sign">
        <p>Peminjam,</p>
        <br><br><br>
        <p><u>{nama_anggota}</u></p>
      </div>
      <div class="sign">
        <p>Petugas,</p>
        <br><br><br>
        <p><u>____________</u></p>
      </div>
    </div>
  </div>
</body>
</html>',

    'buku_tabungan' => '<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Buku Tabungan</title>
</head>
<body>
  <div class="tabungan">
    <div class="header">
      <h2>{company_name}</h2>
      <h3>BUKU TABUNGAN</h3>
    </div>
    
    <table class="info">
      <tr><td>Nama</td><td>: {nama_anggota}</td></tr>
      <tr><td>No. Anggota</td><td>: {no_anggota}</td></tr>
      <tr><td>Periode</td><td>: {bulan}</td></tr>
    </table>
    
    <p>Saldo Awal: <strong>Rp {saldo_awal}</strong></p>
    
    {mutasi_table}
    
    <p class="saldo-akhir">Saldo Akhir: <strong>Rp {saldo_akhir}</strong></p>
  </div>
</body>
</html>',

    'bukti_lunas' => '<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Surat Lunas</title>
</head>
<body>
  <div class="surat-lunas">
    <div class="header">
      <h2>{company_name}</h2>
      <p>{company_address}</p>
    </div>
    
    <h3>SURAT KETERANGAN LUNAS</h3>
    
    <p>Yang bertanda tangan di bawah ini menerangkan bahwa:</p>
    
    <table class="info">
      <tr><td>Nama</td><td>: {nama_anggota}</td></tr>
      <tr><td>No. Anggota</td><td>: {no_anggota}</td></tr>
      <tr><td>No. Pinjaman</td><td>: {no_pinjaman}</td></tr>
    </table>
    
    <p>Telah melunasi seluruh kewajiban pinjaman sebesar <strong>Rp {total_pinjaman}</strong></p>
    <p>Tanggal Realisasi: {tanggal_realisasi}</p>
    <p>Tanggal Lunas: {tanggal_lunas}</p>
    
    <p>Demikian surat keterangan ini dibuat untuk dipergunakan sebagaimana mestinya.</p>
    
    <div class="sign">
      <p>{tanggal_lunas}</p>
      <p>Mengetahui,</p>
      <br><br><br>
      <p><u>_______________</u></p>
      <p>Pimpinan</p>
    </div>
  </div>
</body>
</html>'
  ];
  
  return $templates[$kode] ?? '<p>Template default tidak tersedia. Silakan buat template Anda sendiri.</p>';
}

function get_default_css($kode) {
  $css = [
    'struk_angsuran' => 'body { font-family: monospace; font-size: 12px; margin: 0; padding: 10px; width: 58mm; }
.struk { text-align: center; }
.header h3 { margin: 0; font-size: 14px; }
.header p { margin: 5px 0; font-size: 10px; }
.divider { margin: 5px 0; }
table { width: 100%; text-align: left; font-size: 11px; }
.total { font-size: 14px; margin: 10px 0; }
.footer { font-size: 10px; margin-top: 10px; }
@media print { body { width: 58mm; } }',

    'kwitansi' => 'body { font-family: Arial, sans-serif; padding: 20px; max-width: 210mm; margin: 0 auto; }
.kwitansi { border: 2px solid #000; padding: 20px; }
.header { text-align: center; border-bottom: 1px solid #000; padding-bottom: 10px; margin-bottom: 15px; }
.header h2 { margin: 0; }
.header p { margin: 5px 0; font-size: 12px; }
.title { text-align: center; margin: 15px 0; }
.nomor { text-align: right; }
.detail { width: 100%; margin: 20px 0; }
.detail td { padding: 5px 0; }
.amount-box { border: 1px solid #000; padding: 10px; text-align: center; font-size: 18px; margin: 20px 0; }
.footer { display: flex; justify-content: space-between; margin-top: 30px; }
.sign { text-align: center; }
@media print { body { padding: 0; } }',

    'bukti_pinjaman' => 'body { font-family: Arial, sans-serif; padding: 20px; max-width: 210mm; margin: 0 auto; }
.header { text-align: center; margin-bottom: 20px; }
.header h2 { margin: 0; }
h3 { text-align: center; }
.info, .detail { width: 100%; margin: 15px 0; border-collapse: collapse; }
.detail td { padding: 8px; border: 1px solid #ddd; }
.detail .right { text-align: right; }
.detail .total { font-weight: bold; background: #f5f5f5; }
.sign-area { display: flex; justify-content: space-around; margin-top: 50px; }
.sign { text-align: center; }',

    'buku_tabungan' => 'body { font-family: Arial, sans-serif; padding: 20px; }
.header { text-align: center; margin-bottom: 20px; }
.info { margin: 15px 0; }
.info td { padding: 3px 10px 3px 0; }
table.mutasi { width: 100%; border-collapse: collapse; margin: 15px 0; }
table.mutasi th, table.mutasi td { border: 1px solid #000; padding: 5px; font-size: 12px; }
table.mutasi th { background: #f0f0f0; }
.saldo-akhir { font-size: 16px; margin-top: 15px; }',

    'bukti_lunas' => 'body { font-family: Arial, sans-serif; padding: 30px; max-width: 210mm; margin: 0 auto; }
.header { text-align: center; margin-bottom: 30px; }
.header h2 { margin: 0; }
h3 { text-align: center; text-decoration: underline; }
.info { margin: 20px 0 20px 30px; }
.info td { padding: 5px 10px 5px 0; }
p { line-height: 1.8; }
.sign { text-align: right; margin-top: 50px; margin-right: 50px; }'
  ];
  
  return $css[$kode] ?? '/* Custom CSS */
body { font-family: Arial, sans-serif; padding: 20px; }
@media print { body { padding: 0; } }';
}
?>

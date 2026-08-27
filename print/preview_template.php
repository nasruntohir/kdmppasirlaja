<?php
// print/preview_template.php - Preview Template Cetak
require_once __DIR__ . '/../lib/helpers.php';
$conn = db();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$template = $conn->query("SELECT * FROM ksp_print_template WHERE id = $id")->fetch_assoc();

if (!$template) {
  die('Template tidak ditemukan.');
}

// Get company info
$company_name = setting_get('company_name', 'KSP Harian');
$company_address = setting_get('company_address', 'Jl. Contoh No. 123');
$company_phone = setting_get('company_phone', '08123456789');

// Sample data for preview
$sample_data = [
  'company_name' => $company_name,
  'company_address' => $company_address,
  'company_phone' => $company_phone,
  'tanggal' => date('d/m/Y'),
  'waktu' => date('H:i'),
  'no_pinjaman' => 'PNJ-20260106-0001',
  'no_anggota' => 'ANG-001',
  'nama_anggota' => 'Budi Santoso',
  'nama' => 'Budi Santoso',
  'alamat' => 'Jl. Merdeka No. 45, Jakarta',
  'angsuran_ke' => '5 dari 30',
  'nominal' => number_format(125000, 0, ',', '.'),
  'kasir' => 'Admin',
  'no_kwitansi' => 'KWT-001',
  'terbilang' => 'Seratus dua puluh lima ribu rupiah',
  'keterangan' => 'Pembayaran angsuran pinjaman',
  'pokok' => number_format(5000000, 0, ',', '.'),
  'bunga' => number_format(1000000, 0, ',', '.'),
  'total' => number_format(6000000, 0, ',', '.'),
  'total_pinjaman' => number_format(6000000, 0, ',', '.'),
  'tenor' => '30 hari',
  'angsuran' => number_format(200000, 0, ',', '.'),
  'bulan' => 'Januari 2026',
  'saldo_awal' => number_format(500000, 0, ',', '.'),
  'saldo_akhir' => number_format(750000, 0, ',', '.'),
  'tanggal_realisasi' => '01/01/2026',
  'tanggal_lunas' => '30/01/2026',
  'nama_kolektor' => 'Ahmad',
  'mutasi_table' => '<table class="mutasi" width="100%" border="1" cellpadding="5">
    <tr><th>Tanggal</th><th>Keterangan</th><th>Debit</th><th>Kredit</th><th>Saldo</th></tr>
    <tr><td>02/01/2026</td><td>Setor</td><td>-</td><td>100.000</td><td>600.000</td></tr>
    <tr><td>10/01/2026</td><td>Setor</td><td>-</td><td>150.000</td><td>750.000</td></tr>
  </table>',
  'jadwal_table' => '<table width="100%" border="1" cellpadding="5">
    <tr><th>Ke</th><th>Tanggal</th><th>Pokok</th><th>Bunga</th><th>Total</th><th>Status</th></tr>
    <tr><td>1</td><td>02/01/2026</td><td>166.667</td><td>33.333</td><td>200.000</td><td>Lunas</td></tr>
    <tr><td>2</td><td>03/01/2026</td><td>166.667</td><td>33.333</td><td>200.000</td><td>Lunas</td></tr>
    <tr><td>3</td><td>04/01/2026</td><td>166.667</td><td>33.333</td><td>200.000</td><td>Belum</td></tr>
  </table>',
  'daftar_tagihan' => '<table width="100%" border="1" cellpadding="5">
    <tr><th>No</th><th>Nama</th><th>No. Pinjaman</th><th>Tagihan</th></tr>
    <tr><td>1</td><td>Budi Santoso</td><td>PNJ-001</td><td>Rp 200.000</td></tr>
    <tr><td>2</td><td>Ani Wijaya</td><td>PNJ-002</td><td>Rp 150.000</td></tr>
  </table>'
];

// Get HTML and CSS
$html = $template['html_template'];
$css = $template['css_style'];

// Use default if empty
if (empty($html)) {
  $html = get_default_html($template['kode']);
}
if (empty($css)) {
  $css = get_default_css($template['kode']);
}

// Replace variables with sample data
foreach ($sample_data as $key => $value) {
  $html = str_replace('{' . $key . '}', $value, $html);
}

// Helper function for default HTML
function get_default_html($kode) {
  // Same as in template_cetak.php
  $templates = [
    'struk_angsuran' => '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Struk</title></head><body>
<div class="struk"><div class="header"><h3>{company_name}</h3><p>{company_address}</p></div>
<div class="divider">================================</div>
<table><tr><td>Tanggal</td><td>: {tanggal}</td></tr><tr><td>No. Pinjaman</td><td>: {no_pinjaman}</td></tr>
<tr><td>Nama</td><td>: {nama_anggota}</td></tr><tr><td>Angsuran ke</td><td>: {angsuran_ke}</td></tr></table>
<div class="divider">--------------------------------</div><div class="total"><strong>TOTAL: Rp {nominal}</strong></div>
<div class="divider">================================</div><div class="footer"><p>Kasir: {kasir}</p><p>Terima Kasih</p></div></div></body></html>',
  ];
  return $templates[$kode] ?? '<h1>{company_name}</h1><p>Preview template: ' . $kode . '</p>';
}

function get_default_css($kode) {
  $css = [
    'struk_angsuran' => 'body{font-family:monospace;font-size:12px;margin:0;padding:10px;width:58mm;}.struk{text-align:center;}.header h3{margin:0;font-size:14px;}.header p{margin:5px 0;font-size:10px;}.divider{margin:5px 0;}table{width:100%;text-align:left;font-size:11px;}.total{font-size:14px;margin:10px 0;}.footer{font-size:10px;margin-top:10px;}',
  ];
  return $css[$kode] ?? 'body{font-family:Arial,sans-serif;padding:20px;}';
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Preview: <?php echo htmlspecialchars($template['nama']); ?></title>
  <style>
    <?php echo $css; ?>
  </style>
  <style>
    @media screen {
      body { background: #f0f0f0; }
      .preview-container { 
        background: white; 
        max-width: 210mm; 
        margin: 20px auto; 
        padding: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      }
      .preview-header {
        background: #333;
        color: white;
        padding: 10px 20px;
        margin: -20px -20px 20px -20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
      }
      .preview-header h4 { margin: 0; }
      .preview-header button {
        background: #4f46e5;
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 5px;
        cursor: pointer;
      }
    }
    @media print {
      .preview-header { display: none !important; }
      .preview-container { box-shadow: none; margin: 0; padding: 0; max-width: none; }
    }
  </style>
</head>
<body>
  <div class="preview-container">
    <div class="preview-header">
      <h4><i class="bi bi-eye"></i> Preview: <?php echo htmlspecialchars($template['nama']); ?></h4>
      <button onclick="window.print()">🖨️ Cetak</button>
    </div>
    <?php echo $html; ?>
  </div>
</body>
</html>

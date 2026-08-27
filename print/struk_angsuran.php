<?php
// STRUK ANGSURAN — pastikan TIDAK ada karakter sebelum <?php
include_once dirname(__FILE__) . '/../lib/helpers.php';
require_login();
$conn = db();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$w  = isset($_GET['w']) ? (int)$_GET['w'] : 58;
if ($w !== 58 && $w !== 80) { $w = 58; }

$q = $conn->query("
  SELECT j.*, p.no_pinjaman, p.anggota_id, a.no_anggota, a.nama AS nama_anggota
  FROM ksp_jadwal_angsuran j
  JOIN ksp_pinjaman p ON p.id=j.pinjaman_id
  JOIN ksp_anggota a ON a.id=p.anggota_id
  WHERE j.id={$id} LIMIT 1
");
if (!$q || $q->num_rows === 0) { die('Data tidak ditemukan.'); }
$d = $q->fetch_assoc();

$kas = $conn->query("SELECT nominal, tanggal FROM ksp_kas WHERE ref_table='ksp_jadwal_angsuran' AND ref_id={$id} ORDER BY id DESC LIMIT 1");
$nominal_bayar = 0; 
$tanggal_kas = date('Y-m-d H:i:s');
if ($kas && ($r = $kas->fetch_assoc())) { 
  $nominal_bayar = (float)$r['nominal']; 
  $tanggal_kas   = $r['tanggal']; 
}

$company = setting_get('company_name','Koperasi Harian');
$addr    = setting_get('company_address','');
$phone   = setting_get('company_phone','');

$__u = user(); $__uname = 'Pengguna';
if (is_array($__u)) {
  if (isset($__u['nama']) && $__u['nama']!=='')      { $__uname = $__u['nama']; }
  elseif (isset($__u['username']) && $__u['username']!=='') { $__uname = $__u['username']; }
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Struk Angsuran</title>
<style>
@media print { @page { margin: 5mm; } body { margin: 0; } }
body { font-family: 'Courier New', monospace; font-size: 12px; }
.wrapper { width: <?php echo $w; ?>mm; margin: 0 auto; }
.hr { border-top: 1px dashed #000; margin: 6px 0; }
.center { text-align: center; }
.right { text-align: right; }
.small { font-size: 11px; }
.bold { font-weight: bold; }
table { width: 100%; border-collapse: collapse; }
</style>
</head>
<body onload="window.print()">
<div class="wrapper">
  <div class="center bold"><?php echo htmlspecialchars($company); ?></div>
  <div class="center small"><?php echo htmlspecialchars($addr); ?></div>
  <div class="center small">Telp/WA: <?php echo htmlspecialchars($phone); ?></div>
  <div class="hr"></div>
  <table>
    <tr><td>No. Pinjaman</td><td class="right"><?php echo htmlspecialchars($d['no_pinjaman']); ?></td></tr>
    <tr><td>Nama</td><td class="right"><?php echo htmlspecialchars($d['nama_anggota']); ?></td></tr>
    <tr><td>Hari ke</td><td class="right"><?php echo (int)$d['hari_ke']; ?></td></tr>
    <tr><td>Jatuh Tempo</td><td class="right"><?php echo htmlspecialchars($d['tanggal_jatuh_tempo']); ?></td></tr>
  </table>
  <div class="hr"></div>
  <table>
    <tr><td>Tagihan</td><td class="right">Rp <?php echo rupiah($d['total_tagih']); ?></td></tr>
    <tr><td>Denda</td><td class="right">Rp <?php echo rupiah($d['denda']); ?></td></tr>
    <tr><td class="bold">Bayar</td><td class="right bold">Rp <?php echo rupiah($nominal_bayar); ?></td></tr>
  </table>
  <div class="hr"></div>
  <table>
    <tr><td class="small">Kasir</td><td class="small right"><?php echo htmlspecialchars($__uname); ?></td></tr>
    <tr><td class="small">Waktu</td><td class="small right"><?php echo htmlspecialchars($tanggal_kas); ?></td></tr>
  </table>
  <div class="hr"></div>
  <div class="center small">Terima kasih</div>
</div>
</body>
</html>

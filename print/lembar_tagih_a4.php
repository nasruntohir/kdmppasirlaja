<?php
require_once __DIR__ . '/../lib/helpers.php'; require_login(); $conn = db();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$h = $conn->query("SELECT t.*, k.nama AS nama_kolektor, k.kode AS kode_kolektor FROM ksp_tagih_harian t JOIN ksp_kolektor k ON k.id=t.kolektor_id WHERE t.id=$id")->fetch_assoc();
if (!$h) { die('Tagih tidak ditemukan.'); }
$items = $conn->query("SELECT ti.*, a.nama, a.alamat, a.hp, p.no_pinjaman, j.hari_ke, j.total_tagih FROM ksp_tagih_item ti
JOIN ksp_jadwal_angsuran j ON j.id=ti.jadwal_id
JOIN ksp_pinjaman p ON p.id=j.pinjaman_id
JOIN ksp_anggota a ON a.id=ti.anggota_id
WHERE ti.tagih_id=$id ORDER BY a.nama");
$company = setting_get('company_name','Koperasi Harian'); $addr = setting_get('company_address','');
?><!doctype html><html><head><meta charset='utf-8'><title>Lembar Tagih</title>
<style>@media print{@page{size:A4;margin:12mm}} body{font-family:Arial,sans-serif;font-size:11pt} table{width:100%;border-collapse:collapse} th,td{border:1px solid #333;padding:6px 8px} .small{font-size:10pt;color:#555}</style>
</head><body onload='window.print()'>
<h2><?php echo htmlspecialchars($company); ?></h2><div class='small'><?php echo htmlspecialchars($addr); ?></div><hr>
<table><tr><td><b>Tanggal</b></td><td><?php echo htmlspecialchars($h['tanggal']); ?></td><td><b>Kolektor</b></td><td><?php echo htmlspecialchars($h['nama_kolektor'].' ('.$h['kode_kolektor'].')'); ?></td></tr></table><br>
<table><thead><tr><th>No</th><th>Anggota</th><th>Alamat</th><th>No. Pinjaman</th><th>Hari ke</th><th>Tagih</th><th>Paraf</th></tr></thead><tbody>
<?php $i=1; $sum=0; while($r=$items->fetch_assoc()): $sum+=$r['total_tagih']; ?>
<tr><td><?php echo $i++; ?></td><td><?php echo htmlspecialchars($r['nama']); ?></td><td><?php echo htmlspecialchars($r['alamat']); ?></td><td><?php echo htmlspecialchars($r['no_pinjaman']); ?></td><td><?php echo (int)$r['hari_ke']; ?></td><td style='text-align:right'>Rp <?php echo rupiah($r['total_tagih']); ?></td><td></td></tr>
<?php endwhile; ?><tr><td colspan='5' style='text-align:right'><b>Total</b></td><td style='text-align:right'><b>Rp <?php echo rupiah($sum); ?></b></td><td></td></tr>
</tbody></table></body></html>
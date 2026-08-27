<?php
require_once __DIR__ . '/../lib/helpers.php';
require_login();
$conn = db();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$no = isset($_GET['no']) ? db()->real_escape_string($_GET['no']) : '';
if ($id > 0) {
  $sql = "SELECT p.*, a.no_anggota, a.nama AS nama_anggota, a.alamat, a.hp, pr.nama AS nama_produk
          FROM ksp_pinjaman p
          JOIN ksp_anggota a ON a.id=p.anggota_id
          JOIN ksp_produk_pinjaman pr ON pr.id=p.produk_id
          WHERE p.id=$id LIMIT 1";
} else if ($no !== '') {
  $sql = "SELECT p.*, a.no_anggota, a.nama AS nama_anggota, a.alamat, a.hp, pr.nama AS nama_produk
          FROM ksp_pinjaman p
          JOIN ksp_anggota a ON a.id=p.anggota_id
          JOIN ksp_produk_pinjaman pr ON pr.id=p.produk_id
          WHERE p.no_pinjaman='$no' LIMIT 1";
} else { die('Parameter tidak valid.'); }
$q = $conn->query($sql);
if (!$q || $q->num_rows === 0) { die('Data pinjaman tidak ditemukan.'); }
$p = $q->fetch_assoc();
$total_bunga = round($p['pokok'] * ($p['bunga_persen']/100.0), 2);
$angsuran_harian = round(($p['pokok'] + $total_bunga) / max(1,$p['tenor_hari']), 2);
$company = setting_get('company_name','Koperasi Harian');
$addr = setting_get('company_address',''); $phone = setting_get('company_phone','');
$__u=user(); $__uname='Pengguna'; if (is_array($__u)) { if (isset($__u['nama']) && $__u['nama']!=='') { $__uname=$__u['nama']; } elseif (isset($__u['username']) && $__u['username']!=='') { $__uname=$__u['username']; } };
?><!doctype html>
<html><head><meta charset='utf-8'><title>Perjanjian - <?php echo htmlspecialchars($p['no_pinjaman']); ?></title>
<style>
@media print { @page { size: A4; margin: 15mm; } }
body { font-family: Arial, sans-serif; font-size: 12pt; line-height: 1.35; color:#111; }
h1 { font-size: 18pt; margin:0 0 10px 0; } h2 { font-size:14pt; margin:20px 0 8px 0; }
.table { width:100%; border-collapse: collapse; } .table th,.table td{ border:1px solid #333; padding:6px 8px; }
.small{ font-size:10pt; color:#555; } .meta td{ padding:2px 8px; } .footer-sign{ margin-top:40px; display:flex; justify-content:space-between; } .box{ width:45%; } .center{text-align:center;}
</style></head>
<body onload='window.print()'>
  <h1><?php echo htmlspecialchars($company); ?></h1>
  <div class='small'><?php echo htmlspecialchars($addr); ?> | Telp/WA: <?php echo htmlspecialchars($phone); ?></div>
  <hr><h2>Perjanjian Pinjaman</h2>
  <table class='meta'>
    <tr><td>No. Pinjaman</td><td>: <b><?php echo htmlspecialchars($p['no_pinjaman']); ?></b></td></tr>
    <tr><td>Nama Anggota</td><td>: <?php echo htmlspecialchars($p['nama_anggota']); ?> (<?php echo htmlspecialchars($p['no_anggota']); ?>)</td></tr>
    <tr><td>Alamat</td><td>: <?php echo htmlspecialchars($p['alamat']); ?></td></tr>
    <tr><td>HP/WA</td><td>: <?php echo htmlspecialchars($p['hp']); ?></td></tr>
    <tr><td>Produk</td><td>: <?php echo htmlspecialchars($p['nama_produk']); ?></td></tr>
    <tr><td>Tanggal Realisasi</td><td>: <?php echo htmlspecialchars($p['tanggal_realisasi']); ?></td></tr>
  </table>
  <h2>Rincian Pembiayaan</h2>
  <table class='table'>
    <tr><th>Pokok</th><td>Rp <?php echo rupiah($p['pokok']); ?></td></tr>
    <tr><th>Tenor (hari)</th><td><?php echo (int)$p['tenor_hari']; ?> hari</td></tr>
    <tr><th>Bunga</th><td><?php echo htmlspecialchars($p['bunga_tipe']); ?> <?php echo htmlspecialchars($p['bunga_persen']); ?>% (total: Rp <?php echo rupiah($total_bunga); ?>)</td></tr>
    <tr><th>Biaya Admin</th><td>Rp <?php echo rupiah($p['admin_biaya']); ?></td></tr>
    <tr><th>Angsuran Harian (indikatif)</th><td><b>Rp <?php echo rupiah($angsuran_harian); ?></b></td></tr>
  </table>
  <h2>Ketentuan</h2>
  <ol>
    <li>Pembayaran angsuran <b>setiap hari</b> sesuai jadwal.</li>
    <li>Keterlambatan dikenakan <b>denda harian</b> sesuai kebijakan koperasi.</li>
    <li>Perubahan jadwal/pelunasan mengikuti ketentuan koperasi.</li>
    <li>Perjanjian berlaku sejak tanggal realisasi.</li>
  </ol>
  <div class='footer-sign'>
    <div class='box center'><div>Anggota/Peminjam</div><div style='height:80px'></div><div>( <?php echo htmlspecialchars($p['nama_anggota']); ?> )</div></div>
    <div class='box center'><div>Petugas Koperasi</div><div style='height:80px'></div><div>( <?php echo htmlspecialchars($__uname); ?> )</div></div>
  </div>
</body></html>
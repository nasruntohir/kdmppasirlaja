<?php
// print/bukti_pinjaman.php - Bukti Pengajuan Pinjaman
require_once __DIR__ . '/../lib/helpers.php';
require_login();

$conn = db();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$pinjaman = $conn->query("
  SELECT p.*, a.nama, a.no_anggota, a.nik, a.hp, a.alamat, pr.nama as produk_nama
  FROM ksp_pinjaman p
  JOIN ksp_anggota a ON a.id = p.anggota_id
  JOIN ksp_produk_pinjaman pr ON pr.id = p.produk_id
  WHERE p.id = $id
")->fetch_assoc();

if (!$pinjaman) die('Data tidak ditemukan');

$company = setting_get('company_name', 'KSP Harian');
$address = setting_get('company_address', '');
$phone = setting_get('company_phone', '');

$total_pinjaman = $pinjaman['total_pinjaman'] ?: ($pinjaman['pokok'] + ($pinjaman['pokok'] * $pinjaman['bunga_persen'] / 100));
$total_bunga = $pinjaman['total_bunga'] ?: ($pinjaman['pokok'] * $pinjaman['bunga_persen'] / 100);
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Bukti Pinjaman - <?php echo $pinjaman['no_pinjaman']; ?></title>
  <style>
    @page { size: A4; margin: 15mm; }
    body { font-family: 'Times New Roman', serif; font-size: 12pt; line-height: 1.5; }
    .header { text-align: center; border-bottom: 3px double #000; padding-bottom: 10px; margin-bottom: 20px; }
    .header h1 { margin: 0; font-size: 18pt; }
    .header h2 { margin: 5px 0; font-size: 14pt; font-weight: normal; }
    .header p { margin: 2px 0; font-size: 10pt; }
    .title { text-align: center; margin: 20px 0; }
    .title h3 { margin: 0; text-decoration: underline; font-size: 14pt; }
    .title p { margin: 5px 0; font-size: 10pt; }
    table.info { width: 100%; border-collapse: collapse; margin: 15px 0; }
    table.info td { padding: 5px 10px; vertical-align: top; }
    table.info td:first-child { width: 180px; }
    table.detail { width: 100%; border-collapse: collapse; margin: 15px 0; }
    table.detail th, table.detail td { border: 1px solid #000; padding: 8px; text-align: left; }
    table.detail th { background: #f0f0f0; }
    .signatures { margin-top: 40px; }
    .signatures table { width: 100%; }
    .signatures td { text-align: center; padding: 10px; vertical-align: top; }
    .signatures .line { border-top: 1px solid #000; margin-top: 60px; display: inline-block; min-width: 150px; }
    .footer { margin-top: 30px; font-size: 9pt; text-align: center; color: #666; }
    .box { border: 1px solid #000; padding: 15px; margin: 15px 0; }
    @media print { .no-print { display: none; } }
  </style>
</head>
<body>
  <div class="no-print" style="text-align:center;margin-bottom:20px;">
    <button onclick="window.print()" style="padding:10px 30px;font-size:14px;cursor:pointer;">🖨️ Cetak</button>
    <button onclick="window.close()" style="padding:10px 30px;margin-left:10px;">Tutup</button>
  </div>

  <div class="header">
    <h1><?php echo htmlspecialchars($company); ?></h1>
    <p><?php echo htmlspecialchars($address); ?></p>
    <p>Telp: <?php echo htmlspecialchars($phone); ?></p>
  </div>

  <div class="title">
    <h3>BUKTI REALISASI PINJAMAN</h3>
    <p>No: <?php echo htmlspecialchars($pinjaman['no_pinjaman']); ?></p>
  </div>

  <p>Pada hari ini, <?php echo date('l, d F Y', strtotime($pinjaman['tanggal_realisasi'])); ?>, telah direalisasikan pinjaman dengan data sebagai berikut:</p>

  <h4 style="margin-bottom:5px;">A. Data Peminjam</h4>
  <table class="info">
    <tr><td>Nama Lengkap</td><td>: <strong><?php echo htmlspecialchars($pinjaman['nama']); ?></strong></td></tr>
    <tr><td>No. Anggota</td><td>: <?php echo htmlspecialchars($pinjaman['no_anggota']); ?></td></tr>
    <tr><td>NIK</td><td>: <?php echo htmlspecialchars($pinjaman['nik']); ?></td></tr>
    <tr><td>No. HP</td><td>: <?php echo htmlspecialchars($pinjaman['hp']); ?></td></tr>
    <tr><td>Alamat</td><td>: <?php echo htmlspecialchars($pinjaman['alamat']); ?></td></tr>
  </table>

  <h4 style="margin-bottom:5px;">B. Data Pinjaman</h4>
  <table class="detail">
    <tr><th width="200">Keterangan</th><th>Nilai</th></tr>
    <tr><td>Produk Pinjaman</td><td><?php echo htmlspecialchars($pinjaman['produk_nama']); ?></td></tr>
    <tr><td>Pokok Pinjaman</td><td><strong>Rp <?php echo number_format($pinjaman['pokok'],0,',','.'); ?></strong></td></tr>
    <tr><td>Bunga (<?php echo $pinjaman['bunga_persen']; ?>%)</td><td>Rp <?php echo number_format($total_bunga,0,',','.'); ?></td></tr>
    <tr><td>Total Pinjaman + Bunga</td><td><strong>Rp <?php echo number_format($total_pinjaman,0,',','.'); ?></strong></td></tr>
    <tr><td>Biaya Admin</td><td>Rp <?php echo number_format($pinjaman['admin_biaya'],0,',','.'); ?></td></tr>
    <?php if (isset($pinjaman['potongan_tabungan']) && $pinjaman['potongan_tabungan'] > 0): ?>
    <tr><td>Potongan Tabungan</td><td>Rp <?php echo number_format($pinjaman['potongan_tabungan'],0,',','.'); ?></td></tr>
    <?php endif; ?>
    <tr><td>Dana Diterima</td><td><strong>Rp <?php echo number_format($pinjaman['dana_diterima'] ?: ($pinjaman['pokok'] - $pinjaman['admin_biaya']),0,',','.'); ?></strong></td></tr>
    <tr><td>Tenor</td><td><?php echo $pinjaman['tenor_hari']; ?> hari</td></tr>
    <tr><td>Tipe Angsuran</td><td><?php echo ucfirst($pinjaman['tipe_angsuran'] ?? 'harian'); ?></td></tr>
    <tr><td>Angsuran per Periode</td><td>Rp <?php echo number_format($pinjaman['angsuran_per_periode'] ?: ($total_pinjaman / $pinjaman['tenor_hari']),0,',','.'); ?></td></tr>
  </table>

  <div class="box">
    <p style="margin:0;"><strong>PERNYATAAN:</strong></p>
    <p style="margin:5px 0;">Dengan ini saya menyatakan bahwa:</p>
    <ol style="margin:5px 0;padding-left:20px;">
      <li>Telah menerima dana pinjaman sesuai dengan jumlah di atas</li>
      <li>Bersedia membayar angsuran sesuai jadwal yang telah ditentukan</li>
      <li>Bersedia menerima sanksi jika terjadi keterlambatan pembayaran</li>
    </ol>
  </div>

  <div class="signatures">
    <table>
      <tr>
        <td>
          Peminjam,<br><br>
          <span class="line">(<?php echo htmlspecialchars($pinjaman['nama']); ?>)</span>
        </td>
        <td>
          Mengetahui,<br>
          Petugas<br><br>
          <span class="line">(________________)</span>
        </td>
        <td>
          Menyetujui,<br>
          Pimpinan<br><br>
          <span class="line">(________________)</span>
        </td>
      </tr>
    </table>
  </div>

  <div class="footer">
    Dokumen ini dicetak pada <?php echo date('d/m/Y H:i'); ?> dan merupakan bukti sah realisasi pinjaman.
  </div>
</body>
</html>

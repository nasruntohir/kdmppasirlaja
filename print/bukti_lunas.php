<?php
// print/bukti_lunas.php - Surat Keterangan Lunas
require_once __DIR__ . '/../lib/helpers.php';
require_login();

$conn = db();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$pinjaman = $conn->query("
  SELECT p.*, a.nama, a.no_anggota, a.nik, a.hp, a.alamat, pr.nama as produk_nama
  FROM ksp_pinjaman p
  JOIN ksp_anggota a ON a.id = p.anggota_id
  JOIN ksp_produk_pinjaman pr ON pr.id = p.produk_id
  WHERE p.id = $id AND p.status = 'lunas'
")->fetch_assoc();

if (!$pinjaman) die('Data tidak ditemukan atau pinjaman belum lunas');

// Get last payment date
$last_pay = $conn->query("SELECT MAX(tanggal_bayar) as tgl FROM ksp_jadwal_angsuran WHERE pinjaman_id = $id AND status='lunas'")->fetch_assoc();

$company = setting_get('company_name', 'KSP Harian');
$address = setting_get('company_address', '');
$phone = setting_get('company_phone', '');

$total_pinjaman = $pinjaman['total_pinjaman'] ?: ($pinjaman['pokok'] + ($pinjaman['pokok'] * $pinjaman['bunga_persen'] / 100));
$no_surat = 'SKL/' . date('Y/m') . '/' . str_pad($id, 4, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Surat Keterangan Lunas - <?php echo $pinjaman['no_pinjaman']; ?></title>
  <style>
    @page { size: A4; margin: 20mm; }
    body { font-family: 'Times New Roman', serif; font-size: 12pt; line-height: 1.6; }
    .header { text-align: center; border-bottom: 3px double #000; padding-bottom: 15px; margin-bottom: 20px; }
    .header h1 { margin: 0; font-size: 16pt; letter-spacing: 1px; }
    .header p { margin: 3px 0; font-size: 10pt; }
    .title { text-align: center; margin: 30px 0; }
    .title h2 { margin: 0; font-size: 14pt; text-decoration: underline; letter-spacing: 2px; }
    .title p { margin: 5px 0; font-size: 10pt; }
    .content { text-align: justify; margin: 20px 0; }
    .content p { margin: 15px 0; text-indent: 40px; }
    table.data { margin: 20px auto; border-collapse: collapse; }
    table.data td { padding: 5px 15px; }
    table.data td:first-child { font-weight: bold; }
    .box { border: 2px solid #000; padding: 15px; margin: 20px 0; text-align: center; background: #f9f9f9; }
    .box h3 { margin: 0; color: #006600; }
    .signatures { margin-top: 50px; }
    .signatures table { width: 100%; }
    .signatures td { text-align: center; width: 50%; vertical-align: top; }
    .signatures .line { border-bottom: 1px solid #000; display: inline-block; min-width: 180px; margin-top: 70px; }
    .footer { margin-top: 40px; font-size: 9pt; text-align: center; color: #666; border-top: 1px solid #ccc; padding-top: 10px; }
    .stamp { position: absolute; right: 100px; bottom: 200px; width: 100px; height: 100px; border: 3px solid #006600; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #006600; font-weight: bold; transform: rotate(-15deg); opacity: 0.7; }
    @media print { .no-print { display: none; } }
  </style>
</head>
<body>
  <div class="no-print" style="text-align:center;margin-bottom:20px;">
    <button onclick="window.print()" style="padding:10px 30px;font-size:14px;">🖨️ Cetak</button>
    <button onclick="window.close()" style="padding:10px 30px;margin-left:10px;">Tutup</button>
  </div>

  <div class="header">
    <h1><?php echo htmlspecialchars($company); ?></h1>
    <p><?php echo htmlspecialchars($address); ?></p>
    <p>Telp: <?php echo htmlspecialchars($phone); ?></p>
  </div>

  <div class="title">
    <h2>SURAT KETERANGAN LUNAS</h2>
    <p>Nomor: <?php echo $no_surat; ?></p>
  </div>

  <div class="content">
    <p>Yang bertanda tangan di bawah ini, Pimpinan <?php echo htmlspecialchars($company); ?>, dengan ini menerangkan bahwa:</p>

    <table class="data">
      <tr><td>Nama</td><td>: <?php echo htmlspecialchars($pinjaman['nama']); ?></td></tr>
      <tr><td>No. Anggota</td><td>: <?php echo htmlspecialchars($pinjaman['no_anggota']); ?></td></tr>
      <tr><td>NIK</td><td>: <?php echo htmlspecialchars($pinjaman['nik']); ?></td></tr>
      <tr><td>Alamat</td><td>: <?php echo htmlspecialchars($pinjaman['alamat']); ?></td></tr>
    </table>

    <p>Telah melunasi seluruh kewajiban pinjaman dengan rincian sebagai berikut:</p>

    <table class="data">
      <tr><td>No. Pinjaman</td><td>: <?php echo htmlspecialchars($pinjaman['no_pinjaman']); ?></td></tr>
      <tr><td>Tanggal Pinjam</td><td>: <?php echo date('d F Y', strtotime($pinjaman['tanggal_realisasi'])); ?></td></tr>
      <tr><td>Pokok Pinjaman</td><td>: Rp <?php echo number_format($pinjaman['pokok'],0,',','.'); ?></td></tr>
      <tr><td>Total Pinjaman</td><td>: Rp <?php echo number_format($total_pinjaman,0,',','.'); ?></td></tr>
      <tr><td>Tanggal Lunas</td><td>: <?php echo date('d F Y', strtotime($last_pay['tgl'])); ?></td></tr>
    </table>

    <div class="box">
      <h3>✓ LUNAS</h3>
      <p style="margin:5px 0;">Pinjaman telah dilunasi seluruhnya</p>
    </div>

    <p>Demikian surat keterangan ini dibuat dengan sebenarnya untuk dapat dipergunakan sebagaimana mestinya.</p>
  </div>

  <div class="signatures">
    <table>
      <tr>
        <td></td>
        <td>
          <?php echo date('d F Y'); ?><br>
          Pimpinan,<br><br>
          <span class="line"></span><br>
          (________________)
        </td>
      </tr>
    </table>
  </div>

  <div class="footer">
    Surat ini dicetak secara elektronik pada <?php echo date('d/m/Y H:i'); ?> dan sah tanpa tanda tangan basah.<br>
    Verifikasi: <?php echo md5($pinjaman['no_pinjaman'] . $pinjaman['id']); ?>
  </div>
</body>
</html>

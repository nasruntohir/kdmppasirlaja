<?php
// print/kartu_angsuran.php - Kartu Angsuran
require_once __DIR__ . '/../lib/helpers.php';
require_login();

$conn = db();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$pinjaman = $conn->query("
  SELECT p.*, a.nama, a.no_anggota, a.hp, a.alamat
  FROM ksp_pinjaman p
  JOIN ksp_anggota a ON a.id = p.anggota_id
  WHERE p.id = $id
")->fetch_assoc();

if (!$pinjaman) die('Data tidak ditemukan');

$jadwals = $conn->query("SELECT * FROM ksp_jadwal_angsuran WHERE pinjaman_id = $id ORDER BY hari_ke");

$company = setting_get('company_name', 'KSP Harian');
$total_pinjaman = $pinjaman['total_pinjaman'] ?: ($pinjaman['pokok'] + ($pinjaman['pokok'] * $pinjaman['bunga_persen'] / 100));
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Kartu Angsuran - <?php echo $pinjaman['no_pinjaman']; ?></title>
  <style>
    @page { size: A4; margin: 10mm; }
    body { font-family: Arial, sans-serif; font-size: 10pt; }
    .header { text-align: center; margin-bottom: 15px; }
    .header h2 { margin: 0; font-size: 14pt; }
    .header p { margin: 3px 0; font-size: 9pt; }
    .info { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 9pt; }
    .info-box { border: 1px solid #000; padding: 8px; width: 48%; }
    table { width: 100%; border-collapse: collapse; font-size: 9pt; }
    th, td { border: 1px solid #000; padding: 4px 6px; text-align: center; }
    th { background: #f0f0f0; }
    .status-lunas { background: #d4edda; }
    .status-belum { background: #fff3cd; }
    .footer { margin-top: 10px; font-size: 8pt; text-align: center; }
    @media print { .no-print { display: none; } }
  </style>
</head>
<body>
  <div class="no-print" style="text-align:center;margin-bottom:15px;">
    <button onclick="window.print()">🖨️ Cetak</button>
    <button onclick="window.close()">Tutup</button>
  </div>

  <div class="header">
    <h2><?php echo htmlspecialchars($company); ?></h2>
    <p><strong>KARTU ANGSURAN</strong></p>
    <p>No: <?php echo htmlspecialchars($pinjaman['no_pinjaman']); ?></p>
  </div>

  <div class="info">
    <div class="info-box">
      <strong>Data Nasabah:</strong><br>
      Nama: <?php echo htmlspecialchars($pinjaman['nama']); ?><br>
      No. Anggota: <?php echo $pinjaman['no_anggota']; ?><br>
      HP: <?php echo $pinjaman['hp']; ?>
    </div>
    <div class="info-box">
      <strong>Data Pinjaman:</strong><br>
      Pokok: Rp <?php echo number_format($pinjaman['pokok'],0,',','.'); ?><br>
      Total: Rp <?php echo number_format($total_pinjaman,0,',','.'); ?><br>
      Tenor: <?php echo $pinjaman['tenor_hari']; ?> hari
    </div>
  </div>

  <table>
    <thead>
      <tr>
        <th width="30">No</th>
        <th>Jatuh Tempo</th>
        <th>Tagihan</th>
        <th>Dibayar</th>
        <th>Tgl Bayar</th>
        <th width="50">Paraf</th>
      </tr>
    </thead>
    <tbody>
    <?php while ($j = $jadwals->fetch_assoc()): ?>
      <tr class="<?php echo $j['status'] == 'lunas' ? 'status-lunas' : 'status-belum'; ?>">
        <td><?php echo $j['hari_ke']; ?></td>
        <td><?php echo date('d/m/Y', strtotime($j['tanggal_jatuh_tempo'])); ?></td>
        <td style="text-align:right;">Rp <?php echo number_format($j['total_tagih'],0,',','.'); ?></td>
        <td style="text-align:right;"><?php echo $j['status']=='lunas' ? 'Rp '.number_format($j['bayar_nominal'],0,',','.') : '-'; ?></td>
        <td><?php echo $j['tanggal_bayar'] ? date('d/m/Y', strtotime($j['tanggal_bayar'])) : ''; ?></td>
        <td></td>
      </tr>
    <?php endwhile; ?>
    </tbody>
  </table>

  <div class="footer">
    Dicetak: <?php echo date('d/m/Y H:i'); ?> | Simpan kartu ini sebagai bukti pembayaran
  </div>
</body>
</html>

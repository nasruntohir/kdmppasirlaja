<?php
// print/riwayat_angsuran.php - Riwayat Pembayaran Angsuran
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

// Calculate totals
$total_tagih = 0;
$total_bayar = 0;
$total_denda = 0;
$items = [];
while ($j = $jadwals->fetch_assoc()) {
  $items[] = $j;
  $total_tagih += $j['total_tagih'];
  $total_bayar += $j['bayar_nominal'];
  $total_denda += $j['denda'];
}
$sisa = max(0, $total_tagih - $total_bayar);
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Riwayat Angsuran - <?php echo $pinjaman['no_pinjaman']; ?></title>
  <style>
    @page { size: A4; margin: 15mm; }
    body { font-family: Arial, sans-serif; font-size: 11pt; }
    .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 15px; }
    .header h2 { margin: 0; }
    .info { margin-bottom: 15px; }
    .info table td { padding: 3px 10px 3px 0; }
    table.data { width: 100%; border-collapse: collapse; margin: 15px 0; }
    table.data th, table.data td { border: 1px solid #000; padding: 6px 8px; }
    table.data th { background: #f0f0f0; text-align: center; }
    table.data td.right { text-align: right; }
    table.data td.center { text-align: center; }
    .lunas { background: #d4edda; }
    .belum { background: #fff3cd; }
    .summary { margin-top: 15px; }
    .summary table { width: 300px; margin-left: auto; }
    .summary td { padding: 5px; border: 1px solid #000; }
    .footer { margin-top: 20px; font-size: 9pt; text-align: center; color: #666; }
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
    <p><strong>RIWAYAT PEMBAYARAN ANGSURAN</strong></p>
  </div>

  <div class="info">
    <table>
      <tr><td><strong>No. Pinjaman</strong></td><td>: <?php echo $pinjaman['no_pinjaman']; ?></td></tr>
      <tr><td><strong>Nama Nasabah</strong></td><td>: <?php echo htmlspecialchars($pinjaman['nama']); ?></td></tr>
      <tr><td><strong>No. Anggota</strong></td><td>: <?php echo $pinjaman['no_anggota']; ?></td></tr>
      <tr><td><strong>Tanggal Pinjam</strong></td><td>: <?php echo date('d/m/Y', strtotime($pinjaman['tanggal_realisasi'])); ?></td></tr>
      <tr><td><strong>Total Pinjaman</strong></td><td>: Rp <?php echo number_format($total_pinjaman,0,',','.'); ?></td></tr>
      <tr><td><strong>Status</strong></td><td>: <strong><?php echo strtoupper($pinjaman['status']); ?></strong></td></tr>
    </table>
  </div>

  <table class="data">
    <thead>
      <tr>
        <th width="40">No</th>
        <th>Jatuh Tempo</th>
        <th>Tagihan</th>
        <th>Dibayar</th>
        <th>Denda</th>
        <th>Tanggal Bayar</th>
        <th width="60">Status</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($items as $j): ?>
      <tr class="<?php echo $j['status']; ?>">
        <td class="center"><?php echo $j['hari_ke']; ?></td>
        <td class="center"><?php echo date('d/m/Y', strtotime($j['tanggal_jatuh_tempo'])); ?></td>
        <td class="right">Rp <?php echo number_format($j['total_tagih'],0,',','.'); ?></td>
        <td class="right"><?php echo $j['bayar_nominal'] > 0 ? 'Rp '.number_format($j['bayar_nominal'],0,',','.') : '-'; ?></td>
        <td class="right"><?php echo $j['denda'] > 0 ? 'Rp '.number_format($j['denda'],0,',','.') : '-'; ?></td>
        <td class="center"><?php echo $j['tanggal_bayar'] ? date('d/m/Y', strtotime($j['tanggal_bayar'])) : '-'; ?></td>
        <td class="center"><?php echo strtoupper($j['status']); ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <div class="summary">
    <table>
      <tr><td><strong>Total Tagihan</strong></td><td class="right">Rp <?php echo number_format($total_tagih,0,',','.'); ?></td></tr>
      <tr><td><strong>Total Dibayar</strong></td><td class="right">Rp <?php echo number_format($total_bayar,0,',','.'); ?></td></tr>
      <tr><td><strong>Total Denda</strong></td><td class="right">Rp <?php echo number_format($total_denda,0,',','.'); ?></td></tr>
      <tr style="background:#f0f0f0;"><td><strong>SISA</strong></td><td class="right"><strong>Rp <?php echo number_format($sisa,0,',','.'); ?></strong></td></tr>
    </table>
  </div>

  <div class="footer">
    Dicetak pada <?php echo date('d/m/Y H:i'); ?> | Dokumen ini merupakan bukti riwayat pembayaran yang sah
  </div>
</body>
</html>

<?php
// print/buku_tabungan.php - Cetak Buku Tabungan
require_once __DIR__ . '/../lib/helpers.php';
require_login();

$conn = db();
$anggota_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$bulan = isset($_GET['bulan']) ? $_GET['bulan'] : date('Y-m');

if (!$anggota_id) die('ID anggota tidak valid');

$anggota = $conn->query("
  SELECT a.*, 
    COALESCE(s.simpanan_pokok, 0) as simpanan_pokok,
    COALESCE(s.simpanan_wajib, 0) as simpanan_wajib,
    COALESCE(s.simpanan_sukarela, 0) as simpanan_sukarela,
    COALESCE(s.total_simpanan, 0) as total_simpanan
  FROM ksp_anggota a
  LEFT JOIN ksp_simpanan_anggota s ON s.anggota_id = a.id
  WHERE a.id = $anggota_id
")->fetch_assoc();

if (!$anggota) die('Data anggota tidak ditemukan');

$start_date = $bulan . '-01';
$end_date = date('Y-m-t', strtotime($start_date));

$mutasi = $conn->query("
  SELECT * FROM ksp_mutasi_simpanan 
  WHERE anggota_id = $anggota_id 
  AND tanggal BETWEEN '$start_date' AND '$end_date'
  ORDER BY tanggal, id
");

// Get opening balance
$saldo_awal = $conn->query("
  SELECT SUM(CASE WHEN tipe = 'setor' THEN nominal ELSE -nominal END) as saldo
  FROM ksp_mutasi_simpanan 
  WHERE anggota_id = $anggota_id 
  AND tanggal < '$start_date'
")->fetch_assoc();

$saldo = (float)($saldo_awal['saldo'] ?: 0);

$company = setting_get('company_name', 'KSP Harian');
$address = setting_get('company_address', '');
$phone = setting_get('company_phone', '');
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Buku Tabungan - <?php echo $anggota['nama']; ?></title>
  <style>
    @page { size: A4; margin: 15mm; }
    body { font-family: Arial, sans-serif; font-size: 11pt; }
    .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 15px; }
    .header h2 { margin: 0; }
    .header p { margin: 3px 0; font-size: 10pt; }
    .info { margin-bottom: 15px; }
    .info table td { padding: 3px 10px 3px 0; }
    .saldo-box { display: flex; gap: 10px; margin-bottom: 15px; }
    .saldo-item { flex: 1; border: 1px solid #000; padding: 8px; text-align: center; }
    .saldo-item small { display: block; font-size: 9pt; }
    .saldo-item strong { font-size: 12pt; }
    table.mutasi { width: 100%; border-collapse: collapse; }
    table.mutasi th, table.mutasi td { border: 1px solid #000; padding: 6px 8px; }
    table.mutasi th { background: #f0f0f0; text-align: center; font-size: 10pt; }
    table.mutasi td.right { text-align: right; }
    table.mutasi td.center { text-align: center; }
    .footer { margin-top: 15px; font-size: 9pt; text-align: center; color: #666; }
    @media print { .no-print { display: none; } }
  </style>
</head>
<body>
  <div class="no-print" style="text-align:center;margin-bottom:15px;">
    <button onclick="window.print()" style="padding:10px 30px;">🖨️ Cetak</button>
    <button onclick="window.close()" style="padding:10px 30px;margin-left:10px;">Tutup</button>
  </div>

  <div class="header">
    <h2><?php echo htmlspecialchars($company); ?></h2>
    <p><?php echo htmlspecialchars($address); ?></p>
    <p>Telp: <?php echo htmlspecialchars($phone); ?></p>
    <h3 style="margin-top:10px;">BUKU TABUNGAN</h3>
  </div>

  <div class="info">
    <table>
      <tr><td width="120"><strong>No. Rekening</strong></td><td>: <?php echo $anggota['no_anggota']; ?></td></tr>
      <tr><td><strong>Nama</strong></td><td>: <?php echo htmlspecialchars($anggota['nama']); ?></td></tr>
      <tr><td><strong>Alamat</strong></td><td>: <?php echo htmlspecialchars($anggota['alamat']); ?></td></tr>
      <tr><td><strong>No. HP</strong></td><td>: <?php echo htmlspecialchars($anggota['hp']); ?></td></tr>
    </table>
  </div>

  <div class="saldo-box">
    <div class="saldo-item">
      <small>Simpanan Pokok</small>
      <strong>Rp <?php echo number_format($anggota['simpanan_pokok'], 0, ',', '.'); ?></strong>
    </div>
    <div class="saldo-item">
      <small>Simpanan Wajib</small>
      <strong>Rp <?php echo number_format($anggota['simpanan_wajib'], 0, ',', '.'); ?></strong>
    </div>
    <div class="saldo-item">
      <small>Simpanan Sukarela</small>
      <strong>Rp <?php echo number_format($anggota['simpanan_sukarela'], 0, ',', '.'); ?></strong>
    </div>
  </div>

  <p><strong>Periode:</strong> <?php echo date('F Y', strtotime($bulan . '-01')); ?></p>

  <table class="mutasi">
    <thead>
      <tr>
        <th width="80">Tanggal</th>
        <th>Keterangan</th>
        <th width="60">Jenis</th>
        <th width="110">Debet</th>
        <th width="110">Kredit</th>
        <th width="120">Saldo</th>
      </tr>
    </thead>
    <tbody>
      <tr style="background:#f9f9f9;">
        <td class="center"><?php echo date('01/m/Y', strtotime($bulan . '-01')); ?></td>
        <td><em>Saldo Awal</em></td>
        <td></td>
        <td></td>
        <td></td>
        <td class="right"><strong>Rp <?php echo number_format($saldo, 0, ',', '.'); ?></strong></td>
      </tr>
      <?php 
      $running_saldo = $saldo;
      if ($mutasi && $mutasi->num_rows > 0): 
        while ($m = $mutasi->fetch_assoc()):
          if ($m['tipe'] == 'setor') {
            $running_saldo += $m['nominal'];
          } else {
            $running_saldo -= $m['nominal'];
          }
      ?>
      <tr>
        <td class="center"><?php echo date('d/m/Y', strtotime($m['tanggal'])); ?></td>
        <td>
          <?php echo ucfirst($m['jenis_simpanan']); ?>
          <?php if ($m['keterangan']): ?> - <?php echo htmlspecialchars($m['keterangan']); ?><?php endif; ?>
        </td>
        <td class="center"><?php echo strtoupper(substr($m['jenis_simpanan'], 0, 1)); ?></td>
        <td class="right" style="color:#c00;">
          <?php echo $m['tipe'] == 'tarik' ? 'Rp ' . number_format($m['nominal'], 0, ',', '.') : ''; ?>
        </td>
        <td class="right" style="color:#080;">
          <?php echo $m['tipe'] == 'setor' ? 'Rp ' . number_format($m['nominal'], 0, ',', '.') : ''; ?>
        </td>
        <td class="right"><strong>Rp <?php echo number_format($running_saldo, 0, ',', '.'); ?></strong></td>
      </tr>
      <?php 
        endwhile;
      endif; 
      ?>
      <tr style="background:#fffde7;">
        <td colspan="5" style="text-align:right;"><strong>SALDO AKHIR</strong></td>
        <td class="right"><strong style="font-size:12pt;">Rp <?php echo number_format($running_saldo, 0, ',', '.'); ?></strong></td>
      </tr>
    </tbody>
  </table>

  <div class="footer">
    Dicetak pada <?php echo date('d/m/Y H:i'); ?> | Dokumen ini sah dan dapat dipertanggungjawabkan
  </div>
</body>
</html>

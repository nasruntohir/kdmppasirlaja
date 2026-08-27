<?php
// print/kwitansi.php - Cetak Kwitansi/Bukti Bayar
require_once __DIR__ . '/../lib/helpers.php';
require_login();

$conn = db();
$jadwal_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$jadwal_id) {
  die('ID tidak valid');
}

// Get payment data
$sql = "SELECT j.*, p.no_pinjaman, p.pokok, p.tenor_hari, a.nama as anggota, a.no_anggota, a.alamat, a.hp
        FROM ksp_jadwal_angsuran j
        JOIN ksp_pinjaman p ON p.id = j.pinjaman_id
        JOIN ksp_anggota a ON a.id = p.anggota_id
        WHERE j.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $jadwal_id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();

if (!$data) {
  die('Data tidak ditemukan');
}

$company = setting_get('company_name', 'KSP Harian');
$address = setting_get('company_address', '');
$phone = setting_get('company_phone', '');
$no_kwitansi = 'KW-' . date('Ymd') . '-' . str_pad($jadwal_id, 5, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Kwitansi #<?php echo $no_kwitansi; ?></title>
  <style>
    @page { size: A5 landscape; margin: 10mm; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 11px; padding: 15px; }
    .container { max-width: 600px; margin: 0 auto; border: 2px solid #333; padding: 20px; }
    .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 15px; }
    .header h1 { font-size: 18px; margin-bottom: 3px; }
    .header .subtitle { font-size: 10px; color: #666; }
    .kwitansi-no { text-align: right; margin-bottom: 15px; }
    .kwitansi-no span { background: #f0f0f0; padding: 5px 10px; font-weight: bold; }
    .content { margin-bottom: 15px; }
    .row { display: flex; margin-bottom: 8px; }
    .label { width: 140px; font-weight: bold; }
    .value { flex: 1; border-bottom: 1px dotted #999; padding-left: 5px; }
    .amount-box { background: linear-gradient(135deg, #2563eb, #06b6d4); color: white; padding: 15px; text-align: center; margin: 15px 0; border-radius: 8px; }
    .amount-box .title { font-size: 10px; opacity: 0.9; }
    .amount-box .amount { font-size: 24px; font-weight: bold; }
    .terbilang { font-style: italic; font-size: 10px; text-align: center; margin-bottom: 15px; background: #f9f9f9; padding: 8px; border-radius: 4px; }
    .signatures { display: flex; justify-content: space-between; margin-top: 30px; }
    .sig-box { text-align: center; width: 45%; }
    .sig-box .line { border-top: 1px solid #333; margin-top: 50px; padding-top: 5px; }
    .footer { text-align: center; margin-top: 20px; font-size: 9px; color: #666; border-top: 1px dashed #ccc; padding-top: 10px; }
    .watermark { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-30deg); font-size: 60px; color: rgba(0,128,0,0.1); font-weight: bold; pointer-events: none; }
    @media print {
      .no-print { display: none; }
      body { padding: 0; }
    }
  </style>
</head>
<body>
  <div class="no-print" style="text-align:center;margin-bottom:15px;">
    <button onclick="window.print()" style="padding:10px 30px;font-size:14px;cursor:pointer;background:#2563eb;color:white;border:none;border-radius:5px;">
      🖨️ Cetak Kwitansi
    </button>
    <button onclick="window.close()" style="padding:10px 30px;font-size:14px;cursor:pointer;margin-left:10px;">Tutup</button>
  </div>

  <div class="container">
    <?php if ($data['status'] == 'lunas'): ?>
    <div class="watermark">LUNAS</div>
    <?php endif; ?>
    
    <div class="header">
      <h1><?php echo htmlspecialchars($company); ?></h1>
      <div class="subtitle"><?php echo htmlspecialchars($address); ?> | <?php echo htmlspecialchars($phone); ?></div>
    </div>
    
    <div class="kwitansi-no">
      <span>No: <?php echo $no_kwitansi; ?></span>
    </div>
    
    <div style="text-align:center;margin-bottom:15px;">
      <strong style="font-size:14px;text-transform:uppercase;letter-spacing:2px;">KWITANSI PEMBAYARAN ANGSURAN</strong>
    </div>
    
    <div class="content">
      <div class="row">
        <div class="label">Telah diterima dari</div>
        <div class="value"><?php echo htmlspecialchars($data['anggota']); ?> (<?php echo $data['no_anggota']; ?>)</div>
      </div>
      <div class="row">
        <div class="label">Alamat</div>
        <div class="value"><?php echo htmlspecialchars($data['alamat']); ?></div>
      </div>
      <div class="row">
        <div class="label">No. Pinjaman</div>
        <div class="value"><?php echo htmlspecialchars($data['no_pinjaman']); ?></div>
      </div>
      <div class="row">
        <div class="label">Angsuran ke</div>
        <div class="value"><?php echo $data['hari_ke']; ?> dari <?php echo $data['tenor_hari']; ?></div>
      </div>
      <div class="row">
        <div class="label">Tanggal Bayar</div>
        <div class="value"><?php echo $data['tanggal_bayar'] ?: date('Y-m-d'); ?></div>
      </div>
    </div>
    
    <div class="amount-box">
      <div class="title">JUMLAH PEMBAYARAN</div>
      <div class="amount">Rp <?php echo number_format($data['bayar_nominal'] + $data['denda'], 0, ',', '.'); ?></div>
      <?php if ($data['denda'] > 0): ?>
      <div class="title" style="margin-top:5px;">(Termasuk denda Rp <?php echo number_format($data['denda'], 0, ',', '.'); ?>)</div>
      <?php endif; ?>
    </div>
    
    <div class="terbilang">
      Terbilang: <strong><?php echo terbilang($data['bayar_nominal'] + $data['denda']); ?> rupiah</strong>
    </div>
    
    <div class="signatures">
      <div class="sig-box">
        <div>Penerima,</div>
        <div class="line">(________________)</div>
      </div>
      <div class="sig-box">
        <div>Pembayar,</div>
        <div class="line">(<?php echo htmlspecialchars($data['anggota']); ?>)</div>
      </div>
    </div>
    
    <div class="footer">
      Dicetak pada: <?php echo date('d/m/Y H:i:s'); ?> | Simpan kwitansi ini sebagai bukti pembayaran yang sah
    </div>
  </div>
</body>
</html>
<?php
// Helper function terbilang
function terbilang($n) {
  $n = abs($n);
  $huruf = array('', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas');
  $temp = '';
  if ($n < 12) {
    $temp = ' ' . $huruf[$n];
  } elseif ($n < 20) {
    $temp = terbilang($n - 10) . ' belas';
  } elseif ($n < 100) {
    $temp = terbilang($n / 10) . ' puluh' . terbilang($n % 10);
  } elseif ($n < 200) {
    $temp = ' seratus' . terbilang($n - 100);
  } elseif ($n < 1000) {
    $temp = terbilang($n / 100) . ' ratus' . terbilang($n % 100);
  } elseif ($n < 2000) {
    $temp = ' seribu' . terbilang($n - 1000);
  } elseif ($n < 1000000) {
    $temp = terbilang($n / 1000) . ' ribu' . terbilang($n % 1000);
  } elseif ($n < 1000000000) {
    $temp = terbilang($n / 1000000) . ' juta' . terbilang($n % 1000000);
  } elseif ($n < 1000000000000) {
    $temp = terbilang($n / 1000000000) . ' milyar' . terbilang(fmod($n, 1000000000));
  }
  return $temp;
}
?>

<?php

require_once __DIR__ . '/../lib/helpers.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['member_id'])) {
    header('LOCATION: index.php');
    exit;
}

$conn = db();
$member_id = (int) $_SESSION['member_id'];
$member = $conn->query("SELECT id, no_anggota, nama FROM ksp_anggota WHERE id = $member_id")->fetch_assoc();
$history_sql = "(
SELECT st.id AS id,
  st.tanggal AS tanggal,
  CONCAT('Simpanan ', ps.nama, ' ', IF(st.tipe = 'setor', 'Setor', 'Tarik')) AS judul,
  st.nominal AS nominal,
  st.tipe AS aksi,
  COALESCE(st.catatan, '') AS catatan,
  'simpanan' AS sumber
FROM ksp_simpanan_tx st
JOIN ksp_produk_simpanan ps ON ps.id = st.produk_id
WHERE st.anggota_id = $member_id
)
UNION ALL
(
SELECT j.id + 1000000 AS id,
  j.tanggal_bayar AS tanggal,
  CONCAT('Pembayaran Angsuran ', p.no_pinjaman, ' ke-', j.hari_ke) AS judul,
  j.bayar_nominal + j.denda AS nominal,
  'setor' AS aksi,
  CONCAT('Denda: ', FORMAT(j.denda, 0)) AS catatan,
  'angsuran' AS sumber
FROM ksp_jadwal_angsuran j
JOIN ksp_pinjaman p ON p.id = j.pinjaman_id
WHERE p.anggota_id = $member_id AND j.status = 'lunas'
)
UNION ALL
(
SELECT p.id + 2000000 AS id,
  p.tanggal_realisasi AS tanggal,
  CONCAT('Pencairan Pinjaman ', p.no_pinjaman) AS judul,
  p.dana_diterima AS nominal,
  'masuk' AS aksi,
  CONCAT('Pokok: ', FORMAT(p.pokok, 0), ' · Total: ', FORMAT(p.total_pinjaman, 0)) AS catatan,
  'pinjaman' AS sumber
FROM ksp_pinjaman p
WHERE p.anggota_id = $member_id
)
UNION ALL
(
SELECT p.id + 3000000 AS id,
  p.tanggal AS tanggal,
  CONCAT('Pembelian ', p.no_penjualan) AS judul,
  p.jumlah_bayar AS nominal,
  'tarik' AS aksi,
  COALESCE(p.keterangan, '') AS catatan,
  'penjualan' AS sumber
FROM ksp_penjualan p
WHERE p.customer_tipe = 'anggota' AND p.anggota_id = $member_id AND p.status = 'selesai'
)
ORDER BY tanggal DESC
LIMIT 100";

$history = $conn->query($history_sql);
$company = setting_get('company_name', 'KSP Harian');
$page = 'riwayat';
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title>Riwayat Transaksi - <?php echo htmlspecialchars($company); ?> </title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="assets/mobile.css" rel="stylesheet">
</head>

<body>
    <div class="app-bar app-bar-simple">
        <div class="app-bar-row">
            <a href="dashboard.php" style="color:white;text-decoration:none;font-size:24px;"><i
                    class="bi bi-arrow-left"></i></a>
            <h1 style="flex:1;text-align:center;margin:0;">Riwayat</h1>
            <div style="width: 24px;"></div>
        </div>
    </div>

    <div style="padding:20px; padding-top: 16px;">
        <div class="section-header"></div>
        <h3><i class="bi bi-clock-history me-2"></i> Riwayat Transaksi</h3>
        <p class="text-sm text-gray">Semua transaksi anggota: simpan, pinjaman dan pembelian.</p>
    </div>
    <?php if ($history->num_rows > 0): ?>
        <?php while ($row = $history->fetch_assoc()): ?>
            <div class="list-item">
                <div class="icon <?php echo in_array($row['aksi'], ['setor', 'masuk']) ? 'bg-success' : 'bg-danger'; ?>">
                    <i class="bi bi-card-checklist" style="color:white;"></i>
                </div>
                <div class="content">
                    <div class="title"><?php echo htmlspecialchars($row['judul']); ?></div>
                    <div class="subtitle"><?php echo date('d M Y H:i', strtotime($row['tanggal'])); ?></div>
                    <?php if (!empty($row['catatan'])): ?>
                        <div class="text-sm text-gray"><?php echo htmlspecialchars($row['catatan']); ?></div>
                    <?php endif; ?>
                </div>
                <div style="text-align: right;">
                    <div
                        class="amount <?php echo in_array($row['aksi'], ['setor', 'masuk']) ? 'text-succes' : 'text-danger'; ?>">
                        <?php echo in_array($row['aksi'], ['setor', 'masuk']) ? '+' : '-'; ?>Rp
                        <?php echo number_format($row['nominal'], 0, ',', '.'); ?>
                    </div>
                    <div class="text-sm text-gray">Type: <?php echo htmlspecialchars(ucfirst($row['sumber'])); ?></div>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="empty-state" style="padding: 40px 0; text-align: center; color: #6b7280;">
            <i class="bi bi-inbox" style="font-size: 40px;"></i>
            <h4>Belum ada riwayat</h4>
            <p>Transaksi anggota belum ditemukan</p>
        </div>
    <?php endif; ?>
    </div>

    <div class="bottom-nav">
        <a href="dashboard.php" class="nav-item">
            <i class="bi bi-house"></i>
            <span>Home</span>
        </a>
        <a href="status_pengajuan.php" class="nav-item">
            <i class="bi bi-clock-history"></i>
            <span>Status</span>
        </a>
        <a href="pengajuan.php" class="nav-item center-btn">
            <div class="icon-wrap"><i class="bi bi-plus-lg"></i></div>
            <span>Ajukan</span>
        </a>
        <a href="simpanan.php" class="nav-item">
            <i class="bi bi-wallet2"></i>
            <span>Simpanan</span>
        </a>
        <a href="profil.php" class="nav-item">
            <i class="bi bi-person"></i>
            <span>Profil</span>
        </a>
    </div>

</body>

</html>
<?php
// LAPORAN — Premium Report Center
require_login();
$conn = db();

$from = isset($_GET['from']) ? $_GET['from'] : date('Y-m-01');
$to   = isset($_GET['to'])   ? $_GET['to']   : date('Y-m-d');
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'ringkasan';

function a($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

// Summary stats for ringkasan
$stats = array();
if ($mode == 'ringkasan') {
  $stats['pinjaman_baru'] = (int)$conn->query("SELECT COUNT(*) c FROM ksp_pinjaman WHERE tanggal_realisasi BETWEEN '$from' AND '$to'")->fetch_assoc()['c'];
  $stats['nominal_pinjaman'] = (float)$conn->query("SELECT IFNULL(SUM(pokok),0) s FROM ksp_pinjaman WHERE tanggal_realisasi BETWEEN '$from' AND '$to'")->fetch_assoc()['s'];
  $stats['angsuran_masuk'] = (float)$conn->query("SELECT IFNULL(SUM(bayar_nominal),0) s FROM ksp_jadwal_angsuran WHERE tanggal_bayar BETWEEN '$from' AND '$to' AND status='lunas'")->fetch_assoc()['s'];
  $stats['kas_masuk'] = (float)$conn->query("SELECT IFNULL(SUM(nominal),0) s FROM ksp_kas WHERE jenis='masuk' AND tanggal BETWEEN '$from' AND '$to'")->fetch_assoc()['s'];
  $stats['kas_keluar'] = (float)$conn->query("SELECT IFNULL(SUM(nominal),0) s FROM ksp_kas WHERE jenis='keluar' AND tanggal BETWEEN '$from' AND '$to'")->fetch_assoc()['s'];
  $stats['anggota_baru'] = (int)$conn->query("SELECT COUNT(*) c FROM ksp_anggota WHERE DATE(created_at) BETWEEN '$from' AND '$to'")->fetch_assoc()['c'];
  $stats['pinjaman_lunas'] = (int)$conn->query("SELECT COUNT(*) c FROM ksp_pinjaman WHERE status='lunas' AND EXISTS(SELECT 1 FROM ksp_jadwal_angsuran j WHERE j.pinjaman_id=ksp_pinjaman.id AND j.tanggal_bayar BETWEEN '$from' AND '$to')")->fetch_assoc()['c'];
}
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4><i class="bi bi-bar-chart-line me-2"></i>Pusat Laporan</h4>
</div>

<!-- Quick Export Buttons -->
<div class="row g-3 mb-3">
  <div class="col-md-3">
    <a href="export/export_anggota.php" class="btn btn-outline-success w-100">
      <i class="bi bi-file-earmark-excel me-2"></i>Export Anggota
    </a>
  </div>
  <div class="col-md-3">
    <a href="export/export_pinjaman.php?from=<?php echo $from; ?>&to=<?php echo $to; ?>" class="btn btn-outline-success w-100">
      <i class="bi bi-file-earmark-excel me-2"></i>Export Pinjaman
    </a>
  </div>
  <div class="col-md-3">
    <a href="export/export_kas.php?from=<?php echo $from; ?>&to=<?php echo $to; ?>" class="btn btn-outline-success w-100">
      <i class="bi bi-file-earmark-excel me-2"></i>Export Arus Kas
    </a>
  </div>
  <div class="col-md-3">
    <a href="export/export_aging.php" class="btn btn-outline-warning w-100">
      <i class="bi bi-file-earmark-excel me-2"></i>Export Tunggakan
    </a>
  </div>
</div>

<div class="card card-widget mb-3">
  <div class="card-body">
    <form class="row g-2">
      <input type="hidden" name="m" value="laporan">
      <div class="col-md-2"><label class="form-label">Dari</label><input type="date" name="from" class="form-control" value="<?php echo a($from); ?>"></div>
      <div class="col-md-2"><label class="form-label">Sampai</label><input type="date" name="to" class="form-control" value="<?php echo a($to); ?>"></div>
      <div class="col-md-3">
        <label class="form-label">Jenis Laporan</label>
        <select name="mode" class="form-select">
          <option value="ringkasan" <?php echo $mode=='ringkasan'?'selected':''; ?>>📊 Ringkasan Eksekutif</option>
          <option value="angsuran" <?php echo $mode=='angsuran'?'selected':''; ?>>💰 Angsuran Harian</option>
          <option value="pinjaman" <?php echo $mode=='pinjaman'?'selected':''; ?>>📝 Realisasi Pinjaman</option>
          <option value="aging"    <?php echo $mode=='aging'   ?'selected':''; ?>>⚠️ Aging Tunggakan</option>
          <option value="kolektor" <?php echo $mode=='kolektor'?'selected':''; ?>>👤 Performa Kolektor</option>
          <option value="kas"      <?php echo $mode=='kas'     ?'selected':''; ?>>💵 Arus Kas</option>
        </select>
      </div>
      <div class="col-md-2 align-self-end"><button class="btn btn-gradient w-100"><i class="bi bi-search me-1"></i>Tampilkan</button></div>
      <div class="col-md-3 align-self-end">
        <div class="btn-group w-100">
          <a target="_blank" class="btn btn-outline-primary" href="print/laporan_<?php echo $mode; ?>_a4.php?from=<?php echo a($from); ?>&to=<?php echo a($to); ?>"><i class="bi bi-printer me-1"></i>Print</a>
          <a class="btn btn-outline-success" href="export/export_<?php echo $mode; ?>.php?from=<?php echo a($from); ?>&to=<?php echo a($to); ?>"><i class="bi bi-file-excel me-1"></i>Excel</a>
        </div>
      </div>
    </form>
  </div>
</div>

<div class="card card-widget">
  <div class="card-body">
    <?php if ($mode=='ringkasan'): ?>
      <!-- Executive Summary -->
      <h5 class="mb-3"><i class="bi bi-graph-up me-2"></i>Ringkasan Eksekutif: <?php echo date('d M Y', strtotime($from)); ?> - <?php echo date('d M Y', strtotime($to)); ?></h5>
      <div class="row g-3 mb-4">
        <div class="col-md-3">
          <div class="border rounded p-3 text-center bg-primary bg-opacity-10">
            <div class="text-primary fw-bold fs-4"><?php echo $stats['pinjaman_baru']; ?></div>
            <div class="small text-muted">Pinjaman Baru</div>
            <div class="text-primary small">Rp <?php echo rupiah($stats['nominal_pinjaman']); ?></div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="border rounded p-3 text-center bg-success bg-opacity-10">
            <div class="text-success fw-bold fs-4">Rp <?php echo rupiah($stats['angsuran_masuk']); ?></div>
            <div class="small text-muted">Angsuran Masuk</div>
            <div class="text-success small"><?php echo $stats['pinjaman_lunas']; ?> pinjaman lunas</div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="border rounded p-3 text-center bg-info bg-opacity-10">
            <div class="text-info fw-bold fs-4">Rp <?php echo rupiah($stats['kas_masuk']); ?></div>
            <div class="small text-muted">Total Kas Masuk</div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="border rounded p-3 text-center bg-warning bg-opacity-10">
            <div class="text-warning fw-bold fs-4">Rp <?php echo rupiah($stats['kas_keluar']); ?></div>
            <div class="small text-muted">Total Kas Keluar</div>
          </div>
        </div>
      </div>
      <div class="row g-3">
        <div class="col-md-4">
          <div class="border rounded p-3">
            <h6><i class="bi bi-wallet2 me-2 text-success"></i>Saldo Kas Periode</h6>
            <div class="fs-4 fw-bold <?php echo ($stats['kas_masuk'] - $stats['kas_keluar']) >= 0 ? 'text-success' : 'text-danger'; ?>">
              Rp <?php echo rupiah($stats['kas_masuk'] - $stats['kas_keluar']); ?>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="border rounded p-3">
            <h6><i class="bi bi-people me-2 text-primary"></i>Anggota Baru</h6>
            <div class="fs-4 fw-bold text-primary"><?php echo $stats['anggota_baru']; ?> orang</div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="border rounded p-3">
            <h6><i class="bi bi-check-circle me-2 text-success"></i>Pinjaman Lunas</h6>
            <div class="fs-4 fw-bold text-success"><?php echo $stats['pinjaman_lunas']; ?> pinjaman</div>
          </div>
        </div>
      </div>

    <?php elseif ($mode=='angsuran'): ?>
      <div class="table-responsive">
        <table class="table table-striped table-sm">
          <thead><tr><th>Tanggal Bayar</th><th>No. Pinjaman</th><th>Hari ke</th><th>Bayar</th><th>Denda</th></tr></thead>
          <tbody>
            <?php
            $q = $conn->query("SELECT j.tanggal_bayar, p.no_pinjaman, j.hari_ke, j.bayar_nominal, j.denda
             FROM ksp_jadwal_angsuran j JOIN ksp_pinjaman p ON p.id=j.pinjaman_id
             WHERE j.status='lunas' AND j.tanggal_bayar BETWEEN '".$conn->real_escape_string($from)."' AND '".$conn->real_escape_string($to)."' ORDER BY j.tanggal_bayar");
            while($r=$q->fetch_assoc()): ?>
              <tr>
                <td><?php echo a($r['tanggal_bayar']); ?></td>
                <td><?php echo a($r['no_pinjaman']); ?></td>
                <td><?php echo (int)$r['hari_ke']; ?></td>
                <td>Rp <?php echo rupiah($r['bayar_nominal']); ?></td>
                <td>Rp <?php echo rupiah($r['denda']); ?></td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>

    <?php elseif ($mode=='aging'): ?>
      <div class="table-responsive">
        <table class="table table-striped table-sm">
          <thead><tr><th>No. Pinjaman</th><th>Anggota</th><th>JT</th><th>Hari Telat</th><th>Tagih</th></tr></thead>
          <tbody>
            <?php
            $q = $conn->query("SELECT p.no_pinjaman, a.nama, j.tanggal_jatuh_tempo, DATEDIFF(CURDATE(), j.tanggal_jatuh_tempo) telat, j.total_tagih
             FROM ksp_jadwal_angsuran j JOIN ksp_pinjaman p ON p.id=j.pinjaman_id JOIN ksp_anggota a ON a.id=p.anggota_id
             WHERE j.status='belum' AND j.tanggal_jatuh_tempo < CURDATE() ORDER BY j.tanggal_jatuh_tempo");
            while($r=$q->fetch_assoc()): ?>
              <tr>
                <td><?php echo a($r['no_pinjaman']); ?></td>
                <td><?php echo a($r['nama']); ?></td>
                <td><?php echo a($r['tanggal_jatuh_tempo']); ?></td>
                <td><?php echo (int)$r['telat']; ?></td>
                <td>Rp <?php echo rupiah($r['total_tagih']); ?></td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>

    <?php else: // kolektor ?>
      <div class="table-responsive">
        <table class="table table-striped table-sm">
          <thead><tr><th>Kolektor</th><th>Tanggal</th><th>Jumlah Item</th><th>Total Tagih</th><th>Setoran Kas</th></tr></thead>
          <tbody>
            <?php
            $q = $conn->query("SELECT k.nama, t.tanggal, COUNT(ti.id) jml, IFNULL(SUM(j.total_tagih),0) tagih,
              (SELECT IFNULL(SUM(nominal),0) FROM ksp_kas WHERE ref_table='ksp_tagih_harian' AND ref_id=t.id) setoran
              FROM ksp_tagih_harian t
              JOIN ksp_kolektor k ON k.id=t.kolektor_id
              LEFT JOIN ksp_tagih_item ti ON ti.tagih_id=t.id
              LEFT JOIN ksp_jadwal_angsuran j ON j.id=ti.jadwal_id
              WHERE t.tanggal BETWEEN '".$conn->real_escape_string($from)."' AND '".$conn->real_escape_string($to)."'
              GROUP BY t.id ORDER BY t.tanggal DESC");
            while($r=$q->fetch_assoc()): ?>
              <tr>
                <td><?php echo a($r['nama']); ?></td>
                <td><?php echo a($r['tanggal']); ?></td>
                <td><?php echo (int)$r['jml']; ?></td>
                <td>Rp <?php echo rupiah($r['tagih']); ?></td>
                <td>Rp <?php echo rupiah($r['setoran']); ?></td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

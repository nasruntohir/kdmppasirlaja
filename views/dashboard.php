<?php
// views/dashboard.php — Premium Analytics Dashboard
require_login();
$conn = db();

/* ===== KPI ===== */
$total_anggota       = (int)$conn->query("SELECT COUNT(*) c FROM ksp_anggota WHERE status='aktif'")->fetch_assoc()['c'];
$pinjaman_aktif      = (int)$conn->query("SELECT COUNT(*) c FROM ksp_pinjaman WHERE status='aktif'")->fetch_assoc()['c'];
$pinjaman_lunas      = (int)$conn->query("SELECT COUNT(*) c FROM ksp_pinjaman WHERE status='lunas'")->fetch_assoc()['c'];
$outstanding         = (float)$conn->query("SELECT IFNULL(SUM(pokok),0) s FROM ksp_pinjaman WHERE status='aktif'")->fetch_assoc()['s'];
$angsuran_hari_ini   = (float)$conn->query("SELECT IFNULL(SUM(total_tagih),0) s FROM ksp_jadwal_angsuran WHERE tanggal_jatuh_tempo=CURDATE() AND status='belum'")->fetch_assoc()['s'];
$due_today_count     = (int)$conn->query("SELECT COUNT(*) c FROM ksp_jadwal_angsuran WHERE tanggal_jatuh_tempo=CURDATE() AND status='belum'")->fetch_assoc()['c'];
$tunggakan_lembar    = (int)$conn->query("SELECT COUNT(*) c FROM ksp_jadwal_angsuran WHERE status='belum' AND tanggal_jatuh_tempo<CURDATE()")->fetch_assoc()['c'];
$tunggakan_nominal   = (float)$conn->query("SELECT IFNULL(SUM(total_tagih),0) s FROM ksp_jadwal_angsuran WHERE status='belum' AND tanggal_jatuh_tempo<CURDATE()")->fetch_assoc()['s'];
$setoran_hari_ini    = (float)$conn->query("SELECT IFNULL(SUM(nominal),0) s FROM ksp_kas WHERE jenis='masuk' AND DATE(tanggal)=CURDATE()")->fetch_assoc()['s'];

/* ===== CHART DATA: Pendapatan 7 hari terakhir ===== */
$pendapatan_7hari = array();
for ($i = 6; $i >= 0; $i--) {
  $tgl = date('Y-m-d', strtotime("-$i days"));
  $tgl_label = date('d M', strtotime("-$i days"));
  $masuk = (float)$conn->query("SELECT IFNULL(SUM(nominal),0) s FROM ksp_kas WHERE jenis='masuk' AND DATE(tanggal)='$tgl'")->fetch_assoc()['s'];
  $keluar = (float)$conn->query("SELECT IFNULL(SUM(nominal),0) s FROM ksp_kas WHERE jenis='keluar' AND DATE(tanggal)='$tgl'")->fetch_assoc()['s'];
  $pendapatan_7hari[] = array('tanggal' => $tgl_label, 'masuk' => $masuk, 'keluar' => $keluar);
}

/* ===== CHART DATA: Realisasi pinjaman per bulan (6 bulan) ===== */
$realisasi_bulanan = array();
for ($i = 5; $i >= 0; $i--) {
  $bulan = date('Y-m', strtotime("-$i months"));
  $bulan_label = date('M Y', strtotime("-$i months"));
  $jumlah = (int)$conn->query("SELECT COUNT(*) c FROM ksp_pinjaman WHERE DATE_FORMAT(tanggal_realisasi,'%Y-%m')='$bulan'")->fetch_assoc()['c'];
  $nominal = (float)$conn->query("SELECT IFNULL(SUM(pokok),0) s FROM ksp_pinjaman WHERE DATE_FORMAT(tanggal_realisasi,'%Y-%m')='$bulan'")->fetch_assoc()['s'];
  $realisasi_bulanan[] = array('bulan' => $bulan_label, 'jumlah' => $jumlah, 'nominal' => $nominal);
}

/* ===== CHART DATA: Status Pinjaman Pie ===== */
$status_pinjaman = array(
  'aktif' => $pinjaman_aktif,
  'lunas' => $pinjaman_lunas,
  'tunggakan' => $tunggakan_lembar > 0 ? 1 : 0
);

/* ===== CHART DATA: Collection Rate ===== */
$total_tagihan_bulan = (float)$conn->query("SELECT IFNULL(SUM(total_tagih),0) s FROM ksp_jadwal_angsuran WHERE DATE_FORMAT(tanggal_jatuh_tempo,'%Y-%m')=DATE_FORMAT(CURDATE(),'%Y-%m')")->fetch_assoc()['s'];
$total_terbayar_bulan = (float)$conn->query("SELECT IFNULL(SUM(bayar_nominal),0) s FROM ksp_jadwal_angsuran WHERE DATE_FORMAT(tanggal_jatuh_tempo,'%Y-%m')=DATE_FORMAT(CURDATE(),'%Y-%m') AND status='lunas'")->fetch_assoc()['s'];
$collection_rate = $total_tagihan_bulan > 0 ? round(($total_terbayar_bulan / $total_tagihan_bulan) * 100, 1) : 0;

/* ===== Total Pendapatan Bulan Ini ===== */
$pendapatan_bulan = (float)$conn->query("SELECT IFNULL(SUM(nominal),0) s FROM ksp_kas WHERE jenis='masuk' AND DATE_FORMAT(tanggal,'%Y-%m')=DATE_FORMAT(CURDATE(),'%Y-%m')")->fetch_assoc()['s'];
$pengeluaran_bulan = (float)$conn->query("SELECT IFNULL(SUM(nominal),0) s FROM ksp_kas WHERE jenis='keluar' AND DATE_FORMAT(tanggal,'%Y-%m')=DATE_FORMAT(CURDATE(),'%Y-%m')")->fetch_assoc()['s'];
$laba_bulan = $pendapatan_bulan - $pengeluaran_bulan;

/* ===== Lists ===== */
// Menunggu bayar hari ini (top 10)
$due_today = $conn->query("
  SELECT j.id jadwal_id, j.total_tagih, j.hari_ke, a.nama, a.hp, p.no_pinjaman
  FROM ksp_jadwal_angsuran j
  JOIN ksp_pinjaman p ON p.id=j.pinjaman_id
  JOIN ksp_anggota a ON a.id=p.anggota_id
  WHERE j.tanggal_jatuh_tempo=CURDATE() AND j.status='belum'
  ORDER BY j.total_tagih DESC, a.nama ASC
  LIMIT 10
");

// Tunggakan terlama (top 10)
$overdue = $conn->query("
  SELECT j.id jadwal_id, j.total_tagih, j.tanggal_jatuh_tempo, DATEDIFF(CURDATE(), j.tanggal_jatuh_tempo) AS telat,
         a.nama, a.hp, p.no_pinjaman
  FROM ksp_jadwal_angsuran j
  JOIN ksp_pinjaman p ON p.id=j.pinjaman_id
  JOIN ksp_anggota a ON a.id=p.anggota_id
  WHERE j.status='belum' AND j.tanggal_jatuh_tempo<CURDATE()
  ORDER BY telat DESC, j.tanggal_jatuh_tempo ASC
  LIMIT 10
");

// Kinerja kolektor (7 hari)
$kolektor7 = @$conn->query("
  SELECT k.nama, IFNULL(SUM(kas.nominal),0) setoran
  FROM ksp_kas kas
  JOIN ksp_tagih_harian t ON t.id=kas.ref_id AND kas.ref_table='ksp_tagih_harian'
  JOIN ksp_kolektor k ON k.id=t.kolektor_id
  WHERE kas.jenis='masuk' AND DATE(kas.tanggal) BETWEEN DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND CURDATE()
  GROUP BY k.id
  ORDER BY setoran DESC
");

// Produk pinjaman aktif terbanyak
$produk_aktif = @$conn->query("
  SELECT pr.nama, COUNT(p.id) jml, IFNULL(SUM(p.pokok),0) total
  FROM ksp_pinjaman p
  JOIN ksp_produk_pinjaman pr ON pr.id=p.produk_id
  WHERE p.status='aktif'
  GROUP BY pr.id
  ORDER BY jml DESC
  LIMIT 5
");

$company = setting_get('company_name', 'Koperasi');
$today   = date('Y-m-d');

/* ===== Helper (e() now defined globally in helpers.php) ===== */
if (!function_exists('wa_link')) {
  function wa_link($hp, $text){
    $wa = preg_replace('/\D+/', '', $hp);
    if ($wa !== '' && strpos($wa,'0')===0) $wa = '62'.substr($wa,1);
    return 'https://web.whatsapp.com/send?phone='.rawurlencode($wa).'&text='.rawurlencode($text);
  }
}
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<style>
/* ==== Premium Dashboard styles ==== */
.dash-compact { font-size: 12px; }
.dash-compact .kpi { color:#fff !important; border:0; border-radius:16px; padding:16px 18px; box-shadow:0 10px 25px rgba(20,20,60,.12); position:relative; overflow:hidden; background:#6366f1; }
.dash-compact .kpi .label, .dash-compact .kpi .value, .dash-compact .kpi .sub { color:#fff !important; text-shadow: 0 1px 2px rgba(0,0,0,0.2); }
.dash-compact .kpi::before { content:''; position:absolute; top:-50%; right:-50%; width:100%; height:200%; background:rgba(255,255,255,.05); transform:rotate(30deg); }
.dash-compact .kpi .icon { position:absolute; right:15px; top:50%; transform:translateY(-50%); font-size:40px; opacity:.2; }
.dash-compact .kpi .label { opacity:.95; font-size:11px; letter-spacing:.3px; font-weight:500; }
.dash-compact .kpi .value { font-size:22px; font-weight:800; margin-top:6px; }
.dash-compact .kpi .sub   { font-size:11px; opacity:.9; margin-top:4px; }
.dash-compact .kpi .trend { display:inline-block; padding:2px 8px; border-radius:20px; background:rgba(255,255,255,.2); font-size:10px; margin-top:6px; }
.dash-compact .kpi .trend.up { background:rgba(34,197,94,.3); }
.dash-compact .kpi .trend.down { background:rgba(239,68,68,.3); }

.grad-blue  { background:#2563eb !important; background:linear-gradient(135deg,#2563eb,#06b6d4) !important; }
.grad-green { background:#16a34a !important; background:linear-gradient(135deg,#16a34a,#22c55e) !important; }
.grad-amber { background:#f59e0b !important; background:linear-gradient(135deg,#f59e0b,#fbbf24) !important; }
.grad-red   { background:#ef4444 !important; background:linear-gradient(135deg,#ef4444,#f97316) !important; }
.grad-slate { background:#475569 !important; background:linear-gradient(135deg,#475569,#1f2937) !important; }
.grad-purple { background:#7c3aed !important; background:linear-gradient(135deg,#7c3aed,#a855f7) !important; }
.grad-cyan { background:#0891b2 !important; background:linear-gradient(135deg,#0891b2,#22d3ee) !important; }

.dash-compact h5.card-title { font-size:13px; margin:0 0 10px 0; font-weight:600; }
.dash-compact .text-small { font-size:10px; color:#64748b; }
.dash-compact .table-compact th, .dash-compact .table-compact td { padding:6px 8px; font-size:11px; }
.dash-compact .table-compact thead th { font-weight:600; background:#f8fafc; }
.dash-compact .text-right { text-align:right; }
.dash-compact .pill { display:inline-block; padding:4px 10px; border-radius:999px; background:#e0e7ff; color:#4338ca; font-size:10px; margin-right:6px; font-weight:600; }
.dash-compact .quick a { display:inline-block; margin:4px 6px 0 0; padding:6px 12px; border-radius:8px; border:1px solid #cbd5e1; font-size:11px; transition:all .2s; }
.dash-compact .quick a:hover { background:#4f46e5; color:#fff; border-color:#4f46e5; }
.dash-compact .badge { padding:3px 10px; font-size:10px; border-radius:999px; }

.chart-container { position:relative; height:220px; }
.stat-ring { width:120px; height:120px; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto; position:relative; }
.stat-ring .value { font-size:24px; font-weight:800; }
.stat-ring .label { font-size:10px; color:#64748b; }

.quick-action-btn { display:flex; align-items:center; gap:10px; padding:12px 16px; border-radius:12px; background:#f8fafc; border:1px solid #e2e8f0; margin-bottom:8px; transition:all .2s; text-decoration:none; color:#1e293b; }
.quick-action-btn:hover { background:#4f46e5; color:#fff; border-color:#4f46e5; transform:translateX(5px); }
.quick-action-btn i { font-size:20px; }
.quick-action-btn .text { font-size:12px; font-weight:500; }
</style>

<div class="dash-compact">
  <!-- KPIs Row 1 -->
  <div class="row g-3">
    <div class="col-lg-2 col-md-4 col-sm-6">
      <div class="kpi grad-blue">
        <i class="bi bi-people-fill icon"></i>
        <div class="label">ANGGOTA AKTIF</div>
        <div class="value"><?php echo (int)$total_anggota; ?></div>
        <div class="sub"><i class="bi bi-briefcase me-1"></i><?php echo (int)$pinjaman_aktif; ?> pinjaman aktif</div>
      </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6">
      <div class="kpi grad-green">
        <i class="bi bi-graph-up-arrow icon"></i>
        <div class="label">OUTSTANDING</div>
        <div class="value">Rp <?php echo rupiah($outstanding); ?></div>
        <div class="sub"><i class="bi bi-arrow-up-circle me-1"></i>Setoran: Rp <?php echo rupiah($setoran_hari_ini); ?></div>
      </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6">
      <div class="kpi grad-amber">
        <i class="bi bi-calendar-check icon"></i>
        <div class="label">TAGIHAN HARI INI</div>
        <div class="value">Rp <?php echo rupiah($angsuran_hari_ini); ?></div>
        <div class="sub"><i class="bi bi-file-earmark-text me-1"></i><?php echo (int)$due_today_count; ?> lembar</div>
      </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6">
      <div class="kpi grad-red">
        <i class="bi bi-exclamation-triangle-fill icon"></i>
        <div class="label">TUNGGAKAN</div>
        <div class="value"><?php echo (int)$tunggakan_lembar; ?> lembar</div>
        <div class="sub"><i class="bi bi-cash me-1"></i>Rp <?php echo rupiah($tunggakan_nominal); ?></div>
      </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6">
      <div class="kpi grad-purple">
        <i class="bi bi-percent icon"></i>
        <div class="label">COLLECTION RATE</div>
        <div class="value"><?php echo $collection_rate; ?>%</div>
        <div class="sub"><i class="bi bi-calendar me-1"></i>Bulan ini</div>
      </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6">
      <div class="kpi grad-cyan">
        <i class="bi bi-wallet2 icon"></i>
        <div class="label">LABA BULAN INI</div>
        <div class="value">Rp <?php echo rupiah($laba_bulan); ?></div>
        <div class="sub"><i class="bi bi-arrow-left-right me-1"></i><?php echo date('M Y'); ?></div>
      </div>
    </div>
  </div>

  <!-- Charts Row -->
  <div class="row g-3 mt-2">
    <div class="col-lg-8">
      <div class="card card-widget h-100">
        <div class="card-body">
          <h5 class="card-title"><i class="bi bi-bar-chart-line me-2"></i>Arus Kas 7 Hari Terakhir</h5>
          <div class="chart-container">
            <canvas id="chartPendapatan"></canvas>
          </div>
        </div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card card-widget h-100">
        <div class="card-body">
          <h5 class="card-title"><i class="bi bi-pie-chart me-2"></i>Status Pinjaman</h5>
          <div class="chart-container">
            <canvas id="chartStatus"></canvas>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Second Charts Row -->
  <div class="row g-3 mt-2">
    <div class="col-lg-6">
      <div class="card card-widget h-100">
        <div class="card-body">
          <h5 class="card-title"><i class="bi bi-graph-up me-2"></i>Realisasi Pinjaman (6 Bulan)</h5>
          <div class="chart-container">
            <canvas id="chartRealisasi"></canvas>
          </div>
        </div>
      </div>
    </div>
    <div class="col-lg-3">
      <div class="card card-widget h-100">
        <div class="card-body">
          <h5 class="card-title"><i class="bi bi-speedometer2 me-2"></i>Collection Rate</h5>
          <div class="text-center py-3">
            <div class="stat-ring" style="background: conic-gradient(#22c55e <?php echo $collection_rate * 3.6; ?>deg, #e2e8f0 0deg);">
              <div class="text-center" style="background:#fff; border-radius:50%; width:90px; height:90px; display:flex; flex-direction:column; align-items:center; justify-content:center;">
                <div class="value text-success"><?php echo $collection_rate; ?>%</div>
                <div class="label">Tertagih</div>
              </div>
            </div>
            <div class="mt-3">
              <div class="small text-muted">Target: Rp <?php echo rupiah($total_tagihan_bulan); ?></div>
              <div class="small text-success">Terbayar: Rp <?php echo rupiah($total_terbayar_bulan); ?></div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-lg-3">
      <div class="card card-widget h-100">
        <div class="card-body">
          <h5 class="card-title"><i class="bi bi-lightning me-2"></i>Quick Actions</h5>
          <a href="index.php?m=pinjaman_realisasi" class="quick-action-btn">
            <i class="bi bi-plus-circle text-primary"></i>
            <span class="text">Realisasi Pinjaman Baru</span>
          </a>
          <a href="index.php?m=angsuran" class="quick-action-btn">
            <i class="bi bi-cash-coin text-success"></i>
            <span class="text">Input Pembayaran</span>
          </a>
          <a href="index.php?m=anggota" class="quick-action-btn">
            <i class="bi bi-person-plus text-info"></i>
            <span class="text">Tambah Anggota</span>
          </a>
          <a href="index.php?m=laporan" class="quick-action-btn">
            <i class="bi bi-file-earmark-bar-graph text-warning"></i>
            <span class="text">Lihat Laporan</span>
          </a>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-2 mt-2">
    <!-- Due Today -->
    <div class="col-lg-6">
      <div class="card card-widget">
        <div class="card-body">
          <h5 class="card-title">Menunggu Bayar Hari Ini <span class="pill"><?php echo (int)$due_today_count; ?> lembar</span></h5>
          <div class="table-responsive">
            <table class="table table-striped table-sm table-compact">
              <thead>
                <tr><th>Anggota</th><th>No.</th><th>H</th><th class="text-right">Tagih</th><th>WA</th></tr>
              </thead>
              <tbody>
              <?php if ($due_today && $due_today->num_rows>0): while($r=$due_today->fetch_assoc()):
                $msg = "Halo {$r['nama']}, ini {$company}. Tagihan harian no. {$r['no_pinjaman']} sebesar Rp ".rupiah($r['total_tagih'])." jatuh tempo hari ini ({$today}). Terima kasih.";
              ?>
                <tr>
                  <td><?php echo e($r['nama']); ?></td>
                  <td><?php echo e($r['no_pinjaman']); ?></td>
                  <td><?php echo (int)$r['hari_ke']; ?></td>
                  <td class="text-right">Rp <?php echo rupiah($r['total_tagih']); ?></td>
                  <td><a class="btn btn-sm btn-outline-secondary" target="_blank" href="<?php echo e(wa_link($r['hp'], $msg)); ?>">WA</a></td>
                </tr>
              <?php endwhile; else: ?>
                <tr><td colspan="5" class="text-small">Tidak ada tagihan jatuh tempo hari ini.</td></tr>
              <?php endif; ?>
              </tbody>
            </table>
          </div>
          <div class="quick">
            <a href="index.php?m=tagih&tanggal=<?php echo e($today); ?>">Penagihan Hari Ini</a>
            <a href="print/laporan_angsuran_a4.php?from=<?php echo e($today); ?>&to=<?php echo e($today); ?>" target="_blank">Print Angsuran (Hari Ini)</a>
          </div>
        </div>
      </div>
    </div>

    <!-- Overdue -->
    <div class="col-lg-6">
      <div class="card card-widget">
        <div class="card-body">
          <h5 class="card-title">Tunggakan Terlama</h5>
          <div class="table-responsive">
            <table class="table table-striped table-sm table-compact">
              <thead><tr><th>Anggota</th><th>No.</th><th>Telat</th><th class="text-right">Tagih</th><th>WA</th></tr></thead>
              <tbody>
              <?php if ($overdue && $overdue->num_rows>0): while($r=$overdue->fetch_assoc()):
                $msg = "Halo {$r['nama']}, ini {$company}. Tagihan harian no. {$r['no_pinjaman']} sebesar Rp ".rupiah($r['total_tagih'])." telah telat {$r['telat']} hari (JT {$r['tanggal_jatuh_tempo']}). Mohon segera bayar. Terima kasih.";
              ?>
                <tr>
                  <td><?php echo e($r['nama']); ?></td>
                  <td><?php echo e($r['no_pinjaman']); ?></td>
                  <td><?php echo (int)$r['telat']; ?>h</td>
                  <td class="text-right">Rp <?php echo rupiah($r['total_tagih']); ?></td>
                  <td><a class="btn btn-sm btn-outline-secondary" target="_blank" href="<?php echo e(wa_link($r['hp'], $msg)); ?>">WA</a></td>
                </tr>
              <?php endwhile; else: ?>
                <tr><td colspan="5" class="text-small">Tidak ada tunggakan.</td></tr>
              <?php endif; ?>
              </tbody>
            </table>
          </div>
          <div class="quick">
            <a href="print/laporan_aging_a4.php" target="_blank">Print Aging</a>
            <a href="export/export_aging.php">Export Aging CSV</a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-2 mt-2">
    <!-- Kolektor -->
    <div class="col-lg-6">
      <div class="card card-widget">
        <div class="card-body">
          <h5 class="card-title">Kinerja Kolektor (7 Hari)</h5>
          <div class="table-responsive">
            <table class="table table-striped table-sm table-compact">
              <thead><tr><th>Kolektor</th><th class="text-right">Setoran</th></tr></thead>
              <tbody>
              <?php if ($kolektor7 && $kolektor7->num_rows>0): while($r=$kolektor7->fetch_assoc()): ?>
                <tr>
                  <td><?php echo e($r['nama']); ?></td>
                  <td class="text-right">Rp <?php echo rupiah($r['setoran']); ?></td>
                </tr>
              <?php endwhile; else: ?>
                <tr><td colspan="2" class="text-small">Belum ada setoran kolektor 7 hari terakhir.</td></tr>
              <?php endif; ?>
              </tbody>
            </table>
          </div>
          <div class="text-small">Periode: <?php echo e(date('Y-m-d', strtotime('-6 days'))); ?> s/d <?php echo e($today); ?></div>
        </div>
      </div>
    </div>

    <!-- Produk -->
    <div class="col-lg-6">
      <div class="card card-widget">
        <div class="card-body">
          <h5 class="card-title">Produk Aktif Terbanyak</h5>
          <div class="table-responsive">
            <table class="table table-striped table-sm table-compact">
              <thead><tr><th>Produk</th><th>Aktif</th><th class="text-right">Pokok</th></tr></thead>
              <tbody>
              <?php if ($produk_aktif && $produk_aktif->num_rows>0): while($r=$produk_aktif->fetch_assoc()): ?>
                <tr>
                  <td><?php echo e($r['nama']); ?></td>
                  <td><?php echo (int)$r['jml']; ?></td>
                  <td class="text-right">Rp <?php echo rupiah($r['total']); ?></td>
                </tr>
              <?php endwhile; else: ?>
                <tr><td colspan="3" class="text-small">Belum ada data produk aktif.</td></tr>
              <?php endif; ?>
              </tbody>
            </table>
          </div>
          <div class="quick">
            <a href="index.php?m=pinjaman_realisasi">Tambah Pinjaman</a>
            <a href="index.php?m=angsuran">Input Pembayaran</a>
            <a href="index.php?m=tagih&tanggal=<?php echo e($today); ?>">Penagihan</a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Aktivitas Terbaru -->
  <div class="card card-widget mt-2">
    <div class="card-body">
      <h5 class="card-title">Aktivitas Terbaru (10)</h5>
      <div class="table-responsive">
        <table class="table table-striped table-sm table-compact">
          <thead><tr><th>Tanggal</th><th>Jenis</th><th>Keterangan</th><th class="text-right">Nominal</th></tr></thead>
          <tbody>
          <?php
          $q = $conn->query("SELECT tanggal, jenis, sumber, nominal, CONCAT(ref_table,'#',ref_id) ket FROM ksp_kas ORDER BY id DESC LIMIT 10");
          while ($r = $q->fetch_assoc()):
          ?>
            <tr>
              <td><?php echo e($r['tanggal']); ?></td>
              <td><span class="badge <?php echo $r['jenis']=='masuk'?'text-bg-success':'text-bg-danger'; ?>"><?php echo e($r['jenis']); ?></span></td>
              <td><?php echo e($r['sumber'].' '.$r['ket']); ?></td>
              <td class="text-right">Rp <?php echo rupiah($r['nominal']); ?></td>
            </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Chart.js Initialization -->
<script>
document.addEventListener('DOMContentLoaded', function() {
  // Data dari PHP
  var pendapatan7hari = <?php echo json_encode($pendapatan_7hari); ?>;
  var realisasiBulanan = <?php echo json_encode($realisasi_bulanan); ?>;
  var pinjamanAktif = <?php echo (int)$pinjaman_aktif; ?>;
  var pinjamanLunas = <?php echo (int)$pinjaman_lunas; ?>;

  // Chart 1: Arus Kas 7 Hari
  var ctx1 = document.getElementById('chartPendapatan');
  if (ctx1) {
    new Chart(ctx1, {
      type: 'bar',
      data: {
        labels: pendapatan7hari.map(function(d) { return d.tanggal; }),
        datasets: [
          {
            label: 'Pemasukan',
            data: pendapatan7hari.map(function(d) { return d.masuk; }),
            backgroundColor: 'rgba(34, 197, 94, 0.8)',
            borderColor: 'rgba(34, 197, 94, 1)',
            borderWidth: 1,
            borderRadius: 6
          },
          {
            label: 'Pengeluaran',
            data: pendapatan7hari.map(function(d) { return d.keluar; }),
            backgroundColor: 'rgba(239, 68, 68, 0.8)',
            borderColor: 'rgba(239, 68, 68, 1)',
            borderWidth: 1,
            borderRadius: 6
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'top', labels: { font: { size: 11 } } }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              callback: function(value) {
                return 'Rp ' + value.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
              },
              font: { size: 10 }
            }
          },
          x: { ticks: { font: { size: 10 } } }
        }
      }
    });
  }

  // Chart 2: Status Pinjaman (Doughnut)
  var ctx2 = document.getElementById('chartStatus');
  if (ctx2) {
    new Chart(ctx2, {
      type: 'doughnut',
      data: {
        labels: ['Aktif', 'Lunas'],
        datasets: [{
          data: [pinjamanAktif, pinjamanLunas],
          backgroundColor: ['rgba(37, 99, 235, 0.9)', 'rgba(34, 197, 94, 0.9)'],
          borderWidth: 0
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '60%',
        plugins: {
          legend: { position: 'bottom', labels: { font: { size: 11 }, padding: 15 } }
        }
      }
    });
  }

  // Chart 3: Realisasi Pinjaman Bulanan
  var ctx3 = document.getElementById('chartRealisasi');
  if (ctx3) {
    new Chart(ctx3, {
      type: 'line',
      data: {
        labels: realisasiBulanan.map(function(d) { return d.bulan; }),
        datasets: [
          {
            label: 'Jumlah Pinjaman',
            data: realisasiBulanan.map(function(d) { return d.jumlah; }),
            borderColor: 'rgba(124, 58, 237, 1)',
            backgroundColor: 'rgba(124, 58, 237, 0.1)',
            fill: true,
            tension: 0.4,
            yAxisID: 'y'
          },
          {
            label: 'Nominal (Juta)',
            data: realisasiBulanan.map(function(d) { return d.nominal / 1000000; }),
            borderColor: 'rgba(6, 182, 212, 1)',
            backgroundColor: 'rgba(6, 182, 212, 0.1)',
            fill: true,
            tension: 0.4,
            yAxisID: 'y1'
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { position: 'top', labels: { font: { size: 11 } } }
        },
        scales: {
          y: {
            type: 'linear',
            display: true,
            position: 'left',
            beginAtZero: true,
            ticks: { font: { size: 10 } },
            title: { display: true, text: 'Jumlah', font: { size: 10 } }
          },
          y1: {
            type: 'linear',
            display: true,
            position: 'right',
            beginAtZero: true,
            grid: { drawOnChartArea: false },
            ticks: { font: { size: 10 } },
            title: { display: true, text: 'Juta Rp', font: { size: 10 } }
          },
          x: { ticks: { font: { size: 10 } } }
        }
      }
    });
  }
});
</script>

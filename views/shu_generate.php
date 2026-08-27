<?php
// views/shu_generate.php - Generate & Distribusi SHU
csrf_check();
$conn = db();

// Load settings
$settings = [];
$set_list = $conn->query("SELECT * FROM ksp_setting_shu");
while ($s = $set_list->fetch_assoc()) $settings[$s['kode']] = $s['persen'];

// Handle generate
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='generate') {
  $tahun = (int)$_POST['tahun'];
  $uid = user()['id'];
  
  // Check if already exists
  $existing = $conn->query("SELECT id FROM ksp_shu_periode WHERE tahun=$tahun")->fetch_assoc();
  if ($existing) {
    // Delete existing and regenerate
    $conn->query("DELETE FROM ksp_shu_periode WHERE id=".$existing['id']);
  }
  
  // Calculate total pendapatan & biaya dari transaksi keuangan tahun tersebut
  $tgl_awal = "$tahun-01-01";
  $tgl_akhir = "$tahun-12-31";
  
  $pendapatan = $conn->query("SELECT SUM(nominal) as total FROM ksp_transaksi_keuangan WHERE tipe='pemasukan' AND tanggal BETWEEN '$tgl_awal' AND '$tgl_akhir'")->fetch_assoc()['total'] ?? 0;
  $biaya = $conn->query("SELECT SUM(nominal) as total FROM ksp_transaksi_keuangan WHERE tipe='pengeluaran' AND tanggal BETWEEN '$tgl_awal' AND '$tgl_akhir'")->fetch_assoc()['total'] ?? 0;
  
  // Add pendapatan from bunga pinjaman
  $bunga_pinjaman = $conn->query("SELECT SUM(bunga_harian) as total FROM ksp_jadwal_angsuran j JOIN ksp_pinjaman p ON p.id=j.pinjaman_id WHERE j.status='lunas' AND j.tanggal_bayar BETWEEN '$tgl_awal' AND '$tgl_akhir'")->fetch_assoc()['total'] ?? 0;
  $pendapatan += $bunga_pinjaman;
  
  // Add pendapatan from penjualan
  $penjualan = $conn->query("SELECT SUM(total_bayar) as total FROM ksp_penjualan WHERE status='selesai' AND DATE(tanggal) BETWEEN '$tgl_awal' AND '$tgl_akhir'")->fetch_assoc()['total'] ?? 0;
  $pendapatan += $penjualan;
  
  // Add biaya from pembelian
  $pembelian = $conn->query("SELECT SUM(total_nilai) as total FROM ksp_pembelian WHERE cara_bayar='tunai' AND tanggal BETWEEN '$tgl_awal' AND '$tgl_akhir'")->fetch_assoc()['total'] ?? 0;
  $biaya += $pembelian;
  
  $laba = $pendapatan - $biaya;
  
  if ($laba <= 0) {
    alert('danger', 'Tidak ada laba untuk dibagikan (Laba: Rp '.rupiah($laba).')');
    header("Location: index.php?m=shu_generate"); exit;
  }
  
  // Calculate distribution
  $cadangan = $laba * $settings['cadangan'] / 100;
  $shu_anggota_total = $laba * $settings['shu_anggota'] / 100;
  $jasa_pengurus = $laba * $settings['jasa_pengurus'] / 100;
  $jasa_pengawas = $laba * $settings['jasa_pengawas'] / 100;
  $dana_pendidikan = $laba * $settings['dana_pendidikan'] / 100;
  $dana_sosial = $laba * $settings['dana_sosial'] / 100;
  $dana_pengembangan = $laba * $settings['dana_pengembangan'] / 100;
  
  // Insert periode
  $stmt = $conn->prepare("INSERT INTO ksp_shu_periode(tahun, total_pendapatan, total_biaya, laba_kotor, cadangan, shu_anggota, jasa_pengurus, jasa_pengawas, dana_pendidikan, dana_sosial, dana_pengembangan, created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)");
  $stmt->bind_param("iddddddddddi", $tahun, $pendapatan, $biaya, $laba, $cadangan, $shu_anggota_total, $jasa_pengurus, $jasa_pengawas, $dana_pendidikan, $dana_sosial, $dana_pengembangan, $uid);
  $stmt->execute();
  $periode_id = $conn->insert_id;
  
  // Calculate per anggota
  // Total simpanan pokok+wajib semua anggota
  $total_simpanan = $conn->query("SELECT SUM(simpanan_pokok + simpanan_wajib) as total FROM ksp_simpanan_anggota")->fetch_assoc()['total'] ?? 0;
  
  // Total transaksi (pinjaman + penjualan ke anggota)
  $total_transaksi_all = 0;
  
  // Get all active members
  $anggota_list = $conn->query("SELECT a.id, a.nama, a.kelompok_id, COALESCE(sa.simpanan_pokok,0) + COALESCE(sa.simpanan_wajib,0) as simpanan FROM ksp_anggota a LEFT JOIN ksp_simpanan_anggota sa ON sa.anggota_id = a.id WHERE a.status='aktif'");
  
  $anggota_data = [];
  while ($ang = $anggota_list->fetch_assoc()) {
    $anggota_id = $ang['id'];
    $simpanan = $ang['simpanan'];
    
    // Transaksi pinjaman perorangan
    $trx_pinjaman = $conn->query("SELECT SUM(nominal) as total FROM ksp_pinjaman WHERE anggota_id=$anggota_id AND status IN ('aktif','lunas') AND tanggal_realisasi BETWEEN '$tgl_awal' AND '$tgl_akhir'")->fetch_assoc()['total'] ?? 0;
    
    // Transaksi penjualan ke anggota
    $trx_penjualan = $conn->query("SELECT SUM(total_bayar) as total FROM ksp_penjualan WHERE anggota_id=$anggota_id AND status='selesai' AND DATE(tanggal) BETWEEN '$tgl_awal' AND '$tgl_akhir'")->fetch_assoc()['total'] ?? 0;
    
    // Transaksi pinjaman kelompok (dibagi rata ke anggota kelompok)
    $trx_kelompok = 0;
    if ($ang['kelompok_id']) {
      $kelompok_id = $ang['kelompok_id'];
      // Get jumlah anggota dalam kelompok
      $jml_anggota_kelompok = $conn->query("SELECT COUNT(*) as cnt FROM ksp_anggota WHERE kelompok_id=$kelompok_id AND status='aktif'")->fetch_assoc()['cnt'] ?? 1;
      // Get total pinjaman kelompok (via ketua)
      $ketua = $conn->query("SELECT id FROM ksp_anggota WHERE kelompok_id=$kelompok_id AND jabatan_kelompok='ketua' LIMIT 1")->fetch_assoc();
      if ($ketua) {
        $pinjaman_kelompok = $conn->query("SELECT SUM(p.nominal) as total FROM ksp_pinjaman p JOIN ksp_produk_pinjaman pp ON pp.id=p.produk_id WHERE p.anggota_id=".$ketua['id']." AND pp.jenis_peminjam='kelompok' AND p.tanggal_realisasi BETWEEN '$tgl_awal' AND '$tgl_akhir'")->fetch_assoc()['total'] ?? 0;
        $trx_kelompok = $pinjaman_kelompok / $jml_anggota_kelompok;
      }
    }
    
    $total_trx = $trx_pinjaman + $trx_penjualan + $trx_kelompok;
    $total_transaksi_all += $total_trx;
    
    $anggota_data[] = [
      'anggota_id' => $anggota_id,
      'simpanan' => $simpanan,
      'transaksi' => $total_trx
    ];
  }
  
  // Calculate and insert SHU per anggota
  $jasa_modal_pool = $shu_anggota_total * $settings['jasa_modal'] / 100;
  $jasa_usaha_pool = $shu_anggota_total * $settings['jasa_usaha'] / 100;
  
  foreach ($anggota_data as $ad) {
    $jasa_modal = ($total_simpanan > 0) ? ($ad['simpanan'] / $total_simpanan) * $jasa_modal_pool : 0;
    $jasa_usaha = ($total_transaksi_all > 0) ? ($ad['transaksi'] / $total_transaksi_all) * $jasa_usaha_pool : 0;
    $total_shu = $jasa_modal + $jasa_usaha;
    
    if ($total_shu > 0) {
      $stmt2 = $conn->prepare("INSERT INTO ksp_shu_anggota_detail(shu_periode_id, anggota_id, total_simpanan, total_transaksi, jasa_modal, jasa_usaha, total_shu) VALUES(?,?,?,?,?,?,?)");
      $stmt2->bind_param("iiddddd", $periode_id, $ad['anggota_id'], $ad['simpanan'], $ad['transaksi'], $jasa_modal, $jasa_usaha, $total_shu);
      $stmt2->execute();
    }
  }
  
  alert('success', "SHU tahun $tahun berhasil di-generate. Total Laba: Rp ".rupiah($laba));
  header("Location: index.php?m=shu_generate&tahun=$tahun"); exit;
}

// Handle distribute (bayar ke anggota)
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='distribute') {
  $periode_id = (int)$_POST['periode_id'];
  $tanggal = date('Y-m-d');
  
  // Get all unpaid SHU
  $unpaid = $conn->query("SELECT sad.*, a.nama FROM ksp_shu_anggota_detail sad JOIN ksp_anggota a ON a.id=sad.anggota_id WHERE sad.shu_periode_id=$periode_id AND sad.status_bayar='belum' AND sad.total_shu > 0");
  
  $count = 0;
  while ($u = $unpaid->fetch_assoc()) {
    // Add to simpanan sukarela
    $conn->query("UPDATE ksp_simpanan_anggota SET simpanan_sukarela = simpanan_sukarela + ".$u['total_shu'].", total_simpanan = simpanan_pokok + simpanan_wajib + simpanan_sukarela + ".$u['total_shu']." WHERE anggota_id = ".$u['anggota_id']);
    
    // Record mutasi
    $stmt = $conn->prepare("INSERT INTO ksp_mutasi_simpanan(anggota_id, tanggal, jenis, tipe_simpanan, nominal, keterangan, created_by) VALUES(?,?,'setor','sukarela',?,?,?)");
    $ket = "SHU Tahun - Jasa Modal: Rp ".rupiah($u['jasa_modal']).", Jasa Usaha: Rp ".rupiah($u['jasa_usaha']);
    $uid = user()['id'];
    $stmt->bind_param("isdsi", $u['anggota_id'], $tanggal, $u['total_shu'], $ket, $uid);
    $stmt->execute();
    
    // Mark as paid
    $conn->query("UPDATE ksp_shu_anggota_detail SET status_bayar='sudah', tanggal_bayar='$tanggal' WHERE id=".$u['id']);
    $count++;
  }
  
  // Mark periode as final
  $conn->query("UPDATE ksp_shu_periode SET status='final' WHERE id=$periode_id");
  
  alert('success', "SHU berhasil didistribusikan ke $count anggota (masuk ke Simpanan Sukarela).");
  header("Location: index.php?m=shu_generate&tahun=".$_POST['tahun']); exit;
}

// Current view
$tahun_view = isset($_GET['tahun']) ? (int)$_GET['tahun'] : date('Y');
$periode = $conn->query("SELECT * FROM ksp_shu_periode WHERE tahun=$tahun_view")->fetch_assoc();
$detail_list = $periode ? $conn->query("SELECT sad.*, a.nama, a.no_anggota FROM ksp_shu_anggota_detail sad JOIN ksp_anggota a ON a.id=sad.anggota_id WHERE sad.shu_periode_id=".$periode['id']." ORDER BY sad.total_shu DESC") : null;

// Available years
$years = [];
for ($y = date('Y'); $y >= date('Y') - 5; $y--) $years[] = $y;
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-pie-chart me-2"></i>Generate & Distribusi SHU</h4>
  <a href="index.php?m=setting_shu" class="btn btn-outline-primary"><i class="bi bi-sliders me-1"></i> Setting SHU</a>
</div>

<!-- Year selector & Generate -->
<div class="card card-widget mb-3">
  <div class="card-body">
    <form method="post" class="row g-3 align-items-end">
      <?php echo csrf_input(); ?>
      <input type="hidden" name="action" value="generate">
      <div class="col-md-3">
        <label class="form-label">Pilih Tahun</label>
        <select name="tahun" class="form-select" onchange="location.href='index.php?m=shu_generate&tahun='+this.value">
          <?php foreach($years as $y): ?>
          <option value="<?php echo $y; ?>" <?php echo $tahun_view==$y?'selected':''; ?>><?php echo $y; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-calculator me-1"></i> Generate SHU <?php echo $tahun_view; ?></button>
      </div>
      <?php if ($periode && $periode['status'] == 'draft'): ?>
      <div class="col-md-3">
        <form method="post" style="display:inline">
          <?php echo csrf_input(); ?>
          <input type="hidden" name="action" value="distribute">
          <input type="hidden" name="periode_id" value="<?php echo $periode['id']; ?>">
          <input type="hidden" name="tahun" value="<?php echo $tahun_view; ?>">
          <button type="submit" class="btn btn-success w-100" onclick="return confirm('Distribusikan SHU ke semua anggota? SHU akan masuk ke Simpanan Sukarela.')"><i class="bi bi-send me-1"></i> Distribusi ke Anggota</button>
        </form>
      </div>
      <?php endif; ?>
    </form>
  </div>
</div>

<?php if ($periode): ?>
<!-- Summary -->
<div class="row mb-3">
  <div class="col-md-3">
    <div class="card bg-success text-white">
      <div class="card-body text-center">
        <h5 class="mb-0">Rp <?php echo rupiah($periode['total_pendapatan']); ?></h5>
        <small>Total Pendapatan</small>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card bg-danger text-white">
      <div class="card-body text-center">
        <h5 class="mb-0">Rp <?php echo rupiah($periode['total_biaya']); ?></h5>
        <small>Total Biaya</small>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card bg-primary text-white">
      <div class="card-body text-center">
        <h5 class="mb-0">Rp <?php echo rupiah($periode['laba_kotor']); ?></h5>
        <small>Laba Kotor (SHU)</small>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card bg-info text-white">
      <div class="card-body text-center">
        <h5 class="mb-0"><?php echo $periode['status'] == 'final' ? '✅ Final' : '📝 Draft'; ?></h5>
        <small>Status</small>
      </div>
    </div>
  </div>
</div>

<!-- Distribution breakdown -->
<div class="row mb-3">
  <div class="col-md-6">
    <div class="card card-widget">
      <div class="card-header bg-light"><strong>Pembagian SHU Tahun <?php echo $tahun_view; ?></strong></div>
      <div class="card-body">
        <table class="table table-sm">
          <tr><td>Cadangan (Laba Ditahan)</td><td class="text-end">Rp <?php echo rupiah($periode['cadangan']); ?></td></tr>
          <tr class="table-success"><td><strong>SHU Anggota</strong></td><td class="text-end"><strong>Rp <?php echo rupiah($periode['shu_anggota']); ?></strong></td></tr>
          <tr><td>Jasa Pengurus</td><td class="text-end">Rp <?php echo rupiah($periode['jasa_pengurus']); ?></td></tr>
          <tr><td>Jasa Pengawas</td><td class="text-end">Rp <?php echo rupiah($periode['jasa_pengawas']); ?></td></tr>
          <tr><td>Dana Pendidikan</td><td class="text-end">Rp <?php echo rupiah($periode['dana_pendidikan']); ?></td></tr>
          <tr><td>Dana Sosial</td><td class="text-end">Rp <?php echo rupiah($periode['dana_sosial']); ?></td></tr>
          <tr><td>Dana Pengembangan</td><td class="text-end">Rp <?php echo rupiah($periode['dana_pengembangan']); ?></td></tr>
          <tr class="table-dark"><th>TOTAL</th><th class="text-end">Rp <?php echo rupiah($periode['laba_kotor']); ?></th></tr>
        </table>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card card-widget">
      <div class="card-header bg-success text-white"><strong>SHU Anggota: Rp <?php echo rupiah($periode['shu_anggota']); ?></strong></div>
      <div class="card-body">
        <p>Dibagi berdasarkan:</p>
        <ul>
          <li><strong>Jasa Modal (<?php echo $settings['jasa_modal']; ?>%)</strong>: Berdasarkan simpanan pokok + wajib</li>
          <li><strong>Jasa Usaha (<?php echo $settings['jasa_usaha']; ?>%)</strong>: Berdasarkan transaksi pinjaman + penjualan</li>
        </ul>
        <hr>
        <p class="mb-0"><strong>Pool Jasa Modal:</strong> Rp <?php echo rupiah($periode['shu_anggota'] * $settings['jasa_modal'] / 100); ?></p>
        <p class="mb-0"><strong>Pool Jasa Usaha:</strong> Rp <?php echo rupiah($periode['shu_anggota'] * $settings['jasa_usaha'] / 100); ?></p>
      </div>
    </div>
  </div>
</div>

<!-- Detail per anggota -->
<div class="card card-widget">
  <div class="card-header bg-light d-flex justify-content-between">
    <strong>Detail SHU per Anggota</strong>
    <button class="btn btn-sm btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer me-1"></i> Cetak</button>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-striped table-sm datatable">
        <thead>
          <tr><th>#</th><th>No. Anggota</th><th>Nama</th><th class="text-end">Simpanan</th><th class="text-end">Transaksi</th><th class="text-end">Jasa Modal</th><th class="text-end">Jasa Usaha</th><th class="text-end">Total SHU</th><th>Status</th></tr>
        </thead>
        <tbody>
        <?php $no=1; if($detail_list): while($d=$detail_list->fetch_assoc()): ?>
          <tr>
            <td><?php echo $no++; ?></td>
            <td><?php echo $d['no_anggota']; ?></td>
            <td><?php echo htmlspecialchars($d['nama']); ?></td>
            <td class="text-end">Rp <?php echo rupiah($d['total_simpanan']); ?></td>
            <td class="text-end">Rp <?php echo rupiah($d['total_transaksi']); ?></td>
            <td class="text-end">Rp <?php echo rupiah($d['jasa_modal']); ?></td>
            <td class="text-end">Rp <?php echo rupiah($d['jasa_usaha']); ?></td>
            <td class="text-end"><strong>Rp <?php echo rupiah($d['total_shu']); ?></strong></td>
            <td>
              <?php if ($d['status_bayar'] == 'sudah'): ?>
              <span class="badge bg-success">✅ Dibayar <?php echo date('d/m/Y', strtotime($d['tanggal_bayar'])); ?></span>
              <?php else: ?>
              <span class="badge bg-warning text-dark">Belum</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endwhile; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php else: ?>
<div class="alert alert-warning">
  <i class="bi bi-info-circle me-1"></i> Belum ada data SHU untuk tahun <?php echo $tahun_view; ?>. Klik tombol <strong>Generate SHU</strong> untuk menghitung.
</div>
<?php endif; ?>

<style>
@media print {
  .btn, form, .dataTables_filter, .dataTables_length, .dataTables_info, .dataTables_paginate { display: none !important; }
}
</style>

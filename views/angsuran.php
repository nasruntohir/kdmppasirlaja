<?php
// views/angsuran.php
csrf_check();
$conn = db();

// Handle bayar angsuran biasa
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['bayar'])) {
  $jadwal_id = (int)$_POST['jadwal_id'];
  $bayar = (float)$_POST['bayar_nominal'];

  $r = $conn->query("SELECT j.*, p.no_pinjaman FROM ksp_jadwal_angsuran j JOIN ksp_pinjaman p ON p.id=j.pinjaman_id WHERE j.id=".$jadwal_id)->fetch_assoc();
  if ($r) {
    $due = strtotime($r['tanggal_jatuh_tempo']);
    $today = strtotime(date('Y-m-d'));
    $over = max(0, floor(($today - $due) / 86400));
    $denda_per_hari = (float)setting_get('denda_fixed_per_hari', '1000');
    $denda = $over * $denda_per_hari;
    $total_due = $r['total_tagih'] + $denda;

    $acc = $r['bayar_nominal'] + $bayar;
    // Pastikan tidak melebihi tagihan
    $acc = min($acc, $total_due);
    $status = $acc + 0.0001 >= $total_due ? 'lunas' : 'belum';
    $stmt = $conn->prepare("UPDATE ksp_jadwal_angsuran SET bayar_nominal=?, denda=?, status=?, tanggal_bayar=? WHERE id=?");
    $tanggal_bayar = date('Y-m-d');
    $stmt->bind_param("ddssi", $acc, $denda, $status, $tanggal_bayar, $jadwal_id);
    if ($stmt->execute()) {
      if ($bayar > 0) {
        $stmt2 = $conn->prepare("INSERT INTO ksp_kas(no_kas,tanggal,jenis,sumber,ref_table,ref_id,nominal,user_id,catatan) VALUES(?,?,?,?,?,?,?,?,?)");
        $no_kas = gen_number('prefix_no_kas');
        $jenis = 'masuk'; $sumber='angsuran'; $ref_table='ksp_jadwal_angsuran'; $ref_id=$jadwal_id; $nominal=$bayar; $uid=user()['id']; $cat='Angsuran '.$r['no_pinjaman'].' ke-'.$r['hari_ke'];
        $stmt2->bind_param("sssssiids", $no_kas, now(), $jenis, $sumber, $ref_table, $ref_id, $nominal, $uid, $cat);
        $stmt2->execute();
      }
      
      if ($status === 'lunas') {
        $pinjaman_id = $r['pinjaman_id'];
        $cek_belum = $conn->query("SELECT COUNT(*) c FROM ksp_jadwal_angsuran WHERE pinjaman_id=$pinjaman_id AND status='belum'");
        $sisa = $cek_belum ? (int)$cek_belum->fetch_assoc()['c'] : 1;
        if ($sisa === 0) {
          $conn->query("UPDATE ksp_pinjaman SET status='lunas' WHERE id=$pinjaman_id");
        }
      }
      
      alert('success','Pembayaran dicatat. Status: '.$status);
    } else {
      alert('danger','Gagal update angsuran.');
    }
  } else {
    alert('danger','Data jadwal angsuran tidak ditemukan.');
  }
  $redirect_no = $r ? urlencode($r['no_pinjaman']) : '';
  header("Location: index.php?m=angsuran&no=".$redirect_no); exit;
}

// Handle PELUNASAN LANGSUNG (bayar semua sisa sekaligus)
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['pelunasan'])) {
  $pinjaman_id = (int)$_POST['pinjaman_id'];
  $total_bayar = (float)$_POST['total_pelunasan'];
  
  $p = $conn->query("SELECT * FROM ksp_pinjaman WHERE id=$pinjaman_id AND status='aktif'")->fetch_assoc();
  if ($p) {
    // Get all unpaid schedules
    $jadwals = $conn->query("SELECT * FROM ksp_jadwal_angsuran WHERE pinjaman_id=$pinjaman_id AND status='belum' ORDER BY hari_ke");
    $total_sisa = 0;
    $items = [];
    $denda_per_hari = (float)setting_get('denda_fixed_per_hari', '1000');
    $today_ts = strtotime(date('Y-m-d'));
    while ($j = $jadwals->fetch_assoc()) {
      // Hitung denda per jadwal yang sudah lewat jatuh tempo
      $due_ts = strtotime($j['tanggal_jatuh_tempo']);
      $over = max(0, floor(($today_ts - $due_ts) / 86400));
      $j['_denda'] = $over * $denda_per_hari;
      $items[] = $j;
      $total_sisa += $j['total_tagih'] + $j['_denda'];
    }
    
    if ($total_bayar >= $total_sisa && count($items) > 0) {
      $tanggal_bayar = date('Y-m-d');
      $total_kas = 0;
      
      // Mark all as paid (with denda)
      foreach ($items as $j) {
        $bayar_nominal = $j['total_tagih'] + $j['_denda'];
        $stmt = $conn->prepare("UPDATE ksp_jadwal_angsuran SET bayar_nominal=?, denda=?, status='lunas', tanggal_bayar=? WHERE id=?");
        $stmt->bind_param("ddsi", $bayar_nominal, $j['_denda'], $tanggal_bayar, $j['id']);
        $stmt->execute();
        $total_kas += $bayar_nominal;
      }
      
      // Insert single kas entry for pelunasan
      $stmt2 = $conn->prepare("INSERT INTO ksp_kas(no_kas,tanggal,jenis,sumber,ref_table,ref_id,nominal,user_id,catatan) VALUES(?,?,?,?,?,?,?,?,?)");
      $no_kas = gen_number('prefix_no_kas');
      $jenis = 'masuk'; $sumber='pelunasan'; $ref_table='ksp_pinjaman'; $ref_id=$pinjaman_id; $uid=user()['id']; 
      $cat='Pelunasan '.$p['no_pinjaman'].' ('.count($items).' angsuran)';
      $stmt2->bind_param("sssssiids", $no_kas, now(), $jenis, $sumber, $ref_table, $ref_id, $total_kas, $uid, $cat);
      $stmt2->execute();
      
      // Update loan status
      $conn->query("UPDATE ksp_pinjaman SET status='lunas' WHERE id=$pinjaman_id");
      
      alert('success','Pelunasan berhasil! Total: Rp '.number_format($total_kas,0,',','.'));
    } else {
      alert('danger','Jumlah pelunasan tidak mencukupi atau tidak ada tagihan.');
    }
  } else {
    alert('danger','Pinjaman tidak ditemukan atau sudah lunas.');
  }
  $redirect_no = $p ? urlencode($p['no_pinjaman']) : '';
  header("Location: index.php?m=angsuran&no=".$redirect_no); exit;
}

// Cari pinjaman (no)
$pinjaman = null; $next = null; $loan_stats = null;
if (isset($_GET['no'])) {
  $no = $conn->real_escape_string($_GET['no']);
  $pinjaman = $conn->query("SELECT p.*, a.nama as anggota_nama, a.hp as anggota_hp FROM ksp_pinjaman p JOIN ksp_anggota a ON a.id=p.anggota_id WHERE p.no_pinjaman='$no'")->fetch_assoc();
  if ($pinjaman) {
    $next = $conn->query("SELECT * FROM ksp_jadwal_angsuran WHERE pinjaman_id=".$pinjaman['id']." AND status='belum' ORDER BY hari_ke LIMIT 1")->fetch_assoc();
    
    // Hitung statistik pinjaman
    $stats = $conn->query("SELECT 
      COUNT(*) as total_periode,
      SUM(CASE WHEN status='lunas' THEN 1 ELSE 0 END) as sudah_bayar,
      SUM(CASE WHEN status='belum' THEN 1 ELSE 0 END) as belum_bayar,
      SUM(CASE WHEN status='lunas' THEN bayar_nominal ELSE 0 END) as total_dibayar,
      SUM(total_tagih) as total_harus_bayar
    FROM ksp_jadwal_angsuran WHERE pinjaman_id=".$pinjaman['id'])->fetch_assoc();
    
    // Hitung total pinjaman dengan bunga (jika kolom baru belum ada, hitung manual)
    $total_pinjaman = isset($pinjaman['total_pinjaman']) && $pinjaman['total_pinjaman'] > 0 
      ? $pinjaman['total_pinjaman'] 
      : $pinjaman['pokok'] + ($pinjaman['pokok'] * $pinjaman['bunga_persen'] / 100);
    
    $loan_stats = array(
      'total_periode' => (int)$stats['total_periode'],
      'sudah_bayar' => (int)$stats['sudah_bayar'],
      'belum_bayar' => (int)$stats['belum_bayar'],
      'total_dibayar' => (float)$stats['total_dibayar'],
      'total_pinjaman' => $total_pinjaman,
      'sisa_pinjaman' => max(0, $total_pinjaman - (float)$stats['total_dibayar'])
    );
  }
}
?>
<h4>Angsuran Harian</h4>
<div class="card card-widget mb-3">
  <div class="card-body">
    <form class="row g-2" method="get">
      <input type="hidden" name="m" value="angsuran">
      <div class="col-md-4">
        <label class="form-label">No. Pinjaman</label>
        <input class="form-control" name="no" placeholder="mis. PNJ-YYYYMMDD-001">
      </div>
      <div class="col-md-2 align-self-end"><button class="btn btn-gradient w-100">Cari</button></div>
    </form>
  </div>
</div>

<?php if ($pinjaman && $loan_stats): 
  $tipe_angsuran = isset($pinjaman['tipe_angsuran']) ? $pinjaman['tipe_angsuran'] : 'harian';
  $satuan = $tipe_angsuran == 'harian' ? 'hari' : ($tipe_angsuran == 'mingguan' ? 'minggu' : 'kali');
?>
<!-- Info Pinjaman Lengkap -->
<div class="card card-widget mb-3">
  <div class="card-header bg-primary text-white">
    <h6 class="mb-0"><i class="bi bi-info-circle me-2"></i>Detail Pinjaman - <?php echo htmlspecialchars($pinjaman['anggota_nama']); ?></h6>
  </div>
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-3">
        <div class="border rounded p-3 h-100">
          <div class="text-secondary small">No. Pinjaman</div>
          <div class="fw-bold text-primary"><?php echo htmlspecialchars($pinjaman['no_pinjaman']); ?></div>
          <div class="text-muted small mt-1">Tgl: <?php echo $pinjaman['tanggal_realisasi']; ?></div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="border rounded p-3 h-100">
          <div class="text-secondary small">Total Pinjaman (+ Bunga <?php echo (int)$pinjaman['bunga_persen']; ?>%)</div>
          <div class="fw-bold text-primary fs-5">Rp <?php echo rupiah($loan_stats['total_pinjaman']); ?></div>
          <div class="text-muted small">Pokok: Rp <?php echo rupiah($pinjaman['pokok']); ?></div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="border rounded p-3 h-100 bg-success bg-opacity-10">
          <div class="text-secondary small">Sudah Dibayar</div>
          <div class="fw-bold text-success fs-5">Rp <?php echo rupiah($loan_stats['total_dibayar']); ?></div>
          <div class="text-muted small"><?php echo $loan_stats['sudah_bayar']; ?> dari <?php echo $loan_stats['total_periode']; ?> <?php echo $satuan; ?></div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="border rounded p-3 h-100 bg-danger bg-opacity-10">
          <div class="text-secondary small">Sisa Pinjaman</div>
          <div class="fw-bold text-danger fs-5">Rp <?php echo rupiah($loan_stats['sisa_pinjaman']); ?></div>
          <div class="text-muted small"><?php echo $loan_stats['belum_bayar']; ?> <?php echo $satuan; ?> lagi</div>
        </div>
      </div>
    </div>
    
    <!-- Progress Bar -->
    <?php $progress = $loan_stats['total_periode'] > 0 ? round(($loan_stats['sudah_bayar'] / $loan_stats['total_periode']) * 100) : 0; ?>
    <div class="mt-3">
      <div class="d-flex justify-content-between small mb-1">
        <span>Progress Pembayaran</span>
        <span><?php echo $progress; ?>%</span>
      </div>
      <div class="progress" style="height: 10px;">
        <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $progress; ?>%"></div>
      </div>
    </div>
  </div>
</div>

<?php if ($next): 
  $due = strtotime($next['tanggal_jatuh_tempo']);
  $today = strtotime(date('Y-m-d'));
  $over = max(0, floor(($today - $due)/86400));
  $denda_per_hari = (float)setting_get('denda_fixed_per_hari','1000');
  $denda = $over * $denda_per_hari;
  $total_due = $next['total_tagih'] + $denda;
?>
<!-- Form Bayar -->
<div class="card card-widget">
  <div class="card-header">
    <h6 class="mb-0"><i class="bi bi-cash-coin me-2"></i>Bayar Angsuran ke-<?php echo (int)$next['hari_ke']; ?></h6>
  </div>
  <div class="card-body">
    <div class="row g-3 mb-3">
      <div class="col-md-3">
        <div class="text-secondary small">Angsuran ke</div>
        <div class="fw-bold"><?php echo (int)$next['hari_ke']; ?> dari <?php echo $loan_stats['total_periode']; ?></div>
      </div>
      <div class="col-md-3">
        <div class="text-secondary small">Jatuh Tempo</div>
        <div class="fw-bold"><?php echo htmlspecialchars($next['tanggal_jatuh_tempo']); ?></div>
        <?php if ($over > 0): ?><span class="badge bg-danger"><?php echo $over; ?> hari terlambat</span><?php endif; ?>
      </div>
      <div class="col-md-3">
        <div class="text-secondary small">Tagihan</div>
        <div class="fw-bold">Rp <?php echo rupiah($next['total_tagih']); ?></div>
      </div>
      <div class="col-md-3">
        <div class="text-secondary small">Denda Keterlambatan</div>
        <div class="fw-bold <?php echo $denda > 0 ? 'text-danger' : ''; ?>">Rp <?php echo rupiah($denda); ?></div>
      </div>
    </div>
    <hr>
    <form method="post" class="row g-2">
      <?php echo csrf_input(); ?>
      <input type="hidden" name="jadwal_id" value="<?php echo $next['id']; ?>">
      <div class="col-md-4">
        <label class="form-label">Total Bayar (Rp)</label>
        <input type="number" step="1000" name="bayar_nominal" class="form-control form-control-lg" value="<?php echo (int)$total_due; ?>" required>
      </div>
      <div class="col-md-2 align-self-end"><button name="bayar" class="btn btn-gradient btn-lg w-100"><i class="bi bi-check-lg"></i> Bayar</button></div>
      <div class="col-md-3 align-self-end"><a target="_blank" class="btn btn-outline-primary w-100" href="print/struk_angsuran.php?id=<?php echo $next['id']; ?>&w=58">Cetak 58mm</a></div>
      <div class="col-md-3 align-self-end"><a target="_blank" class="btn btn-outline-primary w-100" href="print/struk_angsuran.php?id=<?php echo $next['id']; ?>&w=80">Cetak 80mm</a></div>
    </form>
    
    <!-- Pelunasan Langsung -->
    <?php if ($loan_stats['sisa_pinjaman'] > 0): ?>
    <hr>
    <div class="bg-warning bg-opacity-10 border border-warning rounded p-3 mt-3">
      <h6 class="text-warning"><i class="bi bi-lightning-charge me-2"></i>Pelunasan Langsung</h6>
      <p class="small text-muted mb-2">Lunasi semua sisa tagihan sekaligus (<?php echo $loan_stats['belum_bayar']; ?> angsuran tersisa)</p>
      <form method="post" class="row g-2" onsubmit="return confirm('Yakin ingin melunasi seluruh sisa pinjaman Rp <?php echo rupiah($loan_stats['sisa_pinjaman']); ?>?')">
        <?php echo csrf_input(); ?>
        <input type="hidden" name="pinjaman_id" value="<?php echo $pinjaman['id']; ?>">
        <input type="hidden" name="total_pelunasan" value="<?php echo $loan_stats['sisa_pinjaman']; ?>">
        <div class="col-md-6">
          <div class="input-group">
            <span class="input-group-text">Total Pelunasan</span>
            <input type="text" class="form-control form-control-lg fw-bold text-danger" value="Rp <?php echo rupiah($loan_stats['sisa_pinjaman']); ?>" readonly>
          </div>
        </div>
        <div class="col-md-3 align-self-end">
          <button name="pelunasan" class="btn btn-warning btn-lg w-100"><i class="bi bi-check-all me-1"></i> Lunasi Semua</button>
        </div>
        <div class="col-md-3 align-self-end">
          <a href="print/bukti_pelunasan.php?id=<?php echo $pinjaman['id']; ?>" target="_blank" class="btn btn-outline-warning w-100">Preview Bukti</a>
        </div>
      </form>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php else: ?>
<div class="alert alert-success"><i class="bi bi-check-circle me-2"></i> Pinjaman ini sudah LUNAS. Selamat!</div>
<?php endif; ?>

<!-- Tombol Cetak -->
<div class="card card-widget mt-3">
  <div class="card-header">
    <h6 class="mb-0"><i class="bi bi-printer me-2"></i>Cetak Dokumen</h6>
  </div>
  <div class="card-body">
    <div class="row g-2">
      <div class="col-md-3">
        <a href="print/bukti_pinjaman.php?id=<?php echo $pinjaman['id']; ?>" target="_blank" class="btn btn-outline-primary w-100">
          <i class="bi bi-file-text me-1"></i> Bukti Pengajuan Pinjaman
        </a>
      </div>
      <div class="col-md-3">
        <a href="print/kartu_angsuran.php?id=<?php echo $pinjaman['id']; ?>" target="_blank" class="btn btn-outline-success w-100">
          <i class="bi bi-card-list me-1"></i> Kartu Angsuran
        </a>
      </div>
      <div class="col-md-3">
        <a href="print/riwayat_angsuran.php?id=<?php echo $pinjaman['id']; ?>" target="_blank" class="btn btn-outline-info w-100">
          <i class="bi bi-clock-history me-1"></i> Riwayat Pembayaran
        </a>
      </div>
      <div class="col-md-3">
        <?php if ($pinjaman['status'] == 'lunas'): ?>
        <a href="print/bukti_lunas.php?id=<?php echo $pinjaman['id']; ?>" target="_blank" class="btn btn-success w-100">
          <i class="bi bi-patch-check me-1"></i> Surat Keterangan Lunas
        </a>
        <?php else: ?>
        <button class="btn btn-secondary w-100" disabled><i class="bi bi-patch-check me-1"></i> Surat Lunas (Belum Lunas)</button>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Riwayat Angsuran Pinjaman Ini -->
<div class="card card-widget mt-3">
  <div class="card-header">
    <h6 class="mb-0"><i class="bi bi-clock-history me-2"></i>Riwayat Angsuran</h6>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-sm table-striped">
        <thead><tr><th>Ke</th><th>Jatuh Tempo</th><th>Tagihan</th><th>Dibayar</th><th>Denda</th><th>Tgl Bayar</th><th>Status</th><th>Aksi</th></tr></thead>
        <tbody>
        <?php
        $riwayat = $conn->query("SELECT * FROM ksp_jadwal_angsuran WHERE pinjaman_id=".$pinjaman['id']." ORDER BY hari_ke");
        while($rw=$riwayat->fetch_assoc()):
          $badge = $rw['status']=='lunas' ? 'bg-success' : 'bg-warning';
        ?>
        <tr>
          <td><?php echo $rw['hari_ke']; ?></td>
          <td><?php echo $rw['tanggal_jatuh_tempo']; ?></td>
          <td>Rp <?php echo rupiah($rw['total_tagih']); ?></td>
          <td>Rp <?php echo rupiah($rw['bayar_nominal']); ?></td>
          <td>Rp <?php echo rupiah($rw['denda']); ?></td>
          <td><?php echo $rw['tanggal_bayar'] ?: '-'; ?></td>
          <td><span class="badge <?php echo $badge; ?>"><?php echo $rw['status']; ?></span></td>
          <td>
            <?php if($rw['status']=='lunas'): ?>
            <a href="print/kwitansi.php?id=<?php echo $rw['id']; ?>" target="_blank" class="btn btn-sm btn-outline-success" title="Cetak Kwitansi"><i class="bi bi-printer"></i></a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php elseif(isset($_GET['no'])): ?>
<div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>Pinjaman tidak ditemukan.</div>
<?php endif; ?>

<div class="card card-widget mt-3">
  <div class="card-body">
    <h6>Jadwal Belum Lunas (30 terakhir)</h6>
    <div class="table-responsive">
      <table class="table table-striped table-sm">
        <thead><tr><th>No. Pinjaman</th><th>Anggota</th><th>HP</th><th>Jatuh Tempo</th><th>Total</th><th>Status</th><th>WA</th></tr></thead>
        <tbody>
        <?php
        $q = $conn->query("SELECT p.no_pinjaman, j.*, a.nama, a.hp FROM ksp_jadwal_angsuran j JOIN ksp_pinjaman p ON p.id=j.pinjaman_id JOIN ksp_anggota a ON a.id=p.anggota_id WHERE j.status='belum' ORDER BY j.tanggal_jatuh_tempo ASC, j.hari_ke ASC LIMIT 30");
        while($r=$q->fetch_assoc()): ?>
          <?php $wa = preg_replace('/\D+/', '', $r['hp']); if (strpos($wa,'0')===0) { $wa='62'.substr($wa,1); } $wa_text = rawurlencode('Halo '.$r['nama'].', ini '.setting_get('company_name','Koperasi').'. Tagihan harian tanggal '.$r['tanggal_jatuh_tempo'].' sebesar Rp '.rupiah($r['total_tagih']).'. Terima kasih.'); ?>
<tr>
<td><a href="index.php?m=angsuran&no=<?php echo urlencode($r['no_pinjaman']); ?>"><?php echo htmlspecialchars($r['no_pinjaman']); ?></a></td>
<td><?php echo htmlspecialchars($r['nama']); ?></td>
<td><?php echo htmlspecialchars($r['hp']); ?></td>
<td><?php echo htmlspecialchars($r['tanggal_jatuh_tempo']); ?></td>
<td>Rp <?php echo rupiah($r['total_tagih']); ?></td>
<td><span class="badge text-bg-warning">belum</span></td>
<td><a target="_blank" class="btn btn-sm btn-outline-secondary" href="https://web.whatsapp.com/send?phone=<?php echo $wa; ?>&text=<?php echo $wa_text; ?>">WA</a></td>
</tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

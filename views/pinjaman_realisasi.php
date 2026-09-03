<?php
// views/pinjaman_realisasi.php
csrf_check();
$conn = db();

if ($_SERVER['REQUEST_METHOD']==='POST') {
  if (!function_exists('angka_input')) {
    function angka_input($value) {
      return (int)preg_replace('/\D/', '', (string)($value ?? 0));
    }
  }

  $anggota_id = (int)$_POST['anggota_id'];
  $produk_id = (int)$_POST['produk_id'];
  $tgl = $_POST['tanggal_realisasi'];
  $pokok = angka_input($_POST['pokok'] ?? 0);
  $tenor = (int)$_POST['tenor'];
  $tipe_angsuran = $_POST['tipe_angsuran'];
  $bp = (int)$_POST['bunga_persen'];
  $pot_tab_persen = (int)$_POST['potongan_tabungan'];
  $admin = angka_input($_POST['admin_biaya'] ?? 0);
  $tgl_jatuh_tempo = isset($_POST['tanggal_jatuh_tempo']) ? $_POST['tanggal_jatuh_tempo'] : null;
  
  // === CEK BLACKLIST ===
  $blacklisted = $conn->query("SELECT id FROM ksp_blacklist WHERE anggota_id = $anggota_id AND status = 'aktif'")->num_rows;
  if ($blacklisted > 0) {
    alert('danger', 'DITOLAK: Anggota ini masuk dalam daftar BLACKLIST!');
    header("Location: index.php?m=pinjaman_realisasi"); exit;
  }
  
  // === CEK JENIS PEMINJAM (KELOMPOK) ===
  $produk_info = $conn->query("SELECT jenis_peminjam, tipe_pinjaman FROM ksp_produk_pinjaman WHERE id = $produk_id")->fetch_assoc();
  if ($produk_info && $produk_info['jenis_peminjam'] == 'kelompok') {
    // Cek apakah anggota adalah ketua kelompok
    $is_ketua = $conn->query("SELECT is_ketua, kelompok_id FROM ksp_anggota WHERE id = $anggota_id")->fetch_assoc();
    if (!$is_ketua['is_ketua'] || !$is_ketua['kelompok_id']) {
      alert('danger', 'DITOLAK: Produk pinjaman ini hanya untuk KETUA KELOMPOK. Anggota ini bukan ketua kelompok.');
      header("Location: index.php?m=pinjaman_realisasi"); exit;
    }
  }
  
  // Validasi tenor untuk harian/mingguan
  if ($tipe_angsuran != 'tempo' && $tenor <= 0) {
    alert('danger', 'Tenor harus lebih dari 0.');
    header("Location: index.php?m=pinjaman_realisasi"); exit;
  }
  
  // Validasi pokok harus > 0
  if ($pokok <= 0) {
    alert('danger', 'Pokok pinjaman harus lebih dari 0.');
    header("Location: index.php?m=pinjaman_realisasi"); exit;
  }

  // === VALIDASI LIMIT PINJAMAN BERDASARKAN TOTAL SIMPANAN ===
  // Contoh: 3 berarti maksimal pinjaman = 3x total simpanan.
  $batas_kali_simpanan = 3;
  $simpanan = $conn->query("
    SELECT
      COALESCE(simpanan_pokok, 0) AS simpanan_pokok,
      COALESCE(simpanan_wajib, 0) AS simpanan_wajib,
      COALESCE(simpanan_sukarela, 0) AS simpanan_sukarela
    FROM ksp_simpanan_anggota
    WHERE anggota_id = $anggota_id
  ")->fetch_assoc();

  $total_simpanan = $simpanan
    ? ((float)$simpanan['simpanan_pokok'] + (float)$simpanan['simpanan_wajib'] + (float)$simpanan['simpanan_sukarela'])
    : 0;
  $limit_pinjaman = $total_simpanan * $batas_kali_simpanan;

  if ($total_simpanan <= 0) {
    alert('danger', 'DITOLAK: Anggota belum memiliki simpanan pokok, wajib, atau sukarela.');
    header("Location: index.php?m=pinjaman_realisasi"); exit;
  }

  if ($pokok > $limit_pinjaman) {
    alert('danger', 'DITOLAK: Maksimal pinjaman anggota ini Rp '.number_format($limit_pinjaman, 0, ',', '.').' berdasarkan total simpanan Rp '.number_format($total_simpanan, 0, ',', '.').'.');
    header("Location: index.php?m=pinjaman_realisasi"); exit;
  }
    
  // === KALKULASI SESUAI PERMINTAAN CLIENT ===
  // Contoh: Pokok 500.000, Bunga 20%, Pot Tabungan 5%
  // Total Bunga = 500.000 x 20% = 100.000
  // Total Pinjaman = 500.000 + 100.000 = 600.000
  // Potongan Tabungan = 500.000 x 5% = 25.000
  // Admin = 25.000
  // Dana Diterima = 500.000 - 25.000 (tabungan) - 25.000 (admin) = 450.000
  // Angsuran/hari = 600.000 / 24 = 25.000
  
  $total_bunga = round($pokok * ($bp / 100));
  $total_pinjaman = $pokok + $total_bunga;
  $potongan_tabungan = round($pokok * ($pot_tab_persen / 100));
  $dana_diterima = $pokok - $potongan_tabungan - $admin;
  
  // Hitung angsuran per periode
  if ($tipe_angsuran == 'tempo') {
    $angsuran_per_periode = $total_pinjaman; // Bayar sekaligus
    $tenor = 1; // 1 kali bayar
  } else {
    $angsuran_per_periode = round($total_pinjaman / max(1, $tenor));
  }
  
  $no_pinjaman = gen_number('prefix_no_pinjaman');

  // Insert pinjaman dengan kolom baru
  $stmt = $conn->prepare("INSERT INTO ksp_pinjaman(no_pinjaman, anggota_id, produk_id, tanggal_realisasi, pokok, tenor_hari, bunga_tipe, bunga_persen, total_bunga, total_pinjaman, potongan_tabungan, dana_diterima, angsuran_per_periode, tipe_angsuran, tanggal_jatuh_tempo, admin_biaya, created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
  $uid = user()['id'];
  $bunga_tipe = 'flat'; // metode bunga (enum flat/menurun); tipe angsuran ada di kolom tipe_angsuran
  $stmt->bind_param("siisdisidddddssii", $no_pinjaman, $anggota_id, $produk_id, $tgl, $pokok, $tenor, $bunga_tipe, $bp, $total_bunga, $total_pinjaman, $potongan_tabungan, $dana_diterima, $angsuran_per_periode, $tipe_angsuran, $tgl_jatuh_tempo, $admin, $uid);
  
  if ($stmt->execute()) {
    $pinjaman_id = $conn->insert_id;
    
    // Generate jadwal angsuran
    if ($tipe_angsuran == 'tempo') {
      // Jadwal tempo - 1 kali bayar di tanggal jatuh tempo
      $jatuh = $tgl_jatuh_tempo ? $tgl_jatuh_tempo : date('Y-m-d', strtotime($tgl . " +1 month"));
      $stmt2 = $conn->prepare("INSERT INTO ksp_jadwal_angsuran(pinjaman_id, hari_ke, tanggal_jatuh_tempo, pokok_harian, bunga_harian, total_tagih) VALUES(?,?,?,?,?,?)");
      $i = 1;
      $stmt2->bind_param("iisddd", $pinjaman_id, $i, $jatuh, $pokok, $total_bunga, $total_pinjaman);
      $stmt2->execute();
    } else {
      // Jadwal bulanan - 1 angsuran per bulan selama tenor bulan
      for ($i=1; $i <= $tenor; $i++) {
        $jatuh = date('Y-m-d', strtotime($tgl . " +" . $i . " month -1 month"));
        $stmt2 = $conn->prepare("INSERT INTO ksp_jadwal_angsuran(pinjaman_id, hari_ke, tanggal_jatuh_tempo, pokok_harian, bunga_harian, total_tagih) VALUES(?,?,?,?,?,?)");
        $pokok_per = round($pokok / $tenor);
        $bunga_per = round($total_bunga / $tenor);
        $total_per = $angsuran_per_periode;
        $stmt2->bind_param("iisddd", $pinjaman_id, $i, $jatuh, $pokok_per, $bunga_per, $total_per);
        $stmt2->execute();
      }
    }
    
    // === INTEGRASI: Potongan tabungan masuk ke simpanan wajib ===
    if ($potongan_tabungan > 0) {
      // Cek/buat record simpanan anggota
      $cek_simp = $conn->query("SELECT id FROM ksp_simpanan_anggota WHERE anggota_id = $anggota_id")->fetch_assoc();
      if (!$cek_simp) {
        $conn->query("INSERT INTO ksp_simpanan_anggota (anggota_id, simpanan_wajib, total_simpanan) VALUES ($anggota_id, $potongan_tabungan, $potongan_tabungan)");
      } else {
        $conn->query("UPDATE ksp_simpanan_anggota SET simpanan_wajib = simpanan_wajib + $potongan_tabungan, total_simpanan = total_simpanan + $potongan_tabungan WHERE anggota_id = $anggota_id");
      }
      // Catat mutasi
      $saldo_baru = $conn->query("SELECT total_simpanan FROM ksp_simpanan_anggota WHERE anggota_id = $anggota_id")->fetch_assoc()['total_simpanan'];
      $conn->query("INSERT INTO ksp_mutasi_simpanan (anggota_id, tanggal, jenis_simpanan, tipe, nominal, saldo_akhir, keterangan, user_id) VALUES ($anggota_id, '$tgl', 'wajib', 'setor', $potongan_tabungan, $saldo_baru, 'Potongan dari pinjaman $no_pinjaman', $uid)");
    }
    
    log_activity('realisasi_pinjaman', 'pinjaman', 'Realisasi pinjaman ' . $no_pinjaman);
    alert('success', 'Pinjaman direalisasi! No: '.$no_pinjaman.' | Total: Rp '.number_format($total_pinjaman,0,',','.').', Dana Cair: Rp '.number_format($dana_diterima,0,',','.'));
  } else {
    alert('danger','Gagal simpan pinjaman: '.$conn->error);
  }
  header("Location: index.php?m=pinjaman_realisasi"); exit;
}

$anggota = $conn->query("
  SELECT a.id, a.no_anggota, a.nama,
    (SELECT COUNT(*) FROM ksp_blacklist WHERE anggota_id = a.id AND status = 'aktif') as is_blacklisted,
    (SELECT COUNT(*) FROM ksp_pinjaman WHERE anggota_id = a.id AND status = 'aktif') as pinjaman_aktif
  FROM ksp_anggota a 
  WHERE a.status='aktif' 
  ORDER BY a.nama
");
$produk = $conn->query("SELECT * FROM ksp_produk_pinjaman ORDER BY id DESC");
?>
<h4>Realisasi Pinjaman</h4>
<?php if (!empty($_SESSION['print_pinjaman_id'])): $pp=(int)$_SESSION['print_pinjaman_id']; unset($_SESSION['print_pinjaman_id']); ?>
<div class='alert alert-info d-flex justify-content-between align-items-center'>
  <div><i class='bi bi-printer me-2'></i> Pinjaman berhasil direalisasi. Cetak perjanjian A4:</div>
  <div><a target='_blank' class='btn btn-sm btn-outline-primary' href='print/perjanjian_a4.php?id=<?php echo $pp; ?>'>Cetak A4</a></div>
</div>
<?php endif; ?>
<div class="card card-widget">
  <div class="card-body">
    <form method="post" id="formRealisasi">
      <?php echo csrf_input(); ?>
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">Anggota</label>
          <select name="anggota_id" class="form-select select2" required id="anggota_select">
            <option value="">- pilih anggota -</option>
            <?php while($a=$anggota->fetch_assoc()): 
              $warning = '';
              if ($a['is_blacklisted']) $warning = '⛔ BLACKLIST - ';
              elseif ($a['pinjaman_aktif'] > 0) $warning = '⚠️ Pinjaman Aktif: '.$a['pinjaman_aktif'].' - ';
            ?>
              <option value="<?php echo $a['id']; ?>" 
                data-blacklist="<?php echo $a['is_blacklisted']; ?>"
                <?php echo $a['is_blacklisted'] ? 'style="color:red;font-weight:bold;"' : ''; ?>>
                <?php echo $warning . htmlspecialchars($a['nama'].' ('.$a['no_anggota'].')'); ?>
              </option>
            <?php endwhile; ?>
          </select>
          <div id="anggotaWarning" class="text-danger small mt-1" style="display:none;"></div>
        </div>
        <div class="col-md-4">
          <label class="form-label">Produk Pinjaman</label>
          <select name="produk_id" id="produk_id" class="form-select" required>
            <option value="">- pilih produk -</option>
            <?php while($p=$produk->fetch_assoc()): 
              $pot_tab = isset($p['potongan_tabungan']) ? $p['potongan_tabungan'] : 0;
            ?>
              <option value="<?php echo $p['id']; ?>" 
                data-tenor="<?php echo $p['tenor_hari_default']; ?>" 
                data-tipe="<?php echo $p['bunga_tipe']; ?>" 
                data-bunga="<?php echo (int)$p['bunga_persen']; ?>" 
                data-pottab="<?php echo $pot_tab; ?>"
                data-admin="<?php echo (int)$p['admin_biaya']; ?>"><?php echo htmlspecialchars($p['nama']); ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="col-md-2"><label class="form-label">Tgl Realisasi</label><input type="date" name="tanggal_realisasi" class="form-control" value="<?php echo date('Y-m-d'); ?>" required></div>
        <div class="col-md-2"><label class="form-label">Pokok Pinjaman (Rp)</label><input type="text" name="pokok_display" id="pokok" class="form-control rupiah-input" data-target="pokok" inputmode="numeric" autocomplete="off"  placeholder="500000" required><input type="hidden" name="pokok"></div>
        
        <div class="col-md-2">
          <label class="form-label">Tipe Angsuran</label>
          <select name="tipe_angsuran" id="tipe_angsuran" class="form-select">
            <option value="bulanan" selected>Bulanan</option>
            <option value="tempo">Tempo (Bayar Langsung)</option>
          </select>
        </div>
        <div class="col-md-2" id="tenorDiv"><label class="form-label" id="tenorLabel">Tenor (bulan)</label><input type="number" id="tenor" name="tenor" class="form-control" value="3"></div>
        <div class="col-md-2" id="jatuhTempoDiv" style="display:none;"><label class="form-label">Tgl Jatuh Tempo</label><input type="date" id="tanggal_jatuh_tempo" name="tanggal_jatuh_tempo" class="form-control" value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>"></div>
        <div class="col-md-2"><label class="form-label">Bunga (%)</label><input type="number" step="1" id="bunga_persen" name="bunga_persen" class="form-control" value="20" required></div>
        <div class="col-md-2"><label class="form-label">Pot. Tabungan (%)</label><input type="number" step="1" id="potongan_tabungan" name="potongan_tabungan" class="form-control" value="5"></div>
        <div class="col-md-2"><label class="form-label">Biaya Admin (Rp)</label><input type="text" name="admin_biaya_display" id="admin_biaya" class="form-control rupiah-input" data-target="admin_biaya" value="25.000" inputmode="numeric" autocomplete="off"><input type="hidden" name="admin_biaya" value="25000"></div>
      </div>
      
      <!-- Simulasi Kalkulasi -->
      <div class="card bg-light mt-3">
        <div class="card-body py-2">
          <h6 class="mb-2"><i class="bi bi-calculator"></i> Simulasi Perhitungan</h6>
          <div class="row g-2 small">
            <div class="col-md-2"><strong>Pokok:</strong> <span id="simPokok">Rp 0</span></div>
            <div class="col-md-2"><strong>Bunga (<span id="simBungaPersen">0</span>%):</strong> <span id="simBunga">Rp 0</span></div>
            <div class="col-md-2"><strong>Total Pinjaman:</strong> <span id="simTotal" class="text-primary fw-bold">Rp 0</span></div>
            <div class="col-md-2"><strong>Pot. Tabungan:</strong> <span id="simPotTab">Rp 0</span></div>
            <div class="col-md-2"><strong>Biaya Admin:</strong> <span id="simAdmin">Rp 0</span></div>
            <div class="col-md-2"><strong>Dana Diterima:</strong> <span id="simDana" class="text-success fw-bold">Rp 0</span></div>
            <div class="col-12 mt-1"><strong>Angsuran per <span id="simPeriodeLabel">hari</span>:</strong> <span id="simAngsuran" class="text-danger fw-bold">Rp 0</span> x <span id="simTenor">0</span> <span id="simPeriodeSatuan">hari</span></div>
          </div>
        </div>
      </div>
      
      <div class="mt-3"><button class="btn btn-gradient btn-lg"><i class="bi bi-check-lg me-1"></i> Realisasi Pinjaman</button></div>
    </form>
  </div>
</div>
<script>
function formatRp(n) { return 'Rp ' + n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, "."); }
function parseAngkaRupiah(value) {
  return parseInt(String(value || '').replace(/\D/g, ''), 10) || 0;
}

function hitungSimulasi() {
  var pokok = parseAngkaRupiah(document.getElementById('pokok').value);
  var bunga = parseInt(document.getElementById('bunga_persen').value) || 0;
  var potTab = parseInt(document.getElementById('potongan_tabungan').value) || 0;
  var admin = parseAngkaRupiah(document.getElementById('admin_biaya').value);
  var tenor = parseInt(document.getElementById('tenor').value) || 1;
  var tipe = document.getElementById('tipe_angsuran').value;
  
  var totalBunga = Math.round(pokok * bunga / 100);
  var totalPinjaman = pokok + totalBunga;
  var potonganTabungan = Math.round(pokok * potTab / 100);
  var danaDiterima = pokok - potonganTabungan - admin;
  var angsuran = tipe == 'tempo' ? totalPinjaman : Math.round(totalPinjaman / Math.max(1, tenor));
  
  document.getElementById('simPokok').textContent = formatRp(pokok);
  document.getElementById('simBungaPersen').textContent = bunga;
  document.getElementById('simBunga').textContent = formatRp(totalBunga);
  document.getElementById('simTotal').textContent = formatRp(totalPinjaman);
  document.getElementById('simPotTab').textContent = formatRp(potonganTabungan);
  document.getElementById('simAdmin').textContent = formatRp(admin);
  document.getElementById('simDana').textContent = formatRp(danaDiterima);
  document.getElementById('simAngsuran').textContent = formatRp(angsuran);
  document.getElementById('simTenor').textContent = tipe == 'tempo' ? '1' : tenor;
  
  var satuan = tipe == 'tempo' ? 'kali' : 'bulan';
  document.getElementById('simPeriodeLabel').textContent = satuan;
  document.getElementById('simPeriodeSatuan').textContent = satuan;
}

document.getElementById('produk_id').addEventListener('change', function(){
  var opt = this.options[this.selectedIndex];
  if (opt.value) {
    document.getElementById('tenor').value = opt.getAttribute('data-tenor');
    document.getElementById('tipe_angsuran').value = opt.getAttribute('data-tipe') || 'bulanan';
    document.getElementById('bunga_persen').value = opt.getAttribute('data-bunga');
    document.getElementById('potongan_tabungan').value = opt.getAttribute('data-pottab') || 0;
    document.getElementById('admin_biaya').value = opt.getAttribute('data-admin');
    if (typeof syncRupiahInput === 'function') {
      syncRupiahInput(document.getElementById('admin_biaya'));
    }
    updateTipeAngsuran();
    hitungSimulasi();
  }
});

document.getElementById('tipe_angsuran').addEventListener('change', updateTipeAngsuran);

function updateTipeAngsuran() {
  var tipe = document.getElementById('tipe_angsuran').value;
  var tenorDiv = document.getElementById('tenorDiv');
  var jatuhTempoDiv = document.getElementById('jatuhTempoDiv');
  var tenorLabel = document.getElementById('tenorLabel');
  
  if (tipe == 'tempo') {
    tenorDiv.style.display = 'none';
    jatuhTempoDiv.style.display = 'block';
  } else {
    tenorDiv.style.display = 'block';
    jatuhTempoDiv.style.display = 'none';
    tenorLabel.textContent = 'Tenor (bulan)';
  }
  hitungSimulasi();
}

// Event listeners untuk simulasi real-time
['pokok', 'bunga_persen', 'potongan_tabungan', 'admin_biaya', 'tenor'].forEach(function(id) {
  document.getElementById(id).addEventListener('input', hitungSimulasi);
});
</script>

<!-- Daftar Pinjaman -->
<div class="card card-widget mt-3">
  <div class="card-body">
    <h6>Daftar Pinjaman (20 Terbaru)</h6>
    <div class="table-responsive">
      <table class="table table-striped table-sm">
        <thead><tr><th>No. Pinjaman</th><th>Anggota</th><th>Produk</th><th>Tgl Realisasi</th><th>Pokok</th><th>Tenor</th><th>Status</th><th>Aksi</th></tr></thead>
        <tbody>
        <?php
        $plist = $conn->query("SELECT p.*, a.nama as anggota_nama, pr.nama as produk_nama FROM ksp_pinjaman p JOIN ksp_anggota a ON a.id=p.anggota_id JOIN ksp_produk_pinjaman pr ON pr.id=p.produk_id ORDER BY p.id DESC LIMIT 20");
        while($row=$plist->fetch_assoc()):
          $badge = $row['status']=='aktif' ? 'text-bg-primary' : ($row['status']=='lunas' ? 'text-bg-success' : 'text-bg-danger');
        ?>
          <tr>
            <td><?php echo htmlspecialchars($row['no_pinjaman']); ?></td>
            <td><?php echo htmlspecialchars($row['anggota_nama']); ?></td>
            <td><?php echo htmlspecialchars($row['produk_nama']); ?></td>
            <td><?php echo htmlspecialchars($row['tanggal_realisasi']); ?></td>
            <td>Rp <?php echo rupiah($row['pokok']); ?></td>
            <td><?php echo $row['tenor_hari']; ?> hari</td>
            <td><span class="badge <?php echo $badge; ?>"><?php echo $row['status']; ?></span></td>
            <td>
              <a class="btn btn-sm btn-outline-primary" href="index.php?m=angsuran&no=<?php echo urlencode($row['no_pinjaman']); ?>">Bayar</a>
              <a class="btn btn-sm btn-outline-secondary" href="print/perjanjian_a4.php?id=<?php echo $row['id']; ?>" target="_blank">Print</a>
            </td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

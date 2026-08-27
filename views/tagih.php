<?php
csrf_check(); $conn = db();
// list kolektor
$kolektor = $conn->query("SELECT id, kode, nama FROM ksp_kolektor WHERE status='aktif' ORDER BY nama");
// generate lembar
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']=='generate') {
  $tgl = $_POST['tanggal']; $kolektor_id = (int)$_POST['kolektor_id'];
  $stmt = $conn->prepare("INSERT INTO ksp_tagih_harian(tanggal,kolektor_id,created_by) VALUES(?,?,?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)"); $uid=user()['id'];
  $stmt->bind_param("sii", $tgl, $kolektor_id, $uid); $stmt->execute(); $tagih_id = $conn->insert_id;
  $sql = "SELECT j.id AS jadwal_id, j.total_tagih, p.anggota_id
          FROM ksp_jadwal_angsuran j JOIN ksp_pinjaman p ON p.id=j.pinjaman_id
          WHERE j.tanggal_jatuh_tempo=? AND j.status='belum'
            AND j.id NOT IN (SELECT jadwal_id FROM ksp_tagih_item WHERE tagih_id=?)";
  $stmt2 = $conn->prepare($sql); $stmt2->bind_param("si", $tgl, $tagih_id); $stmt2->execute(); $res = $stmt2->get_result();
  while($row=$res->fetch_assoc()){
    $stmt3 = $conn->prepare("INSERT IGNORE INTO ksp_tagih_item(tagih_id,jadwal_id,anggota_id,nominal_hari_ini) VALUES(?,?,?,?)");
    $nom = $row['total_tagih']; $stmt3->bind_param("iiid", $tagih_id, $row['jadwal_id'], $row['anggota_id'], $nom); $stmt3->execute();
  }
  $_SESSION['print_tagih_id'] = $tagih_id; alert('success','Lembar tagih dibuat/diupdate.');
  header("Location: index.php?m=tagih&tanggal=".$tgl."&kolektor_id=".$kolektor_id); exit;
}
// setor
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']=='setor') {
  $tagih_id = (int)$_POST['tagih_id']; $nominal = (float)$_POST['nominal'];
  $stmt = $conn->prepare("INSERT INTO ksp_kas(no_kas,tanggal,jenis,sumber,ref_table,ref_id,nominal,user_id,catatan) VALUES(?,?,?,?,?,?,?,?,?)");
  $no_kas = gen_number('prefix_no_kas'); $jenis='masuk'; $sumber='angsuran'; $ref_table='ksp_tagih_harian'; $ref_id=$tagih_id; $uid=user()['id']; $cat='Setoran Kolektor #'.$tagih_id;
  $stmt->bind_param("sssssiiss", $no_kas, now(), $jenis, $sumber, $ref_table, $ref_id, $nominal, $uid, $cat); $stmt->execute();
  alert('success','Setoran kolektor tercatat.'); header("Location: index.php?m=tagih&tagih_id=".$tagih_id); exit;
}
$tanggal = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');
$kolektor_id = isset($_GET['kolektor_id']) ? (int)$_GET['kolektor_id'] : 0;
$tagih = null; $items = null;
if ($kolektor_id > 0) {
  $tagih = $conn->query("SELECT * FROM ksp_tagih_harian WHERE kolektor_id=".$kolektor_id." AND tanggal='".$conn->real_escape_string($tanggal)."'")->fetch_assoc();
  if ($tagih) {
    $items = $conn->query("SELECT ti.*, a.nama, a.hp, p.no_pinjaman, j.hari_ke FROM ksp_tagih_item ti
      JOIN ksp_jadwal_angsuran j ON j.id=ti.jadwal_id
      JOIN ksp_pinjaman p ON p.id=j.pinjaman_id
      JOIN ksp_anggota a ON a.id=ti.anggota_id
      WHERE ti.tagih_id=".$tagih['id']." ORDER BY a.nama");
  }
}
?>
<h4>Penagihan Kolektor</h4>
<?php if (!empty($_SESSION['print_tagih_id'])): $tid=(int)$_SESSION['print_tagih_id']; unset($_SESSION['print_tagih_id']); ?>
<div class='alert alert-info d-flex justify-content-between align-items-center'>
  <div>Lembar tagih siap dicetak.</div>
  <div><a class='btn btn-sm btn-outline-primary' target='_blank' href='print/lembar_tagih_a4.php?id=<?php echo $tid; ?>'>Cetak A4</a></div>
</div>
<?php endif; ?>
<div class='card card-widget mb-3'><div class='card-body'>
  <form class='row g-2' method='get'><input type='hidden' name='m' value='tagih'>
    <div class='col-md-3'><label class='form-label'>Tanggal</label><input type='date' name='tanggal' class='form-control' value='<?php echo htmlspecialchars($tanggal); ?>'></div>
    <div class='col-md-4'><label class='form-label'>Kolektor</label><select name='kolektor_id' class='form-select'>
      <option value='0'>- pilih -</option>
      <?php while($k=$kolektor->fetch_assoc()): ?>
      <option value='<?php echo $k['id']; ?>' <?php echo $kolektor_id==$k['id']?'selected':''; ?>><?php echo htmlspecialchars($k['nama'].' ('.$k['kode'].')'); ?></option>
      <?php endwhile; ?>
    </select></div>
    <div class='col-md-2 align-self-end'><button class='btn btn-gradient w-100'>Tampilkan</button></div>
  </form>
</div></div>
<div class='card card-widget'><div class='card-body'>
  <form method='post' class='row g-2'><?php echo csrf_input(); ?><input type='hidden' name='action' value='generate'>
    <div class='col-md-3'><label class='form-label'>Tanggal</label><input type='date' name='tanggal' class='form-control' value='<?php echo htmlspecialchars($tanggal); ?>'></div>
    <div class='col-md-4'><label class='form-label'>Kolektor</label><select name='kolektor_id' class='form-select' required>
      <option value=''>- pilih -</option>
      <?php $kk = db()->query("SELECT id,kode,nama FROM ksp_kolektor WHERE aktif=1 ORDER BY nama"); while($x=$kk->fetch_assoc()): ?>
      <option value='<?php echo $x['id']; ?>' <?php echo $kolektor_id==$x['id']?'selected':''; ?>><?php echo htmlspecialchars($x['nama'].' ('.$x['kode'].')'); ?></option>
      <?php endwhile; ?>
    </select></div>
    <div class='col-md-2 align-self-end'><button class='btn btn-gradient w-100'>Generate</button></div>
    <?php if ($tagih): ?><div class='col-md-3 align-self-end'><a target='_blank' class='btn btn-outline-primary w-100' href='print/lembar_tagih_a4.php?id=<?php echo $tagih['id']; ?>'>Cetak A4</a></div><?php endif; ?>
  </form>
  <?php if ($items): ?>
  <hr>
  <div class='d-flex justify-content-between align-items-center mb-2'>
    <div class='fw-semibold'>Daftar Tagih (<?php echo $items->num_rows; ?>)</div>
    <div>
      <form method='post' class='d-flex gap-2'><?php echo csrf_input(); ?><input type='hidden' name='action' value='setor'>
        <input type='hidden' name='tagih_id' value='<?php echo $tagih['id']; ?>'>
        <input type='number' step='0.01' name='nominal' class='form-control' placeholder='Nominal Setoran'>
        <button class='btn btn-outline-success'>Rekap Setoran</button>
      </form>
    </div>
  </div>
  <div class='table-responsive'>
    <table class='table table-striped table-sm'>
      <thead><tr><th>Anggota</th><th>No. Pinjaman</th><th>Hari ke</th><th>Nominal</th><th>WA</th></tr></thead>
      <tbody>
        <?php while($r=$items->fetch_assoc()):
          $wa = preg_replace('/\D+/', '', $r['hp']); if (strpos($wa,'0')===0) $wa = '62'.substr($wa,1);
          $msg = rawurlencode("Halo ".$r['nama'].", ini ".setting_get('company_name','Koperasi').". Tagihan hari ini Rp".rupiah($r['nominal_hari_ini']).". Terima kasih.");
        ?>
        <tr>
          <td><?php echo htmlspecialchars($r['nama']); ?></td>
          <td><?php echo htmlspecialchars($r['no_pinjaman']); ?></td>
          <td><?php echo (int)$r['hari_ke']; ?></td>
          <td>Rp <?php echo rupiah($r['nominal_hari_ini']); ?></td>
          <td><a target='_blank' class='btn btn-sm btn-outline-secondary' href='https://web.whatsapp.com/send?phone=<?php echo $wa; ?>&text=<?php echo $msg; ?>'>WA</a></td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div></div>
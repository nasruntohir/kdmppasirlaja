<?php
// views/setting_shu.php - Setting SHU (Persentase Pembagian)
csrf_check();
$conn = db();

// Handle update
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='update') {
  $settings = $_POST['setting'] ?? [];
  foreach ($settings as $kode => $persen) {
    $persen = (float)$persen;
    $stmt = $conn->prepare("UPDATE ksp_setting_shu SET persen = ? WHERE kode = ?");
    $stmt->bind_param("ds", $persen, $kode);
    $stmt->execute();
  }
  alert('success', 'Setting SHU berhasil disimpan.');
  header("Location: index.php?m=setting_shu"); exit;
}

$list = $conn->query("SELECT * FROM ksp_setting_shu ORDER BY urutan");
$settings = [];
while ($r = $list->fetch_assoc()) $settings[$r['kode']] = $r;

// Calculate validation
$total_utama = ($settings['cadangan']['persen'] ?? 0) + ($settings['shu_anggota']['persen'] ?? 0) + 
               ($settings['jasa_pengurus']['persen'] ?? 0) + ($settings['jasa_pengawas']['persen'] ?? 0) +
               ($settings['dana_pendidikan']['persen'] ?? 0) + ($settings['dana_sosial']['persen'] ?? 0) +
               ($settings['dana_pengembangan']['persen'] ?? 0);
$total_shu_anggota = ($settings['jasa_modal']['persen'] ?? 0) + ($settings['jasa_usaha']['persen'] ?? 0);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-sliders me-2"></i>Setting Pembagian SHU</h4>
</div>

<div class="alert alert-info">
  <i class="bi bi-info-circle me-1"></i> 
  <strong>Rumus Pembagian SHU:</strong><br>
  Total persentase pembagian utama harus = 100%.<br>
  Jasa Modal + Jasa Usaha (dari SHU Anggota) juga harus = 100%.
</div>

<form method="post">
  <?php echo csrf_input(); ?>
  <input type="hidden" name="action" value="update">
  
  <div class="row">
    <div class="col-md-6">
      <div class="card card-widget mb-3">
        <div class="card-header bg-primary text-white"><strong>Pembagian Utama</strong></div>
        <div class="card-body">
          <table class="table table-sm">
            <thead><tr><th>Komponen</th><th width="120">Persentase</th></tr></thead>
            <tbody>
              <tr>
                <td><i class="bi bi-piggy-bank me-2"></i>Cadangan (Laba Ditahan)</td>
                <td><div class="persen-input-wrap"><input type="number" name="setting[cadangan]"class="form-control text-end persen-utama" value="<?php echo round($settings['cadangan']['persen']); ?>" step="0.01" min="0" max="100"></div></td>
              </tr>
              <tr class="table-success">
                <td><i class="bi bi-people me-2"></i><strong>SHU Anggota</strong></td>
                 <td><div class="persen-input-wrap"><input type="number" name="setting[shu_anggota]"class="form-control text-end persen-utama" value="<?php echo  round($settings['shu_anggota']['persen']); ?>" step="0.01" min="0" max="100"></div></td>
              </tr>
              <tr>
                <td><i class="bi bi-person-badge me-2"></i>Jasa Pengurus</td>
                 <td><div class="persen-input-wrap"><input type="number" name="setting[jasa_pengurus]"class="form-control text-end persen-utama" value="<?php echo  round($settings['jasa_pengurus']['persen']); ?>" step="0.01" min="0" max="100"></div></td>
              </tr>
              <tr>
                <td><i class="bi bi-eye me-2"></i>Jasa Pengawas</td>
                <td><div class="persen-input-wrap"><input type="number" name="setting[jasa_pengawas]"class="form-control text-end persen-utama" value="<?php echo round($settings['jasa_pengawas']['persen']); ?>" step="0.01" min="0" max="100"></div></td>
              </tr>
              <tr>
                <td><i class="bi bi-book me-2"></i>Dana Pendidikan</td>
                 <td><div class="persen-input-wrap"><input type="number" name="setting[dana_pendidikan]"class="form-control text-end persen-utama" value="<?php echo round($settings['dana_pendidikan']['persen']); ?>" step="0.01" min="0" max="100"></div></td>
              </tr>
              <tr>
                <td><i class="bi bi-heart me-2"></i>Dana Sosial</td>
                 <td><div class="persen-input-wrap"><input type="number" name="setting[dana_sosial]"class="form-control text-end persen-utama" value="<?php echo round($settings['dana_sosial']['persen']); ?>" step="0.01" min="0" max="100"></div></td>
              </tr>
              <tr>
                <td><i class="bi bi-graph-up-arrow me-2"></i>Dana Pengembangan Usaha</td>
                <td><div class="persen-input-wrap"><input type="number" name="setting[dana_pengembangan]" class="form-control text-end persen-utama" value="<?php echo round($settings['dana_pengembangan']['persen']); ?>" step="0.01" min="0" max="100"></div></td>
              </tr>
            </tbody>
            <tfoot>
              <tr class="table-dark">
                <th>Total</th>
                <th class="text-end"><span id="totalUtama"><?php echo (int)round($total_utama); ?></span>%</th>
              </tr>
            </tfoot>
          </table>
          <?php if (abs($total_utama - 100) > 0.01): ?>
          <div class="alert alert-danger mb-0"><i class="bi bi-exclamation-triangle me-1"></i> Total harus 100%</div>
          <?php else: ?>
          <div class="alert alert-success mb-0"><i class="bi bi-check-circle me-1"></i> Total sudah 100%</div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    
    <div class="col-md-6">
      <div class="card card-widget mb-3">
        <div class="card-header bg-success text-white"><strong>Pembagian SHU Anggota</strong></div>
        <div class="card-body">
          <p class="text-muted">Dari porsi SHU Anggota (<?php echo $settings['shu_anggota']['persen']; ?>%), dibagi menjadi:</p>
          <table class="table table-sm">
            <thead><tr><th>Komponen</th><th width="120">Persentase</th></tr></thead>
            <tbody>
              <tr>
                <td>
                  <i class="bi bi-coin me-2"></i><strong>Jasa Modal</strong><br>
                  <small class="text-muted">Berdasarkan simpanan pokok + wajib anggota</small>
                </td>
                <td><div class="persen-input-wrap"><input type="number" name="setting[jasa_modal]" class="form-control text-end persen-anggota" value="<?php echo round($settings['jasa_modal']['persen']); ?>" step="1" min="0" max="100"></div></td>
              </tr>
              <tr>
                <td>
                  <i class="bi bi-cart me-2"></i><strong>Jasa Usaha</strong><br>
                  <small class="text-muted">Berdasarkan transaksi pinjaman + penjualan</small>
                </td>
                <td><div class="persen-input-wrap"><input type="number" name="setting[jasa_usaha]" class="form-control text-end persen-anggota" value="<?php echo round($settings['jasa_usaha']['persen']); ?>" step="1" min="0" max="100"></div></td>
              </tr>
            </tbody>
            <tfoot>
              <tr class="table-dark">
                <th>Total</th>
                <th class="text-end"><span id="totalAnggota"><?php echo (int)round($total_shu_anggota); ?></span>%</th>
              </tr>
            </tfoot>
          </table>
          <?php if (abs($total_shu_anggota - 100) > 0.01): ?>
          <div class="alert alert-danger mb-0"><i class="bi bi-exclamation-triangle me-1"></i> Total harus 100%</div>
          <?php else: ?>
          <div class="alert alert-success mb-0"><i class="bi bi-check-circle me-1"></i> Total sudah 100%</div>
          <?php endif; ?>
        </div>
      </div>
      
      <div class="card card-widget">
        <div class="card-header bg-info text-white"><strong>Contoh Perhitungan</strong></div>
        <div class="card-body">
          <p class="mb-2"><strong>Misal Laba = Rp 100.000.000</strong></p>
          <ul class="mb-0">
            <li>Cadangan: <?php echo round($settings['cadangan']['persen']); ?>% = Rp <?php echo rupiah(100000000 * $settings['cadangan']['persen'] / 100); ?></li>
            <li>SHU Anggota: <?php echo round($settings['shu_anggota']['persen']); ?>% = Rp <?php echo rupiah(100000000 * $settings['shu_anggota']['persen'] / 100); ?>
              <ul>
                <li>Jasa Modal: <?php echo round($settings['jasa_modal']['persen']); ?>% = Rp <?php echo rupiah(100000000 * $settings['shu_anggota']['persen'] / 100 * $settings['jasa_modal']['persen'] / 100); ?></li>
                <li>Jasa Usaha: <?php echo round($settings['jasa_usaha']['persen']); ?>% = Rp <?php echo rupiah(100000000 * $settings['shu_anggota']['persen'] / 100 * $settings['jasa_usaha']['persen'] / 100); ?></li>
              </ul>
            </li>
            <li>Jasa Pengurus: <?php echo round($settings['jasa_pengurus']['persen']); ?>% = Rp <?php echo rupiah(100000000 * $settings['jasa_pengurus']['persen'] / 100); ?></li>
          </ul>
        </div>
      </div>
    </div>
  </div>
  
  <div class="mt-3">
    <button type="submit" class="btn btn-gradient btn-lg"><i class="bi bi-save me-1"></i> Simpan Setting</button>
  </div>
</form>

<script>
document.querySelectorAll('.persen-utama').forEach(inp => {
  inp.addEventListener('input', () => {
    let total = 0;
    document.querySelectorAll('.persen-utama').forEach(i => total += parseFloat(i.value) || 0);
    document.getElementById('totalUtama').textContent = Math.round(total);
  });
});
document.querySelectorAll('.persen-anggota').forEach(inp => {
  inp.addEventListener('input', () => {
    let total = 0;
    document.querySelectorAll('.persen-anggota').forEach(i => total += parseFloat(i.value) || 0);
    document.getElementById('totalAnggota').textContent = parseFloat(total.toFixed(2));
  });
});
</script>

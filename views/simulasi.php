<?php
// views/simulasi.php - Kalkulator Simulasi Pinjaman
require_login();

$conn = db();
$products = $conn->query("SELECT * FROM ksp_produk_pinjaman ORDER BY nama");
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4><i class="bi bi-calculator me-2"></i>Simulasi Pinjaman</h4>
</div>

<div class="row g-4">
  <!-- Form Simulasi -->
  <div class="col-lg-5">
    <div class="card card-widget">
      <div class="card-header bg-gradient text-white" style="background: linear-gradient(135deg, #2563eb, #06b6d4);">
        <h5 class="mb-0"><i class="bi bi-sliders me-2"></i>Parameter Pinjaman</h5>
      </div>
      <div class="card-body">
        <div class="mb-3">
          <label class="form-label fw-bold">Produk Pinjaman</label>
          <select id="simProduk" class="form-select">
            <option value="">-- Pilih Produk --</option>
            <?php while($p = $products->fetch_assoc()): ?>
            <option value="<?php echo $p['id']; ?>" 
                    data-tenor="<?php echo $p['tenor_hari_default']; ?>"
                    data-bunga="<?php echo $p['bunga_persen']; ?>"
                    data-admin="<?php echo $p['admin_biaya']; ?>"
                    data-potong="<?php echo $p['potongan_tabungan']; ?>">
              <?php echo htmlspecialchars($p['nama']); ?>
            </option>
            <?php endwhile; ?>
          </select>
        </div>
        
        <div class="mb-3">
          <label class="form-label fw-bold">Pokok Pinjaman (Rp)</label>
          <input type="text" id="simPokok" class="form-control form-control-lg" placeholder="1.000.000" oninput="formatRupiah(this)">
        </div>
        
        <div class="row g-2 mb-3">
          <div class="col-6">
            <label class="form-label fw-bold">Tenor (Hari)</label>
            <input type="number" id="simTenor" class="form-control" value="30" min="1">
          </div>
          <div class="col-6">
            <label class="form-label fw-bold">Bunga (%)</label>
            <input type="number" id="simBunga" class="form-control" value="20" min="0" step="0.1">
          </div>
        </div>
        
        <div class="row g-2 mb-3">
          <div class="col-6">
            <label class="form-label fw-bold">Biaya Admin (Rp)</label>
            <input type="text" id="simAdmin" class="form-control" value="0" oninput="formatRupiah(this)">
          </div>
          <div class="col-6">
            <label class="form-label fw-bold">Pot. Tabungan (%)</label>
            <input type="number" id="simPotong" class="form-control" value="0" min="0" max="100">
          </div>
        </div>
        
        <div class="mb-3">
          <label class="form-label fw-bold">Tipe Angsuran</label>
          <select id="simTipe" class="form-select">
            <option value="bulanan" selected>Bulanan</option>
            <option value="tempo">Tempo (Jatuh Tempo)</option>
          </select>
        </div>
        
        <button type="button" class="btn btn-gradient btn-lg w-100" onclick="hitungSimulasi()">
          <i class="bi bi-calculator me-2"></i>Hitung Simulasi
        </button>
      </div>
    </div>
  </div>
  
  <!-- Hasil Simulasi -->
  <div class="col-lg-7">
    <div class="card card-widget">
      <div class="card-header bg-success text-white">
        <h5 class="mb-0"><i class="bi bi-receipt me-2"></i>Hasil Simulasi</h5>
      </div>
      <div class="card-body" id="hasilSimulasi">
        <div class="text-center text-muted py-5">
          <i class="bi bi-arrow-left-circle fs-1 mb-3 d-block"></i>
          <p>Isi parameter di sebelah kiri lalu klik <strong>Hitung Simulasi</strong></p>
        </div>
      </div>
    </div>
    
    <!-- Jadwal Angsuran Preview -->
    <div class="card card-widget mt-3" id="cardJadwal" style="display:none;">
      <div class="card-header">
        <h6 class="mb-0"><i class="bi bi-calendar3 me-2"></i>Preview Jadwal Angsuran</h6>
      </div>
      <div class="card-body">
        <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
          <table class="table table-sm table-striped" id="tabelJadwal">
            <thead class="sticky-top bg-white">
              <tr><th>Ke</th><th>Tanggal</th><th>Angsuran</th><th>Sisa</th></tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function formatRupiah(el) {
  let v = el.value.replace(/\D/g, '');
  el.value = v.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

function parseRupiah(str) {
  return parseInt(str.replace(/\./g, '')) || 0;
}

function rupiah(n) {
  return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

document.getElementById('simProduk').addEventListener('change', function() {
  var opt = this.options[this.selectedIndex];
  if (opt.value) {
    document.getElementById('simTenor').value = opt.dataset.tenor || 30;
    document.getElementById('simBunga').value = opt.dataset.bunga || 20;
    document.getElementById('simAdmin').value = rupiah(opt.dataset.admin || 0);
    document.getElementById('simPotong').value = opt.dataset.potong || 0;
  }
});

function hitungSimulasi() {
  var pokok = parseRupiah(document.getElementById('simPokok').value);
  var tenor = parseInt(document.getElementById('simTenor').value) || 30;
  var bunga = parseFloat(document.getElementById('simBunga').value) || 0;
  var admin = parseRupiah(document.getElementById('simAdmin').value);
  var potong = parseFloat(document.getElementById('simPotong').value) || 0;
  var tipe = document.getElementById('simTipe').value;
  
  if (pokok <= 0) {
    alert('Masukkan jumlah pokok pinjaman!');
    return;
  }
  
  // Hitung
  var totalBunga = Math.round(pokok * (bunga / 100));
  var totalPinjaman = pokok + totalBunga;
  var potonganTab = Math.round(pokok * (potong / 100));
  var danaDiterima = pokok - potonganTab - admin;
  
  var periode = tenor;
  if (tipe == 'tempo') periode = 1;
  
  var angsuranPer = Math.ceil(totalPinjaman / periode);
  
  // Tampilkan hasil
  var html = '<div class="row g-3">';
  html += '<div class="col-6"><div class="border rounded p-3 text-center"><div class="small text-muted">Pokok Pinjaman</div><div class="fs-5 fw-bold">Rp ' + rupiah(pokok) + '</div></div></div>';
  html += '<div class="col-6"><div class="border rounded p-3 text-center bg-warning bg-opacity-10"><div class="small text-muted">Total Bunga (' + bunga + '%)</div><div class="fs-5 fw-bold text-warning">Rp ' + rupiah(totalBunga) + '</div></div></div>';
  html += '<div class="col-6"><div class="border rounded p-3 text-center bg-primary bg-opacity-10"><div class="small text-muted">Total Pinjaman</div><div class="fs-5 fw-bold text-primary">Rp ' + rupiah(totalPinjaman) + '</div></div></div>';
  html += '<div class="col-6"><div class="border rounded p-3 text-center bg-info bg-opacity-10"><div class="small text-muted">Potongan Tabungan (' + potong + '%)</div><div class="fs-5 fw-bold text-info">Rp ' + rupiah(potonganTab) + '</div></div></div>';
  html += '<div class="col-6"><div class="border rounded p-3 text-center bg-danger bg-opacity-10"><div class="small text-muted">Biaya Admin</div><div class="fs-5 fw-bold text-danger">Rp ' + rupiah(admin) + '</div></div></div>';
  html += '<div class="col-6"><div class="border rounded p-3 text-center bg-success bg-opacity-10"><div class="small text-muted">Dana Diterima</div><div class="fs-5 fw-bold text-success">Rp ' + rupiah(danaDiterima) + '</div></div></div>';
  html += '</div>';
  
  html += '<hr>';
  html += '<div class="row g-3">';
  html += '<div class="col-4 text-center"><div class="small text-muted">Tipe</div><div class="fw-bold text-capitalize">' + tipe + '</div></div>';
  html += '<div class="col-4 text-center"><div class="small text-muted">Jumlah Periode</div><div class="fw-bold">' + periode + 'x</div></div>';
  html += '<div class="col-4 text-center"><div class="small text-muted">Angsuran/Periode</div><div class="fw-bold text-primary">Rp ' + rupiah(angsuranPer) + '</div></div>';
  html += '</div>';
  
  document.getElementById('hasilSimulasi').innerHTML = html;
  
  // Generate jadwal
  var tbody = document.querySelector('#tabelJadwal tbody');
  tbody.innerHTML = '';
  var sisa = totalPinjaman;
  var today = new Date();
  
  for (var i = 1; i <= periode; i++) {
    var bayar = (i == periode) ? sisa : angsuranPer;
    sisa -= bayar;
    
    var tgl = new Date(today);
    if (tipe == 'tempo') tgl.setMonth(today.getMonth() + tenor);
    else tgl.setMonth(today.getMonth() + i);
    
    var tglStr = tgl.toISOString().split('T')[0];
    
    tbody.innerHTML += '<tr><td>' + i + '</td><td>' + tglStr + '</td><td>Rp ' + rupiah(bayar) + '</td><td>Rp ' + rupiah(Math.max(0, sisa)) + '</td></tr>';
  }
  
  document.getElementById('cardJadwal').style.display = 'block';
}
</script>

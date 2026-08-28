<?php
// partials/sidebar.php
$active = isset($_GET['m']) ? $_GET['m'] : 'dashboard';
function active($m) { global $active; return $active === $m ? 'active' : ''; }
$app_name = setting_get('app_name', 'KSP Harian');
?>
<aside class="sidebar" id="sidebar">
  <!-- Brand -->
  <div class="sidebar-brand">
    <div class="d-flex align-items-center gap-3">
      <div class="brand-icon">
        <img src="member/assets/image/LOGO_KOPDES PASIRLAJA.png" alt="Logo">
      </div>
      <div>
        <h5><?php echo htmlspecialchars($app_name); ?></h5>
        <small>Sistem Koperasi</small>
      </div>
    </div>
  </div>
  
  <!-- Menu -->
  <div class="sidebar-menu">
    <!-- Main Menu -->
    <div class="menu-header">Menu Utama</div>
    <ul class="nav nav-pills flex-column">
      <li class="nav-item">
        <a class="nav-link <?php echo active('dashboard'); ?>" href="index.php?m=dashboard">
          <i class="bi bi-grid-1x2-fill"></i>
          <span>Dashboard</span>
        </a>
      </li>
    </ul>
    
    <!-- Master Data -->
    <div class="menu-header">Master Data</div>
    <ul class="nav nav-pills flex-column">
      <?php if(is_role('admin')): ?>
        <li class="nav-item">
          <a class="nav-link <?php echo active('user'); ?>" href="index.php?m=user">
            <i class="bi bi-people-fill"></i>
            <span>Users</span>
          </a>
        </li>
      <?php endif; ?>
      <li class="nav-item">
        <a class="nav-link <?php echo active('anggota'); ?>" href="index.php?m=anggota">
          <i class="bi bi-people-fill"></i>
          <span>Data Anggota</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?php echo active('kelompok'); ?>" href="index.php?m=kelompok">
          <i class="bi bi-diagram-3-fill"></i>
          <span>Master Kelompok</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?php echo active('kolektor'); ?>" href="index.php?m=kolektor">
          <i class="bi bi-person-badge-fill"></i>
          <span>Master Kolektor</span>
        </a>
      </li>
    </ul>
    
    <!-- Simpanan -->
    <div class="menu-header">Simpanan</div>
    <ul class="nav nav-pills flex-column">
      <li class="nav-item">
        <a class="nav-link <?php echo active('produk_simpanan'); ?>" href="index.php?m=produk_simpanan">
          <i class="bi bi-box-seam-fill"></i>
          <span>Produk Simpanan</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?php echo active('simpanan'); ?>" href="index.php?m=simpanan">
          <i class="bi bi-wallet2"></i>
          <span>Transaksi Simpanan</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?php echo active('simpanan_anggota'); ?>" href="index.php?m=simpanan_anggota">
          <i class="bi bi-wallet2"></i>
          <span>Simpanan Wajib/Pokok</span>
          <span class="badge bg-success ms-auto">NEW</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?php echo active('buku_tabungan'); ?>" href="index.php?m=buku_tabungan">
          <i class="bi bi-book"></i>
          <span>Buku Tabungan</span>
        </a>
      </li>
    </ul>
    
    <!-- Pinjaman -->
    <div class="menu-header">Pinjaman</div>
    <ul class="nav nav-pills flex-column">
      <li class="nav-item">
        <a class="nav-link <?php echo active('produk_pinjaman'); ?>" href="index.php?m=produk_pinjaman">
          <i class="bi bi-credit-card-2-front-fill"></i>
          <span>Produk Pinjaman</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?php echo active('produk_barang'); ?>" href="index.php?m=produk_barang">
          <i class="bi bi-box-seam"></i>
          <span>Produk Barang</span>
          <span class="badge bg-success ms-auto">NEW</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?php echo active('pinjaman_realisasi'); ?>" href="index.php?m=pinjaman_realisasi">
          <i class="bi bi-file-earmark-plus-fill"></i>
          <span>Realisasi Pinjaman</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?php echo active('pengajuan_online'); ?>" href="index.php?m=pengajuan_online">
          <i class="bi bi-cloud-arrow-down"></i>
          <span>Pengajuan Online</span>
          <span class="badge bg-danger ms-auto">HOT</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?php echo active('approval'); ?>" href="index.php?m=approval">
          <i class="bi bi-clipboard-check"></i>
          <span>Approval Pinjaman</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?php echo active('jaminan'); ?>" href="index.php?m=jaminan">
          <i class="bi bi-safe"></i>
          <span>Jaminan/Agunan</span>
          <span class="badge bg-success ms-auto">NEW</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?php echo active('angsuran'); ?>" href="index.php?m=angsuran">
          <i class="bi bi-calendar-check-fill"></i>
          <span>Angsuran Harian</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?php echo active('tagih'); ?>" href="index.php?m=tagih">
          <i class="bi bi-clipboard-check-fill"></i>
          <span>Penagihan</span>
        </a>
      </li>
    </ul>
    
    <!-- Inventori -->
    <div class="menu-header">Inventori</div>
    <ul class="nav nav-pills flex-column">
      <li class="nav-item">
        <a class="nav-link <?php echo active('kategori_barang'); ?>" href="index.php?m=kategori_barang">
          <i class="bi bi-tags"></i>
          <span>Kategori Barang</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?php echo active('satuan'); ?>" href="index.php?m=satuan">
          <i class="bi bi-rulers"></i>
          <span>Satuan & Konversi</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?php echo active('supplier'); ?>" href="index.php?m=supplier">
          <i class="bi bi-truck"></i>
          <span>Master Supplier</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?php echo active('penerimaan_barang'); ?>" href="index.php?m=penerimaan_barang">
          <i class="bi bi-box-arrow-in-down"></i>
          <span>Penerimaan Barang</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?php echo active('pembelian'); ?>" href="index.php?m=pembelian">
          <i class="bi bi-cart-plus"></i>
          <span>Pembelian</span>
          <span class="badge bg-success ms-auto">NEW</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?php echo active('penjualan'); ?>" href="index.php?m=penjualan">
          <i class="bi bi-shop"></i>
          <span>Penjualan (POS)</span>
          <span class="badge bg-success ms-auto">NEW</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?php echo active('laporan_stok'); ?>" href="index.php?m=laporan_stok">
          <i class="bi bi-clipboard-data"></i>
          <span>Laporan Stok</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?php echo active('laporan_penjualan'); ?>" href="index.php?m=laporan_penjualan">
          <i class="bi bi-graph-up"></i>
          <span>Laporan Penjualan</span>
          <span class="badge bg-success ms-auto">NEW</span>
        </a>
      </li>
    </ul>
    
    <!-- Keuangan -->
    <div class="menu-header">Keuangan</div>
    <ul class="nav nav-pills flex-column">
      <li class="nav-item">
        <a class="nav-link <?php echo active('master_pendapatan'); ?>" href="index.php?m=master_pendapatan">
          <i class="bi bi-arrow-down-circle"></i>
          <span>Master Pendapatan</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?php echo active('master_biaya'); ?>" href="index.php?m=master_biaya">
          <i class="bi bi-arrow-up-circle"></i>
          <span>Master Biaya</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?php echo active('kas_bank'); ?>" href="index.php?m=kas_bank">
          <i class="bi bi-wallet2"></i>
          <span>Kas & Bank</span>
          <span class="badge bg-success ms-auto">NEW</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?php echo active('transaksi_keuangan'); ?>" href="index.php?m=transaksi_keuangan">
          <i class="bi bi-arrow-left-right"></i>
          <span>Transaksi Keuangan</span>
          <span class="badge bg-success ms-auto">NEW</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?php echo active('kas'); ?>" href="index.php?m=kas">
          <i class="bi bi-wallet-fill"></i>
          <span>Kas (Lama)</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?php echo active('setting_shu'); ?>" href="index.php?m=setting_shu">
          <i class="bi bi-sliders"></i>
          <span>Setting SHU</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?php echo active('shu_generate'); ?>" href="index.php?m=shu_generate">
          <i class="bi bi-pie-chart"></i>
          <span>Generate SHU</span>
          <span class="badge bg-success ms-auto">NEW</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?php echo active('laporan'); ?>" href="index.php?m=laporan">
          <i class="bi bi-graph-up-arrow"></i>
          <span>Laporan (Lama)</span>
        </a>
      </li>
    </ul>
    
    <!-- Laporan -->
    <div class="menu-header">Laporan</div>
    <ul class="nav nav-pills flex-column">
      <li class="nav-item">
        <a class="nav-link <?php echo active('dashboard_koperasi'); ?>" href="index.php?m=dashboard_koperasi">
          <i class="bi bi-speedometer2"></i>
          <span>Dashboard Executive</span>
          <span class="badge bg-success ms-auto">NEW</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?php echo active('laporan_laba_rugi'); ?>" href="index.php?m=laporan_laba_rugi">
          <i class="bi bi-file-earmark-bar-graph"></i>
          <span>Laba Rugi</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?php echo active('laporan_neraca'); ?>" href="index.php?m=laporan_neraca">
          <i class="bi bi-journal-bookmark"></i>
          <span>Neraca</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?php echo active('laporan_arus_kas'); ?>" href="index.php?m=laporan_arus_kas">
          <i class="bi bi-arrow-left-right"></i>
          <span>Arus Kas</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?php echo active('laporan_pinjaman'); ?>" href="index.php?m=laporan_pinjaman">
          <i class="bi bi-credit-card"></i>
          <span>Pinjaman & NPL</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?php echo active('laporan_simpanan'); ?>" href="index.php?m=laporan_simpanan">
          <i class="bi bi-piggy-bank"></i>
          <span>Simpanan Anggota</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?php echo active('rekap_angsuran'); ?>" href="index.php?m=rekap_angsuran">
          <i class="bi bi-calendar-check"></i>
          <span>Rekap Angsuran</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?php echo active('laporan_inventori'); ?>" href="index.php?m=laporan_inventori">
          <i class="bi bi-boxes"></i>
          <span>Laporan Inventori</span>
        </a>
      </li>
    </ul>
    
    <!-- Risk Management -->
    <div class="menu-header">Risk Management</div>
    <ul class="nav nav-pills flex-column">
      <li class="nav-item">
        <a class="nav-link <?php echo active('blacklist'); ?>" href="index.php?m=blacklist">
          <i class="bi bi-person-x"></i>
          <span>Blacklist Anggota</span>
          <span class="badge bg-danger ms-auto">NEW</span>
        </a>
      </li>
    </ul>
    
    <!-- Analitik -->
    <div class="menu-header">Analitik</div>
    <ul class="nav nav-pills flex-column">
      <li class="nav-item">
        <a class="nav-link <?php echo active('simulasi'); ?>" href="index.php?m=simulasi">
          <i class="bi bi-calculator"></i>
          <span>Simulasi Pinjaman</span>
          <span class="badge bg-success ms-auto">NEW</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?php echo active('credit_score'); ?>" href="index.php?m=credit_score">
          <i class="bi bi-speedometer2"></i>
          <span>Credit Scoring</span>
          <span class="badge bg-success ms-auto">NEW</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?php echo active('wa_broadcast'); ?>" href="index.php?m=wa_broadcast">
          <i class="bi bi-whatsapp"></i>
          <span>WA Broadcast</span>
          <span class="badge bg-success ms-auto">NEW</span>
        </a>
      </li>
    </ul>
    
    <!-- Settings -->
    <div class="menu-header">Sistem</div>
    <ul class="nav nav-pills flex-column">
      <li class="nav-item">
        <a class="nav-link <?php echo active('setting'); ?>" href="index.php?m=setting">
          <i class="bi bi-gear-fill"></i>
          <span>Pengaturan</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?php echo active('nota'); ?>" href="index.php?m=nota">
          <i class="bi bi-receipt"></i>
          <span>Edit Nota/Faktur</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?php echo active('template_cetak'); ?>" href="index.php?m=template_cetak">
          <i class="bi bi-brush"></i>
          <span>Template Cetak</span>
          <span class="badge bg-info ms-auto">NEW</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?php echo active('cabang'); ?>" href="index.php?m=cabang">
          <i class="bi bi-building"></i>
          <span>Multi Cabang</span>
          <span class="badge bg-success ms-auto">NEW</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?php echo active('sms_gateway'); ?>" href="index.php?m=sms_gateway">
          <i class="bi bi-chat-dots"></i>
          <span>SMS Gateway</span>
          <span class="badge bg-success ms-auto">NEW</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?php echo active('backup'); ?>" href="index.php?m=backup">
          <i class="bi bi-cloud-arrow-down"></i>
          <span>Backup Database</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?php echo active('activity_log'); ?>" href="index.php?m=activity_log">
          <i class="bi bi-clock-history"></i>
          <span>Activity Log</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?php echo active('reset_data'); ?>" href="index.php?m=reset_data">
          <i class="bi bi-arrow-counterclockwise"></i>
          <span>Reset Data</span>
          <span class="badge bg-danger ms-auto">!</span>
        </a>
      </li>
    </ul>
    
    <!-- External Portals -->
    <div class="menu-header">Portal Eksternal</div>
    <ul class="nav nav-pills flex-column">
      <li class="nav-item">
        <a class="nav-link" href="member/" target="_blank">
          <i class="bi bi-person-circle"></i>
          <span>Member Portal</span>
          <i class="bi bi-box-arrow-up-right ms-auto small"></i>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="kolektor/" target="_blank">
          <i class="bi bi-person-badge"></i>
          <span>Kolektor Portal</span>
          <i class="bi bi-box-arrow-up-right ms-auto small"></i>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="api/" target="_blank">
          <i class="bi bi-code-slash"></i>
          <span>REST API</span>
          <i class="bi bi-box-arrow-up-right ms-auto small"></i>
        </a>
      </li>
    </ul>
  </div>
  
  <!-- Footer -->
  <div class="sidebar-footer">
    <div class="d-flex align-items-center justify-content-between text-secondary small">
      <span>© <?php echo date('Y'); ?> <?php echo htmlspecialchars($app_name); ?></span>
      <span class="badge bg-primary bg-opacity-25 text-primary-emphasis">v2.0</span>
    </div>
  </div>
</aside>

<!-- Sidebar Overlay for Mobile -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<script>
// Simpan dan restore posisi scroll sidebar
(function() {
  var sidebar = document.getElementById('sidebar');
  var sidebarMenu = sidebar ? sidebar.querySelector('.sidebar-menu') : null;
  
  if (sidebarMenu) {
    // Restore scroll position saat halaman load
    var savedPos = sessionStorage.getItem('sidebarScrollPos');
    if (savedPos) {
      sidebarMenu.scrollTop = parseInt(savedPos);
    }
    
    // Simpan scroll position sebelum pindah halaman
    window.addEventListener('beforeunload', function() {
      sessionStorage.setItem('sidebarScrollPos', sidebarMenu.scrollTop);
    });
    
    // Simpan juga saat klik link menu
    var menuLinks = sidebarMenu.querySelectorAll('a.nav-link');
    menuLinks.forEach(function(link) {
      link.addEventListener('click', function() {
        sessionStorage.setItem('sidebarScrollPos', sidebarMenu.scrollTop);
      });
    });
  }
})();
</script>

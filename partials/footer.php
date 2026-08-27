<?php
// partials/footer.php
?>
    </div>
  </div><!-- /.content-wrapper -->
</div><!-- /.main-wrapper -->

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- DataTables -->
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

<!-- Select2 -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Custom JS -->
<script src="public/js/app.js"></script>

<script>
// Initialize components when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
  // Initialize DataTables with modern styling (check if not already initialized)
  if ($.fn.DataTable) {
    $('.datatable').each(function() {
      if (!$.fn.DataTable.isDataTable(this)) {
        $(this).DataTable({
          language: {
            search: '<i class="bi bi-search"></i>',
            searchPlaceholder: 'Cari...',
            lengthMenu: 'Tampilkan _MENU_ data',
            info: 'Menampilkan _START_ - _END_ dari _TOTAL_ data',
            infoEmpty: 'Tidak ada data',
            infoFiltered: '(filter dari _MAX_ total data)',
            zeroRecords: 'Data tidak ditemukan',
            paginate: {
              first: '<i class="bi bi-chevron-double-left"></i>',
              last: '<i class="bi bi-chevron-double-right"></i>',
              next: '<i class="bi bi-chevron-right"></i>',
              previous: '<i class="bi bi-chevron-left"></i>'
            }
          },
          pageLength: 10,
          responsive: true,
          dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip'
        });
      }
    });
  }
  
  // Initialize Select2 with Bootstrap 5 theme
  if ($.fn.select2) {
    $('.select2').select2({
      theme: 'bootstrap-5',
      placeholder: 'Pilih...',
      allowClear: true
    });
  }
  
  // Smooth scroll for anchor links
  document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
      e.preventDefault();
      const target = document.querySelector(this.getAttribute('href'));
      if (target) {
        target.scrollIntoView({ behavior: 'smooth' });
      }
    });
  });
});

// Toggle Sidebar for Mobile
function toggleSidebar() {
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('sidebarOverlay');
  sidebar.classList.toggle('show');
  overlay.classList.toggle('show');
}

// Toggle Fullscreen
function toggleFullscreen() {
  if (!document.fullscreenElement) {
    document.documentElement.requestFullscreen();
  } else {
    document.exitFullscreen();
  }
}

// Global Search Handler
document.getElementById('globalSearch')?.addEventListener('keypress', function(e) {
  if (e.key === 'Enter') {
    const query = this.value.trim();
    if (query) {
      // Redirect to anggota search or implement global search
      window.location.href = 'index.php?m=anggota&search=' + encodeURIComponent(query);
    }
  }
});

// Toggle Launchpad
function toggleLaunchpad() {
  const overlay = document.getElementById('launchpadOverlay');
  const searchInput = document.getElementById('launchpadSearch');
  overlay.classList.toggle('show');
  if (overlay.classList.contains('show')) {
    setTimeout(() => searchInput.focus(), 100);
  } else {
    searchInput.value = '';
    filterLaunchpad('');
  }
}

// Filter Launchpad items
function filterLaunchpad(query) {
  const items = document.querySelectorAll('.launchpad-item');
  const q = query.toLowerCase();
  items.forEach(item => {
    const name = item.getAttribute('data-name').toLowerCase();
    const text = item.textContent.toLowerCase();
    if (name.includes(q) || text.includes(q)) {
      item.style.display = '';
    } else {
      item.style.display = 'none';
    }
  });
}

// Close launchpad with ESC
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    const overlay = document.getElementById('launchpadOverlay');
    if (overlay && overlay.classList.contains('show')) {
      toggleLaunchpad();
    }
  }
});

// Auto-hide alerts after 5 seconds
document.querySelectorAll('.alert').forEach(function(alert) {
  setTimeout(function() {
    alert.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
    alert.style.opacity = '0';
    alert.style.transform = 'translateY(-10px)';
    setTimeout(function() {
      alert.remove();
    }, 500);
  }, 5000);
});

// Tooltip initialization
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
tooltipTriggerList.map(function(el) {
  return new bootstrap.Tooltip(el);
});

// Format currency input
document.querySelectorAll('input[type="number"]').forEach(function(input) {
  input.addEventListener('focus', function() {
    this.select();
  });
});

function onlyDigits(value) {
  return String(value || '').replace(/\D/g, '');
}

function formatRupiahInput(value) {
  var angka = onlyDigits(value);
  return angka.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

function syncRupiahInput(input) {
  var angka = onlyDigits(input.value);
  input.value = formatRupiahInput(angka);

  var targetName = input.getAttribute('data-target');
  if (targetName && input.form) {
    var hidden = input.form.querySelector('input[name="' + targetName + '"]');
    if (hidden) {
      hidden.value = angka;
    }
  }
}

document.querySelectorAll('.rupiah-input').forEach(function(input) {
  syncRupiahInput(input);

  input.addEventListener('input', function() {
    syncRupiahInput(this);
  });

  input.addEventListener('focus', function() {
    this.select();
  });
});

// Prevent double submit - disable button after first click
function disableSubmitButton(form) {
  form.querySelectorAll('.rupiah-input').forEach(function(input) {
    syncRupiahInput(input);
  });

  var btn = form.querySelector('button[type="submit"], button:not([type])');
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Memproses...';
  }
  return true; // allow form submission
}

// Also handle forms with class 'prevent-double-submit'
document.querySelectorAll('form.prevent-double-submit, form[onsubmit*="disableSubmitButton"]').forEach(function(form) {
  form.addEventListener('submit', function() {
    disableSubmitButton(this);
  });
});

// Konfirmasi Hapus dengan SweetAlert2
function confirmHapus(nama, kode, formId) {
  Swal.fire({
    title: 'Hapus Data?',
    html: '<div class="text-start"><strong>Data yang akan dihapus:</strong><br>' +
          '<table class="table table-sm mt-2"><tr><td>Nama</td><td>: ' + nama + '</td></tr>' +
          '<tr><td>Kode</td><td>: ' + kode + '</td></tr></table>' +
          '<span class="text-danger"><i class="bi bi-exclamation-triangle me-1"></i>Data yang dihapus tidak dapat dikembalikan!</span></div>',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#dc3545',
    cancelButtonColor: '#6c757d',
    confirmButtonText: '<i class="bi bi-trash me-1"></i>Ya, Hapus!',
    cancelButtonText: 'Batal',
    reverseButtons: true
  }).then(function(result) {
    if (result.isConfirmed) {
      document.getElementById(formId).submit();
    }
  });
  return false;
}

// Konfirmasi Simpan dengan SweetAlert2
function confirmSimpan(form, title) {
  event.preventDefault();
  var formData = new FormData(form);
  var dataHtml = '<div class="text-start"><table class="table table-sm">';
  formData.forEach(function(value, key) {
    if (key !== 'csrf' && key !== 'action' && key !== 'form_token' && key !== 'id' && value) {
      var label = key.replace(/_/g, ' ').replace(/\b\w/g, function(l){ return l.toUpperCase(); });
      dataHtml += '<tr><td>' + label + '</td><td>: ' + value + '</td></tr>';
    }
  });
  dataHtml += '</table></div>';
  
  Swal.fire({
    title: title || 'Simpan Data?',
    html: '<strong>Data yang akan disimpan:</strong>' + dataHtml,
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#4f46e5',
    cancelButtonColor: '#6c757d',
    confirmButtonText: '<i class="bi bi-check-lg me-1"></i>Ya, Simpan!',
    cancelButtonText: 'Batal',
    reverseButtons: true
  }).then(function(result) {
    if (result.isConfirmed) {
      disableSubmitButton(form);
      form.submit();
    }
  });
  return false;
}

// Konfirmasi Transaksi Setor/Tarik dengan nominal
function confirmTransaksi(form, tipe, nominal) {
  event.preventDefault();
  if (form.nominal_display) {
    syncRupiahInput(form.nominal_display);
    nominal = form.nominal.value;
  }
  var formattedNominal = new Intl.NumberFormat('id-ID').format(onlyDigits(nominal));
  var icon = tipe === 'setor' ? 'bi-arrow-down-circle text-success' : 'bi-arrow-up-circle text-danger';
  var colorBtn = tipe === 'setor' ? '#198754' : '#dc3545';
  var tipeText = tipe === 'setor' ? 'menyetor' : 'menarik';
  
  Swal.fire({
    title: 'Konfirmasi Transaksi',
    html: '<div class="text-center py-3">' +
          '<i class="bi ' + icon + '" style="font-size:3rem;"></i>' +
          '<h4 class="mt-3">Anda akan <strong>' + tipeText + '</strong> uang sebesar</h4>' +
          '<h2 class="text-primary">Rp ' + formattedNominal + '</h2></div>',
    icon: null,
    showCancelButton: true,
    confirmButtonColor: colorBtn,
    cancelButtonColor: '#6c757d',
    confirmButtonText: '<i class="bi bi-check-lg me-1"></i>Ya, Proses!',
    cancelButtonText: 'Batal',
    reverseButtons: true
  }).then(function(result) {
    if (result.isConfirmed) {
      disableSubmitButton(form);
      form.submit();
    }
  });
  return false;
}

// Konfirmasi Transaksi Simpanan Anggota (Pokok/Wajib/Sukarela)
function confirmTransaksiSimpanan(form) {
  event.preventDefault();
  var tipe = form.tipe.value;
  var jenis = form.jenis_simpanan.value;
  if (form.nominal_display) {
    syncRupiahInput(form.nominal_display);
  }
  var nominal = form.nominal.value;
  var formattedNominal = new Intl.NumberFormat('id-ID').format(onlyDigits(nominal));
  var icon = tipe === 'setor' ? 'bi-arrow-down-circle text-success' : 'bi-arrow-up-circle text-danger';
  var colorBtn = tipe === 'setor' ? '#198754' : '#dc3545';
  var tipeText = tipe === 'setor' ? 'menyetor' : 'menarik';
  
  Swal.fire({
    title: 'Konfirmasi Transaksi',
    html: '<div class="text-center py-3">' +
          '<i class="bi ' + icon + '" style="font-size:3rem;"></i>' +
          '<h4 class="mt-3">Anda akan <strong>' + tipeText + '</strong></h4>' +
          '<p class="mb-1">Simpanan <strong>' + jenis.charAt(0).toUpperCase() + jenis.slice(1) + '</strong></p>' +
          '<h2 class="text-primary">Rp ' + formattedNominal + '</h2></div>',
    icon: null,
    showCancelButton: true,
    confirmButtonColor: colorBtn,
    cancelButtonColor: '#6c757d',
    confirmButtonText: '<i class="bi bi-check-lg me-1"></i>Ya, Proses!',
    cancelButtonText: 'Batal',
    reverseButtons: true
  }).then(function(result) {
    if (result.isConfirmed) {
      disableSubmitButton(form);
      form.submit();
    }
  });
  return false;
}

</script>

<!-- Launchpad Overlay -->
<div id="launchpadOverlay" class="launchpad-overlay" onclick="if(event.target===this)toggleLaunchpad()">
  <div class="launchpad-container">
    <div class="launchpad-search">
      <i class="bi bi-search"></i>
      <input type="text" id="launchpadSearch" placeholder="Cari menu..." oninput="filterLaunchpad(this.value)">
    </div>
    <div class="launchpad-grid">
      <a href="index.php?m=dashboard" class="launchpad-item" data-name="dashboard">
        <div class="launchpad-icon bg-primary"><i class="bi bi-speedometer2"></i></div>
        <span>Dashboard</span>
      </a>
      <a href="index.php?m=anggota" class="launchpad-item" data-name="anggota">
        <div class="launchpad-icon bg-info"><i class="bi bi-people"></i></div>
        <span>Anggota</span>
      </a>
      <a href="index.php?m=pinjaman_realisasi" class="launchpad-item" data-name="pinjaman realisasi">
        <div class="launchpad-icon bg-success"><i class="bi bi-cash-stack"></i></div>
        <span>Pinjaman</span>
      </a>
      <a href="index.php?m=angsuran" class="launchpad-item" data-name="angsuran">
        <div class="launchpad-icon bg-warning"><i class="bi bi-calendar-check"></i></div>
        <span>Angsuran</span>
      </a>
      <a href="index.php?m=simpanan" class="launchpad-item" data-name="simpanan tabungan">
        <div class="launchpad-icon bg-cyan"><i class="bi bi-piggy-bank"></i></div>
        <span>Simpanan</span>
      </a>
      <a href="index.php?m=tagih" class="launchpad-item" data-name="tagih penagihan">
        <div class="launchpad-icon bg-orange"><i class="bi bi-bicycle"></i></div>
        <span>Penagihan</span>
      </a>
      <a href="index.php?m=produk_barang" class="launchpad-item" data-name="produk barang inventori">
        <div class="launchpad-icon bg-teal"><i class="bi bi-box-seam"></i></div>
        <span>Produk</span>
      </a>
      <a href="index.php?m=penjualan" class="launchpad-item" data-name="penjualan pos kasir">
        <div class="launchpad-icon bg-purple"><i class="bi bi-cart3"></i></div>
        <span>POS</span>
      </a>
      <a href="index.php?m=pembelian" class="launchpad-item" data-name="pembelian supplier">
        <div class="launchpad-icon bg-indigo"><i class="bi bi-truck"></i></div>
        <span>Pembelian</span>
      </a>
      <a href="index.php?m=kas_bank" class="launchpad-item" data-name="kas bank">
        <div class="launchpad-icon bg-danger"><i class="bi bi-wallet2"></i></div>
        <span>Kas & Bank</span>
      </a>
      <a href="index.php?m=transaksi_keuangan" class="launchpad-item" data-name="transaksi keuangan">
        <div class="launchpad-icon bg-pink"><i class="bi bi-arrow-left-right"></i></div>
        <span>Keuangan</span>
      </a>
      <a href="index.php?m=shu_generate" class="launchpad-item" data-name="shu bagi hasil">
        <div class="launchpad-icon bg-yellow"><i class="bi bi-pie-chart"></i></div>
        <span>SHU</span>
      </a>
      <a href="index.php?m=dashboard_koperasi" class="launchpad-item" data-name="dashboard executive laporan">
        <div class="launchpad-icon bg-gradient-primary"><i class="bi bi-graph-up-arrow"></i></div>
        <span>Executive</span>
      </a>
      <a href="index.php?m=laporan_laba_rugi" class="launchpad-item" data-name="laporan laba rugi">
        <div class="launchpad-icon bg-gradient-success"><i class="bi bi-file-earmark-bar-graph"></i></div>
        <span>Laba Rugi</span>
      </a>
      <a href="index.php?m=laporan_pinjaman" class="launchpad-item" data-name="laporan pinjaman npl">
        <div class="launchpad-icon bg-gradient-warning"><i class="bi bi-credit-card"></i></div>
        <span>Lap. Pinjaman</span>
      </a>
      <a href="index.php?m=setting" class="launchpad-item" data-name="setting pengaturan">
        <div class="launchpad-icon bg-secondary"><i class="bi bi-gear"></i></div>
        <span>Pengaturan</span>
      </a>
      <a href="index.php?m=backup" class="launchpad-item" data-name="backup database">
        <div class="launchpad-icon bg-dark"><i class="bi bi-cloud-download"></i></div>
        <span>Backup</span>
      </a>
      <a href="logout.php" class="launchpad-item" data-name="logout keluar">
        <div class="launchpad-icon bg-danger"><i class="bi bi-box-arrow-right"></i></div>
        <span>Logout</span>
      </a>
    </div>
    <div class="launchpad-hint">Tekan <kbd>ESC</kbd> untuk menutup</div>
  </div>
</div>

</body>
</html>

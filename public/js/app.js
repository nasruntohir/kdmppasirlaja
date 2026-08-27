/**
 * KSP Harian - Modern UI JavaScript
 * Premium Design with Animations
 */

// Document Ready
$(function() {
  // Add page load animation
  document.body.classList.add('loaded');
  
  // Initialize number formatting
  initNumberInputs();
  
  // Keyboard shortcuts
  initKeyboardShortcuts();
});

// Initialize Bootstrap Tooltips
function initTooltips() {
  var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  tooltipTriggerList.forEach(function(el) {
    new bootstrap.Tooltip(el);
  });
}

// Number input auto-select on focus
function initNumberInputs() {
  document.querySelectorAll('input[type="number"]').forEach(function(input) {
    input.addEventListener('focus', function() {
      this.select();
    });
  });
}


// Keyboard Shortcuts
function initKeyboardShortcuts() {
  document.addEventListener('keydown', function(e) {
    // Ctrl+K for global search
    if (e.ctrlKey && e.key === 'k') {
      e.preventDefault();
      var search = document.getElementById('globalSearch');
      if (search) search.focus();
    }
    
    // Escape to close sidebar on mobile
    if (e.key === 'Escape') {
      var sidebar = document.getElementById('sidebar');
      var overlay = document.getElementById('sidebarOverlay');
      if (sidebar && sidebar.classList.contains('show')) {
        sidebar.classList.remove('show');
        if (overlay) overlay.classList.remove('show');
      }
    }
  });
}

// Format number to Indonesian Rupiah
function formatRupiah(num) {
  return new Intl.NumberFormat('id-ID').format(num);
}

// Parse Rupiah string to number
function parseRupiah(str) {
  return parseInt(str.replace(/\./g, '').replace(/,/g, '')) || 0;
}

// Show loading overlay
function showLoading() {
  var overlay = document.createElement('div');
  overlay.className = 'loading-overlay';
  overlay.id = 'loadingOverlay';
  overlay.innerHTML = '<div class="loading-spinner"></div>';
  document.body.appendChild(overlay);
}

// Hide loading overlay
function hideLoading() {
  var overlay = document.getElementById('loadingOverlay');
  if (overlay) overlay.remove();
}

// Toast notification
function showToast(message, type) {
  type = type || 'info';
  Swal.fire({
    toast: true,
    position: 'top-end',
    icon: type,
    title: message,
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true
  });
}

// Confirm dialog
function confirmAction(title, text, callback) {
  Swal.fire({
    title: title,
    text: text,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#4f46e5',
    cancelButtonColor: '#6b7280',
    confirmButtonText: 'Ya, Lanjutkan',
    cancelButtonText: 'Batal',
    reverseButtons: true
  }).then(function(result) {
    if (result.isConfirmed && callback) {
      callback();
    }
  });
}

// Copy to clipboard
function copyToClipboard(text) {
  navigator.clipboard.writeText(text).then(function() {
    showToast('Berhasil disalin!', 'success');
  });
}

// Print element
function printElement(elementId) {
  var content = document.getElementById(elementId);
  if (!content) return;
  
  var printWindow = window.open('', '_blank');
  printWindow.document.write('<html><head><title>Print</title>');
  printWindow.document.write('<link href="public/css/app.css" rel="stylesheet">');
  printWindow.document.write('</head><body>');
  printWindow.document.write(content.innerHTML);
  printWindow.document.write('</body></html>');
  printWindow.document.close();
  printWindow.print();
}

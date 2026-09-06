/**
 * KSP Harian - Modern UI JavaScript
 * Premium Design with Animations
 */

// Document Ready
$(function () {
  // Add page load animation
  document.body.classList.add("loaded");

  // Initialize number formatting
  initNumberInputs();

  // Keyboard shortcuts
  initKeyboardShortcuts();
});

// Initialize Bootstrap Tooltips
function initTooltips() {
  var tooltipTriggerList = [].slice.call(
    document.querySelectorAll('[data-bs-toggle="tooltip"]'),
  );
  tooltipTriggerList.forEach(function (el) {
    new bootstrap.Tooltip(el);
  });
}

// Number input auto-select on focus
function initNumberInputs() {
  document.querySelectorAll('input[type="number"]').forEach(function (input) {
    input.addEventListener("focus", function () {
      this.select();
    });
  });
}

// Keyboard Shortcuts
function initKeyboardShortcuts() {
  document.addEventListener("keydown", function (e) {
    // Ctrl+K for global search
    if (e.ctrlKey && e.key === "k") {
      e.preventDefault();
      var search = document.getElementById("globalSearch");
      if (search) search.focus();
    }

    // Escape to close sidebar on mobile
    if (e.key === "Escape") {
      var sidebar = document.getElementById("sidebar");
      var overlay = document.getElementById("sidebarOverlay");
      if (sidebar && sidebar.classList.contains("show")) {
        sidebar.classList.remove("show");
        if (overlay) overlay.classList.remove("show");
      }
    }
  });
}

// Format number to Indonesian Rupiah
function formatRupiah(num) {
  return new Intl.NumberFormat("id-ID").format(num);
}

// Parse Rupiah string to number
function parseRupiah(str) {
  return parseInt(str.replace(/\./g, "").replace(/,/g, "")) || 0;
}

// Show loading overlay
function showLoading() {
  var overlay = document.createElement("div");
  overlay.className = "loading-overlay";
  overlay.id = "loadingOverlay";
  overlay.innerHTML = '<div class="loading-spinner"></div>';
  document.body.appendChild(overlay);
}

// Hide loading overlay
function hideLoading() {
  var overlay = document.getElementById("loadingOverlay");
  if (overlay) overlay.remove();
}

// Toast notification
function showToast(message, type) {
  type = type || "info";
  Swal.fire({
    toast: true,
    position: "top-end",
    icon: type,
    title: message,
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
  });
}

// Confirm dialog
function confirmAction(title, text, callback) {
  Swal.fire({
    title: title,
    text: text,
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#4f46e5",
    cancelButtonColor: "#6b7280",
    confirmButtonText: "Ya, Lanjutkan",
    cancelButtonText: "Batal",
    reverseButtons: true,
  }).then(function (result) {
    if (result.isConfirmed && callback) {
      callback();
    }
  });
}

// Copy to clipboard
function copyToClipboard(text) {
  navigator.clipboard.writeText(text).then(function () {
    showToast("Berhasil disalin!", "success");
  });
}

// Print element
function printElement(elementId) {
  var content = document.getElementById(elementId);
  if (!content) return;

  var printWindow = window.open("", "_blank");
  printWindow.document.write("<html><head><title>Print</title>");
  printWindow.document.write(
    '<link href="public/css/app.css" rel="stylesheet">',
  );
  printWindow.document.write("</head><body>");
  printWindow.document.write(content.innerHTML);
  printWindow.document.write("</body></html>");
  printWindow.document.close();
  printWindow.print();
}

// cek notifikasi
var popupShown = false;

function checkNotifikasi() {
  $.ajax({
    url: "api/get_notifications.php",
    method: "GET",
    dataType: "json",
    success: function (response) {
      if (response.success) {
        //update budge
        if (response.count > 0) {
          $("#notif-count").text(response.count).addClass("shown");
        } else {
          $("#notif-count").removeClass("shown");
        }

        //update dropdown
        let html = "";

        if (response.count === 0) {
          html = `<div class="no-notification">
                                Tidak ada pengajuan baru
                            </div> `;
        } else {
          response.data.forEach(function (notif) {
            html += `<div class="notification-item" onclick="window.location.href='index.php?m=pengajuan_online&status=pending'">
            <strong>${notif.nama}</strong>
            <p> Mengajukan pengajuan sebesar
            <strong> Rp ${Number(notif.jumlah_pinjaman).toLocaleString('id-ID')}</strong>
            </p>
            <small>${notif.created_at}</small>
            </div>`;

          });
        }
        $(".notif-content").html(html);

        // tampilkan popup sekali saja per halaman
        if (response.count > 0 && !popupShown) {
          popupShown = true;
          showPopup(response.data[0]);
        }

      }
    },
    error: function (xhr, status, error) {
      console.log('Gagal mengambil data notifikasi: ', error);
    }
  });
}


//tampilkan popup

function showPopup(notif){
  $('#loan-popup-content').html(`<strong>${notif.nama}</strong>
            <p> Mengajukan pengajuan sebesar
            <strong> Rp ${Number(notif.jumlah_pinjaman).toLocaleString('id-ID')}</strong>
            </p>
            <small>${notif.created_at}</small>
            `);
  $('#loan-popup').fadeIn();

  //Hilang otomatis setelah 7 detik

  setTimeout(function(){
    $('#loan-popup').fadeOut();
  }, 7000);
}

//Jalakan saat web dibuka

checkNotifikasi();

//cek setiap 10 detik

setInterval(function(){checkNotifikasi();}, 10000);


// tandai sudah dibaca saat dropdown dibuka
function markNotificationsRead() {
  $.ajax({
    url: "api/mark_notifications_read.php",
    method: "POST",
    dataType: "json",
    success: function (response) {
      if (response.success) {
        $("#notif-count").removeClass("shown");
        $(".notif-content").html(`<div class="no-notification">
              Tidak ada notifikasi baru
            </div>`);
      }
    }
  });
}

$('#notificationBtn').click(function(e){
  e.stopPropagation();

  var isOpen = $('#notificationDropdown').hasClass('show');
  $('#notificationDropdown').toggleClass('show');

  // saat dropdown dibuka, tandai notifikasi sudah dibaca
  if (!isOpen) {
    markNotificationsRead();
  }
});


//tutup popup

$('.popup-close').click(function(){
  $('#loan-popup').fadeOut();
});

$(document).click(function (e) {

        if (!$(e.target).closest('.notification-wrapper').length) {

            $('#notificationDropdown')
                .removeClass('show');

        }

    });



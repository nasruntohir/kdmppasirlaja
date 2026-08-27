<?php
// lib/helpers.php — revisi full fix (PHP 5.6 friendly)

// Start session (hindari notice "already started")
if (function_exists('session_status')) {
  if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
} else {
  if (session_id() === '') { @session_start(); }
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/auth.php'; // pusatkan fungsi login di sini (aman dengan require_once)

// --- Setting helpers ---
if (!function_exists('setting_get')) {
  function setting_get($key, $default = '') {
    $conn = db();
    $stmt = $conn->prepare("SELECT `value` FROM ksp_setting WHERE `key`=?");
    if (!$stmt) return $default;
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $stmt->bind_result($val);
    $out = $default;
    if ($stmt->fetch()) { $out = $val; }
    $stmt->close();
    return $out;
  }
}

if (!function_exists('setting_set')) {
  function setting_set($key, $value) {
    $conn = db();
    $stmt = $conn->prepare("REPLACE INTO ksp_setting(`key`,`value`) VALUES(?,?)");
    if (!$stmt) return false;
    $stmt->bind_param("ss", $key, $value);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
  }
}

// --- Format ---
if (!function_exists('rupiah')) {
  function rupiah($n) {
    return number_format((float)$n, 2, ',', '.');
  }
}

// --- CSRF helpers ---
if (!function_exists('csrf_token')) {
  function csrf_token() {
    if (empty($_SESSION['csrf'])) {
      if (function_exists('openssl_random_pseudo_bytes')) {
        $_SESSION['csrf'] = bin2hex(openssl_random_pseudo_bytes(16));
      } else {
        $_SESSION['csrf'] = sha1(uniqid(mt_rand(), true));
      }
    }
    return $_SESSION['csrf'];
  }
}

if (!function_exists('csrf_input')) {
  function csrf_input() {
    return '<input type="hidden" name="csrf" value="'.htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8').'">';
  }
}

if (!function_exists('csrf_check')) {
  function csrf_check() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      $sess = isset($_SESSION['csrf']) ? $_SESSION['csrf'] : '';
      if (!isset($_POST['csrf']) || $_POST['csrf'] !== $sess) {
        http_response_code(403);
        die('CSRF token invalid');
      }
    }
  }
}

// --- Date helpers ---
if (!function_exists('now')) {
  function now() { return date('Y-m-d H:i:s'); }
}
if (!function_exists('today')) {
  function today() { return date('Y-m-d'); }
}

// --- Auth helpers (fallback jika belum ada di auth.php) ---
if (!function_exists('require_login')) {
  function require_login() {
    if (empty($_SESSION['user'])) {
      header('Location: login.php');
      exit;
    }
  }
}

if (!function_exists('user')) {
  function user($key = null, $default = null) {
    $u = isset($_SESSION['user']) && is_array($_SESSION['user']) ? $_SESSION['user'] : array();
    if ($key === null) return $u;
    return isset($u[$key]) ? $u[$key] : $default;
  }
}

if (!function_exists('is_role')) {
  function is_role($r) {
    return isset($_SESSION['user']) && isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === $r;
  }
}

// --- Number generator (improved: uses MAX+1 to prevent collision) ---
if (!function_exists('gen_number')) {
  function gen_number($prefix_key, $table = null, $column = null) {
    $prefix = setting_get($prefix_key, 'NO');
    $date   = date('Ymd');
    $pattern = $prefix . '-' . $date . '-%';
    
    // Try to get max sequence from relevant table
    $seq = 1;
    $conn = db();
    
    // Determine table and column based on prefix_key
    if ($table === null) {
      switch ($prefix_key) {
        case 'prefix_no_anggota':
          $table = 'ksp_anggota'; $column = 'no_anggota'; break;
        case 'prefix_no_pinjaman':
          $table = 'ksp_pinjaman'; $column = 'no_pinjaman'; break;
        case 'prefix_no_kas':
          $table = 'ksp_kas'; $column = 'no_kas'; break;
        default:
          $table = null;
      }
    }
    
    if ($table && $column) {
      $sql = "SELECT $column FROM $table WHERE $column LIKE ? ORDER BY $column DESC LIMIT 1";
      $stmt = $conn->prepare($sql);
      if ($stmt) {
        $stmt->bind_param("s", $pattern);
        $stmt->execute();
        $stmt->bind_result($last);
        if ($stmt->fetch() && $last) {
          // Extract sequence number from last entry (format: PREFIX-YYYYMMDD-NNN)
          $parts = explode('-', $last);
          if (count($parts) >= 3) {
            $seq = (int)end($parts) + 1;
          }
        }
        $stmt->close();
      }
    }
    
    return sprintf('%s-%s-%03d', $prefix, $date, $seq);
  }
}

// Nomor anggota: YYMM + urutan lima digit, misalnya 260800057.
if (!function_exists('gen_nomor_anggota')) {
  function gen_nomor_anggota() {
    $conn = db();
    $seq = 1;
    $result = $conn->query("SELECT MAX(CAST(RIGHT(no_anggota, 3) AS UNSIGNED)) AS nomor_terakhir FROM ksp_anggota WHERE no_anggota REGEXP '^[0-9]{9}$'");

    if ($result && ($row = $result->fetch_assoc()) && $row['nomor_terakhir'] !== null) {
      $seq = (int) $row['nomor_terakhir'] + 1;
    }

    return date('ym') . str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
  }
}

// --- Global escape function (prevent redeclare in views) ---
if (!function_exists('e')) {
  function e($s) {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
  }
}

// --- Activity Log ---
if (!function_exists('log_activity')) {
  function log_activity($action, $module, $description = '') {
    $conn = db();
    $user_id = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null;
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 500) : '';
    
    $stmt = $conn->prepare("INSERT INTO ksp_activity_log (user_id, action, module, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)");
    if ($stmt) {
      $stmt->bind_param("isssss", $user_id, $action, $module, $description, $ip, $ua);
      $stmt->execute();
      $stmt->close();
    }
  }
}

// --- Flash message ---
if (!function_exists('alert')) {
  function alert($type, $msg) {
    $_SESSION['flash'] = array('type' => $type, 'msg' => $msg);
  }
}
if (!function_exists('flash')) {
  function flash() {
    if (!empty($_SESSION['flash'])) {
      $f = $_SESSION['flash'];
      unset($_SESSION['flash']);
      $type = isset($f['type']) ? $f['type'] : 'info';
      $msg  = isset($f['msg'])  ? $f['msg']  : '';
      // Gunakan kelas Bootstrap jika ada; kalau tidak, tetap tampil rapi.
      echo '<div class="alert alert-'.$type.' alert-dismissible" role="alert" style="margin:8px 0">'
         . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8')
         . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" style="float:right;border:0;background:transparent;font-size:16px;line-height:1">&times;</button>'
         . '</div>';
    }
  }
}

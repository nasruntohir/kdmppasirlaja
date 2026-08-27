<?php
// lib/auth.php — aman untuk PHP 5.6, tanpa array-dereference pada require
require_once __DIR__ . '/../config/db.php';

// Muat config app SEKALI dan aman untuk 2 pola:
// 1) config/app.php "return array('USE_PLAIN_PASSWORD'=>...);"  (preferred)
// 2) config/app.php mendefinisikan konstanta USE_PLAIN_PASSWORD
$__APP_CFG = @include __DIR__ . '/../config/app.php';
if (!is_array($__APP_CFG)) { $__APP_CFG = array(); }

if (session_id() === '') { session_start(); }

if (!function_exists('login')) {
  function login($username, $password) {
    $conn = db();
    $stmt = $conn->prepare("SELECT id, nama, username, password, role, aktif FROM ksp_users WHERE username=? AND aktif=1 LIMIT 1");
    if (!$stmt) { return false; }
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
      // Ambil setting USE_PLAIN_PASSWORD dari array config atau constant
      $use_plain = false;
      if (isset($GLOBALS['__APP_CFG']) && is_array($GLOBALS['__APP_CFG']) && isset($GLOBALS['__APP_CFG']['USE_PLAIN_PASSWORD'])) {
        $use_plain = (bool)$GLOBALS['__APP_CFG']['USE_PLAIN_PASSWORD'];
      } elseif (defined('USE_PLAIN_PASSWORD')) {
        $use_plain = (bool)USE_PLAIN_PASSWORD;
      }

      $ok = $use_plain ? ($password === $row['password']) : (function_exists('password_verify') ? password_verify($password, $row['password']) : ($password === $row['password']));
      if ($ok) {
        $_SESSION['user'] = array(
          'id'       => (int)$row['id'],
          'nama'     => $row['nama'],
          'username' => $row['username'],
          'role'     => $row['role']
        );
        return true;
      }
    }
    return false;
  }
}

if (!function_exists('logout')) {
  function logout() {
    if (session_id() === '') { session_start(); }
    $_SESSION = array(); // jangan pakai [] untuk aman di versi lama
    if (ini_get('session.use_cookies')) {
      $p = session_get_cookie_params();
      setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    // Kalau ingin langsung ke login, boleh aktifkan redirect di bawah:
    // header('Location: login.php'); exit;
  }
}

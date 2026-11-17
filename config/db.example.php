<?php
// config/db.php
$app = require __DIR__ . '/app.php';

function db() {
  static $conn;
  global $app;
  if ($conn instanceof mysqli) return $conn;
  $conn = @new mysqli($app['DB_HOST'], $app['DB_USER'], $app['DB_PASS'], $app['DB_NAME']);
  if ($conn->connect_errno) {
    die('DB Error: ' . $conn->connect_error);
  }
  $conn->set_charset('utf8');
  return $conn;
}

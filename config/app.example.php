<?php
// config/app.php
date_default_timezone_set('Asia/Jakarta');

return [
  'DB_HOST' => '127.0.0.1',
  'DB_USER' => 'root',
  'DB_PASS' => '',
  'DB_NAME' => 'koperasipasirlaja',
  'APP_NAME' => 'kspro',
  // Toggle: if true, login compares plaintext; if false, uses password_hash/verify
  'USE_PLAIN_PASSWORD' => false,
];

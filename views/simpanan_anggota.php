<?php
// views/simpanan_anggota.php - Simpanan Wajib/Pokok/Sukarela per Anggota
require_login();
$conn = db();

// Generate submission token untuk mencegah double submit
if (empty($_SESSION['form_token_sa'])) {
  $_SESSION['form_token_sa'] = bin2hex(random_bytes(16));
}

// Handle transaksi simpanan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  
  if (isset($_POST['transaksi'])) {
    // Cek submission token untuk mencegah double submit
    $submitted_token = isset($_POST['form_token']) ? $_POST['form_token'] : '';
    if (empty($submitted_token) || $submitted_token !== $_SESSION['form_token_sa']) {
      alert('warning', 'Form sudah disubmit sebelumnya. Silakan cek data.');
      $anggota_id = (int)$_POST['anggota_id'];
      header('Location: index.php?m=simpanan_anggota&id=' . $anggota_id);
      exit;
    }
    // Regenerate token setelah submit berhasil diproses
    $_SESSION['form_token_sa'] = bin2hex(random_bytes(16));
    $anggota_id = (int)$_POST['anggota_id'];
    $jenis = isset($_POST['jenis_simpanan']) ? $_POST['jenis_simpanan'] : '';
    $tipe = isset($_POST['tipe']) ? $_POST['tipe'] : '';
    $nominal = (float)$_POST['nominal'];
    $keterangan = trim($_POST['keterangan']);
    $jenis_allowed = array('pokok', 'wajib', 'sukarela');
    $tipe_allowed = array('setor', 'tarik');
    
    if (!in_array($jenis, $jenis_allowed) || !in_array($tipe, $tipe_allowed) || $nominal <= 0) {
      alert('danger', 'Data transaksi simpanan tidak valid.');
      $_SESSION['form_token_sa'] = $submitted_token;
      header('Location: index.php?m=simpanan_anggota&id=' . $anggota_id);
      exit;
    }
    
    // Get current simpanan
    $current = $conn->query("SELECT * FROM ksp_simpanan_anggota WHERE anggota_id = $anggota_id")->fetch_assoc();
    if (!$current) {
      $conn->query("INSERT INTO ksp_simpanan_anggota (anggota_id) VALUES ($anggota_id)");
      $current = ['simpanan_pokok' => 0, 'simpanan_wajib' => 0, 'simpanan_sukarela' => 0];
    }
    
    $field = 'simpanan_' . $jenis;
    $current_val = (float)$current[$field];
    
    if ($tipe == 'tarik' && $nominal > $current_val) {
      alert('danger', 'Saldo tidak mencukupi untuk penarikan.');
      // Kembalikan token jika gagal agar bisa retry
      $_SESSION['form_token_sa'] = $submitted_token;
    } else {
      $delta = $tipe == 'setor' ? $nominal : -$nominal;
      $uid = user()['id'];
      $ok = true;
      
      $conn->begin_transaction();
      
      $stmt_update = $conn->prepare("UPDATE ksp_simpanan_anggota SET $field = COALESCE($field, 0) + ? WHERE anggota_id = ?");
      if (!$stmt_update) $ok = false;
      if ($ok) {
        $stmt_update->bind_param("di", $delta, $anggota_id);
        $ok = $stmt_update->execute();
        $stmt_update->close();
      }
      
      if ($ok) {
        $ok = $conn->query("UPDATE ksp_simpanan_anggota SET total_simpanan = COALESCE(simpanan_pokok,0) + COALESCE(simpanan_wajib,0) + COALESCE(simpanan_sukarela,0) WHERE anggota_id = $anggota_id");
      }
      
      $updated = $ok ? $conn->query("SELECT $field AS saldo_akhir FROM ksp_simpanan_anggota WHERE anggota_id = $anggota_id")->fetch_assoc() : null;
      $saldo_akhir = $updated ? (float)$updated['saldo_akhir'] : 0;
      
      if ($ok) {
        // Insert mutasi
        $stmt = $conn->prepare("INSERT INTO ksp_mutasi_simpanan (anggota_id, tanggal, jenis_simpanan, tipe, nominal, saldo_akhir, keterangan, user_id) VALUES (?, CURDATE(), ?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
          $ok = false;
        } else {
          $stmt->bind_param("issddsi", $anggota_id, $jenis, $tipe, $nominal, $saldo_akhir, $keterangan, $uid);
          $ok = $stmt->execute();
          $stmt->close();
        }
      }
      
      if ($ok) {
        // Insert kas
        $no_kas = gen_number('prefix_no_kas');
        $jenis_kas = $tipe == 'setor' ? 'masuk' : 'keluar';
        $cat = ucfirst($tipe) . ' Simpanan ' . ucfirst($jenis);
        $stmt2 = $conn->prepare("INSERT INTO ksp_kas (no_kas, tanggal, jenis, sumber, ref_table, ref_id, nominal, user_id, catatan) VALUES (?, CURDATE(), ?, 'simpanan', 'ksp_simpanan_anggota', ?, ?, ?, ?)");
        if (!$stmt2) {
          $ok = false;
        } else {
          $stmt2->bind_param("ssiids", $no_kas, $jenis_kas, $anggota_id, $nominal, $uid, $cat);
          $ok = $stmt2->execute();
          $stmt2->close();
        }
      }
      
      if ($ok) {
        $conn->commit();
        log_activity($tipe . '_simpanan', 'simpanan', $cat . ' Rp ' . number_format($nominal, 0, ',', '.'));
        alert('success', 'Transaksi simpanan berhasil dicatat. Saldo menjadi Rp ' . rupiah($saldo_akhir) . '.');
      } else {
        $conn->rollback();
        $_SESSION['form_token_sa'] = $submitted_token;
        alert('danger', 'Gagal memproses transaksi simpanan: ' . $conn->error);
      }
    }
    header('Location: index.php?m=simpanan_anggota&id=' . $anggota_id);
    exit;
  }

  if (($_POST['action'] ?? '') === 'delete_mutasi') {
    $mutasi_id = (int) ($_POST['mutasi_id'] ?? 0);
    $stmt = $conn->prepare('SELECT * FROM ksp_mutasi_simpanan WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $mutasi_id);
    $stmt->execute();
    $mutasi = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$mutasi) {
      alert('danger', 'Transaksi simpanan tidak ditemukan.');
      header('Location: index.php?m=simpanan_anggota');
      exit;
    }

    $anggota_id = (int) $mutasi['anggota_id'];
    $jenis = $mutasi['jenis_simpanan'];
    $field = 'simpanan_' . $jenis;
    $nominal = (float) $mutasi['nominal'];
    $delta = $mutasi['tipe'] === 'setor' ? -$nominal : $nominal;

    $saldo = $conn->query("SELECT COALESCE($field, 0) AS saldo FROM ksp_simpanan_anggota WHERE anggota_id = $anggota_id")->fetch_assoc();
    if (!$saldo || ((float) $saldo['saldo'] + $delta) < 0) {
      alert('danger', 'Transaksi tidak dapat dihapus karena saldo simpanan sudah dipakai transaksi lain.');
      header('Location: index.php?m=simpanan_anggota&id=' . $anggota_id);
      exit;
    }

    $conn->begin_transaction();
    $ok = true;

    $stmt = $conn->prepare("UPDATE ksp_simpanan_anggota SET $field = $field + ? WHERE anggota_id = ?");
    $stmt->bind_param('di', $delta, $anggota_id);
    $ok = $stmt->execute();
    $stmt->close();

    if ($ok) {
      $ok = $conn->query("UPDATE ksp_simpanan_anggota SET total_simpanan = COALESCE(simpanan_pokok, 0) + COALESCE(simpanan_wajib, 0) + COALESCE(simpanan_sukarela, 0) WHERE anggota_id = $anggota_id");
    }

    if ($ok) {
      $stmt = $conn->prepare('DELETE FROM ksp_mutasi_simpanan WHERE id = ?');
      $stmt->bind_param('i', $mutasi_id);
      $ok = $stmt->execute();
      $stmt->close();
    }

    if ($ok) {
      $no_kas = gen_number('prefix_no_kas');
      $jenis_kas = $mutasi['tipe'] === 'setor' ? 'keluar' : 'masuk';
      $catatan = 'Pembatalan ' . ucfirst($mutasi['tipe']) . ' Simpanan ' . ucfirst($jenis);
      $stmt = $conn->prepare("INSERT INTO ksp_kas (no_kas, tanggal, jenis, sumber, ref_table, ref_id, nominal, user_id, catatan) VALUES (?, CURDATE(), ?, 'simpanan', 'ksp_mutasi_simpanan', ?, ?, ?, ?)");
      $uid = user()['id'];
      $stmt->bind_param('ssidis', $no_kas, $jenis_kas, $mutasi_id, $nominal, $uid, $catatan);
      $ok = $stmt->execute();
      $stmt->close();
    }

    if ($ok) {
      $conn->commit();
      alert('success', 'Transaksi simpanan berhasil dihapus dan kas dibatalkan.');
    } else {
      $conn->rollback();
      alert('danger', 'Gagal menghapus transaksi simpanan.');
    }

    header('Location: index.php?m=simpanan_anggota&id=' . $anggota_id);
    exit;
  }
}

// Get anggota list with simpanan
$anggota_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';

$where = $search ? "WHERE a.nama LIKE '%$search%' OR a.no_anggota LIKE '%$search%'" : '';
$list = $conn->query("
  SELECT a.*, 
    COALESCE(s.simpanan_pokok, 0) as simpanan_pokok,
    COALESCE(s.simpanan_wajib, 0) as simpanan_wajib,
    COALESCE(s.simpanan_sukarela, 0) as simpanan_sukarela,
    COALESCE(s.total_simpanan, 0) as total_simpanan
  FROM ksp_anggota a
  LEFT JOIN ksp_simpanan_anggota s ON s.anggota_id = a.id
  $where
  ORDER BY a.nama
  LIMIT 100
");

// Get detail if selected
$detail = null;
$mutasi = null;
if ($anggota_id) {
  $detail = $conn->query("
    SELECT a.*, 
      COALESCE(s.simpanan_pokok, 0) as simpanan_pokok,
      COALESCE(s.simpanan_wajib, 0) as simpanan_wajib,
      COALESCE(s.simpanan_sukarela, 0) as simpanan_sukarela,
      COALESCE(s.total_simpanan, 0) as total_simpanan
    FROM ksp_anggota a
    LEFT JOIN ksp_simpanan_anggota s ON s.anggota_id = a.id
    WHERE a.id = $anggota_id
  ")->fetch_assoc();
  
  $mutasi = $conn->query("SELECT * FROM ksp_mutasi_simpanan WHERE anggota_id = $anggota_id ORDER BY created_at DESC LIMIT 50");
}

// Summary
$summary = $conn->query("
  SELECT 
    COUNT(DISTINCT anggota_id) as total_anggota,
    SUM(simpanan_pokok) as total_pokok,
    SUM(simpanan_wajib) as total_wajib,
    SUM(simpanan_sukarela) as total_sukarela,
    SUM(total_simpanan) as grand_total
  FROM ksp_simpanan_anggota
")->fetch_assoc();
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4><i class="bi bi-wallet2 me-2"></i>Simpanan Anggota</h4>
  <a href="index.php?m=buku_tabungan" class="btn btn-outline-primary"><i class="bi bi-book me-1"></i>Buku Tabungan</a>
</div>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
  <div class="col-md-3">
    <div class="card card-widget bg-primary bg-opacity-10">
      <div class="card-body text-center">
        <i class="bi bi-wallet2 fs-2 text-primary"></i>
        <h5 class="mt-2 mb-0">Rp <?php echo number_format($summary['total_pokok'] ?: 0, 0, ',', '.'); ?></h5>
        <small class="text-muted">Total Simpanan Pokok</small>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card card-widget bg-success bg-opacity-10">
      <div class="card-body text-center">
        <i class="bi bi-calendar-check fs-2 text-success"></i>
        <h5 class="mt-2 mb-0">Rp <?php echo number_format($summary['total_wajib'] ?: 0, 0, ',', '.'); ?></h5>
        <small class="text-muted">Total Simpanan Wajib</small>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card card-widget bg-info bg-opacity-10">
      <div class="card-body text-center">
        <i class="bi bi-cash-stack fs-2 text-info"></i>
        <h5 class="mt-2 mb-0">Rp <?php echo number_format($summary['total_sukarela'] ?: 0, 0, ',', '.'); ?></h5>
        <small class="text-muted">Total Simpanan Sukarela</small>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card card-widget bg-warning bg-opacity-10">
      <div class="card-body text-center">
        <i class="bi bi-bank fs-2 text-warning"></i>
        <h5 class="mt-2 mb-0">Rp <?php echo number_format($summary['grand_total'] ?: 0, 0, ',', '.'); ?></h5>
        <small class="text-muted">Total Seluruh Simpanan</small>
      </div>
    </div>
  </div>
</div>

<div class="row">
  <!-- List Anggota -->
  <div class="col-md-5">
    <div class="card card-widget">
      <div class="card-header">
        <form class="d-flex gap-2">
          <input type="hidden" name="m" value="simpanan_anggota">
          <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari anggota..." value="<?php echo htmlspecialchars($search); ?>">
          <button class="btn btn-sm btn-primary"><i class="bi bi-search"></i></button>
        </form>
      </div>
      <div class="card-body p-0" style="max-height:500px;overflow-y:auto;">
        <table class="table table-sm table-hover mb-0">
          <thead class="sticky-top bg-light">
            <tr><th>Anggota</th><th class="text-end">Total Simpanan</th></tr>
          </thead>
          <tbody>
          <?php while ($a = $list->fetch_assoc()): ?>
          <tr class="<?php echo $a['id'] == $anggota_id ? 'table-primary' : ''; ?>" style="cursor:pointer;" onclick="location.href='index.php?m=simpanan_anggota&id=<?php echo $a['id']; ?>'">
            <td>
              <strong><?php echo htmlspecialchars($a['nama']); ?></strong><br>
              <small class="text-muted"><?php echo $a['no_anggota']; ?></small>
            </td>
            <td class="text-end">
              <strong>Rp <?php echo number_format($a['total_simpanan'], 0, ',', '.'); ?></strong>
            </td>
          </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  
  <!-- Detail & Form -->
  <div class="col-md-7">
    <?php if ($detail): ?>
    <!-- Info Simpanan -->
    <div class="card card-widget mb-3">
      <div class="card-header bg-primary text-white">
        <h6 class="mb-0"><i class="bi bi-person me-2"></i><?php echo htmlspecialchars($detail['nama']); ?> (<?php echo $detail['no_anggota']; ?>)</h6>
      </div>
      <div class="card-body">
        <div class="row g-3 text-center">
          <div class="col-4">
            <div class="border rounded p-3">
              <div class="text-muted small">Simpanan Pokok</div>
              <div class="fs-5 fw-bold text-primary">Rp <?php echo number_format($detail['simpanan_pokok'], 0, ',', '.'); ?></div>
            </div>
          </div>
          <div class="col-4">
            <div class="border rounded p-3">
              <div class="text-muted small">Simpanan Wajib</div>
              <div class="fs-5 fw-bold text-success">Rp <?php echo number_format($detail['simpanan_wajib'], 0, ',', '.'); ?></div>
            </div>
          </div>
          <div class="col-4">
            <div class="border rounded p-3">
              <div class="text-muted small">Simpanan Sukarela</div>
              <div class="fs-5 fw-bold text-info">Rp <?php echo number_format($detail['simpanan_sukarela'], 0, ',', '.'); ?></div>
            </div>
          </div>
        </div>
        <div class="text-center mt-3 p-3 bg-light rounded">
          <div class="text-muted small">TOTAL SIMPANAN</div>
          <div class="fs-3 fw-bold text-warning">Rp <?php echo number_format($detail['total_simpanan'], 0, ',', '.'); ?></div>
        </div>
      </div>
    </div>
    
    <!-- Form Transaksi -->
    <div class="card card-widget mb-3">
      <div class="card-header"><h6 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Transaksi Simpanan</h6></div>
      <div class="card-body">
        <form method="post" class="row g-2" onsubmit="return disableSubmitButton(this);">
          <?php echo csrf_input(); ?>
          <input type="hidden" name="form_token" value="<?php echo htmlspecialchars($_SESSION['form_token_sa']); ?>">
          <input type="hidden" name="anggota_id" value="<?php echo $detail['id']; ?>">
          <input type="hidden" name="transaksi" value="1">
          <div class="col-md-4">
            <label class="form-label">Jenis Simpanan</label>
            <select name="jenis_simpanan" class="form-select" required>
              <option value="pokok">Pokok</option>
              <option value="wajib">Wajib</option>
              <option value="sukarela">Sukarela</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label">Tipe</label>
            <select name="tipe" class="form-select" required>
              <option value="setor">Setor</option>
              <option value="tarik">Tarik</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Nominal (Rp)</label>
            <input type="text" name="nominal_display" class="form-control rupiah-input" data-target="nominal" inputmode="numeric" autocomplete="off" required>
            <input type="hidden" name="nominal">
            <input type="hidden" name="nominal">
          </div>
          <div class="col-md-8">
            <label class="form-label">Keterangan</label>
            <input type="text" name="keterangan" class="form-control" placeholder="Opsional">
          </div>
          <div class="col-md-4 align-self-end">
            <button type="submit" class="btn btn-gradient w-100" onclick="return confirmTransaksiSimpanan(this.form)"><i class="bi bi-check-lg me-1"></i>Proses</button>
          </div>
        </form>
      </div>
    </div>
    
    <!-- Mutasi -->
    <div class="card card-widget">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="bi bi-clock-history me-2"></i>Riwayat Mutasi</h6>
        <a href="index.php?m=buku_tabungan&id=<?php echo $detail['id']; ?>" class="btn btn-sm btn-outline-primary">
          <i class="bi bi-printer me-1"></i>Cetak Buku
        </a>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive" style="max-height:300px;">
          <table class="table table-sm table-striped mb-0">
            <thead class="sticky-top bg-light">
              <tr><th>Tanggal</th><th>Jenis</th><th>Tipe</th><th class="text-end">Nominal</th><th class="text-end">Saldo</th><th>Aksi</th></tr>
            </thead>
            <tbody>
            <?php if ($mutasi && $mutasi->num_rows > 0): ?>
              <?php while ($m = $mutasi->fetch_assoc()): ?>
              <tr>
                <td><?php echo date('d/m/Y H:i', strtotime($m['created_at'])); ?></td>
                <td><span class="badge bg-secondary"><?php echo ucfirst($m['jenis_simpanan']); ?></span></td>
                <td>
                  <span class="badge <?php echo $m['tipe'] == 'setor' ? 'bg-success' : 'bg-danger'; ?>">
                    <?php echo $m['tipe'] == 'setor' ? '+' : '-'; ?>
                  </span>
                </td>
                <td class="text-end <?php echo $m['tipe'] == 'setor' ? 'text-success' : 'text-danger'; ?>">
                  Rp <?php echo number_format($m['nominal'], 0, ',', '.'); ?>
                </td>
                <td class="text-end fw-bold">Rp <?php echo number_format($m['saldo_akhir'], 0, ',', '.'); ?></td>
                <td>
                  <form method="post" onsubmit="return confirm('Hapus transaksi simpanan ini?');">
                    <?php echo csrf_input(); ?>
                    <input type="hidden" name="action" value="delete_mutasi">
                    <input type="hidden" name="mutasi_id" value="<?php echo (int)$m['id']; ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus transaksi">
                      <i class="bi bi-trash"></i>
                    </button>
                  </form>
                </td>
              </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="5" class="text-center text-muted py-3">Belum ada transaksi</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <?php else: ?>
    <div class="card card-widget">
      <div class="card-body text-center py-5">
        <i class="bi bi-arrow-left-circle fs-1 text-muted"></i>
        <p class="mt-3 text-muted">Pilih anggota dari daftar untuk melihat detail simpanan</p>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

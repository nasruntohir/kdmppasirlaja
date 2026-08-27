<?php
require_login();
csrf_check();

if (!is_role('admin')) {
  alert('danger', 'Akses ditolak. Master User hanya dapat diakses admin.');
  header("Location: index.php?m=dashboard");
  exit;
}

$conn = db();

$roles = array('admin', 'manager', 'teller');

$cfg = @include __DIR__ . '/../config/app.php';
$use_plain = is_array($cfg) && !empty($cfg['USE_PLAIN_PASSWORD']);

function redirect_user_page(){
  header("Location: index.php?m=user");
  exit;
}

/* crud */
if($_SERVER['REQUEST_METHOD'] === 'POST'){
  $action = $_POST['action'] ?? '';

  /* create */
  if($action === 'create'){
    $nama = trim($_POST['nama']?? '');
    $username = trim($_POST['username']?? '');
    $role =$_POST['role']?? 'teller';
    $password = $_POST['password'] ?? '';

    if($nama === '' ||  $username === '' || $password === ''){
      alert('danger', 'Nama, username, dan password harus diisi.');
      redirect_user_page();
    }
    if(!in_array($role, $roles)){
      $role = 'teller';
    }
    $cek = $conn->prepare("SELECT id FROM ksp_users WHERE username = ? LIMIT 1");
    $cek->bind_param('s', $username);
    $cek->execute();
    $hasil =  $cek->get_result();

    if ($hasil->num_rows > 0){
      alert('danger', 'Username sudah digunakan.');
      redirect_user_page();
    }

    $password_simpan = $password;

    $stmt = $conn->prepare("INSERT INTO ksp_users (nama, username,password, role,aktif) VALUES (?,?,?,?,1)");
    $stmt->bind_param("ssss", $nama, $username, $password_simpan, $role);

    if($stmt->execute()){
      alert('success', 'User berhasil ditambahkan.');
      redirect_user_page();
    } else {
      alert('danger', 'Gagal menambahkan user:' . $conn->error);
    }
    redirect_user_page();
    
  } 

  /*update user*/ 
  if($action === 'update'){
  $id =(int)($_POST['id'] ?? 0);
  $nama = trim($_POST['nama']?? '');
  $username = trim($_POST['username']?? '');
  $role =$_POST['role'] ?? 'teller';
  $password = $_POST['password'] ?? '';

  if ($id <= 0 || $nama === '' || $username === ''){
    alert('danger', 'Data user tidak lengkap');
    redirect_user_page();
  }

  if(!in_array($role, $roles)){
    $role = 'teller';
  }

  $cek = $conn->prepare(" SELECT id FROM ksp_users WHERE username = ? AND id  != ? LIMIT 1");
  $cek->bind_param('si', $username, $id);
  $cek->execute();
  $hasil = $cek->get_result();

  if ($hasil->num_rows > 0) {
    alert('danger', 'Username sudah digunakan.');
    header('Location: index.php?m=user&edit=' . $id);
    exit;
  }

  if ($password !== ''){
    $password_simpan  = $password;

    $stmt = $conn->prepare("UPDATE ksp_users SET nama = ?, username = ?, password = ?, role = ? WHERE id = ?");
    $stmt->bind_param("ssssi", $nama, $username, $password_simpan, $role, $id);
  }else{
    $stmt = $conn->prepare("UPDATE ksp_users SET nama = ? , username = ?, role = ?  WHERE id =? ");
    $stmt->bind_param("sssi" , $nama, $username, $role, $id);
  }

  if($stmt->execute()){ 
    alert('success', 'Data user berhasil diperbarui.');
  }else{
    alert('danger', 'Gagal memperbarui data user.');
  }
  redirect_user_page();
  }
  /*  aktif/nonaktif */
  if($action === 'toggle'){
  $id = (int)($_POST['id'] ?? 0);
  $user_login_id = (int)user('id', 0);

  if ($id === $user_login_id){
    alert('danger', 'Tidak dapat menonaktifkan akun Anda sendiri.');
    redirect_user_page();
  }

  $stmt = $conn->prepare( "UPDATE ksp_users SET aktif = IF(aktif = 1,0,1) WHERE id = ? ");
  $stmt-> bind_param("i", $id);

  if($stmt->execute()){
    alert('success', 'Status user berhasil diubah. ');
  } else {
    alert('danger', 'Gagal mengubah status user.');
  }

  redirect_user_page();
  }
  /* delete */
  if($action === 'delete'){
  $id = (int)($_POST['id'] ?? 0);
  $user_login_id = (int)user('id', 0);

  if ($id === $user_login_id){
    alert('danger', 'Tidak boleh menghapus akun Anda sendiri.');
    redirect_user_page();
  }

  $stmt = $conn->prepare("DELETE FROM ksp_users WHERE id = ?");
  $stmt->bind_param("i", $id);

  if($stmt->execute()){
    alert('success', 'User berhasil dihapus.');
  }else{
    alert('danger', 'User tidak dapat dihapus karena masih memiliki transaksi.');
  }
  redirect_user_page();
  }
}

/* get data edit*/

$edit_user = null;

if(isset($_GET['edit'])){
  $edit_id  = (int)$_GET['edit'];

  $stmt = $conn->prepare("SELECT id, nama, username, role FROM ksp_users WHERE id = ? LIMIT 1");
  $stmt->bind_param("i", $edit_id);
  $stmt->execute();
  $edit_user = $stmt->get_result()->fetch_assoc();
}

/* list user */

$list_user = $conn->query("
  SELECT id, nama, username, role, aktif, created_at
  FROM ksp_users
  ORDER BY id DESC
");
?>


<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0">
    <i class="bi bi-people-fill me-2"></i>Master User
  </h4>

  <button class="btn btn-gradient" data-bs-toggle="collapse" data-bs-target="#formTambahUser">
    <i class="bi bi-plus-lg me-1"></i>Tambah User
  </button>
</div>

<?php flash(); ?>

<!-- FORM TAMBAH USER -->
<div id="formTambahUser" class="collapse mb-3">
  <div class="card card-widget">
    <div class="card-body">
      <h6>Tambah User Baru</h6>

      <form method="post" class="row g-3">
        <?php echo csrf_input(); ?>
        <input type="hidden" name="action" value="create">

        <div class="col-md-4">
          <label class="form-label">Nama</label>
          <input type="text" name="nama" class="form-control" required>
        </div>

        <div class="col-md-3">
          <label class="form-label">Username</label>
          <input type="text" name="username" class="form-control" required>
        </div>

        <div class="col-md-2">
          <label class="form-label">Role</label>
          <select name="role" class="form-select" required>
            <option value="teller">Teller</option>
            <option value="manager">Manager</option>
            <option value="admin">Admin</option>
          </select>
        </div>

        <div class="col-md-3">
          <label class="form-label">Password</label>
          <input type="password" name="password" class="form-control" required>
        </div>

        <div class="col-12">
          <button type="submit" class="btn btn-gradient">
            <i class="bi bi-save me-1"></i>Simpan User
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- FORM EDIT USER -->
<?php if ($edit_user): ?>
<div class="card card-widget mb-3 border-primary">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h6 class="mb-0">Edit User: <?php echo htmlspecialchars($edit_user['nama']); ?></h6>

      <a href="index.php?m=user" class="btn btn-sm btn-outline-secondary">
        Batal
      </a>
    </div>

    <form method="post" class="row g-3">
      <?php echo csrf_input(); ?>
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="id" value="<?php echo (int)$edit_user['id']; ?>">

      <div class="col-md-4">
        <label class="form-label">Nama</label>
        <input
          type="text"
          name="nama"
          class="form-control"
          value="<?php echo htmlspecialchars($edit_user['nama']); ?>"
          required>
      </div>

      <div class="col-md-3">
        <label class="form-label">Username</label>
        <input
          type="text"
          name="username"
          class="form-control"
          value="<?php echo htmlspecialchars($edit_user['username']); ?>"
          required>
      </div>

      <div class="col-md-2">
        <label class="form-label">Role</label>
        <select name="role" class="form-select" required>
          <option value="teller" <?php echo $edit_user['role'] === 'teller' ? 'selected' : ''; ?>>Teller</option>
          <option value="manager" <?php echo $edit_user['role'] === 'manager' ? 'selected' : ''; ?>>Manager</option>
          <option value="admin" <?php echo $edit_user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
        </select>
      </div>

      <div class="col-md-3">
        <label class="form-label">Password Baru</label>
        <input type="password" name="password" class="form-control">
        <small class="text-muted">Kosongkan bila tidak diubah.</small>
      </div>

      <div class="col-12">
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-save me-1"></i>Update User
        </button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- TABEL USER -->
<div class="card card-widget">
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-striped datatable">
        <thead>
          <tr>
            <th>#</th>
            <th>Nama</th>
            <th>Username</th>
            <th>Role</th>
            <th>Status</th>
            <th>Dibuat</th>
            <th>Aksi</th>
          </tr>
        </thead>

        <tbody>
          <?php $no = 1; while ($user_data = $list_user->fetch_assoc()): ?>
            <tr>
              <td><?php echo $no++; ?></td>

              <td><?php echo htmlspecialchars($user_data['nama']); ?></td>

              <td><?php echo htmlspecialchars($user_data['username']); ?></td>

              <td>
                <?php
                  $warna_role = 'secondary';

                  if ($user_data['role'] === 'admin') {
                    $warna_role = 'danger';
                  } elseif ($user_data['role'] === 'manager') {
                    $warna_role = 'primary';
                  } elseif ($user_data['role'] === 'teller') {
                    $warna_role = 'success';
                  }
                ?>

                <span class="badge text-bg-<?php echo $warna_role; ?>">
                  <?php echo htmlspecialchars(ucfirst($user_data['role'])); ?>
                </span>
              </td>

              <td>
                <span class="badge text-bg-<?php echo $user_data['aktif'] ? 'success' : 'secondary'; ?>">
                  <?php echo $user_data['aktif'] ? 'Aktif' : 'Nonaktif'; ?>
                </span>
              </td>

              <td><?php echo htmlspecialchars($user_data['created_at']); ?></td>

              <td class="text-nowrap">
                <a
                  href="index.php?m=user&edit=<?php echo (int)$user_data['id']; ?>"
                  class="btn btn-sm btn-outline-primary"
                  title="Edit User">
                  <i class="bi bi-pencil"></i>
                </a>

                <?php if ((int)$user_data['id'] !== (int)user('id', 0)): ?>
                  <form method="post" class="d-inline">
                    <?php echo csrf_input(); ?>
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="id" value="<?php echo (int)$user_data['id']; ?>">

                    <button
                      type="submit"
                      class="btn btn-sm btn-outline-warning"
                      title="<?php echo $user_data['aktif'] ? 'Nonaktifkan' : 'Aktifkan'; ?>"
                      onclick="return confirm('Ubah status user ini?')">
                      <i class="bi bi-power"></i>
                    </button>
                  </form>

                  <form method="post" class="d-inline">
                    <?php echo csrf_input(); ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo (int)$user_data['id']; ?>">

                    <button
                      type="submit"
                      class="btn btn-sm btn-outline-danger"
                      title="Hapus User"
                      onclick="return confirm('Hapus user <?php echo htmlspecialchars($user_data['nama'], ENT_QUOTES); ?>?')">
                      <i class="bi bi-trash"></i>
                    </button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
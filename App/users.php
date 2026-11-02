<?php
require 'config/database.php';
require 'includes/functions.php';

// Proteksi: Hanya admin yang dapat mengakses manajemen pengguna
if (($_SESSION['role'] ?? '') !== 'admin') {
    flash('Akses ditolak. Halaman Manajemen Pengguna hanya untuk Admin.', 'danger');
    redirect('index.php');
}

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $act = $_POST['form_action'] ?? '';

    if ($act === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([(int)$_POST['id']]);
        flash('Pengguna berhasil dihapus.');
        redirect('users.php');
    }

    if ($act === 'save') {
        $username = trim($_POST['username'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $role = $_POST['role'] ?? 'staff';
        $password = $_POST['password'] ?? '';
        $uid = (int)($_POST['id'] ?? 0);

        if ($username === '' || $name === '') {
            flash('Username dan Nama Wajib diisi.', 'danger');
            redirect('users.php?action=' . ($uid ? 'edit&id='.$uid : 'create'));
        }

        if ($uid) {
            if ($password !== '') {
                // Diubah menggunakan md5() sesuai database
                $hash = md5($password);
                $q = $pdo->prepare("UPDATE users SET username=?, name=?, role=?, password=? WHERE id=?");
                $q->execute([$username, $name, $role, $hash, $uid]);
            } else {
                $q = $pdo->prepare("UPDATE users SET username=?, name=?, role=? WHERE id=?");
                $q->execute([$username, $name, $role, $uid]);
            }
            flash('Data Pengguna berhasil diperbarui.');
        } else {
            if ($password === '') {
                flash('Password wajib diisi untuk pengguna baru.', 'danger');
                redirect('users.php?action=create');
            }
            // Diubah menggunakan md5() sesuai database
            $hash = md5($password);
            $q = $pdo->prepare("INSERT INTO users(username, name, role, password) VALUES(?,?,?,?)");
            $q->execute([$username, $name, $role, $hash]);
            flash('Pengguna baru berhasil ditambahkan.');
        }
        redirect('users.php');
    }
}

$edit = null;
if ($action === 'edit' && $id) {
    $q = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $q->execute([$id]);
    $edit = $q->fetch();
    if (!$edit) {
        flash('Pengguna tidak ditemukan.', 'danger');
        redirect('users.php');
    }
}

// Logika Pencarian Pengguna
$search = trim($_GET['q'] ?? '');
$whereSql = "1=1";
$params = [];

if ($search !== '') {
    $whereSql .= " AND (username LIKE ? OR name LIKE ? OR role LIKE ?)";
    $like = "%$search%";
    $params = [$like, $like, $like];
}

// Konfigurasi Paginasi (Maksimal 10 data per halaman)
$limit = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

// Hitung total data
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM users WHERE $whereSql");
$stmtCount->execute($params);
$totalData = (int)$stmtCount->fetchColumn();
$totalPages = ceil($totalData / $limit);

// Query data pengguna dengan limit dan offset
$sql = "SELECT id, username, name, role, created_at FROM users WHERE $whereSql ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$pageTitle = 'Manajemen Pengguna';
require 'includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
        <h1 class="page-heading h3 mb-1">Manajemen Pengguna</h1>
        <div class="text-muted">Kelola akun pengguna aplikasi.</div>
    </div>
    <?php if ($action === 'list'): ?>
        <a class="btn btn-primary" href="users.php?action=create">+ Tambah Pengguna</a>
    <?php endif; ?>
</div>

<?php if (in_array($action, ['create', 'edit'])): ?>
<div class="card p-3 p-md-4 mb-4 shadow-sm border-0">
    <h2 class="h5 mb-3"><?= $edit ? 'Edit Pengguna' : 'Tambah Pengguna' ?></h2>
    <form method="post" class="row g-3">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="form_action" value="save">
        <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
        
        <div class="col-md-6"><label class="form-label">Username *</label><input required class="form-control" name="username" value="<?= e($edit['username'] ?? '') ?>"></div>
        <div class="col-md-6"><label class="form-label">Nama Lengkap *</label><input required class="form-control" name="name" value="<?= e($edit['name'] ?? '') ?>"></div>
        
        <div class="col-md-6">
            <label class="form-label">Password <?= $edit ? '<small class="text-muted">(Kosongkan jika tidak diganti)</small>' : '*' ?></label>
            <div class="input-group">
                <input type="password" class="form-control" name="password" id="passwordInput" <?= $edit ? '' : 'required' ?>>
                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                    <i class="bi bi-eye" id="toggleIcon"></i>
                </button>
            </div>
        </div>

        <div class="col-md-6"><label class="form-label">Role</label>
            <select name="role" class="form-select">
                <option value="admin" <?= ($edit['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
                <option value="karyawan" <?= ($edit['role'] ?? '') === 'karyawan' ? 'selected' : '' ?>>Karyawan</option>
                <option value="staff" <?= ($edit['role'] ?? '') === 'staff' ? 'selected' : '' ?>>Staff</option>
            </select>
        </div>
        <div class="col-12">
            <button class="btn btn-primary">Simpan</button>
            <a href="users.php" class="btn btn-light">Batal</a>
        </div>
    </form>
</div>

<script>
document.getElementById('togglePassword').addEventListener('click', function () {
    const passwordInput = document.getElementById('passwordInput');
    const toggleIcon = document.getElementById('toggleIcon');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleIcon.classList.remove('bi-eye');
        toggleIcon.classList.add('bi-eye-slash');
    } else {
        passwordInput.type = 'password';
        toggleIcon.classList.remove('bi-eye-slash');
        toggleIcon.classList.add('bi-eye');
    }
});
</script>

<?php else: ?>
<!-- Form Filter/Pencarian Pengguna -->
<div class="card p-3 p-md-4 mb-4 shadow-sm border-0">
    <form class="row g-2">
        <div class="col-md-5">
            <input class="form-control" name="q" placeholder="Cari username, nama, atau role..." value="<?= e($search) ?>">
        </div>
        <div class="col-auto">
            <button class="btn btn-outline-primary"><i class="bi bi-search"></i> Cari</button> 
            <a class="btn btn-light" href="users.php">Reset</a>
        </div>
    </form>
</div>

<div class="card p-3 p-md-4 shadow-sm border-0">
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Nama</th>
                    <th>Role</th>
                    <th>Tanggal Dibuat</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">Tidak ada pengguna yang ditemukan.</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $u): ?>
                <tr>
                    <td class="fw-semibold"><?= e($u['username']) ?></td>
                    <td><?= e($u['name']) ?></td>
                    <td><span class="badge text-bg-<?= $u['role'] === 'admin' ? 'primary' : 'secondary' ?>"><?= e($u['role']) ?></span></td>
                    <td><?= date('d-m-Y H:i', strtotime($u['created_at'])) ?></td>
                    <td>
                        <a class="btn btn-sm btn-outline-primary" href="users.php?action=edit&id=<?= $u['id'] ?>">Edit</a>
                        <form class="d-inline" method="post" onsubmit="return confirm('Hapus pengguna ini?')">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="form_action" value="delete">
                            <input type="hidden" name="id" value="<?= $u['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger">Hapus</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Navigasi Paginasi -->
    <?php if ($totalPages > 1): ?>
    <nav class="mt-4">
        <ul class="pagination justify-content-center mb-0">
            <?php 
                $queryParams = $_GET;
                unset($queryParams['page']);
            ?>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <?php 
                    $queryParams['page'] = $i;
                    $queryString = http_build_query($queryParams);
                ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="users.php?<?= $queryString ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php require 'includes/footer.php'; ?>
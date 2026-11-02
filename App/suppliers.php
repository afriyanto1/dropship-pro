<?php
require 'config/database.php';
require 'includes/functions.php';

// Proteksi: Jika pengguna belum login atau sesi habis, arahkan ke halaman login
if (!isset($_SESSION['user_id'])) {
    flash('Sesi Anda telah kedaluwarsa. Silakan login kembali.', 'warning');
    redirect('login.php');
}

// Proteksi: Hanya admin yang dapat mengakses halaman Supplier
if (($_SESSION['role'] ?? '') !== 'admin') {
    flash('Akses ditolak. Halaman Supplier hanya untuk Admin.', 'danger');
    redirect('index.php');
}

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf(); 
    $act = $_POST['form_action'] ?? '';
    
    if ($act === 'delete') {
        $q = $pdo->prepare("DELETE FROM suppliers WHERE id = ?");
        $q->execute([(int)$_POST['id']]);
        flash('Supplier dihapus.');
        redirect('suppliers.php');
    }
    
    if ($act === 'save') {
        $suppId = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $contact = trim($_POST['contact_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        
        if ($name === '') {
            flash('Nama supplier wajib diisi.', 'danger');
            redirect($suppId ? "suppliers.php?action=edit&id=$suppId" : 'suppliers.php?action=create');
        }
        
        if ($suppId) {
            $q = $pdo->prepare("UPDATE suppliers SET name=?, contact_name=?, phone=?, email=?, address=?, notes=? WHERE id=?");
            $q->execute([$name, $contact ?: null, $phone ?: null, $email ?: null, $address ?: null, $notes ?: null, $suppId]);
            flash('Supplier diperbarui.');
        } else {
            $q = $pdo->prepare("INSERT INTO suppliers(name, contact_name, phone, email, address, notes) VALUES(?,?,?,?,?,?)");
            $q->execute([$name, $contact ?: null, $phone ?: null, $email ?: null, $address ?: null, $notes ?: null]);
            flash('Supplier ditambahkan.');
        }
        redirect('suppliers.php');
    }
}

$edit = null;
if ($action === 'edit' && $id) {
    $q = $pdo->prepare("SELECT * FROM suppliers WHERE id = ?");
    $q->execute([$id]);
    $edit = $q->fetch();
    if (!$edit) {
        flash('Supplier tidak ditemukan.', 'danger');
        redirect('suppliers.php');
    }
}

// Logika Pencarian Supplier
$search = trim($_GET['q'] ?? '');
$whereSql = "1=1";
$params = [];

if ($search !== '') {
    $whereSql .= " AND (s.name LIKE ? OR s.contact_name LIKE ? OR s.phone LIKE ? OR s.email LIKE ?)";
    $like = "%$search%";
    $params = [$like, $like, $like, $like];
}

// Konfigurasi Paginasi (Maksimal 10 data per halaman)
$limit = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

// Hitung total data
$stmtCount = $pdo->prepare("SELECT COUNT(DISTINCT s.id) FROM suppliers s LEFT JOIN products p ON p.supplier_id = s.id WHERE $whereSql");
$stmtCount->execute($params);
$totalData = (int)$stmtCount->fetchColumn();
$totalPages = ceil($totalData / $limit);

// Ambil data supplier dengan limit dan offset
$sql = "SELECT s.*, COUNT(p.id) product_count FROM suppliers s LEFT JOIN products p ON p.supplier_id = s.id WHERE $whereSql GROUP BY s.id ORDER BY s.name ASC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$pageTitle = 'Supplier';
require 'includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
        <h1 class="page-heading h3 mb-1">Supplier</h1>
        <div class="text-muted">Data pemasok dan kontak supplier.</div>
    </div>
    <?php if ($action === 'list'): ?>
        <a class="btn btn-primary" href="suppliers.php?action=create">+ Tambah Supplier</a>
    <?php endif; ?>
</div>

<?php if (in_array($action, ['create', 'edit'])): ?>
<div class="card p-3 p-md-4 mb-4 shadow-sm border-0">
    <h2 class="h5 mb-3"><?= $edit ? 'Edit Supplier' : 'Tambah Supplier' ?></h2>
    <form method="post" class="row g-3">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="form_action" value="save">
        <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
        
        <div class="col-md-6"><label class="form-label">Nama supplier *</label><input required class="form-control" name="name" value="<?= e($edit['name'] ?? '') ?>"></div>
        <div class="col-md-6"><label class="form-label">Nama kontak</label><input class="form-control" name="contact_name" value="<?= e($edit['contact_name'] ?? '') ?>"></div>
        <div class="col-md-4"><label class="form-label">Telepon</label><input class="form-control" name="phone" value="<?= e($edit['phone'] ?? '') ?>"></div>
        <div class="col-md-4"><label class="form-label">Email</label><input type="email" class="form-control" name="email" value="<?= e($edit['email'] ?? '') ?>"></div>
        <div class="col-md-4"><label class="form-label">Alamat</label><input class="form-control" name="address" value="<?= e($edit['address'] ?? '') ?>"></div>
        <div class="col-12"><label class="form-label">Catatan</label><textarea class="form-control" name="notes" rows="2"><?= e($edit['notes'] ?? '') ?></textarea></div>
        <div class="col-12"><button class="btn btn-primary">Simpan</button> <a href="suppliers.php" class="btn btn-light">Batal</a></div>
    </form>
</div>
<?php else: ?>
<!-- Form Filter/Pencarian Supplier -->
<div class="card p-3 p-md-4 mb-4 shadow-sm border-0">
    <form class="row g-2">
        <div class="col-md-5">
            <input class="form-control" name="q" placeholder="Cari nama supplier, kontak, telepon, email..." value="<?= e($search) ?>">
        </div>
        <div class="col-auto">
            <button class="btn btn-outline-primary"><i class="bi bi-search"></i> Cari</button> 
            <a class="btn btn-light" href="suppliers.php">Reset</a>
        </div>
    </form>
</div>

<div class="card p-3 p-md-4 shadow-sm border-0">
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Supplier</th>
                    <th>Kontak</th>
                    <th>Telepon</th>
                    <th>Email</th>
                    <th>Jumlah produk</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">Belum ada supplier yang ditemukan.</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $s): ?>
                <tr>
                    <td class="fw-semibold"><?= e($s['name']) ?></td>
                    <td><?= e($s['contact_name'] ?? '-') ?></td>
                    <td><?= e($s['phone'] ?? '-') ?></td>
                    <td><?= e($s['email'] ?? '-') ?></td>
                    <td><?= $s['product_count'] ?></td>
                    <td class="text-nowrap">
                        <a class="btn btn-sm btn-outline-primary" href="suppliers.php?action=edit&id=<?= $s['id'] ?>">Edit</a> 
                        <form class="d-inline" method="post" onsubmit="return confirm('Hapus supplier ini? Produk terkait akan tetap ada tanpa supplier.')">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="form_action" value="delete">
                            <input type="hidden" name="id" value="<?= $s['id'] ?>">
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
                    <a class="page-link" href="suppliers.php?<?= $queryString ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php require 'includes/footer.php'; ?>
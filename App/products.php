<?php
require 'config/database.php'; 
require 'includes/functions.php';

// Proteksi: Jika pengguna belum login atau sesi habis, arahkan ke halaman login
if (!isset($_SESSION['user_id'])) {
    flash('Sesi Anda telah kedaluwarsa. Silakan login kembali.', 'warning');
    redirect('login.php');
}

$action = $_GET['action'] ?? 'list'; 
$id = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf(); 
    $act = $_POST['form_action'] ?? '';
    
    if ($act === 'delete') { 
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?"); 
        $stmt->execute([(int)$_POST['id']]); 
        flash('Produk berhasil dihapus.'); 
        redirect('products.php'); 
    }
    
    if ($act === 'save') {
        $name = trim($_POST['name'] ?? ''); 
        $sku = trim($_POST['sku'] ?? ''); 
        $supplier = (int)($_POST['supplier_id'] ?? 0);
        $cost = (float)($_POST['cost_price'] ?? 0); 
        $sell = (float)($_POST['selling_price'] ?? 0); 
        $category = trim($_POST['category'] ?? ''); 
        $description = trim($_POST['description'] ?? '');
        
        if ($name === '' || $cost < 0 || $sell < 0) { 
            flash('Nama produk wajib diisi dan harga tidak boleh negatif.', 'danger'); 
            redirect('products.php?action='.$act.'&id='.(int)($_POST['id'] ?? 0)); 
        }
        
        $pid = (int)($_POST['id'] ?? 0);
        if ($pid) { 
            $q = $pdo->prepare("UPDATE products SET name=?, sku=?, supplier_id=?, cost_price=?, selling_price=?, category=?, description=? WHERE id=?"); 
            $q->execute([$name, $sku ?: null, $supplier ?: null, $cost, $sell, $category ?: null, $description ?: null, $pid]); 
            flash('Produk berhasil diperbarui.'); 
        } else { 
            $q = $pdo->prepare("INSERT INTO products(name, sku, supplier_id, cost_price, selling_price, category, description) VALUES(?,?,?,?,?,?,?)"); 
            $q->execute([$name, $sku ?: null, $supplier ?: null, $cost, $sell, $category ?: null, $description ?: null]); 
            flash('Produk berhasil ditambahkan.'); 
        }
        redirect('products.php');
    }
}

$edit = null; 
if ($action === 'edit' && $id) { 
    $q = $pdo->prepare("SELECT * FROM products WHERE id = ?"); 
    $q->execute([$id]); 
    $edit = $q->fetch(); 
    if (!$edit) {
        flash('Produk tidak ditemukan.', 'danger');
        redirect('products.php');
    } 
}

$suppliers = $pdo->query("SELECT id, name FROM suppliers ORDER BY name")->fetchAll();
$search = trim($_GET['q'] ?? '');

$whereSql = "1=1";
$params = []; 
if ($search !== '') {
    $whereSql .= " AND (p.name LIKE ? OR p.sku LIKE ? OR p.category LIKE ?)";
    $like = "%$search%";
    $params = [$like, $like, $like];
}

// Konfigurasi Paginasi (Maksimal 10 data per halaman)
$limit = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

// Hitung total data
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM products p WHERE $whereSql");
$stmtCount->execute($params);
$totalData = (int)$stmtCount->fetchColumn();
$totalPages = ceil($totalData / $limit);

// Ambil data produk dengan limit dan offset
$sql = "SELECT p.*, s.name supplier_name FROM products p LEFT JOIN suppliers s ON s.id = p.supplier_id WHERE $whereSql ORDER BY p.created_at DESC LIMIT $limit OFFSET $offset";
$q = $pdo->prepare($sql);
$q->execute($params);
$rows = $q->fetchAll();

$pageTitle = 'Produk';
require 'includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
        <h1 class="page-heading h3 mb-1">Produk</h1>
        <div class="text-muted">Kelola katalog, harga modal, dan harga jual.</div>
    </div>
    <?php if ($action === 'list'): ?>
        <a class="btn btn-primary" href="products.php?action=create">+ Tambah Produk</a>
    <?php endif; ?>
</div>

<?php if (in_array($action, ['create', 'edit'])): ?>
<div class="card p-3 p-md-4 mb-4">
    <h2 class="h5 mb-3"><?= $edit ? 'Edit Produk' : 'Tambah Produk' ?></h2>
    <form method="post" class="row g-3">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="form_action" value="save">
        <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
        
        <div class="col-md-6"><label class="form-label">Nama produk *</label><input required class="form-control" name="name" value="<?= e($edit['name'] ?? '') ?>"></div>
        <div class="col-md-3"><label class="form-label">SKU</label><input class="form-control" name="sku" value="<?= e($edit['sku'] ?? '') ?>"></div>
        <div class="col-md-3"><label class="form-label">Kategori</label><input class="form-control" name="category" value="<?= e($edit['category'] ?? '') ?>"></div>
        <div class="col-md-6"><label class="form-label">Supplier</label>
            <select class="form-select" name="supplier_id">
                <option value="">-- Pilih supplier --</option>
                <?php foreach ($suppliers as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= (int)($edit['supplier_id'] ?? 0) === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3"><label class="form-label">Harga modal</label><input type="number" min="0" step="0.01" required class="form-control" name="cost_price" value="<?= e($edit['cost_price'] ?? 0) ?>"></div>
        <div class="col-md-3"><label class="form-label">Harga jual</label><input type="number" min="0" step="0.01" required class="form-control" name="selling_price" value="<?= e($edit['selling_price'] ?? 0) ?>"></div>
        <div class="col-12"><label class="form-label">Deskripsi</label><textarea class="form-control" name="description" rows="2"><?= e($edit['description'] ?? '') ?></textarea></div>
        <div class="col-12">
            <button class="btn btn-primary">Simpan</button> 
            <a href="products.php" class="btn btn-light">Batal</a>
        </div>
    </form>
</div>
<?php else: ?>
<div class="card p-3 p-md-4">
    <form class="row g-2 mb-3">
        <div class="col-md-5"><input class="form-control" name="q" placeholder="Cari nama, SKU, kategori..." value="<?= e($search) ?>"></div>
        <div class="col-auto"><button class="btn btn-outline-primary">Cari</button> <a class="btn btn-light" href="products.php">Reset</a></div>
    </form>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Produk</th>
                    <th>Supplier</th>
                    <th>Harga modal</th>
                    <th>Harga jual</th>
                    <th>Margin/unit</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">Produk belum tersedia.</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $p): ?>
                <tr>
                    <td>
                        <div class="fw-semibold"><?= e($p['name']) ?></div>
                        <small class="text-muted"><?= e($p['sku'] ?: 'Tanpa SKU') ?><?= $p['category'] ? ' · ' . e($p['category']) : '' ?></small>
                    </td>
                    <td><?= e($p['supplier_name'] ?? '-') ?></td>
                    <td><?= rupiah($p['cost_price']) ?></td>
                    <td><?= rupiah($p['selling_price']) ?></td>
                    <td class="text-success"><?= rupiah($p['selling_price'] - $p['cost_price']) ?></td>
                    <td class="text-nowrap">
                        <a class="btn btn-sm btn-outline-primary" href="products.php?action=edit&id=<?= $p['id'] ?>">Edit</a>
                        <form class="d-inline" method="post" onsubmit="return confirm('Hapus produk ini?')">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="form_action" value="delete">
                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
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
                    <a class="page-link" href="products.php?<?= $queryString ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php require 'includes/footer.php'; ?>
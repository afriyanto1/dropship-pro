<?php
require 'config/database.php';
require 'includes/functions.php';

// Fitur Otomatis: Pesanan yang sudah >= 7 hari otomatis Lunas & Selesai
$pdo->exec("UPDATE orders SET payment_status = 'Lunas', partial_amount = total_amount, status = 'Selesai' WHERE created_at <= NOW() - INTERVAL 7 DAY AND (payment_status != 'Lunas' OR status != 'Selesai')");

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf(); 
    $act = $_POST['form_action'] ?? '';
    
    // Proteksi Ubah Status & Biaya: Hanya Admin yang diizinkan
    if ($act === 'status') {
        if (($_SESSION['role'] ?? '') !== 'admin') {
            flash('Akses ditolak. Hanya admin yang dapat mengubah status pesanan.', 'danger');
            redirect('orders.php?action=view&id=' . (int)$_POST['id']);
        }
        
        $status = $_POST['status'];
        $paymentStatus = $_POST['payment_status'];
        $trackingNumber = trim($_POST['tracking_number'] ?? '') ?: null;
        $partialAmount = (float)($_POST['partial_amount'] ?? 0);
        $fulfillmentCost = (float)($_POST['fulfillment_cost'] ?? 0);
        $adCost = (float)($_POST['ad_cost'] ?? 0);
        
        // Ambil total tagihan pesanan
        $qOrder = $pdo->prepare("SELECT total_amount FROM orders WHERE id=?");
        $qOrder->execute([(int)$_POST['id']]);
        $ordData = $qOrder->fetch();
        $totalAmount = $ordData['total_amount'] ?? 0;
        
        // Atur nominal otomatis berdasarkan status pembayaran
        if ($paymentStatus === 'Lunas') {
            $partialAmount = $totalAmount;
        } elseif ($paymentStatus === 'Belum dibayar' || $paymentStatus === 'Dikembalikan') {
            $partialAmount = 0; 
        } elseif ($paymentStatus === 'Pembayaran Partial') {
            if ($partialAmount >= $totalAmount) {
                $paymentStatus = 'Lunas';
                $partialAmount = $totalAmount;
            } elseif ($partialAmount <= 0) {
                $paymentStatus = 'Belum dibayar';
                $partialAmount = 0;
            }
        }

        $q = $pdo->prepare("UPDATE orders SET status=?, payment_status=?, partial_amount=?, fulfillment_cost=?, ad_cost=?, tracking_number=? WHERE id=?");
        $q->execute([$status, $paymentStatus, $partialAmount, $fulfillmentCost, $adCost, $trackingNumber, (int)$_POST['id']]);
        
        flash('Status dan rincian pesanan berhasil diperbarui.');
        redirect('orders.php?action=view&id=' . (int)$_POST['id']);
    }
    
    // Pembuatan Pesanan Baru
    if ($act === 'create') {
        $shipping = (float)($_POST['shipping_cost'] ?? 0);
        $fulfillmentCost = (float)($_POST['fulfillment_cost'] ?? 0);
        $adCost = (float)($_POST['ad_cost'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');
        $productIds = $_POST['product_id'] ?? [];
        $qtys = $_POST['quantity'] ?? [];
        
        if (!is_array($productIds) || count($productIds) === 0) {
            flash('Minimal satu produk wajib dipilih.', 'danger');
            redirect('orders.php?action=create');
        }
        
        try {
            $pdo->beginTransaction();
            $items = []; 
            $subtotal = 0;
            
            foreach ($productIds as $i => $pid) {
                $pid = (int)$pid;
                $qty = max(1, (int)($qtys[$i] ?? 1));
                $q = $pdo->prepare("SELECT id, name, cost_price, selling_price FROM products WHERE id=?");
                $q->execute([$pid]);
                $p = $q->fetch();
                if (!$p) continue;
                $items[] = ['p' => $p, 'qty' => $qty]; 
                $subtotal += (float)$p['selling_price'] * $qty;
            }
            
            if (!$items) throw new RuntimeException('Pilih produk yang valid.');
            
            $code = 'DS' . date('ymd') . strtoupper(bin2hex(random_bytes(3)));
            $total = $subtotal; // Total tagihan murni dari subtotal produk (tanpa penambahan ongkir)
            $customer = 'Customer';
            $address = '-';
            
            $q = $pdo->prepare("INSERT INTO orders(order_code, customer_name, customer_phone, shipping_address, subtotal, shipping_cost, fulfillment_cost, ad_cost, total_amount, status, payment_status, partial_amount, notes) VALUES(?,?,?,?,?,?,?,?,?,'Menunggu diproses','Belum dibayar',0,?)");
            $q->execute([$code, $customer, null, $address, $subtotal, $shipping, $fulfillmentCost, $adCost, $total, $notes ?: null]);
            $orderId = (int)$pdo->lastInsertId();
            
            $q = $pdo->prepare("INSERT INTO order_items(order_id, product_id, product_name, cost_price, selling_price, quantity, line_total) VALUES(?,?,?,?,?,?,?)");
            foreach ($items as $it) {
                $p = $it['p']; 
                $qty = $it['qty']; 
                $q->execute([$orderId, $p['id'], $p['name'], $p['cost_price'], $p['selling_price'], $qty, $p['selling_price'] * $qty]);
            }
            
            $pdo->commit();
            flash("Pesanan $code berhasil dibuat.");
            redirect('orders.php?action=view&id=' . $orderId);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flash('Pesanan gagal dibuat: ' . $e->getMessage(), 'danger');
            redirect('orders.php?action=create');
        }
    }
}

$products = $pdo->query("SELECT id, name, sku, cost_price, selling_price FROM products ORDER BY name")->fetchAll();

if ($action === 'view' && $id) {
    $q = $pdo->prepare("SELECT * FROM orders WHERE id=?");
    $q->execute([$id]);
    $order = $q->fetch();
    if (!$order) {
        flash('Pesanan tidak ditemukan.', 'danger');
        redirect('orders.php');
    }
    $q = $pdo->prepare("SELECT * FROM order_items WHERE order_id=?");
    $q->execute([$id]);
    $items = $q->fetchAll();
}

// Logika Filter Pesanan & Pencarian dengan Paginasi (10 data per halaman)
$search = trim($_GET['q'] ?? '');
$status_filter = $_GET['status'] ?? '';
$payment_filter = $_GET['payment_status'] ?? '';
$filter_active = $_GET['filter'] ?? '';

$whereSql = "1=1";
$params = [];

if ($search !== '') {
    $whereSql .= " AND (order_code LIKE ? OR customer_name LIKE ? OR customer_phone LIKE ?)";
    $like = "%$search%";
    $params[] = $like; 
    $params[] = $like; 
    $params[] = $like;
}

if ($filter_active === 'active') {
    $whereSql .= " AND status NOT IN ('Selesai','Dibatalkan')";
} elseif ($status_filter !== '') {
    $whereSql .= " AND status = ?";
    $params[] = $status_filter;
}

if ($payment_filter !== '') {
    $whereSql .= " AND payment_status = ?";
    $params[] = $payment_filter;
}

// Konfigurasi Paginasi
$limit = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

// Hitung total data sesuai filter
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE $whereSql");
$stmtCount->execute($params);
$totalData = (int)$stmtCount->fetchColumn();
$totalPages = ceil($totalData / $limit);

// Ambil data pesanan dengan limit dan offset
$sql = "SELECT * FROM orders WHERE $whereSql ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$pageTitle = 'Pesanan';
require 'includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
        <h1 class="page-heading h3 mb-1">Pesanan</h1>
        <div class="text-muted">Kelola pesanan pelanggan, biaya operasional, dan pengiriman.</div>
    </div>
    <div>
        <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
            <a class="btn btn-success me-2" href="import_po.php"><i class="bi bi-file-earmark-arrow-up me-1"></i> Import Pesanan</a>
        <?php endif; ?>
        <a class="btn btn-primary" href="orders.php?action=create"><i class="bi bi-plus-lg me-1"></i> Buat Pesanan</a>
    </div>
</div>

<?php if ($action === 'create'): ?>
<div class="card p-3 p-md-4 shadow-sm border-0">
    <h2 class="h5 mb-3">Buat Pesanan Baru</h2>
    <?php if (!$products): ?>
        <div class="alert alert-warning">Tambahkan produk terlebih dahulu sebelum membuat pesanan.</div>
    <?php endif; ?>
    <form method="post" id="orderForm" class="row g-3">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="form_action" value="create">
        
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <label class="form-label mb-0">Produk *</label>
                <button type="button" class="btn btn-sm btn-outline-primary" id="addItem">+ Tambah item</button>
            </div>
            <div id="items"></div>
        </div>
        
        <div class="col-md-4"><label class="form-label">Biaya pengiriman (Rp)</label><input type="number" min="0" step="1" class="form-control" name="shipping_cost" value="0"></div>
        <div class="col-md-4"><label class="form-label">Biaya FO (Rp)</label><input type="number" min="0" step="1" class="form-control" name="fulfillment_cost" value="0"></div>
        <div class="col-md-4"><label class="form-label">Biaya Iklan (Rp)</label><input type="number" min="0" step="1" class="form-control" name="ad_cost" value="0"></div>
        <div class="col-12"><label class="form-label">Catatan</label><input class="form-control" name="notes" placeholder="Catatan untuk pesanan"></div>
        
        <div class="col-12">
            <button class="btn btn-primary" <?= !$products ? 'disabled' : '' ?>>Simpan Pesanan</button> 
            <a href="orders.php" class="btn btn-light">Batal</a>
        </div>
    </form>
</div>
<script>
const products = <?= json_encode($products, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const wrap = document.getElementById('items');
function addItem() {
    const row = document.createElement('div');
    row.className = 'row g-2 align-items-end mb-2 order-item';
    let opts = '<option value="">Pilih produk</option>';
    products.forEach(p => opts += `<option value="${p.id}">${String(p.name).replace(/&/g,'&amp;').replace(/</g,'&lt;')} — Rp ${Number(p.selling_price).toLocaleString('id-ID')}</option>`);
    row.innerHTML = `<div class="col-md-8"><select required name="product_id[]" class="form-select">${opts}</select></div><div class="col-3 col-md-2"><input type="number" name="quantity[]" min="1" value="1" class="form-control" required></div><div class="col-3 col-md-2"><button type="button" class="btn btn-outline-danger w-100 remove">Hapus</button></div>`;
    row.querySelector('.remove').onclick = () => { if (wrap.children.length > 1) row.remove(); };
    wrap.appendChild(row);
}
document.getElementById('addItem').addEventListener('click', addItem);
addItem();
</script>

<?php elseif ($action === 'view' && isset($order)): ?>
<div class="row g-3">
    <div class="<?= (($_SESSION['role'] ?? '') === 'admin') ? 'col-lg-8' : 'col-12' ?>">
        <div class="card p-3 p-md-4 mb-3 shadow-sm border-0">
            <div class="d-flex flex-wrap justify-content-between gap-2">
                <div>
                    <div class="text-muted small">Nomor pesanan</div>
                    <h2 class="h4"><?= e($order['order_code']) ?></h2>
                </div>
                <?php 
                    $vStatus = trim($order['payment_status'] ?? '');
                    $isL = (strcasecmp($vStatus, 'Lunas') === 0);
                    $isP = (strcasecmp($vStatus, 'Pembayaran Partial') === 0 || strcasecmp($vStatus, 'Partial') === 0 || strcasecmp($vStatus, 'Belum lunas') === 0);
                    $badgeCol = $isL ? 'success' : ($isP ? 'warning text-dark' : 'danger');
                    $textDisp = $isP ? 'Pembayaran Partial' : ($vStatus !== '' ? $vStatus : 'Belum dibayar');
                ?>
                <div class="d-flex gap-2">
                    <span class="badge text-bg-secondary align-self-start py-2 px-3"><?= e($order['status']) ?></span>
                    <span class="badge text-bg-<?= $badgeCol ?> align-self-start py-2 px-3"><?= e($textDisp) ?></span>
                </div>
            </div>
            <hr>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Produk</th>
                            <th>Qty</th>
                            <th>Harga</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $it): ?>
                        <tr>
                            <td><?= e($it['product_name']) ?></td>
                            <td><?= $it['quantity'] ?></td>
                            <td><?= rupiah($it['selling_price']) ?></td>
                            <td><?= rupiah($it['line_total']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="ms-auto" style="max-width:360px">
                <div class="d-flex justify-content-between mb-1"><span>Subtotal Produk</span><span><?= rupiah($order['subtotal']) ?></span></div>
                <div class="d-flex justify-content-between mb-1 text-muted"><span>Ongkir</span><span><?= rupiah($order['shipping_cost']) ?></span></div>
                <div class="d-flex justify-content-between mb-1 text-muted"><span>Biaya FO</span><span><?= rupiah($order['fulfillment_cost']) ?></span></div>
                <div class="d-flex justify-content-between mb-1 text-muted"><span>Biaya Iklan</span><span><?= rupiah($order['ad_cost']) ?></span></div>
                <hr class="my-2">
                <div class="d-flex justify-content-between fw-bold mb-1"><span>Total Tagihan</span><span><?= rupiah($order['total_amount']) ?></span></div>
                <div class="d-flex justify-content-between text-success mb-1"><span>Sudah Dibayar</span><span><?= rupiah($order['partial_amount']) ?></span></div>
                <div class="d-flex justify-content-between text-danger fw-semibold">
                    <span>Sisa Kekurangan</span>
                    <span><?= rupiah(max(0, $order['total_amount'] - $order['partial_amount'])) ?></span>
                </div>
            </div>
        </div>
    </div>

    <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
    <div class="col-lg-4">
        <div class="card p-3 p-md-4 shadow-sm border-0">
            <h3 class="h5 mb-3">Perbarui Status & Biaya</h3>
            <form method="post" class="vstack gap-3">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="form_action" value="status">
                <input type="hidden" name="id" value="<?= $order['id'] ?>">
                <div>
                    <label class="form-label">Status pesanan</label>
                    <select name="status" class="form-select">
                        <?php foreach(['Menunggu diproses','Diproses supplier','Dikirim','Selesai','Dibatalkan'] as $s): ?>
                            <option <?= $order['status'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label">Status pembayaran</label>
                    <select name="payment_status" id="paymentStatusSelect" class="form-select">
                        <?php foreach(['Belum dibayar','Pembayaran Partial','Lunas','Dikembalikan'] as $s): ?>
                            <option <?= $order['payment_status'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div id="partialAmountWrapper">
                    <label class="form-label">Jumlah Dibayar (Rp)</label>
                    <input type="number" min="0" step="1" class="form-control" name="partial_amount" id="partialAmountInput" value="<?= (float)$order['partial_amount'] ?>">
                    <div class="form-text">Masukkan nominal cicilan/sebagian pembayaran.</div>
                </div>
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label">Biaya FO (Rp)</label>
                        <input type="number" min="0" step="1" class="form-control" name="fulfillment_cost" value="<?= (float)$order['fulfillment_cost'] ?>">
                    </div>
                    <div class="col-6">
                        <label class="form-label">Biaya Iklan (Rp)</label>
                        <input type="number" min="0" step="1" class="form-control" name="ad_cost" value="<?= (float)$order['ad_cost'] ?>">
                    </div>
                </div>
                <div>
                    <label class="form-label">Nomor resi</label>
                    <input class="form-control" name="tracking_number" value="<?= e($order['tracking_number'] ?? '') ?>">
                </div>
                <button class="btn btn-primary w-100">Simpan Perubahan</button>
            </form>
            <script>
            document.getElementById('paymentStatusSelect').addEventListener('change', function() {
                const total = <?= (float)$order['total_amount'] ?>;
                const partialInput = document.getElementById('partialAmountInput');
                if (this.value === 'Lunas') {
                    partialInput.value = total;
                } else if (this.value === 'Belum dibayar' || this.value === 'Dikembalikan') {
                    partialInput.value = 0;
                }
            });
            </script>
        </div>
    </div>
    <?php endif; ?>
</div>
<a href="orders.php" class="btn btn-light mt-3">← Kembali ke Pesanan</a>

<?php else: ?>
<!-- Form Filter Pesanan -->
<div class="card p-3 p-md-4 mb-4 shadow-sm border-0">
    <form method="get" class="row g-3 align-items-end">
        <?php if ($filter_active === 'active'): ?>
            <input type="hidden" name="filter" value="active">
        <?php endif; ?>
        <div class="col-md-6">
            <label class="form-label">Cari (No. Pesanan)</label>
            <input type="text" class="form-control" name="q" value="<?= e($search) ?>" placeholder="Kata kunci...">
        </div>
        <div class="col-md-2">
            <label class="form-label">Status Pesanan</label>
            <select name="status" class="form-select" <?= $filter_active === 'active' ? 'disabled' : '' ?>>
                <option value="">Semua Status</option>
                <?php foreach(['Menunggu diproses','Diproses supplier','Dikirim','Selesai','Dibatalkan'] as $s): ?>
                    <option value="<?= e($s) ?>" <?= $status_filter === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Status Pembayaran</label>
            <select name="payment_status" class="form-select">
                <option value="">Semua Pembayaran</option>
                <?php foreach(['Belum dibayar','Pembayaran Partial','Lunas','Dikembalikan'] as $s): ?>
                    <option value="<?= e($s) ?>" <?= $payment_filter === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <button class="btn btn-primary w-100 mb-1"><i class="bi bi-search"></i> Filter</button>
            <a href="orders.php" class="btn btn-light w-100">Reset</a>
        </div>
    </form>
</div>

<!-- Tabel Daftar Pesanan -->
<div class="card p-3 p-md-4 shadow-sm border-0">
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>No. Pesanan</th>
                    <th>Tanggal</th>
                    <th>Total</th>
                    <th class="d-none d-md-table-cell">Biaya FO / Iklan</th>
                    <th>Pembayaran</th>
                    <th class="d-none d-md-table-cell">Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">Tidak ada pesanan yang sesuai dengan filter.</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $o): ?>
                <tr>
                    <td class="fw-semibold"><?= e($o['order_code']) ?></td>
                    <!-- Format tanggal diubah ke dd/mm/yy -->
                    <td><?= e(date('d/m/y', strtotime($o['created_at']))) ?></td>
                    <td><?= rupiah($o['total_amount']) ?></td>
                    <td class="small text-muted d-none d-md-table-cell">
                        FO: <?= rupiah($o['fulfillment_cost']) ?><br>
                        Iklan: <?= rupiah($o['ad_cost']) ?>
                    </td>
                    <td>
                        <?php 
                            $rowStatus = trim($o['payment_status'] ?? '');
                            $rLunas = (strcasecmp($rowStatus, 'Lunas') === 0);
                            $rPartial = (strcasecmp($rowStatus, 'Pembayaran Partial') === 0 || strcasecmp($rowStatus, 'Partial') === 0);
                            $bClass = $rLunas ? 'success' : ($rPartial ? 'warning text-dark' : 'danger');
                            $tDisp = $rPartial ? 'Pembayaran Partial' : ($rowStatus !== '' ? $rowStatus : 'Belum dibayar');
                        ?>
                        <span class="badge text-bg-<?= $bClass ?>"><?= e($tDisp) ?></span>
                        <?php if($rPartial): ?>
                            <?php $sisaKurang = max(0, (float)$o['total_amount'] - (float)$o['partial_amount']); ?>
                            <div class="small text-danger mt-1" style="font-size: 0.75rem;">Belum dibayar: <?= rupiah($sisaKurang) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="d-none d-md-table-cell"><span class="badge text-bg-secondary bg-opacity-75"><?= e($o['status']) ?></span></td>
                    <td><a class="btn btn-sm btn-outline-primary" href="orders.php?action=view&id=<?= $o['id'] ?>">Detail</a></td>
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
                    <a class="page-link" href="orders.php?<?= $queryString ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
</div>
<?php endif; 
require 'includes/footer.php';
?>
<?php
require 'config/database.php';
require 'includes/functions.php';

// Proteksi Sesi: Jika belum login atau sesi habis, arahkan ke login.php
if (!isset($_SESSION['id'])) {
    flash('Sesi Anda telah kedaluwarsa. Silakan login kembali.', 'warning');
    redirect('login.php');
}

$pageTitle = 'Dashboard';
require 'includes/header.php';

$isAdmin = (($_SESSION['role'] ?? '') === 'admin');

// 1. Hitung Total Pendapatan (dari pesanan Lunas atau Pembayaran Partial)
$qPendapatan = $pdo->query("SELECT SUM(total_amount) as total FROM orders WHERE payment_status IN ('Lunas', 'Pembayaran Partial')")->fetch();
$totalPendapatan = $qPendapatan['total'] ?? 0;

if ($isAdmin) {
    // 2. Hitung Total Margin Produk dari pesanan yang valid/lunas/partial
    $qMargin = $pdo->query("SELECT SUM((oi.selling_price - oi.cost_price) * oi.quantity) as total_margin 
        FROM order_items oi JOIN orders o ON oi.order_id = o.id 
        WHERE o.payment_status IN ('Lunas', 'Pembayaran Partial')")->fetch();
    $totalMargin = $qMargin['total_margin'] ?? 0;

    // 3. Hitung Total Ongkir, Biaya FO, dan Biaya Iklan dari pesanan valid
    $qBiaya = $pdo->query("SELECT SUM(shipping_cost) as total_ongkir, SUM(fulfillment_cost) as total_fo, SUM(ad_cost) as total_ad 
        FROM orders WHERE payment_status IN ('Lunas', 'Pembayaran Partial')")->fetch();
    $totalOngkir = $qBiaya['total_ongkir'] ?? 0;
    $totalFo = $qBiaya['total_fo'] ?? 0;
    $totalAd = $qBiaya['total_ad'] ?? 0;

    // 4. Hitung Pendapatan Bersih = Total Margin - (Ongkir + Biaya FO + Biaya Iklan)
    $pendapatanBersih = $totalMargin - ($totalOngkir + $totalFo + $totalAd);

    // 5. Hitung Jumlah Pesanan Aktif (selain Selesai & Dibatalkan)
    $qActive = $pdo->query("SELECT COUNT(*) as total FROM orders WHERE status NOT IN ('Selesai', 'Dibatalkan')")->fetch();
    $activeOrdersCount = $qActive['total'] ?? 0;
}

// 6. Ambil 5 Pesanan Terbaru
$recentOrders = $pdo->query("SELECT * FROM orders ORDER BY created_at DESC LIMIT 5")->fetchAll();
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
        <h1 class="page-heading h3 mb-1">Dashboard</h1>
        <div class="text-muted">Selamat datang kembali, <strong><?= e($_SESSION['username'] ?? 'User') ?></strong>!</div>
    </div>
    <div>
        <a href="orders.php?action=create" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Buat Pesanan Baru</a>
    </div>
</div>

<!-- Kartu Ringkasan Statistik -->
<div class="row g-3 mb-4">
    <div class="<?= $isAdmin ? 'col-md-6 col-xl-3' : 'col-12' ?>">
        <div class="card p-3 shadow-sm border-0 bg-primary text-white h-100">
            <div class="small text-uppercase fw-semibold opacity-75">Total Pendapatan</div>
            <div class="h4 fw-bold my-2"><?= rupiah($totalPendapatan) ?></div>
            <div class="small opacity-85">Dari pesanan Lunas & Partial</div>
        </div>
    </div>
    <?php if ($isAdmin): ?>
    <div class="col-md-6 col-xl-3">
        <div class="card p-3 shadow-sm border-0 bg-success text-white h-100">
            <div class="small text-uppercase fw-semibold opacity-75">Laba Bersih</div>
            <div class="h4 fw-bold my-2"><?= rupiah($pendapatanBersih) ?></div>
            <div class="small opacity-85">Margin - (Ongkir + FO + Iklan)</div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card p-3 shadow-sm border-0 bg-warning text-dark h-100">
            <div class="small text-uppercase fw-semibold opacity-75">Pesanan Aktif</div>
            <div class="h4 fw-bold my-2"><?= number_format($activeOrdersCount) ?> Pesanan</div>
            <div class="small mt-auto">
                <a href="orders.php?filter=active" class="text-dark text-decoration-underline fw-semibold">Lihat Pesanan Aktif →</a>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card p-3 shadow-sm border-0 bg-info text-white h-100">
            <div class="small text-uppercase fw-semibold opacity-75">Total Biaya Operasional</div>
            <div class="h4 fw-bold my-2"><?= rupiah($totalOngkir + $totalFo + $totalAd) ?></div>
            <div class="small opacity-85">Akumulasi Biaya Ongkir, FO, & Iklan</div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Tabel Pesanan Terbaru -->
<div class="card p-3 p-md-4 shadow-sm border-0">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h5 mb-0">Pesanan Terbaru</h2>
        <a href="orders.php" class="btn btn-sm btn-outline-primary">Lihat Semua Pesanan</a>
    </div>
    <div class="table-responsive">
        <table class="table align-middle text-nowrap">
            <thead>
                <tr>
                    <th>No. Pesanan</th>
                    <th class="d-none d-md-table-cell">Pelanggan</th>
                    <th>Tanggal</th>
                    <th>Total</th>
                    <th>Pembayaran</th>
                    <th class="d-none d-md-table-cell">Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$recentOrders): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">Belum ada pesanan masuk.</td></tr>
                <?php endif; ?>
                <?php foreach ($recentOrders as $o): ?>
                <tr>
                    <td class="fw-semibold"><?= e($o['order_code']) ?></td>
                    <td class="d-none d-md-table-cell"><?= e($o['customer_name']) ?></td>
                    <!-- Format tanggal diubah ke dd/mm/yy -->
                    <td><?= e(date('d/m/y', strtotime($o['created_at']))) ?></td>
                    <td><?= rupiah($o['total_amount']) ?></td>
                    <td>
                        <?php 
                            $rowStatus = trim($o['payment_status'] ?? '');
                            $rLunas = (strcasecmp($rowStatus, 'Lunas') === 0);
                            $rPartial = (strcasecmp($rowStatus, 'Pembayaran Partial') === 0 || strcasecmp($rowStatus, 'Partial') === 0);
                            $bClass = $rLunas ? 'success' : ($rPartial ? 'warning text-dark' : 'danger');
                            $tDisp = $rPartial ? 'Pembayaran Partial' : ($rowStatus !== '' ? $rowStatus : 'Belum dibayar');
                        ?>
                        <span class="badge text-bg-<?= $bClass ?>"><?= e($tDisp) ?></span>
                    </td>
                    <td class="d-none d-md-table-cell"><span class="badge text-bg-secondary"><?= e($o['status']) ?></span></td>
                    <td><a class="btn btn-sm btn-outline-primary" href="orders.php?action=view&id=<?= $o['id'] ?>">Detail</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
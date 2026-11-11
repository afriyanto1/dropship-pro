<?php
require 'config/database.php';
require 'includes/functions.php';

// Proteksi Sesi: Jika belum login atau sesi habis, arahkan ke login.php
if (!isset($_SESSION['id'])) {
    flash('Sesi Anda telah kedaluwarsa. Silakan login kembali.', 'warning');
    redirect('login.php');
}
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$paymentStatusFilter = $_GET['payment_status'] ?? '';
$orderStatusFilter = $_GET['status'] ?? '';

$dateCondition = "1=1";
$params = [];

if ($startDate !== '' && $endDate !== '') {
    $dateCondition .= " AND DATE(created_at) BETWEEN ? AND ?";
    $params[] = $startDate;
    $params[] = $endDate;
} elseif ($startDate !== '') {
    $dateCondition .= " AND DATE(created_at) >= ?";
    $params[] = $startDate;
} elseif ($endDate !== '') {
    $dateCondition .= " AND DATE(created_at) <= ?";
    $params[] = $endDate;
}

if ($paymentStatusFilter !== '') {
    $dateCondition .= " AND payment_status = ?";
    $params[] = $paymentStatusFilter;
}

if ($orderStatusFilter !== '') {
    $dateCondition .= " AND status = ?";
    $params[] = $orderStatusFilter;
}

$isAdmin = (($_SESSION['role'] ?? '') === 'admin');

// Fitur Ekspor Excel (Hanya untuk Admin)
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    if (!$isAdmin) {
        flash('Akses ditolak. Staff tidak diizinkan mengekspor data.', 'danger');
        redirect('reports.php');
    }

    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename=laporan_keuangan_' . ($startDate ?: 'semua') . '_to_' . ($endDate ?: 'semua') . '.xls');
    
    $sqlExport = "SELECT o.*, 
        (SELECT SUM((oi.selling_price - oi.cost_price) * (oi.quantity - oi.returned_qty)) FROM order_items oi WHERE oi.order_id = o.id) as product_margin,
        (SELECT GROUP_CONCAT(p.name SEPARATOR ', ') FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = o.id) as ordered_items_list,
        (SELECT SUM(oi.quantity - oi.returned_qty) FROM order_items oi WHERE oi.order_id = o.id) as total_quantity
        FROM orders o WHERE $dateCondition ORDER BY o.created_at DESC";
    $stmtExp = $pdo->prepare($sqlExport);
    $stmtExp->execute($params);
    $ordersExp = $stmtExp->fetchAll();
    
    function formatExcelNumber($number) {
        return number_format((float)$number, 2, ',', '.');
    }

    echo "<table border='1'>";
    echo "<tr style='background-color: #f2f2f2; font-weight: bold;'>
            <th>No. Pesanan</th>
            <th>Tanggal</th>
            <th>Barang yang Dipesan</th>
            <th>Quantity (Efektif)</th>
            <th>Ongkir</th>
            <th>Biaya FO</th>
            <th>Biaya Iklan</th>
            <th>Total Pendapatan</th>
            <th>Pendapatan Bersih</th>
            <th>Laba Bersih</th>
            <th>Status Pembayaran</th>
            <th>Status Pesanan</th>
          </tr>";
          
    $grandTotal = 0;
    $grandPendapatanBersih = 0;
    $grandLabaBersih = 0;
    $grandTotalQty = 0;
    $grandOngkir = 0;
    $grandFo = 0;
    $grandAd = 0;

    foreach ($ordersExp as $o) {
        $margin = (float)($o['product_margin'] ?? 0);
        $ongkir = (float)($o['shipping_cost'] ?? 0);
        $fo = (float)($o['fulfillment_cost'] ?? 0);
        $ad = (float)($o['ad_cost'] ?? 0);
        $revenue = (float)($o['total_amount'] ?? 0);
        $totalQty = (int)($o['total_quantity'] ?? 0);

        // Pendapatan Bersih = Total Pendapatan - (Ongkir + Biaya FO + Biaya Iklan)
        $pendapatanBersihRow = $revenue - ($ongkir + $fo + $ad);
        // Laba Bersih = Total Margin - (Ongkir + Biaya FO + Biaya Iklan)
        $labaBersihRow = $margin - ($ongkir + $fo + $ad);

        if (in_array($o['payment_status'], ['Lunas', 'Pembayaran Partial'])) {
            $grandTotal += $revenue;
            $grandPendapatanBersih += $pendapatanBersihRow;
            $grandLabaBersih += $labaBersihRow;
            $grandTotalQty += $totalQty;
            $grandOngkir += $ongkir;
            $grandFo += $fo;
            $grandAd += $ad;
        }

        $excelDateFormatted = date('d/m/y', strtotime($o['created_at']));

        echo "<tr>
                <td>{$o['order_code']}</td>
                <td>{$excelDateFormatted}</td>
                <td>" . e($o['ordered_items_list'] ?? '-') . "</td>
                <td style='text-align: center;'>{$totalQty}</td>
                <td>" . formatExcelNumber($ongkir) . "</td>
                <td>" . formatExcelNumber($fo) . "</td>
                <td>" . formatExcelNumber($ad) . "</td>
                <td>" . formatExcelNumber($revenue) . "</td>
                <td>" . formatExcelNumber($pendapatanBersihRow) . "</td>
                <td>" . formatExcelNumber($labaBersihRow) . "</td>
                <td>{$o['payment_status']}</td>
                <td>{$o['status']}</td>
              </tr>";
    }
    
    echo "<tr style='font-weight: bold; background-color: #e6e6e6;'>
            <td colspan='3' style='text-align: right;'>TOTAL KESELURUHAN:</td>
            <td style='text-align: center;'>{$grandTotalQty}</td>
            <td>" . formatExcelNumber($grandOngkir) . "</td>
            <td>" . formatExcelNumber($grandFo) . "</td>
            <td>" . formatExcelNumber($grandAd) . "</td>
            <td>" . formatExcelNumber($grandTotal) . "</td>
            <td>" . formatExcelNumber($grandPendapatanBersih) . "</td>
            <td>" . formatExcelNumber($grandLabaBersih) . "</td>
            <td colspan='2'></td>
          </tr>";
    echo "</table>";
    exit;
}

$pageTitle = 'Laporan & Pendapatan';
require 'includes/header.php';

// Hitung Ringkasan Metrik
$sqlStats = "SELECT 
    SUM(CASE WHEN payment_status IN ('Lunas', 'Pembayaran Partial') THEN total_amount ELSE 0 END) as total_pendapatan,
    SUM(shipping_cost) as total_ongkir,
    SUM(fulfillment_cost) as total_fo,
    SUM(ad_cost) as total_ad
    FROM orders WHERE $dateCondition";
$stmtStats = $pdo->prepare($sqlStats);
$stmtStats->execute($params);
$stats = $stmtStats->fetch();

$totalPendapatan = $stats['total_pendapatan'] ?? 0;
$totalOngkir = $stats['total_ongkir'] ?? 0;
$totalFo = $stats['total_fo'] ?? 0;
$totalAd = $stats['total_ad'] ?? 0;

if ($isAdmin) {
    // Hitung Total Margin dengan mengurangi returned_qty
    $sqlMargin = "SELECT SUM((oi.selling_price - oi.cost_price) * (oi.quantity - oi.returned_qty)) as total_margin 
        FROM order_items oi JOIN orders o ON oi.order_id = o.id 
        WHERE o.payment_status IN ('Lunas', 'Pembayaran Partial') AND " . str_replace("created_at", "o.created_at", $dateCondition);
    $stmtMargin = $pdo->prepare($sqlMargin);
    $stmtMargin->execute($params);
    $margins = $stmtMargin->fetch();

    $totalMargin = $margins['total_margin'] ?? 0;

    $pendapatanBersih = $totalPendapatan - ($totalOngkir + $totalFo + $totalAd);
    $labaBersih = $totalMargin - ($totalOngkir + $totalFo + $totalAd);
}

// Paginasi Tabel Laporan
$limit = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$qCount = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE $whereSql"); // Wait, use $dateCondition
$qCount = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE $dateCondition");
$qCount->execute($params);
$totalData = $qCount->fetchColumn();
$totalPages = ceil($totalData / $limit);

$sqlOrders = "SELECT o.*, 
    (SELECT GROUP_CONCAT(CONCAT(p.name, ' (', (oi.quantity - oi.returned_qty), 'x)') SEPARATOR ', ') FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = o.id) as ordered_items_list 
    FROM orders o WHERE $dateCondition ORDER BY o.created_at DESC LIMIT $limit OFFSET $offset";
$stmtOrders = $pdo->prepare($sqlOrders);
$stmtOrders->execute($params);
$orders = $stmtOrders->fetchAll();
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
        <h1 class="page-heading h3 mb-1">Laporan Keuangan</h1>
        <div class="text-muted">Rekapitulasi Total Pendapatan usaha dropship.</div>
    </div>
    <?php if ($isAdmin): ?>
    <div>
        <a href="reports.php?export=excel&start_date=<?= e($startDate) ?>&end_date=<?= e($endDate) ?>&payment_status=<?= urlencode($paymentStatusFilter) ?>&status=<?= urlencode($orderStatusFilter) ?>" class="btn btn-success">
            <i class="bi bi-file-earmark-excel me-1"></i> Ekspor ke Excel
        </a>
    </div>
    <?php endif; ?>
</div>

<!-- Form Filter Laporan -->
<div class="card p-3 p-md-4 mb-4 shadow-sm border-0">
    <form method="get" class="row g-3 align-items-end">
        <div class="col-md-3">
            <label class="form-label">Dari Tanggal</label>
            <input type="date" name="start_date" class="form-control" value="<?= e($startDate) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Sampai Tanggal</label>
            <input type="date" name="end_date" class="form-control" value="<?= e($endDate) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Status Pembayaran</label>
            <select name="payment_status" class="form-select">
                <option value="">Semua Pembayaran</option>
                <?php foreach(['Belum dibayar', 'Pembayaran Partial', 'Lunas', 'Dikembalikan'] as $ps): ?>
                    <option value="<?= e($ps) ?>" <?= $paymentStatusFilter === $ps ? 'selected' : '' ?>><?= e($ps) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Status Pesanan</label>
            <select name="status" class="form-select">
                <option value="">Semua Status</option>
                <?php foreach(['Menunggu diproses', 'Diproses supplier', 'Dikirim', 'Selesai', 'Dibatalkan'] as $st): ?>
                    <option value="<?= e($st) ?>" <?= $orderStatusFilter === $st ? 'selected' : '' ?>><?= e($st) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 d-flex gap-2">
            <button class="btn btn-primary"><i class="bi bi-filter me-1"></i> Terapkan Filter</button>
            <a href="reports.php" class="btn btn-light">Reset</a>
        </div>
    </form>
</div>

<!-- Kartu Ringkasan Metrik -->
<div class="row g-3 mb-4">
    <div class="col-md-<?= $isAdmin ? '4' : '12' ?>">
        <div class="card p-3 shadow-sm border-0 bg-primary text-white h-100">
            <div class="small text-uppercase fw-semibold opacity-75">Total Pendapatan</div>
            <div class="h4 fw-bold my-1"><?= rupiah($totalPendapatan) ?></div>
            <div class="small opacity-85">Akumulasi nilai pesanan lunas & partial</div>
        </div>
    </div>
    <?php if ($isAdmin): ?>
    <div class="col-md-4">
        <div class="card p-3 shadow-sm border-0 bg-info text-white h-100">
            <div class="small text-uppercase fw-semibold opacity-75">Pendapatan Bersih</div>
            <div class="h4 fw-bold my-1"><?= rupiah($pendapatanBersih) ?></div>
            <div class="small opacity-85">Total Pendapatan - (Ongkir + FO + Iklan)</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card p-3 shadow-sm border-0 bg-success text-white h-100">
            <div class="small text-uppercase fw-semibold opacity-75">Laba Bersih</div>
            <div class="h4 fw-bold my-1"><?= rupiah($labaBersih) ?></div>
            <div class="small opacity-85">Total Margin - (Ongkir + FO + Iklan)</div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Tabel Rekapitulasi Laporan -->
<div class="card p-3 p-md-4 shadow-sm border-0">
    <h2 class="h5 mb-3">Rincian Laporan Pesanan</h2>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>No. Pesanan</th>
                    <th class="d-none d-md-table-cell">Tanggal</th>
                    <th>Barang Dipesan (Qty Efektif)</th>
                    <th>Total Pendapatan</th>
                    <?php if ($isAdmin): ?><th class="d-none d-md-table-cell">Biaya Operasional (Ongkir/FO/Iklan)</th><?php endif; ?>
                    <th>Status Pembayaran</th>
                    <th class="d-none d-md-table-cell">Status Pesanan</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$orders): ?>
                    <tr><td colspan="<?= $isAdmin ? '6' : '5' ?>" class="text-center text-muted py-4">Tidak ada data laporan pada filter tersebut.</td></tr>
                <?php endif; ?>
                <?php foreach ($orders as $o): ?>
                <tr>
                    <td class="fw-semibold"><?= e($o['order_code']) ?></td>
                    <td class="d-none d-md-table-cell"><?= e(date('d/m/y', strtotime($o['created_at']))) ?></td>
                    <td><small><?= e($o['ordered_items_list'] ?? '-') ?></small></td>
                    <td><?= rupiah($o['total_amount']) ?></td>
                    <?php if ($isAdmin): ?>
                    <td class="small text-muted d-none d-md-table-cell">
                        Ongkir: <?= rupiah($o['shipping_cost'] ?? 0) ?><br>
                        FO: <?= rupiah($o['fulfillment_cost'] ?? 0) ?><br>
                        Iklan: <?= rupiah($o['ad_cost'] ?? 0) ?>
                    </td>
                    <?php endif; ?>
                    <td>
                        <span class="badge text-bg-<?= $o['payment_status'] === 'Lunas' ? 'success' : ($o['payment_status'] === 'Pembayaran Partial' ? 'warning text-dark' : 'danger') ?>">
                            <?= e($o['payment_status']) ?>
                        </span>
                    </td>
                    <td class="d-none d-md-table-cell"><span class="badge text-bg-secondary"><?= e($o['status']) ?></span></td>
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
                    <a class="page-link" href="reports.php?<?= $queryString ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
</div>

<?php require 'includes/footer.php'; ?>
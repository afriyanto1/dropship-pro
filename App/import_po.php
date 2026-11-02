<?php
require 'config/database.php';
require 'includes/functions.php';

// Proteksi: Hanya admin yang dapat melakukan impor
if (($_SESSION['role'] ?? '') !== 'admin') {
    flash('Akses ditolak. Impor pesanan hanya untuk Admin.', 'danger');
    redirect('orders.php');
}

// Fitur Unduh Template Excel (Kolom status_pembayaran dihapus, format tanggal dd/mm/yy)
if (isset($_GET['template']) && $_GET['template'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=template_import_pesanan.csv');
    $output = fopen('php://output', 'w');
    // Header tanpa kolom status_pembayaran
    fputcsv($output, ['tanggal', 'nama_produk', 'jumlah_barang', 'jumlah_pembayaran', 'biaya_fo', 'biaya_iklan', 'status_pesanan']);
    // Contoh baris dengan format tanggal dd/mm/yyyy atau dd/mm/yy (misal: 17/10/2026)
    fputcsv($output, ['17/10/2026', 'Botol Minum 600ml', '2', '50000', '5000', '10000', 'Menunggu diproses']);
    fputcsv($output, ['09/11/2026', 'Canvas M', '1', '70000', '0', '0', 'Selesai']);
    fclose($output);
    exit;
}

// Proses Upload & Impor File Excel/CSV
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    
    if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
        flash('Pilih file CSV/Excel yang valid untuk diimpor.', 'danger');
        redirect('import_po.php');
    }

    $fileTmpPath = $_FILES['import_file']['tmp_name'];
    $fileName = $_FILES['import_file']['name'];
    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    if (!in_array($fileExtension, ['csv', 'txt'])) {
        flash('Gunakan format file CSV untuk impor.', 'danger');
        redirect('import_po.php');
    }

    $handle = fopen($fileTmpPath, 'r');
    if ($handle === false) {
        flash('Gagal membaca file.', 'danger');
        redirect('import_po.php');
    }

    // Lewati baris header
    $header = fgetcsv($handle);
    
    $successCount = 0;
    $errorCount = 0;

    try {
        $pdo->beginTransaction();

        while (($row = fgetcsv($handle)) !== false) {
            // Urutan kolom baru (tanpa status_pembayaran):
            // [0] tanggal, [1] nama_produk, [2] jumlah_barang, [3] jumlah_pembayaran, [4] biaya_fo, [5] biaya_iklan, [6] status_pesanan
            if (count($row) < 3) continue;

            $rawDate = trim($row[0]);
            $productName = trim($row[1]);
            $qty = max(1, (int)$row[2]);
            $partialAmount = (float)($row[3] ?? 0);
            $fulfillmentCost = (float)($row[4] ?? 0);
            $adCost = (float)($row[5] ?? 0);
            $orderStatus = trim($row[6] ?? 'Menunggu diproses');

            // Parse format tanggal dd/mm/yy ke Y-m-d
            $createdAt = date('Y-m-d H:i:s');
            if ($rawDate !== '') {
                // Mendukung pemisah / atau - (misal 17/10/26 atau 17-10-26)
                $dateParts = preg_split('/[\/\-]/', $rawDate);
                if (count($dateParts) === 3) {
                    $d = $dateParts[0];
                    $m = $dateParts[1];
                    $y = $dateParts[2];
                    if (strlen($y) === 2) {
                        $y = '20' . $y; // Konversi yy (26) menjadi 2026
                    }
                    if (checkdate((int)$m, (int)$d, (int)$y)) {
                        $createdAt = "$y-$m-$d 08:00:00";
                    }
                }
            }

            // Cari produk di database berdasarkan nama_produk untuk mengambil harga otomatis
            $qProd = $pdo->prepare("SELECT id, name, cost_price, selling_price FROM products WHERE name LIKE ?");
            $qProd->execute(["%$productName%"]);
            $prod = $qProd->fetch();

            if (!$prod) {
                $errorCount++;
                continue; // Lewati jika produk tidak ditemukan di database
            }

            $sellingPrice = (float)$prod['selling_price'];
            $costPrice = (float)$prod['cost_price'];
            $subtotal = $sellingPrice * $qty;
            $shippingCost = 0; // Ongkir terpisah

            // Aturan Total Tagihan murni dari subtotal produk (tanpa ongkir)
            $totalAmount = $subtotal;

            // Penentuan Status Pembayaran otomatis oleh aplikasi berdasarkan jumlah_pembayaran
            if ($partialAmount <= 0) {
                $paymentStatus = 'Belum dibayar';
                $partialAmount = 0;
            } elseif ($partialAmount >= $totalAmount) {
                $paymentStatus = 'Lunas';
                $partialAmount = $totalAmount;
            } else {
                $paymentStatus = 'Pembayaran Partial';
            }

            $code = 'IMP' . date('ymd', strtotime($createdAt)) . strtoupper(bin2hex(random_bytes(2)));

            // Simpan Pesanan
            $qOrd = $pdo->prepare("INSERT INTO orders(order_code, customer_name, customer_phone, shipping_address, subtotal, shipping_cost, fulfillment_cost, ad_cost, total_amount, status, payment_status, partial_amount, created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $qOrd->execute([
                $code, 
                'Import Excel Customer', 
                null, 
                '-', 
                $subtotal, 
                $shippingCost, 
                $fulfillmentCost, 
                $adCost, 
                $totalAmount, 
                $orderStatus ?: 'Menunggu diproses', 
                $paymentStatus, 
                $partialAmount, 
                $createdAt
            ]);
            $orderId = (int)$pdo->lastInsertId();

            // Simpan Item Pesanan
            $qItem = $pdo->prepare("INSERT INTO order_items(order_id, product_id, product_name, cost_price, selling_price, quantity, line_total) VALUES(?,?,?,?,?,?,?)");
            $qItem->execute([
                $orderId, 
                $prod['id'], 
                $prod['name'], 
                $costPrice, 
                $sellingPrice, 
                $qty, 
                $subtotal
            ]);

            $successCount++;
        }

        fclose($handle);
        $pdo->commit();
        flash("Berhasil mengimpor $successCount pesanan. " . ($errorCount > 0 ? "$errorCount baris dilewati karena produk tidak terdaftar." : ""));
        redirect('orders.php');

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if (is_resource($handle)) fclose($handle);
        flash('Gagal memproses file impor: ' . $e->getMessage(), 'danger');
        redirect('import_po.php');
    }
}

$pageTitle = 'Impor Pesanan';
require 'includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
        <h1 class="page-heading h3 mb-1">Impor Pesanan dari Excel / CSV</h1>
        <div class="text-muted">Unggah file CSV dengan format tanggal <code>dd/mm/yy</code> (harga & status pembayaran dihitung otomatis oleh aplikasi).</div>
    </div>
    <div>
        <a href="import_po.php?template=csv" class="btn btn-outline-success">
            <i class="bi bi-file-earmark-arrow-down me-1"></i> Unduh Template CSV
        </a>
    </div>
</div>

<div class="card p-4 shadow-sm border-0" style="max-width: 600px;">
    <form method="post" enctype="multipart/form-data" class="vstack gap-3">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <div>
            <label class="form-label fw-semibold">Pilih File CSV (.csv)</label>
            <input type="file" name="import_file" accept=".csv" required class="form-control">
            <div class="form-text mt-2">
                Pastikan kolom Excel/CSV sesuai template: <code>tanggal</code> (dd/mm/yy), <code>nama_produk</code>, <code>jumlah_barang</code>, <code>jumlah_pembayaran</code>, <code>biaya_fo</code>, <code>biaya_iklan</code>, <code>status_pesanan</code>.
            </div>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-primary"><i class="bi bi-upload me-1"></i> Unggah & Proses Impor</button>
            <a href="orders.php" class="btn btn-light">Kembali</a>
        </div>
    </form>
</div>

<?php require 'includes/footer.php'; ?>
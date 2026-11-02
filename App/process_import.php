<?php
require 'config/database.php';
require 'includes/functions.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    flash('Akses ditolak.', 'danger');
    redirect('orders.php');
}

// Fungsi bantu untuk mengonversi berbagai format tanggal Excel/CSV ke standar MySQL (Y-m-d H:i:s)
function parseExcelDate($dateValue) {
    if (empty($dateValue)) {
        return date('Y-m-d H:i:s');
    }
    
    $dateValue = trim($dateValue);

    // Jika format angka serial dari Excel
    if (is_numeric($dateValue)) {
        $unixDate = ($dateValue - 25569) * 86400;
        return gmdate('Y-m-d H:i:s', $unixDate);
    }
    
    // Coba parsing format m/d/Y atau format umum lainnya menggunakan DateTime
    $formats = ['m/d/Y', 'd/m/Y', 'Y-m-d', 'm-d-Y', 'd-m-Y'];
    foreach ($formats as $fmt) {
        $d = DateTime::createFromFormat($fmt, $dateValue);
        if ($d && $d->format($fmt) === $dateValue) {
            return $d->format('Y-m-d H:i:s');
        }
    }
    
    // Fallback menggunakan strtotime
    $timestamp = strtotime($dateValue);
    if ($timestamp !== false) {
        return date('Y-m-d H:i:s', $timestamp);
    }
    
    return date('Y-m-d H:i:s');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        flash('Gagal mengunggah file CSV.', 'danger');
        redirect('import_po.php');
    }

    $fileTmpPath = $_FILES['csv_file']['tmp_name'];
    $handle = fopen($fileTmpPath, 'r');
    
    if ($handle === FALSE) {
        flash('Tidak dapat membuka file CSV.', 'danger');
        redirect('import_po.php');
    }

    // Lewati baris header
    fgetcsv($handle, 1000, ',');
    
    try {
        $pdo->beginTransaction();
        $rowNumber = 1;
        $importedCount = 0;

        while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
            $rowNumber++;
            
            // Urutan CSV: tanggal, nama_produk, harga, jumlah_barang, jumlah_total_harga, status_pembayaran, jumlah_pembayaran_partial, biaya_fo, biaya_iklan, status_pesanan
            $rawTanggal      = trim($data[0] ?? '');
            $tanggalOrder    = parseExcelDate($rawTanggal);
            
            $namaProduk      = trim($data[1] ?? '');
            $harga           = (float)($data[2] ?? 0);
            $jumlahBarang    = (int)($data[3] ?? 1);
            $totalHarga      = (float)($data[4] ?? 0);
            $statusBayar     = trim($data[5] ?? 'Belum dibayar');
            $partialAmount   = (float)($data[6] ?? 0);
            $biayaFo         = (float)($data[7] ?? 0);
            $biayaIklan      = (float)($data[8] ?? 0);
            $statusPesanan   = trim($data[9] ?? 'Menunggu diproses');

            if ($namaProduk === '') {
                continue; // Lewati baris kosong
            }

            // VALIDASI: Cek apakah nama produk terdaftar di database
            $stmtCheck = $pdo->prepare("SELECT id, name, cost_price, selling_price FROM products WHERE name = ? OR sku = ?");
            $stmtCheck->execute([$namaProduk, $namaProduk]);
            $product = $stmtCheck->fetch();

            if (!$product) {
                throw new RuntimeException("Gagal Import: Produk **'$namaProduk'** (pada baris ke-$rowNumber file CSV) tidak ditemukan di master data produk. Harap daftarkan produk tersebut terlebih dahulu.");
            }

            $code = 'IMP' . date('ymd') . strtoupper(bin2hex(random_bytes(3)));
            $customerName = 'Import Excel Customer';
            $shippingCost = 0;
            $subtotal = (float)$product['selling_price'] * $jumlahBarang;
            $totalAmount = $subtotal + $shippingCost;

            // Penyesuaian status pembayaran & partial
            $isPartial = (strcasecmp($statusBayar, 'Pembayaran Partial') === 0 || strcasecmp($statusBayar, 'Partial') === 0);
            if ($isPartial) {
                if ($partialAmount >= $totalAmount) {
                    $statusBayar = 'Lunas';
                    $partialAmount = $totalAmount;
                } elseif ($partialAmount <= 0) {
                    $statusBayar = 'Belum dibayar';
                    $partialAmount = 0;
                }
            } elseif (strcasecmp($statusBayar, 'Lunas') === 0) {
                $partialAmount = $totalAmount;
            } else {
                $statusBayar = 'Belum dibayar';
                $partialAmount = 0;
            }

            // Insert ke tabel orders dengan tanggal hasil parsing akurat
            $stmtOrder = $pdo->prepare("INSERT INTO orders (order_code, customer_name, shipping_address, subtotal, shipping_cost, fulfillment_cost, ad_cost, total_amount, status, payment_status, partial_amount, notes, created_at) VALUES (?, ?, 'Alamat Gudang', ?, 0, ?, ?, ?, ?, ?, ?, 'Import via CSV', ?)");
            $stmtOrder->execute([
                $code, 
                $customerName, 
                $subtotal, 
                $biayaFo, 
                $biayaIklan, 
                $totalAmount, 
                $statusPesanan ?: 'Menunggu diproses', 
                $statusBayar, 
                $partialAmount, 
                $tanggalOrder
            ]);
            $orderId = (int)$pdo->lastInsertId();

            // Insert ke tabel order_items
            $stmtItem = $pdo->prepare("INSERT INTO order_items (order_id, product_id, product_name, cost_price, selling_price, quantity, line_total) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmtItem->execute([
                $orderId, 
                $product['id'], 
                $product['name'], 
                $product['cost_price'], 
                $product['selling_price'], 
                $jumlahBarang, 
                $subtotal
            ]);

            $importedCount++;
        }

        fclose($handle);
        $pdo->commit();
        
        flash("Berhasil mengimpor $importedCount data pesanan.", 'success');
        redirect('orders.php');

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if (is_resource($handle)) fclose($handle);
        
        flash($e->getMessage(), 'danger');
        redirect('import_po.php');
    }
} else {
    redirect('import_po.php');
}
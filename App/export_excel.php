<?php
// Contoh cuplikan penanganan ekspor Excel (.xls / .csv) pada laporan
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename=laporan_pesanan_' . date('Y-m-d') . '.xls');
    
    // Ambil data pesanan dari database
    $orders = $pdo->query("SELECT * FROM orders ORDER BY created_at DESC")->fetchAll();
    
    echo "<table border='1'>";
    echo "<tr>
            <th>No. Pesanan</th>
            <th>Pelanggan</th>
            <th>Tanggal</th>
            <th>Subtotal Produk</th>
            <th>Ongkir</th>
            <th>Biaya FO</th>
            <th>Biaya Iklan</th>
            <th>Total Tagihan</th>
            <th>Sudah Dibayar</th>
            <th>Status Pembayaran</th>
            <th>Status Pesanan</th>
          </tr>";
          
    foreach ($orders as $o) {
        echo "<tr>
                <td>{$o['order_code']}</td>
                <td>{$o['customer_name']}</td>
                <td>{$o['created_at']}</td>
                <td>{$o['subtotal']}</td>
                <td>{$o['shipping_cost']}</td>
                <td>{$o['fulfillment_cost']}</td>
                <td>{$o['ad_cost']}</td>
                <td>{$o['total_amount']}</td>
                <td>{$o['partial_amount']}</td>
                <td>{$o['payment_status']}</td>
                <td>{$o['status']}</td>
              </tr>";
    }
    echo "</table>";
    exit;
}
?>
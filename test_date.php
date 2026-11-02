<?php
require 'config/database.php';
require 'includes/functions.php';

// Proteksi: Hanya admin
if (($_SESSION['role'] ?? '') !== 'admin') {
    flash('Akses ditolak.', 'danger');
    redirect('index.php');
}

// Proses ubah tanggal pesanan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $orderId = (int)($_POST['order_id'] ?? 0);
    $daysAgo = (int)($_POST['days_ago'] ?? 7);

    if ($orderId) {
        // Mundurkan tanggal created_at sesuai pilihan hari
        $stmt = $pdo->prepare("UPDATE orders SET created_at = NOW() - INTERVAL ? DAY WHERE id = ?");
        $stmt->execute([$daysAgo, $orderId]);
        
        flash("Tanggal pesanan berhasil dimundurkan $daysAgo hari yang lalu. Silakan cek halaman Pesanan atau Dashboard untuk melihat hasil validasi otomatisnya.");
        redirect('test_date.php');
    }
}

// Ambil daftar pesanan untuk diuji
$orders = $pdo->query("SELECT id, order_code, created_at, payment_status, status FROM orders ORDER BY created_at DESC LIMIT 20")->fetchAll();

$pageTitle = 'Testing Ubah Tanggal Pesanan';
require 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="page-heading h3 mb-1">Uji Coba Otomatis 7 Hari</h1>
        <div class="text-muted">Pilih pesanan untuk dimundurkan tanggalnya demi mengetes validasi otomatis Lunas & Selesai.</div>
    </div>
    <div>
        <a href="orders.php" class="btn btn-light">← Kembali ke Pesanan</a>
    </div>
</div>

<div class="card p-3 p-md-4 shadow-sm border-0">
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>No. Pesanan</th>
                    <th>Tanggal Saat Ini</th>
                    <th>Status Pembayaran</th>
                    <th>Status Pesanan</th>
                    <th>Aksi Pengujian</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$orders): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">Belum ada data pesanan.</td></tr>
                <?php endif; ?>
                <?php foreach ($orders as $o): ?>
                <tr>
                    <td class="fw-semibold"><?= e($o['order_code']) ?></td>
                    <td><?= e(date('d/m/y H:i', strtotime($o['created_at']))) ?></td>
                    <td><span class="badge text-bg-secondary"><?= e($o['payment_status']) ?></span></td>
                    <td><span class="badge text-bg-secondary"><?= e($o['status']) ?></span></td>
                    <td>
                        <form method="post" class="d-inline-flex gap-2 align-items-center">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                            <input type="hidden" name="days_ago" value="8"> <!-- Mundur 8 hari (melewati ambang 7 hari) -->
                            <button class="btn btn-sm btn-warning text-dark fw-semibold" onclick="return confirm('Mundurkan tanggal pesanan ini menjadi 8 hari lalu?')">
                                ⚡ Set -8 Hari (Tes 7 Hari)
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="form-text mt-3">
        * Catatan: Ketika tombol <strong>"Set -8 Hari"</strong> diklik, tanggal pesanan akan diubah menjadi 8 hari yang lalu. Berdasarkan kode skrip otomatis di file <code>orders.php</code>, saat halaman pesanan dimuat, sistem akan langsung mengeksekusi validasi dan mengubah status pembayaran menjadi <strong>Lunas</strong> dan status pesanan menjadi <strong>Selesai</strong> secara otomatis.
    </div>
</div>

<?php require 'includes/footer.php'; ?>
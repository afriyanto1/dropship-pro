<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$role = $_SESSION['role'] ?? 'staff';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Dashboard') ?> - DropshipPro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        /* Mengatur struktur halaman menjadi flex container vertikal */
        html, body {
            height: 100%;
            margin: 0;
        }
        body { 
            background-color: #f1f5f9; 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #334155;
            display: flex;
            flex-direction: column;
        }
        .main-content {
            flex: 1; /* Membuat konten utama mengisi ruang kosong agar footer terdorong ke bawah */
        }
        .navbar { 
            background: #0f172a !important;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
        }
        .stat-label { 
            font-size: 0.85rem; 
            color: #64748b; 
            font-weight: 600; 
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .stat-value { 
            font-size: 1.75rem; 
            font-weight: 700; 
            color: #0f172a;
        }
        .page-heading { 
            font-weight: 700; 
            color: #0f172a;
        }
        .table td, .table th {
            vertical-align: middle;
        }
        .table thead th {
            background-color: #f8fafc;
            color: #475569;
            font-weight: 600;
            border-bottom: 2px solid #e2e8f0;
            padding: 12px;
        }
        .table tbody td {
            padding: 12px;
            color: #334155;
        }
        .btn {
            border-radius: 8px;
            font-weight: 500;
            padding: 8px 16px;
        }
        .form-control, .form-select {
            border-radius: 8px;
            padding: 10px;
            border-color: #cbd5e1;
        }

        /* Penyesuaian khusus tampilan mobile/HP agar rapi tanpa mengubah tampilan laptop */
        @media (max-width: 991.98px) {
            .navbar-collapse {
                background-color: #0f172a;
                padding: 1rem;
                border-radius: 8px;
                margin-top: 10px;
                box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.3);
            }
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark">
  <div class="container">
    <a class="navbar-brand fw-bold fs-4" href="index.php"><i class="bi bi-box-seam text-info"></i> DropshipPro</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-3">
        <li class="nav-item"><a class="nav-link px-3 <?= ($pageTitle ?? '') === 'Dashboard' ? 'active fw-bold text-info' : '' ?>" href="index.php">Dashboard</a></li>
        <li class="nav-item"><a class="nav-link px-3 <?= ($pageTitle ?? '') === 'Produk' ? 'active fw-bold text-info' : '' ?>" href="products.php">Produk</a></li>
        
        <?php if ($role === 'admin'): ?>
          <li class="nav-item"><a class="nav-link px-3 <?= ($pageTitle ?? '') === 'Supplier' ? 'active fw-bold text-info' : '' ?>" href="suppliers.php">Supplier</a></li>
        <?php endif; ?>

        <li class="nav-item"><a class="nav-link px-3 <?= ($pageTitle ?? '') === 'Pesanan' ? 'active fw-bold text-info' : '' ?>" href="orders.php">Pesanan</a></li>
        <li class="nav-item"><a class="nav-link px-3 <?= ($pageTitle ?? '') === 'Laporan' ? 'active fw-bold text-info' : '' ?>" href="reports.php">Laporan</a></li>
        <li class="nav-item"><a class="nav-link px-3 <?= ($pageTitle === 'Cek Resi / Tracking') ? 'active fw-bold text-info' : '' ?>" href="tracking.php">Tracking</a></li>

        <?php if ($role === 'admin'): ?>
          <li class="nav-item"><a class="nav-link px-3 <?= ($pageTitle ?? '') === 'Manajemen Pengguna' ? 'active fw-bold text-info' : '' ?>" href="users.php">Pengguna</a></li>
        <?php endif; ?>
      </ul>
      <div class="d-flex align-items-center gap-3 pt-2 pt-lg-0 border-top border-secondary border-opacity-25 border-top-lg-0">
        <?php if (isset($_SESSION['username'])): ?>
          <span class="navbar-text text-light small">
            Halo, <strong class="text-white"><?= e($_SESSION['username']) ?></strong> <span class="badge bg-secondary ms-1"><?= e($role) ?></span>
          </span>
          <a href="logout.php" class="btn btn-outline-danger btn-sm px-3" onclick="return confirm('Yakin ingin keluar?')">
            <i class="bi bi-box-arrow-right"></i> Logout
          </a>
        <?php else: ?>
          <a href="login.php" class="btn btn-primary btn-sm px-4">Login</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>

<!-- Pembungkus Konten Utama -->
<div class="container my-4 main-content">
<?php display_flash(); ?>
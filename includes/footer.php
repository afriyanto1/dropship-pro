</div> <!-- Close main-content container -->

<footer class="bg-white border-top py-4 mt-auto shadow-sm">
  <div class="container d-flex flex-wrap justify-content-between align-items-center text-muted small">
    <div>
      &copy; <?= date('Y') ?> <strong>DropshipPro</strong>. Hak Cipta Dilindungi.
    </div>
    <ul class="nav">
      <li class="nav-item"><a href="index.php" class="nav-link px-2 text-muted">Dashboard</a></li>
      <li class="nav-item"><a href="products.php" class="nav-link px-2 text-muted">Produk</a></li>
      <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
        <li class="nav-item"><a href="suppliers.php" class="nav-link px-2 text-muted">Supplier</a></li>
      <?php endif; ?>
      <li class="nav-item"><a href="orders.php" class="nav-link px-2 text-muted">Pesanan</a></li>
      <li class="nav-item"><a href="reports.php" class="nav-link px-2 text-muted">Laporan</a></li>
      <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
        <li class="nav-item"><a href="users.php" class="nav-link px-2 text-muted">Pengguna</a></li>
      <?php endif; ?>
      <li class="nav-item"><a href="tracking.php" class="nav-link px-2 text-muted">Tracking</a></li>
      <?php if (isset($_SESSION['username'])): ?>
        <li class="nav-item"><a href="logout.php" class="nav-link px-2 text-danger fw-semibold" onclick="return confirm('Yakin ingin keluar?')">Logout</a></li>
      <?php endif; ?>
    </ul>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
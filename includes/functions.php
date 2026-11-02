<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Fungsi untuk escaping HTML agar aman dari XSS
function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// Format angka ke mata uang Rupiah
function rupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

// Fungsi redirect halaman
function redirect($url) {
    header("Location: $url");
    exit;
}

// Fungsi untuk menyimpan pesan flash (notifikasi)
function flash($message, $type = 'success') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}

// Fungsi untuk menampilkan pesan flash di header
function display_flash() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'success';
        // Mapping tipe alert ke Bootstrap
        $alertClass = ($type === 'danger') ? 'danger' : (($type === 'warning') ? 'warning' : 'success');
        
        echo '<div class="alert alert-' . $alertClass . ' alert-dismissible fade show" role="alert">
                ' . e($message) . '
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>';
        
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
    }
}

// Generate CSRF Token
function csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Verifikasi CSRF Token
function verify_csrf() {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        die('CSRF token validation failed.');
    }
}
?>
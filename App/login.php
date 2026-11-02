<?php
session_start();
require_once 'config/database.php';

$error = '';
if (isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = md5($_POST['password'] ?? '');

    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND password = ?");
        $stmt->execute([$username, $password]);
        $user = $stmt->fetch();

        if ($user) {
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            header("Location: index.php");
            exit;
        } else {
            $error = "Username atau Password salah!";
        }
    } catch (PDOException $e) {
        $error = "Terjadi kesalahan pada database: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - DropshipPro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { 
            /* Background foto gudang/logistik dengan efek gelap di atasnya */
            background: linear-gradient(rgba(15, 23, 42, 0.75), rgba(15, 23, 42, 0.85)), 
                        url('https://images.unsplash.com/photo-1586528116311-ad8dd3c8310d?auto=format&fit=crop&q=80&w=1920') no-repeat center center fixed;
            background-size: cover;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex; 
            justify-content: center; 
            align-items: center; 
            height: 100vh; 
            margin: 0; 
        }
        .login-card { 
            background: rgba(255, 255, 255, 0.92); 
            backdrop-filter: blur(10px);
            padding: 35px; 
            border-radius: 16px; 
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3), 0 10px 10px -5px rgba(0, 0, 0, 0.04); 
            width: 100%;
            max-width: 400px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .login-card h2 { 
            text-align: center; 
            margin-bottom: 25px; 
            color: #1e293b; 
            font-size: 1.75rem; 
            font-weight: 700; 
        }
        .form-control {
            padding: 12px;
            border-radius: 8px;
        }
        .btn-login { 
            padding: 12px;
            border-radius: 8px;
            font-weight: 600;
            background: #2563eb;
            border: none;
            transition: background 0.2s;
        }
        .btn-login:hover { 
            background: #1d4ed8; 
        }
    </style>
</head>
<body>
    <div class="login-card">
        <h2><i class="bi bi-box-seam text-primary"></i> DropshipPro</h2>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2 text-center" style="font-size: 13px; border-radius: 8px;"><?= $error ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="mb-3">
                <label class="form-label text-secondary fw-semibold" style="font-size: 0.9rem;">Username</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-person text-muted"></i></span>
                    <input type="text" class="form-control border-start-0" name="username" placeholder="Masukkan username" required>
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label text-secondary fw-semibold" style="font-size: 0.9rem;">Password</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-lock text-muted"></i></span>
                    <input type="password" class="form-control border-start-0" name="password" placeholder="Masukkan password" required>
                </div>
            </div>
            <button type="submit" name="login" class="btn btn-primary btn-login w-100 text-white shadow-sm">Masuk ke Sistem</button>
        </form>
    </div>
</body>
</html>
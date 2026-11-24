<?php
session_start();
require_once '../config/database.php';

if (empty($_SESSION['reset_email'])) {
    header("Location: lupasandi.php");
    exit;
}

$reset_email = $_SESSION['reset_email'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['new_password'] ?? '';

    // Validasi kriteria kata sandi
    $has_min_length  = strlen($new_password) >= 8;
    $has_upper_lower = preg_match('/[A-Z]/', $new_password) && preg_match('/[a-z]/', $new_password);
    $has_num_symbol  = preg_match('/[0-9]/', $new_password) && preg_match('/[^A-Za-z0-9]/', $new_password);

    if (!$has_min_length || !$has_upper_lower || !$has_num_symbol) {
        $error = 'Kata sandi belum memenuhi semua kriteria keamanan.';
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        try {
            if (isset($pdo)) {
                $stmt = $pdo->prepare("UPDATE users SET password_hash = :password_hash WHERE email = :email");
                $stmt->execute([
                    ':password_hash' => $hashed_password,
                    ':email'         => $reset_email
                ]);
                unset($_SESSION['reset_email']);
                $success = 'Kata sandi berhasil diperbarui. Silakan masuk menggunakan kata sandi baru Anda.';
            } elseif (isset($conn)) {
                $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
                $stmt->bind_param("ss", $hashed_password, $reset_email);
                $stmt->execute();
                $stmt->close();
                unset($_SESSION['reset_email']);
                $success = 'Kata sandi berhasil diperbarui. Silakan masuk menggunakan kata sandi baru Anda.';
            } else {
                $error = 'Koneksi database tidak ditemukan.';
            }
        } catch (Throwable $e) {
            $error = 'Terjadi kesalahan saat memperbarui kata sandi: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Buat Kata Sandi Baru - CareerMap</title>
  <link rel="stylesheet" href="../assets/css/resetsandi.css">
</head>
<body>

  <header class="top-navbar">
    <a href="../index.php" class="brand-logo">
      <img src="../assets/images/uajy.jpeg" alt="Logo CareerMap">
      <span>CareerMap</span>
    </a>
  </header>

  <main class="auth-wrapper">
    <p class="auth-eyebrow">PEMULIHAN AKUN</p>

    <div class="auth-card">
      <!-- Ikon Gembok -->
      <div class="icon-badge">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
          <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
        </svg>
      </div>

      <h1 class="auth-title">Buat Kata Sandi Baru</h1>
      <p class="auth-subtitle">
        Kata sandi Anda harus berbeda dari kata sandi sebelumnya dan memenuhi kriteria keamanan
      </p>

      <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
      <?php endif; ?>

      <form action="" method="POST" class="auth-form">
        <div class="form-group">
          <label for="new_password" class="form-label">Kata Sandi Baru</label>
          <input
            type="password"
            id="new_password"
            name="new_password"
            class="form-control"
            placeholder="Masukkan Kata Sandi Baru"
            required
          >
        </div>

        <ul class="password-rules">
          <li>Minimal 8 Karakter</li>
          <li>Mengandung huruf besar dan kecil</li>
          <li>Mengandung angka dan simbol</li>
        </ul>

        <button type="submit" class="btn-primary">Simpan Kata Sandi</button>
      </form>

      <div class="back-link-wrapper">
        <a href="login.php" class="back-link">Kembali ke halaman Masuk</a>
      </div>
    </div>
  </main>

</body>
</html>
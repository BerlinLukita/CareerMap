<?php
session_start();
require_once '../config/database.php';

$error = '';
$email_input = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email_input = trim($_POST['email'] ?? '');

    if (empty($email_input)) {
        $error = 'Email tidak ditemukan';
    } else {
        try {
            $found = false;

            if (isset($pdo)) {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
                $stmt->execute([':email' => $email_input]);
                $found = (bool) $stmt->fetch(PDO::FETCH_ASSOC);
            } elseif (isset($conn)) {
                $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
                $stmt->bind_param("s", $email_input);
                $stmt->execute();
                $result = $stmt->get_result();
                $found = ($result && $result->num_rows > 0);
                $stmt->close();
            }

            if ($found) {
                $_SESSION['reset_email'] = $email_input;
                header("Location: resetsandi.php");
                exit;
            } else {
                $error = 'Email tidak ditemukan';
            }
        } catch (Exception $e) {
            $error = 'Terjadi kesalahan saat memeriksa email.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reset Kata Sandi - CareerMap</title>
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
      <!-- Ikon Amplop -->
      <div class="icon-badge">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="2" y="4" width="20" height="16" rx="2"></rect>
          <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path>
        </svg>
      </div>

      <h1 class="auth-title">Reset Kata Sandi</h1>
      <p class="auth-subtitle">
        Masukkan email akun Anda untuk mengkonfirmasi reset kata sandi
      </p>

      <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form action="" method="POST" class="auth-form">
        <div class="form-group">
          <label for="email" class="form-label">Email Terdaftar</label>
          <input
            type="email"
            id="email"
            name="email"
            class="form-control"
            placeholder="nama@email.com"
            value="<?= htmlspecialchars($email_input) ?>"
            required
          >
        </div>

        <button type="submit" class="btn-primary">Reset Kata Sandi</button>
      </form>

      <div class="back-link-wrapper">
        <a href="login.php" class="back-link">Kembali ke halaman Masuk</a>
      </div>
    </div>
  </main>

</body>
</html>
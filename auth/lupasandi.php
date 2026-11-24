
<?php
session_start();

require_once "../config/database.php";

$error = "";
$email_input = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email_input = trim($_POST["email"] ?? "");

    if ($email_input === "") {

        $error = "Email wajib diisi.";

    } elseif (!filter_var($email_input, FILTER_VALIDATE_EMAIL)) {

        $error = "Format email tidak valid.";

    } else {

        try {

            // Cek email pada tabel users
            $stmt = $pdo->prepare(
                "SELECT id FROM users WHERE email = :email LIMIT 1"
            );

            $stmt->execute([
                ":email" => $email_input
            ]);

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {

                // Buat session baru
                session_regenerate_id(true);

                // Simpan identitas akun untuk proses reset
                $_SESSION["reset_user_id"] = $user["id"];

                // Batas waktu sesi reset: 10 menit
                $_SESSION["reset_expires"] = time() + 600;

                // Token CSRF
                $_SESSION["reset_csrf"] = bin2hex(random_bytes(32));

                header("Location: resetsandi.php");
                exit;

            } else {

                $error = "Email tidak ditemukan.";

            }

        } catch (PDOException $e) {

            error_log("Reset password error: " . $e->getMessage());

            $error = "Terjadi kesalahan saat memeriksa email.";

        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Lupa Kata Sandi - CareerMap</title>

    <link rel="stylesheet" href="../assets/css/resetsandi.css">

</head>

<body>

    <!-- NAVBAR -->

    <header class="top-navbar">

        <a href="../index.php" class="brand-logo">

            <img
                src="../assets/images/uajy.jpeg"
                alt="Logo UAJY"
            >

            <span>CareerMap</span>

        </a>

    </header>


    <!-- KONTEN -->

    <main class="auth-wrapper">

        <p class="auth-eyebrow">PEMULIHAN AKUN</p>

        <div class="auth-card">

            <!-- IKON AMPLOP -->

            <div class="icon-badge">

                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <rect x="2" y="4" width="20" height="16" rx="2"></rect>
                    <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path>
                </svg>

            </div>


            <h1 class="auth-title">Lupa Kata Sandi?</h1>

            <p class="auth-subtitle">
                Masukkan email akun CareerMap Anda untuk melanjutkan
                proses penggantian kata sandi.
            </p>


            <!-- PESAN ERROR -->

            <?php if (!empty($error)): ?>

                <div class="alert alert-error" role="alert">

                    <?php
                    echo htmlspecialchars(
                        $error,
                        ENT_QUOTES,
                        "UTF-8"
                    );
                    ?>

                </div>

            <?php endif; ?>


            <!-- FORM EMAIL -->

            <form method="POST" action="" class="auth-form">

                <div class="form-group">

                    <label for="email" class="form-label">
                        Email Terdaftar
                    </label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        class="form-control"
                        placeholder="nama@email.com"
                        value="<?php
                            echo htmlspecialchars(
                                $email_input,
                                ENT_QUOTES,
                                "UTF-8"
                            );
                        ?>"
                        autocomplete="email"
                        required
                    >

                </div>


                <button type="submit" class="btn-primary">

                    Lanjutkan

                </button>

            </form>


            <!-- KEMBALI LOGIN -->

            <div class="back-link-wrapper">

                <a href="login.php" class="back-link">
                    ← Kembali ke halaman Masuk
                </a>

            </div>

        </div>

    </main>

</body>

</html>

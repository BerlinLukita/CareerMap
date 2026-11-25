
<?php
session_start();

require_once "../config/database.php";

$error = "";
$success = false;

// =====================================
// CEK SESSION RESET
// =====================================

if (
    empty($_SESSION["reset_user_id"]) ||
    empty($_SESSION["reset_expires"]) ||
    empty($_SESSION["reset_csrf"])
) {

    header("Location: lupasandi.php");
    exit;

}

// =====================================
// CEK MASA BERLAKU SESSION
// =====================================

if (time() > $_SESSION["reset_expires"]) {

    unset(
        $_SESSION["reset_user_id"],
        $_SESSION["reset_expires"],
        $_SESSION["reset_csrf"]
    );

    header("Location: lupasandi.php");
    exit;

}


// =====================================
// PROSES PASSWORD BARU
// =====================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $new_password = $_POST["new_password"] ?? "";

    $csrf_token = $_POST["csrf_token"] ?? "";

    // =================================
    // VALIDASI TOKEN
    // =================================

    if (
        !hash_equals(
            $_SESSION["reset_csrf"],
            $csrf_token
        )
    ) {

        $error = "Permintaan tidak valid. Silakan ulangi proses.";

    } else {

        // =================================
        // VALIDASI PASSWORD
        // =================================

        $has_min_length = strlen($new_password) >= 8;

        $has_upper_lower =
            preg_match("/[A-Z]/", $new_password) &&
            preg_match("/[a-z]/", $new_password);

        $has_num_symbol =
            preg_match("/[0-9]/", $new_password) &&
            preg_match("/[^A-Za-z0-9]/", $new_password);

        if (
            !$has_min_length ||
            !$has_upper_lower ||
            !$has_num_symbol
        ) {

            $error = "Kata sandi belum memenuhi semua kriteria keamanan.";

        } else {

            try {

                // =================================
                // HASH PASSWORD
                // =================================

                $hashed_password = password_hash(
                    $new_password,
                    PASSWORD_DEFAULT
                );

                // =================================
                // UPDATE DATABASE
                // =================================

                $stmt = $pdo->prepare(
                    "UPDATE users
                     SET password_hash = :password_hash,
                         must_change_password = 0
                     WHERE id = :user_id"
                );

                $stmt->execute([
                    ":password_hash" => $hashed_password,
                    ":user_id" => $_SESSION["reset_user_id"]
                ]);

                if ($stmt->rowCount() > 0) {

                    // Hapus session reset
                    unset(
                        $_SESSION["reset_user_id"],
                        $_SESSION["reset_expires"],
                        $_SESSION["reset_csrf"]
                    );

                    $success = true;

                } else {

                    $error = "Password tidak berhasil diperbarui. Silakan coba lagi.";

                }

            } catch (PDOException $e) {

                error_log("Update password error: " . $e->getMessage());

                $error = "Terjadi kesalahan saat memperbarui kata sandi.";

            }

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

    <!-- NAVBAR -->

    <header class="top-navbar">

        <a href="../index.php" class="brand-logo">

            <img
                src="../assets/images/logo-uajy.jpg"
                alt="Logo UAJY"
            >

            <span>CareerMap</span>

        </a>

    </header>


    <!-- KONTEN -->

    <main class="auth-wrapper">

        <p class="auth-eyebrow">PEMULIHAN AKUN</p>

        <div class="auth-card">

            <?php if ($success): ?>

                <!-- BERHASIL RESET -->

                <div class="icon-badge success-icon">

                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path d="M20 6 9 17l-5-5"></path>
                    </svg>

                </div>

                <h1 class="auth-title">
                    Kata Sandi Berhasil Diubah!
                </h1>

                <p class="auth-subtitle">
                    Kata sandi akun CareerMap Anda sudah diperbarui.
                    Silakan masuk menggunakan kata sandi baru.
                </p>

                <a href="login.php" class="btn-primary login-link">

                    Kembali ke Login

                </a>

            <?php else: ?>

                <!-- IKON GEMBOK -->

                <div class="icon-badge">

                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <rect x="3" y="11" width="18" height="11" rx="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>

                </div>


                <h1 class="auth-title">
                    Buat Kata Sandi Baru
                </h1>

                <p class="auth-subtitle">
                    Buat kata sandi baru untuk akun CareerMap Anda.
                    Pastikan memenuhi seluruh kriteria keamanan.
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


                <!-- FORM PASSWORD -->

                <form method="POST" action="" class="auth-form">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?php
                            echo htmlspecialchars(
                                $_SESSION["reset_csrf"],
                                ENT_QUOTES,
                                "UTF-8"
                            );
                        ?>"
                    >

                    <div class="form-group">

                        <label for="new_password" class="form-label">
                            Kata Sandi Baru
                        </label>

                        <input
                            type="password"
                            id="new_password"
                            name="new_password"
                            class="form-control"
                            placeholder="Masukkan kata sandi baru"
                            autocomplete="new-password"
                            minlength="8"
                            required
                        >

                    </div>


                    <!-- KRITERIA PASSWORD -->

                    <ul class="password-rules">

                        <li>Minimal 8 karakter</li>

                        <li>Mengandung huruf besar dan kecil</li>

                        <li>Mengandung angka dan simbol</li>

                    </ul>


                    <button type="submit" class="btn-primary">

                        Simpan Kata Sandi

                    </button>

                </form>


                <div class="back-link-wrapper">

                    <a href="login.php" class="back-link">
                        Kembali ke halaman Masuk
                    </a>

                </div>

            <?php endif; ?>

        </div>

    </main>

</body>

</html>

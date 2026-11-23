<?php
session_start();

require_once "../config/database.php";

$message = "";
$errors = [];
$identity = "";

// =====================================
// PROSES LOGIN
// =====================================

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $identity = trim($_POST["identity"] ?? "");
    $password = $_POST["password"] ?? "";

    // VALIDASI EMAIL / USERNAME
    if ($identity === "") {
        $errors["identity"] = "Email wajib diisi.";
    }

    // VALIDASI PASSWORD
    if ($password === "") {
        $errors["password"] = "Kata sandi wajib diisi.";
    }

    // =================================
    // CEK DATABASE
    // =================================

    if (empty($errors)) {

        $sql = "SELECT * FROM users
                WHERE email = ? OR username = ?
                LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$identity, $identity]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // =================================
        // VERIFIKASI EMAIL DAN PASSWORD
        // =================================

        if (
            !$user ||
            !password_verify($password, $user["password_hash"])
        ) {

            $errors["identity"] =
                "Email atau kata sandi salah. Periksa kembali email Anda.";

            $errors["password"] =
                "Kata sandi tidak sesuai. Silakan coba lagi.";

        } else {

            // =================================
            // CEK STATUS AKUN
            // =================================

            if ($user["status"] !== "active") {

                $message = "Akun kamu belum aktif atau dinonaktifkan!";

            } else {

                // =================================
                // BUAT SESSION BARU
                // =================================

                session_regenerate_id(true);

                $_SESSION["user_id"] = $user["id"];
                $_SESSION["name"] = $user["name"];
                $_SESSION["role"] = $user["role"];

                // =================================
                // WAJIB GANTI PASSWORD
                // =================================

                if (!empty($user["must_change_password"])) {

                    header("Location: change_password.php");
                    exit;

                }

                // =================================
                // REDIRECT BERDASARKAN ROLE
                // =================================

                if ($user["role"] === "student") {

                    header("Location: ../mahasiswa/dashboard.php");
                    exit;

                } elseif ($user["role"] === "company") {

                    header("Location: ../perusahaan/dashboard.php");
                    exit;

                } elseif ($user["role"] === "admin") {

                    header("Location: ../admin/dashboard.php");
                    exit;

                } else {

                    // ROLE TIDAK VALID
                    $_SESSION = [];
                    session_destroy();

                    die("Role akun tidak valid.");

                }
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

    <title>Login - CareerMap</title>

    <!-- CSS LOGIN -->
    <link rel="stylesheet" href="../assets/css/login.css">

    <!-- GOOGLE FONT -->
    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@300;400;500;600;700&display=swap"
        rel="stylesheet"
    >

</head>

<body>

    <div class="login-page">

        <!-- =================================
             PANEL KIRI
        ================================== -->

        <div class="login-left">

            <!-- LOGO UAJY DAN CAREERMAP -->

            <div class="brand">

                <img
                    src="../assets/images/logo-uajy.png"
                    alt="Logo Universitas Atma Jaya Yogyakarta"
                    class="brand-logo"
                >

                <span>CareerMap</span>

            </div>


            <!-- SLOGAN CAREERMAP -->

            <div class="left-content">

                <h1>
                    Langkah kecil hari ini,<br>
                    <span>karier besar esok hari.</span>
                </h1>

                <p>
                    Temukan kesempatan yang sesuai dengan potensimu.
                    Terhubung dengan dunia profesional bersama UAJY.
                </p>

            </div>

        </div>


        <!-- =================================
             PANEL KANAN
        ================================== -->

        <div class="login-right">

            <div class="login-card">

                <!-- HEADER LOGIN -->

                <div class="login-header">

                    <h2>Selamat datang kembali!</h2>

                    <p>
                        Masuk untuk melanjutkan perjalanan kariermu.
                    </p>

                </div>


                <!-- PESAN STATUS AKUN -->

                <?php if (!empty($message)): ?>

                    <div class="message" role="alert">

                        <?php
                        echo htmlspecialchars(
                            $message,
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>

                    </div>

                <?php endif; ?>


                <!-- =================================
                     FORM LOGIN
                ================================== -->

                <form method="POST" action="" novalidate>

                    <!-- INPUT EMAIL -->

                    <div class="form-group">

                        <label for="identity">Email</label>

                        <input
                            type="text"
                            id="identity"
                            name="identity"
                            placeholder="mahasiswa@uajy.com"

                            value="<?php
                                echo htmlspecialchars(
                                    $identity,
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                            ?>"

                            autocomplete="username"

                            class="<?php
                                echo isset($errors["identity"])
                                    ? "is-invalid"
                                    : "";
                            ?>"

                            aria-invalid="<?php
                                echo isset($errors["identity"])
                                    ? "true"
                                    : "false";
                            ?>"

                            required
                        >

                        <!-- ERROR EMAIL -->

                        <?php if (isset($errors["identity"])): ?>

                            <div class="field-error" role="alert">

                                <span class="error-icon">!</span>

                                <span>
                                    <?php
                                    echo htmlspecialchars(
                                        $errors["identity"],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                    ?>
                                </span>

                            </div>

                        <?php endif; ?>

                    </div>


                    <!-- INPUT PASSWORD -->

                    <div class="form-group">

                        <label for="password">Kata Sandi</label>

                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="••••••••••"
                            autocomplete="current-password"

                            class="<?php
                                echo isset($errors["password"])
                                    ? "is-invalid"
                                    : "";
                            ?>"

                            aria-invalid="<?php
                                echo isset($errors["password"])
                                    ? "true"
                                    : "false";
                            ?>"

                            required
                        >

                        <!-- ERROR PASSWORD -->

                        <?php if (isset($errors["password"])): ?>

                            <div class="field-error" role="alert">

                                <span class="error-icon">!</span>

                                <span>
                                    <?php
                                    echo htmlspecialchars(
                                        $errors["password"],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                    ?>
                                </span>

                            </div>

                        <?php endif; ?>

                    </div>


                    <!-- LUPA PASSWORD -->

                    <div class="forgot-password">

                        <a href="forgot_password.php">
                            Lupa kata sandi?
                        </a>

                    </div>


                    <!-- TOMBOL MASUK -->

                    <button type="submit" class="login-button">

                        Masuk

                    </button>

                </form>


                <!-- DAFTAR AKUN -->

                <p class="register-text">

                    Belum punya akun?

                    <a href="register.php">
                        Daftar sekarang
                    </a>

                </p>

            </div>

        </div>

    </div>

</body>

</html>

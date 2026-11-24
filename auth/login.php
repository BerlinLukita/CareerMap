<?php
session_start();

require_once "../config/database.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $identity = trim($_POST["identity"]);
    $password = $_POST["password"];

    if (empty($identity) || empty($password)) {

        $message = "Email/username dan password wajib diisi!";

    } else {

        $sql = "SELECT * FROM users
                WHERE email = ? OR username = ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$identity, $identity]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user["password_hash"])) {

            if ($user["status"] != "active") {

                $message = "Akun kamu belum aktif atau dinonaktifkan!";

            } else {

                session_regenerate_id(true);

                $_SESSION["user_id"] = $user["id"];
                $_SESSION["name"] = $user["name"];
                $_SESSION["role"] = $user["role"];

                if ($user["must_change_password"]) {
                    header("Location: change_password.php");
                    exit;
                }

                if ($user["role"] == "student") {
                    header("Location: ../mahasiswa/dashboard.php");

                } elseif ($user["role"] == "company") {
                    header("Location: ../perusahaan/dashboard.php");

                } elseif ($user["role"] == "admin") {
                    header("Location: ../admin/dashboard.php");

                } else {
                    session_destroy();
                    die("Role akun tidak valid.");
                }

                exit;
            }

        } else {

            $message = "Email/username atau password salah!";

        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Login CareerMap</title>
</head>
<body>

    <h2>Login CareerMap</h2>

    <p><?php echo htmlspecialchars($message); ?></p>

    <form method="POST">

        <label>Email atau Username</label><br>
        <input type="text" name="identity" required>
        <br><br>

        <label>Password</label><br>
        <input type="password" name="password" required>
        <br><br>

        <button type="submit">Masuk</button>

    </form>

    <p>Belum punya akun?
        <a href="register.php">Daftar sekarang</a>
    </p>

</body>
</html>

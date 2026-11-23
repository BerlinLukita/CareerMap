<?php
require_once "../config/database.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $name = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $username = trim($_POST["username"]);
    $password = $_POST["password"];
    $confirm_password = $_POST["confirm_password"];
    $role = $_POST["role"];

    if (
        empty($name) ||
        empty($email) ||
        empty($username) ||
        empty($password) ||
        empty($confirm_password)
    ) {
        $message = "Semua kolom wajib diisi!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Format email tidak valid!";
    } elseif ($password !== $confirm_password) {
        $message = "Konfirmasi password tidak cocok!";
    } elseif (strlen($password) < 8) {
        $message = "Password minimal 8 karakter!";
    } elseif (!in_array($role, ["student", "company"])) {
        $message = "Jenis akun tidak valid!";
    } else {

        $check = $pdo->prepare(
            "SELECT id FROM users WHERE email = ? OR username = ?"
        );
        $check->execute([$email, $username]);

        if ($check->rowCount() > 0) {
            $message = "Email atau username sudah digunakan!";
        } else {

            $password_hash = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            try {
                $pdo->beginTransaction();

                $sql = "INSERT INTO users
                        (name, email, username, password_hash, role)
                        VALUES (?, ?, ?, ?, ?)";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $name,
                    $email,
                    $username,
                    $password_hash,
                    $role
                ]);

                $user_id = $pdo->lastInsertId();

                if ($role == "student") {
                    $profile = $pdo->prepare(
                        "INSERT INTO student_profiles (user_id)
                         VALUES (?)"
                    );
                    $profile->execute([$user_id]);
                } else {
                    $profile = $pdo->prepare(
                        "INSERT INTO company_profiles
                         (user_id, company_name)
                         VALUES (?, ?)"
                    );
                    $profile->execute([$user_id, $name]);
                }

                $pdo->commit();

                $message = "Registrasi berhasil! Silakan login.";

            } catch (PDOException $e) {
                $pdo->rollBack();
                $message = "Registrasi gagal. Silakan coba lagi.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Registrasi CareerMap</title>
</head>
<body>

    <h2>Registrasi CareerMap</h2>

    <p><?php echo htmlspecialchars($message); ?></p>

    <form method="POST">

        <label>Nama Lengkap</label><br>
        <input type="text" name="name" required><br><br>

        <label>Email</label><br>
        <input type="email" name="email" required><br><br>

        <label>Username</label><br>
        <input type="text" name="username" required><br><br>

        <label>Password</label><br>
        <input type="password" name="password" required><br><br>

        <label>Konfirmasi Password</label><br>
        <input type="password" name="confirm_password" required><br><br>

        <label>Jenis Akun</label><br>
        <select name="role" required>
            <option value="">Pilih Jenis Akun</option>
            <option value="student">Mahasiswa</option>
            <option value="company">Perusahaan</option>
        </select><br><br>

        <button type="submit">Daftar</button>

    </form>

    <p>Sudah punya akun? <a href="login.php">Masuk</a></p>

</body>
</html>

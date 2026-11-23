<?php
session_start();

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] != "student") {
    header("Location: ../auth/login.php");
    exit;
}
?>

<h1>Dashboard Mahasiswa</h1>
<p>Selamat datang, <?php echo htmlspecialchars($_SESSION["name"]); ?>!</p>
<a href="profile.php">Profil Saya</a>
<a href="../auth/logout.php">Logout</a>
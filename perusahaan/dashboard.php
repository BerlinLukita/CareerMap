<?php
session_start();

if (
    !isset($_SESSION["user_id"]) ||
    $_SESSION["role"] != "company"
) {
    header("Location: ../auth/login.php");
    exit;
}

require_once "../config/database.php";

$user_id = $_SESSION["user_id"];

// Mengambil informasi perusahaan
$sql = "SELECT company_name, verification_status
        FROM company_profiles
        WHERE user_id = ?";

$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$company = $stmt->fetch(PDO::FETCH_ASSOC);

// Menghitung jumlah lowongan perusahaan
$sqlJobs = "SELECT COUNT(*)
            FROM job_vacancies
            WHERE company_id = ?";

$stmtJobs = $pdo->prepare($sqlJobs);
$stmtJobs->execute([$user_id]);
$total_jobs = $stmtJobs->fetchColumn();

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Dashboard Perusahaan CareerMap</title>
</head>

<body>

    <h1>Dashboard Perusahaan</h1>

    <h2>
        Halo, <?php echo htmlspecialchars($company["company_name"]); ?>!
    </h2>

    <p>Selamat datang di CareerMap UAJY.</p>

    <hr>

    <h3>Ringkasan Aktivitas</h3>

    <p>
        Total Lowongan:
        <?php echo $total_jobs; ?>
    </p>

    <p>
        Status Verifikasi:
        <?php echo htmlspecialchars($company["verification_status"]); ?>
    </p>

    <hr>

    <h3>Menu Perusahaan</h3>

    <ul>
        <li>
            <a href="profile.php">Profil Perusahaan</a>
        </li>

        <li>
            <a href="jobs.php">Kelola Lowongan</a>
        </li>

        <li>
            <a href="#">Daftar Pelamar</a>
        </li>
    </ul>

    <br>

    <a href="../auth/logout.php">Logout</a>

</body>
</html>

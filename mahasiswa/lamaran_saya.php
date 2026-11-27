<?php
session_start();

if (!isset($_SESSION["user_id"]) || ($_SESSION["role"] ?? "") !== "student") {
    header("Location: ../auth/login.php");
    exit;
}

require_once "../config/database.php";

function e($value) {
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

$studentId = (int) $_SESSION["user_id"];
$userName = $_SESSION["name"] ?? "Mahasiswa";

$sql = "
    SELECT
        a.id AS application_id,
        a.applied_at,
        a.status,
        j.title,
        cp.company_name
    FROM applications a
    INNER JOIN job_vacancies j ON a.job_id = j.id
    INNER JOIN company_profiles cp ON j.company_id = cp.user_id
    WHERE a.student_id = :student_id
    ORDER BY a.applied_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute(["student_id" => $studentId]);
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalApplications = count($applications);

function statusLabel($status) {
    $labels = [
        "submitted" => "Terkirim",
        "under_review" => "Dalam Review",
        "interview" => "Interview",
        "accepted" => "Diterima",
        "rejected" => "Ditolak"
    ];

    return $labels[$status] ?? "Tidak diketahui";
}

function formatDate($date) {
    if (!$date) return "-";

    $months = [
        1 => "Jan", "Feb", "Mar", "Apr", "Mei", "Jun",
        "Jul", "Agu", "Sep", "Okt", "Nov", "Des"
    ];

    $timestamp = strtotime($date);

    return date("d", $timestamp) . " " .
        $months[(int)date("n", $timestamp)] . " " .
        date("Y", $timestamp);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Lamaran Saya - CareerMap</title>

    <link rel="stylesheet" href="../assets/css/lamaran.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>

<body>

<div class="dashboard-layout">

    <!-- SIDEBAR -->
    <aside class="sidebar">

        <div class="sidebar-logo">
            <img src="../assets/images/logo-uajy.jpg" alt="CareerMap">
            <span>CareerMap</span>
        </div>

        <div class="sidebar-label">MAHASISWA</div>

        <nav class="sidebar-menu">

            <a href="dashboard.php" class="menu-item">Dashboard</a>

            <a href="cari_lowongan.php" class="menu-item">Cari Lowongan</a>

            <a href="lamaran_saya.php" class="menu-item active">Lamaran Saya</a>

            <a href="lowongan_disimpan.php" class="menu-item">Lowongan Disimpan</a>

            <a href="profile.php" class="menu-item">Profil Saya</a>

        </nav>

        <div class="sidebar-bottom">
            <a href="index.php" class="menu-item">
    Bantuan & Pusat Dukungan
</a>

            <a href="../auth/logout.php" class="menu-item logout">
                Keluar
            </a>
        </div>

    </aside>

    <!-- KONTEN -->
    <main class="main-content">

        <header class="page-header">
            <div>
                <h1>Lamaran Saya</h1>
                <p>Pantau perkembangan proses rekrutmenmu.</p>
            </div>
        </header>

        <section class="application-container">

            <div class="application-heading">
                <h2>Daftar Lamaran</h2>

                <span class="total-badge">
                    <?php echo $totalApplications; ?> Lamaran
                </span>
            </div>

            <?php if ($totalApplications > 0): ?>

                <div class="table-wrapper">

                    <table class="application-table">

                        <thead>
                            <tr>
                                <th>Posisi</th>
                                <th>Perusahaan</th>
                                <th>Tanggal</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($applications as $application): ?>

                                <tr>

                                    <td class="position-cell">
                                        <?php echo e($application["title"]); ?>
                                    </td>

                                    <td>
                                        <?php echo e($application["company_name"]); ?>
                                    </td>

                                    <td>
                                        <?php echo e(formatDate($application["applied_at"])); ?>
                                    </td>

                                    <td>
                                        <span class="status-badge status-<?php echo e($application["status"]); ?>">
                                            <?php echo e(statusLabel($application["status"])); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <a
                                            href="detail_lamaran.php?id=<?php echo (int)$application["application_id"]; ?>"
                                            class="detail-button"
                                        >
                                            Detail →
                                        </a>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="empty-state">
                    <h3>Belum Ada Lamaran</h3>
                    <p>
                        Kamu belum mengirim lamaran pekerjaan.
                        Temukan lowongan yang sesuai dan mulai melamar.
                    </p>

                    <a href="cari_lowongan.php" class="detail-button">
                        Cari Lowongan →
                    </a>
                </div>

            <?php endif; ?>

        </section>

    </main>

</div>

</body>
</html>
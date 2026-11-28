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

$userId = (int) $_SESSION["user_id"];

$userStmt = $pdo->prepare("
    SELECT u.name, u.email, sp.nim, sp.study_program, sp.cv_path, sp.portfolio_url
    FROM users u
    LEFT JOIN student_profiles sp ON sp.user_id = u.id
    WHERE u.id = :id AND u.role = 'student'
    LIMIT 1
");
$userStmt->execute(["id" => $userId]);
$student = $userStmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    session_destroy();
    header("Location: ../auth/login.php");
    exit;
}

$_SESSION["name"] = $student["name"];
$name = e($student["name"]);
$initial = strtoupper(mb_substr($student["name"], 0, 1));

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE student_id = ?");
$countStmt->execute([$userId]);
$totalApplications = (int) $countStmt->fetchColumn();

$processStmt = $pdo->prepare("
    SELECT COUNT(*) FROM applications
    WHERE student_id = ? AND status IN ('submitted','under_review')
");
$processStmt->execute([$userId]);
$inProcessCount = (int) $processStmt->fetchColumn();

$interviewStmt = $pdo->prepare("
    SELECT COUNT(*) FROM applications
    WHERE student_id = ? AND status = 'interview'
");
$interviewStmt->execute([$userId]);
$interviewCount = (int) $interviewStmt->fetchColumn();

$viewsCount = 0;

$jobsStmt = $pdo->prepare("
    SELECT j.id, j.title, j.job_type, j.location, cp.company_name
    FROM job_vacancies j
    INNER JOIN company_profiles cp ON cp.user_id = j.company_id
    WHERE j.status = 'published'
    ORDER BY j.published_at DESC, j.created_at DESC
    LIMIT 3
");
$jobsStmt->execute();
$recommendedJobs = $jobsStmt->fetchAll(PDO::FETCH_ASSOC);

$profileFields = [
    !empty($student["name"]),
    !empty($student["email"]),
    !empty($student["nim"]),
    !empty($student["study_program"]),
    !empty($student["cv_path"]),
    !empty($student["portfolio_url"])
];

$profileCompletion = (int) round(
    count(array_filter($profileFields)) / count($profileFields) * 100
);

$cvFileName = !empty($student["cv_path"])
    ? basename($student["cv_path"])
    : "";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Mahasiswa | CareerMap</title>
    <link rel="stylesheet" href="../assets/css/dashboard-mahasiswa.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>

<div class="layout">

    <aside class="sidebar">
        <div class="sidebar-logo">
            <span class="brand-logo-circle">
                <img src="../assets/images/logo-uajy.jpg" alt="Logo UAJY">
            </span>
            <span class="brand-name">CareerMap</span>
        </div>

        <div class="sidebar-label">MAHASISWA</div>

        <nav class="sidebar-menu">
            <a href="dashboard.php" class="menu-item active" aria-current="page">Dashboard</a>
            <a href="cari_lowongan.php" class="menu-item">Cari Lowongan</a>
            <a href="lamaran_saya.php" class="menu-item">Lamaran Saya</a>
            <a href="lowongan_disimpan.php" class="menu-item">Lowongan Disimpan</a>
            <a href="profile.php" class="menu-item">Profil Saya</a>
        </nav>

        <div class="sidebar-bottom">
            <a href="index.php" class="menu-item">Bantuan &amp; Pusat Dukungan</a>
            <a href="../auth/logout.php" class="menu-item logout">Keluar</a>
        </div>
    </aside>

    <main class="main-content">

        <header class="page-header">
            <div>
                <h1>Halo, <?php echo $name; ?>! 👋</h1>
                <p>
                    Temukan peluang karier yang sesuai dengan minat
                    dan kemampuanmu.
                </p>
            </div>

            <div class="user-avatar">
                <?php echo $initial; ?>
            </div>
        </header>

        <section class="search-box">
            <h3>CARI LOWONGAN</h3>

            <form action="cari_lowongan.php" method="GET" class="search-form">
                <input
                    type="text"
                    name="keyword"
                    placeholder="Cari posisi, keahlian, atau perusahaan..."
                >

                <select name="location">
                    <option value="">Semua Lokasi</option>
                    <option value="Yogyakarta">Yogyakarta</option>
                    <option value="Jakarta">Jakarta</option>
                    <option value="Remote">Remote</option>
                </select>

                <select name="type">
                    <option value="">Tipe Pekerjaan</option>
                    <option value="Magang">Magang</option>
                    <option value="Full-time">Full-time</option>
                    <option value="Part-time">Part-time</option>
                </select>

                <button type="submit">Cari Lowongan</button>
            </form>
        </section>

        <section class="statistics">
            <div class="stat-card">
                <h2><?php echo (int)$viewsCount; ?></h2>
                <p>Lowongan dilihat</p>
            </div>

            <div class="stat-card">
                <h2><?php echo (int)$totalApplications; ?></h2>
                <p>Lamaran terkirim</p>
            </div>

            <div class="stat-card">
                <h2><?php echo (int)$inProcessCount; ?></h2>
                <p>Dalam proses</p>
            </div>

            <div class="stat-card">
                <h2><?php echo (int)$interviewCount; ?></h2>
                <p>Interview</p>
            </div>
        </section>

        <section class="content-grid">

            <div class="recommendations">
                <div class="section-header">
                    <h2>Rekomendasi untukmu</h2>
                    <a href="cari_lowongan.php">Lihat semua →</a>
                </div>

                <?php if (!empty($recommendedJobs)): ?>
                    <?php foreach ($recommendedJobs as $job): ?>
                        <article class="job-card">
                            <div class="company-logo blue-logo">
                                <?php echo e(mb_substr($job["company_name"], 0, 1)); ?>
                            </div>

                            <div class="job-info">
                                <h3><?php echo e($job["title"]); ?></h3>
                                <p>
                                    <?php echo e($job["company_name"]); ?>
                                    •
                                    <?php echo e($job["location"]); ?>
                                </p>

                                <span class="badge <?php echo $job["job_type"] === "Full-time" ? "fulltime" : "internship"; ?>">
                                    <?php echo e($job["job_type"]); ?>
                                </span>
                            </div>

                            <a href="detail_lowongan.php?id=<?php echo (int)$job["id"]; ?>" class="detail-button">
                                Lihat Detail →
                            </a>
                        </article>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>Belum ada lowongan yang dipublikasikan.</p>
                <?php endif; ?>
            </div>

            <aside class="profile-panel">
                <div class="profile-card">
                    <h3>Lengkapi Profil</h3>

                    <p class="percentage">
                        Profilmu <?php echo (int)$profileCompletion; ?>% lengkap
                    </p>

                    <div class="progress">
                        <div
                            class="progress-value"
                            style="width: <?php echo (int)$profileCompletion; ?>%;"
                        ></div>
                    </div>

                    <h4>Tingkatkan peluang dilirik</h4>

                    <p class="profile-description">
                        Tambahkan portofolio dan CV
                    </p>

                    <h3 class="document-title">Dokumen Karier</h3>

                    <div class="document">
                        <?php echo $cvFileName ? e($cvFileName) : "CV belum diunggah"; ?>
                    </div>

                    <a href="profile.php" class="edit-button">
                        Edit Profil
                    </a>
                </div>
            </aside>

        </section>

    </main>
</div>

</body>
</html>

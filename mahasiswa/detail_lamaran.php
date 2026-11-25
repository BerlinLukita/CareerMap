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

$applicationId = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$applicationId) {
    header("Location: lamaran_saya.php");
    exit;
}

// Ambil data lamaran dan lowongan
$sql = "
    SELECT
        a.id AS application_id,
        a.job_id,
        a.student_id,
        a.cv_path,
        a.cover_letter,
        a.status,
        a.applied_at,
        a.updated_at,

        j.title,
        j.job_type,
        j.duration,
        j.location,

        cp.company_name,
        u.email AS company_email

    FROM applications a

    INNER JOIN job_vacancies j ON a.job_id = j.id
    INNER JOIN company_profiles cp ON j.company_id = cp.user_id
    INNER JOIN users u ON cp.user_id = u.id

    WHERE a.id = :application_id
      AND a.student_id = :student_id

    LIMIT 1
";

$stmt = $pdo->prepare($sql);

$stmt->execute([
    "application_id" => $applicationId,
    "student_id" => $studentId
]);

$application = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$application) {
    http_response_code(404);
    exit("Data lamaran tidak ditemukan.");
}

// Ambil riwayat status
$historySql = "
    SELECT
        h.status,
        h.note,
        h.changed_at
    FROM application_status_history h
    WHERE h.application_id = :application_id
    ORDER BY h.changed_at ASC, h.id ASC
";

$historyStmt = $pdo->prepare($historySql);

$historyStmt->execute([
    "application_id" => $applicationId
]);

$history = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

// Jika riwayat belum dibuat, tampilkan status lamaran saat ini
if (empty($history)) {
    $history[] = [
        "status" => $application["status"],
        "note" => "Status lamaran saat ini.",
        "changed_at" => $application["updated_at"] ?: $application["applied_at"]
    ];
}

$statusLabels = [
    "submitted" => "Lamaran terkirim",
    "under_review" => "Dalam review",
    "interview" => "Interview",
    "accepted" => "Diterima",
    "rejected" => "Ditolak"
];

function formatDateLong($date) {
    if (!$date) return "-";

    $months = [
        1 => "Januari", "Februari", "Maret", "April",
        "Mei", "Juni", "Juli", "Agustus",
        "September", "Oktober", "November", "Desember"
    ];

    $timestamp = strtotime($date);

    return date("d", $timestamp) . " " .
        $months[(int)date("n", $timestamp)] . " " .
        date("Y", $timestamp);
}

function formatDateShort($date) {
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

$status = $application["status"];
$cvPath = $application["cv_path"];
$cvUrl = "../" . ltrim($cvPath, "/\\");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Detail Lamaran - CareerMap</title>

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
            <img src="../assets/images/logo%20uajy.png" alt="CareerMap">
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
            <a href="../bantuan/index.php" class="menu-item">
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
            <h1>Detail Lamaran</h1>
            <p>Lihat informasi dan perkembangan proses rekrutmen.</p>
        </header>

        <section class="detail-container">

            <!-- DETAIL UTAMA -->
            <div class="detail-card">

                <h2>Detail Lamaran</h2>

                <div class="job-summary">
                    <h3><?php echo e($application["title"]); ?></h3>

                    <p>
                        <?php echo e($application["company_name"]); ?>
                        •
                        <?php echo e($application["location"]); ?>
                    </p>
                </div>

                <h3 class="section-title">Informasi Lamaran</h3>

                <div class="information-list">

                    <div class="information-row">
                        <span>Tanggal Melamar</span>
                        <strong>
                            <?php echo e(formatDateLong($application["applied_at"])); ?>
                        </strong>
                    </div>

                    <div class="information-row">
                        <span>Jenis Pekerjaan</span>
                        <strong>
                            <?php echo e($application["job_type"]); ?>
                        </strong>
                    </div>

                    <?php if (!empty($application["duration"])): ?>
                        <div class="information-row">
                            <span>Durasi</span>
                            <strong><?php echo e($application["duration"]); ?></strong>
                        </div>
                    <?php endif; ?>

                    <div class="information-row">
                        <span>Lokasi</span>
                        <strong><?php echo e($application["location"]); ?></strong>
                    </div>

                    <div class="information-row">
                        <span>Dokumen CV</span>
                        <strong>
                            <?php if (!empty($cvPath)): ?>
                                <a
                                    href="<?php echo e($cvUrl); ?>"
                                    target="_blank"
                                    class="document-link"
                                >
                                    <?php echo e(basename($cvPath)); ?>
                                </a>
                            <?php else: ?>
                                Belum tersedia
                            <?php endif; ?>
                        </strong>
                    </div>

                    <div class="information-row">
                        <span>Surat Pengantar</span>
                        <strong class="cover-letter-value">
                            <?php
                            echo !empty($application["cover_letter"])
                                ? nl2br(e($application["cover_letter"]))
                                : "Tidak dilampirkan";
                            ?>
                        </strong>
                    </div>

                </div>

                <!-- RIWAYAT STATUS -->
                <h3 class="section-title history-title">
                    Riwayat Status
                </h3>

                <div class="timeline">

                    <?php foreach ($history as $item): ?>

                        <div class="timeline-item">

                            <div class="timeline-dot"></div>

                            <div class="timeline-content">

                                <strong>
                                    <?php
                                    echo e(
                                        $statusLabels[$item["status"]]
                                        ?? $item["status"]
                                    );
                                    ?>
                                </strong>

                                <p>
                                    <?php echo e(formatDateShort($item["changed_at"])); ?>

                                    <?php if (!empty($item["note"])): ?>
                                        • <?php echo e($item["note"]); ?>
                                    <?php endif; ?>
                                </p>

                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

                <a href="lamaran_saya.php" class="back-button">
                    Kembali ke Lamaran
                </a>

            </div>

            <!-- RINGKASAN LOWONGAN -->
            <aside class="job-summary-card">

                <span class="status-badge status-<?php echo e($status); ?>">
                    <?php echo e($statusLabels[$status] ?? $status); ?>
                </span>

                <h3>Ringkasan Lowongan</h3>

                <p class="summary-title">
                    <?php echo e($application["title"]); ?>
                </p>

                <p class="summary-company">
                    <?php echo e($application["company_name"]); ?>
                </p>

                <h3 class="contact-title">Kontak perusahaan</h3>

                <a href="mailto:<?php echo e($application["company_email"]); ?>">
                    <?php echo e($application["company_email"]); ?>
                </a>

            </aside>

        </section>

    </main>

</div>

</body>
</html>

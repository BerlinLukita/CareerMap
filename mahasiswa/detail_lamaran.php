<?php
session_start();

if (!isset($_SESSION["user_id"]) || ($_SESSION["role"] ?? "") !== "student") {
    header("Location: ../auth/login.php");
    exit;
}

require_once "../config/database.php";

function e($value) {
    return htmlspecialchars((string) ($value ?? ""), ENT_QUOTES, "UTF-8");
}

$studentId = (int) $_SESSION["user_id"];
$applicationId = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$applicationId) {
    header("Location: lamaran_saya.php");
    exit;
}

$sql = "
    SELECT
        a.id AS application_id,
        a.job_id,
        a.student_id,
        a.cv_path,
        sp.cv_path AS profile_cv_path,
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
    LEFT JOIN student_profiles sp ON sp.user_id = a.student_id
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

$historySql = "
    SELECT h.status, h.note, h.changed_at
    FROM application_status_history h
    WHERE h.application_id = :application_id
    ORDER BY h.changed_at ASC, h.id ASC
";

$historyStmt = $pdo->prepare($historySql);
$historyStmt->execute(["application_id" => $applicationId]);
$history = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

$currentStatus = $application["status"] ?? "submitted";
$lastHistory = !empty($history) ? end($history) : null;

if (!$lastHistory || $lastHistory["status"] !== $currentStatus) {
    $history[] = [
        "status" => $currentStatus,
        "note" => "Status lamaran saat ini.",
        "changed_at" => $application["updated_at"] ?: $application["applied_at"]
    ];
}

$statusLabels = [
    "submitted" => "Lamaran terkirim",
    "verified" => "Lamaran diverifikasi",
    "under_review" => "Dalam review",
    "interview" => "Interview",
    "accepted" => "Diterima",
    "rejected" => "Ditolak"
];

function formatDateLong($date) {
    if (!$date) {
        return "-";
    }

    $timestamp = strtotime($date);

    if ($timestamp === false) {
        return "-";
    }

    $months = [
        1 => "Januari", "Februari", "Maret", "April",
        "Mei", "Juni", "Juli", "Agustus",
        "September", "Oktober", "November", "Desember"
    ];

    return date("d", $timestamp) . " " .
        $months[(int) date("n", $timestamp)] . " " .
        date("Y", $timestamp);
}

function formatDateShort($date) {
    if (!$date) {
        return "-";
    }

    $timestamp = strtotime($date);

    if ($timestamp === false) {
        return "-";
    }

    $months = [
        1 => "Jan", "Feb", "Mar", "Apr", "Mei", "Jun",
        "Jul", "Agu", "Sep", "Okt", "Nov", "Des"
    ];

    return date("d", $timestamp) . " " .
        $months[(int) date("n", $timestamp)] . " " .
        date("Y", $timestamp);
}

$status = $currentStatus;

$projectRoot = realpath(__DIR__ . "/..");
$cvPath = "";
$cvFileName = "";
$cvUrl = "";
$cvExists = false;

$possibleCvPaths = [
    $application["cv_path"] ?? "",
    $application["profile_cv_path"] ?? ""
];

if ($projectRoot !== false) {
    foreach ($possibleCvPaths as $storedPath) {
        $storedPath = trim((string) $storedPath);

        if ($storedPath === "") {
            continue;
        }

        $normalizedPath = str_replace("\\", "/", $storedPath);

        if ($cvPath === "") {
            $cvPath = $normalizedPath;
            $cvFileName = basename($normalizedPath);
        }

        if (
            str_starts_with($normalizedPath, "/") ||
            preg_match('/^[A-Za-z]:\//', $normalizedPath) ||
            in_array("..", explode("/", $normalizedPath), true)
        ) {
            continue;
        }

        $candidatePath = $projectRoot . DIRECTORY_SEPARATOR .
            str_replace("/", DIRECTORY_SEPARATOR, $normalizedPath);

        $realCvPath = realpath($candidatePath);
        $rootPrefix = rtrim($projectRoot, DIRECTORY_SEPARATOR) .
            DIRECTORY_SEPARATOR;

        if (
            $realCvPath !== false &&
            is_file($realCvPath) &&
            strpos($realCvPath, $rootPrefix) === 0
        ) {
            $cvPath = $normalizedPath;
            $cvFileName = basename($normalizedPath);

            $encodedParts = array_map(
                "rawurlencode",
                explode("/", $normalizedPath)
            );

            $cvUrl = "../" . implode("/", $encodedParts);
            $cvExists = true;
            break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Lamaran - CareerMap</title>
    <link rel="stylesheet" href="../assets/css/detail-lamaran.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>

<body>
<div class="dashboard-layout">
    <aside class="sidebar">
        <div class="sidebar-logo">
            <span class="brand-logo-circle">
                <img src="../assets/images/logo-uajy.jpg" alt="Logo UAJY">
            </span>
            <span class="brand-name">CareerMap</span>
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
            <a href="index.php" class="menu-item">Bantuan &amp; Pusat Dukungan</a>
            <a href="../auth/logout.php" class="menu-item logout">Keluar</a>
        </div>
    </aside>

    <main class="main-content">
        <header class="page-header">
            <h1>Detail Lamaran</h1>
            <p>Lihat informasi dan perkembangan proses rekrutmen.</p>
        </header>

        <section class="detail-container">
            <div class="detail-card">
                <h2>Detail Lamaran</h2>

                <div class="job-summary">
                    <h3><?php echo e($application["title"]); ?></h3>
                    <p>
                        <?php echo e($application["company_name"]); ?>
                        <span aria-hidden="true">•</span>
                        <?php echo e($application["location"]); ?>
                    </p>
                </div>

                <h3 class="section-title">Informasi Lamaran</h3>

                <div class="information-list">
                    <div class="information-row">
                        <span>Tanggal Melamar</span>
                        <strong><?php echo e(formatDateLong($application["applied_at"])); ?></strong>
                    </div>

                    <div class="information-row">
                        <span>Jenis Pekerjaan</span>
                        <strong><?php echo e($application["job_type"]); ?></strong>
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

                    <div class="information-row document-row">
                        <span>Dokumen CV</span>
                        <div class="document-actions">
                            <?php if ($cvExists): ?>
                                <div class="document-file">
                                    <span class="document-icon">PDF</span>
                                    <div class="document-meta">
                                        <strong><?php echo e($cvFileName); ?></strong>
                                        <small>CV yang dikirim saat melamar</small>
                                    </div>
                                </div>

                                <div class="document-buttons">
                                    <a
                                        href="<?php echo e($cvUrl); ?>"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="document-button preview-button"
                                    >
                                        Lihat CV
                                    </a>
                                    <a
                                        href="<?php echo e($cvUrl); ?>"
                                        download="<?php echo e($cvFileName); ?>"
                                        class="document-button download-button"
                                    >
                                        Unduh CV
                                    </a>
                                </div>
                            <?php elseif ($cvPath !== ""): ?>
                                <div class="document-unavailable">
                                    File CV belum ditemukan pada folder penyimpanan.
                                    <small>
                                        Sistem sudah memeriksa CV pada data lamaran dan profil mahasiswa.
                                        Pastikan file tersedia di folder uploads/cvs/ dan nilai cv_path
                                        pada database sesuai dengan nama file tersebut.
                                    </small>
                                </div>
                            <?php else: ?>
                                <span class="document-empty">Belum ada CV yang dilampirkan.</span>
                            <?php endif; ?>
                        </div>
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

                <h3 class="section-title history-title">Riwayat Status</h3>

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
                                        <span aria-hidden="true">•</span>
                                        <?php echo e($item["note"]); ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <a href="lamaran_saya.php" class="back-button">Kembali ke Lamaran</a>
            </div>

            <aside class="job-summary-card">
                <span class="status-badge status-<?php echo e($status); ?>">
                    <?php echo e($statusLabels[$status] ?? $status); ?>
                </span>

                <h3>Ringkasan Lowongan</h3>
                <p class="summary-title"><?php echo e($application["title"]); ?></p>
                <p class="summary-company"><?php echo e($application["company_name"]); ?></p>

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

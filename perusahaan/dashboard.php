
<?php
session_start();
require_once '../config/database.php';

// Pastikan pengguna sudah login
if (empty($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$error_db = '';

// Periksa role pengguna
try {
    $roleStmt = $pdo->prepare("
        SELECT role
        FROM users
        WHERE id = :user_id
        LIMIT 1
    ");

    $roleStmt->execute([
        ':user_id' => $user_id
    ]);

    $userRole = $roleStmt->fetchColumn();

    if ($userRole !== 'company') {
        header("Location: ../auth/login.php");
        exit;
    }
} catch (PDOException $e) {
    die("Gagal memeriksa akses pengguna.");
}


// Fungsi untuk menentukan URL logo perusahaan
function resolveCompanyLogoUrl(string $logoPath): string
{
    $clean = trim($logoPath);

    if ($clean === '') {
        return '';
    }

    if (
        str_starts_with($clean, 'http://') ||
        str_starts_with($clean, 'https://')
    ) {
        return $clean;
    }

    $normalized = ltrim(
        str_replace('\\', '/', $clean),
        '/'
    );

    if (str_starts_with($normalized, '../')) {
        $normalized = substr($normalized, 3);
    }

    if (!str_starts_with($normalized, 'uploads/')) {
        $normalized = 'uploads/perusahaan/logos/' . basename($normalized);
    }

    $fsPath = dirname(__DIR__) .
        DIRECTORY_SEPARATOR .
        str_replace('/', DIRECTORY_SEPARATOR, $normalized);

    $version = is_file($fsPath)
        ? '?v=' . filemtime($fsPath)
        : '';

    return '../' . $normalized . $version;
}


// Data awal perusahaan
$company = [
    'user_id'      => $user_id,
    'company_name' => '',
    'industry'     => '',
    'address'      => '',
    'logo_path'    => '',
    'description'  => '',
];


// Statistik dashboard
$stats = [
    'lowongan_aktif' => 0,
    'total_pelamar'  => 0,
    'pelamar_baru'   => 0,
    'perlu_ditinjau' => 0,
];

$recent_applicants = [];


// Ambil data perusahaan dan statistik
try {

    // Ambil profil perusahaan
    $stmt = $pdo->prepare("
        SELECT *
        FROM company_profiles
        WHERE user_id = :user_id
        LIMIT 1
    ");

    $stmt->execute([
        ':user_id' => $user_id
    ]);

    $rowCompany = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($rowCompany) {

        $company = array_merge($company, $rowCompany);

        $companyId = (int) $rowCompany['user_id'];


        // Total lowongan aktif
        $q1 = $pdo->prepare("
            SELECT COUNT(*)
            FROM job_vacancies
            WHERE company_id = :cid
            AND status = 'published'
        ");

        $q1->execute([
            ':cid' => $companyId
        ]);

        $stats['lowongan_aktif'] = (int) $q1->fetchColumn();


        // Total pelamar
        $q2 = $pdo->prepare("
            SELECT COUNT(*)
            FROM applications a
            JOIN job_vacancies j
                ON a.job_id = j.id
            WHERE j.company_id = :cid
        ");

        $q2->execute([
            ':cid' => $companyId
        ]);

        $stats['total_pelamar'] = (int) $q2->fetchColumn();


        // Pelamar baru
        $q3 = $pdo->prepare("
            SELECT COUNT(*)
            FROM applications a
            JOIN job_vacancies j
                ON a.job_id = j.id
            WHERE j.company_id = :cid
            AND a.status = 'submitted'
        ");

        $q3->execute([
            ':cid' => $companyId
        ]);

        $stats['pelamar_baru'] = (int) $q3->fetchColumn();


        // Pelamar yang perlu ditinjau
        $q4 = $pdo->prepare("
            SELECT COUNT(*)
            FROM applications a
            JOIN job_vacancies j
                ON a.job_id = j.id
            WHERE j.company_id = :cid
            AND a.status IN ('submitted', 'under_review')
        ");

        $q4->execute([
            ':cid' => $companyId
        ]);

        $stats['perlu_ditinjau'] = (int) $q4->fetchColumn();


        // Aktivitas pelamar terbaru
        $q5 = $pdo->prepare("
            SELECT
                a.id,
                a.status,
                u.name AS applicant_name,
                j.title AS job_title
            FROM applications a
            JOIN job_vacancies j
                ON a.job_id = j.id
            JOIN users u
                ON a.student_id = u.id
            WHERE j.company_id = :cid
            ORDER BY a.applied_at DESC
            LIMIT 5
        ");

        $q5->execute([
            ':cid' => $companyId
        ]);

        $recent_applicants = $q5->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (Throwable $e) {

    $error_db = 'Gagal memuat data dashboard: ' . $e->getMessage();

}


// Informasi perusahaan untuk ditampilkan
$companyName = !empty($company['company_name'])
    ? $company['company_name']
    : 'Perusahaan';

$companyIndustry = !empty($company['industry'])
    ? $company['industry']
    : '-';

$companyLocation = !empty($company['address'])
    ? $company['address']
    : '-';

$companyLogoUrl = resolveCompanyLogoUrl(
    (string) ($company['logo_path'] ?? '')
);


// Menghitung kelengkapan profil perusahaan
$filled = 0;

foreach (
    ['company_name', 'industry', 'address', 'description', 'logo_path']
    as $field
) {
    if (!empty($company[$field])) {
        $filled++;
    }
}

$completionPercent = (int) round(($filled / 5) * 100);


// Inisial perusahaan jika belum memiliki logo
$words = preg_split('/\s+/', trim($companyName));

$initials = strtoupper(
    substr($words[0] ?? 'P', 0, 1) .
    (isset($words[1]) ? substr($words[1], 0, 1) : '')
);

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Dashboard Perusahaan - CareerMap</title>



    <link rel="stylesheet" href="../assets/css/dashboard-perusahaan.css?v=1">
<link rel="stylesheet" href="../assets/css/company-sidebar.css?v=1">
</head>

<body>

    <!-- Sidebar Perusahaan -->
    <?php require_once '../includes/company-sidebar.php'; ?>


    <!-- Konten Utama -->
    <main class="main-content">

        <!-- Header Dashboard -->
        <div class="page-header">

            <h1 class="page-title">
                Dashboard Perusahaan
            </h1>

            <p class="page-subtitle">
                Selamat datang,
                <?= htmlspecialchars($companyName) ?>.
                Kelola lowongan dan profil perusahaanmu.
            </p>

        </div>


        <!-- Pesan Error -->
        <?php if (!empty($error_db)): ?>

            <div class="alert-error">
                <?= htmlspecialchars($error_db) ?>
            </div>

        <?php endif; ?>


        <!-- Statistik Dashboard -->
        <section class="stats-grid">

            <div class="stat-card">

                <div class="stat-number">
                    <?= $stats['lowongan_aktif'] ?>
                </div>

                <div class="stat-label">
                    Lowongan Aktif
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-number">
                    <?= $stats['total_pelamar'] ?>
                </div>

                <div class="stat-label">
                    Total Pelamar
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-number">
                    <?= $stats['pelamar_baru'] ?>
                </div>

                <div class="stat-label">
                    Pelamar Baru
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-number">
                    <?= $stats['perlu_ditinjau'] ?>
                </div>

                <div class="stat-label">
                    Perlu Ditinjau
                </div>

            </div>

        </section>


        <!-- Kartu Profil Perusahaan -->
        <section class="profile-card">

            <div class="profile-left">

                <div class="company-avatar">

                    <?php if ($companyLogoUrl !== ''): ?>

                        <img
                            src="<?= htmlspecialchars($companyLogoUrl) ?>"
                            alt="Logo Perusahaan"
                        >

                    <?php else: ?>

                        <?= htmlspecialchars($initials) ?>

                    <?php endif; ?>

                </div>


                <div>

                    <h2 class="company-name">
                        Profil Perusahaan
                    </h2>

                    <p class="company-meta">

                        <?= htmlspecialchars($companyName) ?>

                        &bull;

                        <?= htmlspecialchars($companyIndustry) ?>

                        &bull;

                        <?= htmlspecialchars($companyLocation) ?>

                    </p>


                    <span class="completion-badge">

                        Profil <?= $completionPercent ?>% Lengkap

                    </span>


                    <p class="profile-hint">

                        Lengkapi deskripsi dan dokumen perusahaan
                        agar profil lebih terpercaya.

                    </p>

                </div>

            </div>


            <a
                href="profile.php"
                class="btn-primary"
            >
                Lihat / Edit Profil
            </a>

        </section>


        <!-- Aktivitas Terbaru -->
        <section>

            <div class="activity-header">

                <h2 class="section-heading">
                    Aktivitas Terbaru
                </h2>


                <a
                    href="jobs.php?action=create"
                    class="btn-primary"
                >
                    + Buat Lowongan
                </a>

            </div>


            <div class="applicants-card">

                <h3 class="applicants-title">
                    Pelamar terbaru
                </h3>


                <ul class="applicant-list">

                    <?php if (empty($recent_applicants)): ?>

                        <li class="applicant-row">

                            <span
                                class="applicant-role"
                                style="grid-column: 1 / -1;"
                            >
                                Belum ada data pelamar terbaru.
                            </span>

                        </li>

                    <?php else: ?>

                        <?php foreach ($recent_applicants as $app): ?>

                            <?php

                            $isReview = (
                                $app['status'] === 'under_review' ||
                                $app['status'] === 'interview'
                            );

                            $badgeClass = $isReview
                                ? 'status-review'
                                : 'status-baru';

                            $badgeText = $app['status'] === 'under_review'
                                ? 'Dalam Review'
                                : (
                                    $app['status'] === 'interview'
                                    ? 'Wawancara'
                                    : 'Baru'
                                );

                            ?>


                            <li class="applicant-row">

                                <span class="applicant-name">

                                    <?= htmlspecialchars($app['applicant_name']) ?>

                                </span>


                                <span class="applicant-role">

                                    <?= htmlspecialchars($app['job_title']) ?>

                                </span>


                                <span class="status-badge <?= $badgeClass ?>">

                                    <?= htmlspecialchars($badgeText) ?>

                                </span>


                                <a
                                    href="applicants.php?process=<?= (int) $app['id'] ?>"
                                    class="detail-link"
                                >
                                    Lihat &rarr;
                                </a>

                            </li>


                        <?php endforeach; ?>

                    <?php endif; ?>

                </ul>

            </div>

        </section>

    </main>

</body>
</html>

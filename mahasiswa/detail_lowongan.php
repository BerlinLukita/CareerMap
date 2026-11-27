<?php
session_start();

if (!isset($_SESSION["user_id"]) || ($_SESSION["role"] ?? "") !== "student") {
    header("Location: ../auth/login.php");
    exit;
}

require_once "../config/database.php";

$name = $_SESSION["name"] ?? "Mahasiswa";
$initialUser = strtoupper(mb_substr($name, 0, 1));

function e($value) {
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

$jobId = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);
$job = null;
$savedNotice = "";

// Token keamanan untuk aksi simpan / batal simpan.
if (empty($_SESSION["csrf_saved_jobs"])) {
    $_SESSION["csrf_saved_jobs"] = bin2hex(random_bytes(32));
}

// Proses tombol simpan atau hapus simpanan.
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["saved_action"])) {
    $token = $_POST["csrf_token"] ?? "";
    $postedJobId = filter_input(INPUT_POST, "job_id", FILTER_VALIDATE_INT);
    $action = $_POST["saved_action"];

    if (!is_string($token) || !hash_equals($_SESSION["csrf_saved_jobs"], $token)) {
        $_SESSION["saved_job_notice"] = "Sesi tidak valid. Silakan coba kembali.";
    } elseif (!$postedJobId || $postedJobId <= 0) {
        $_SESSION["saved_job_notice"] = "Lowongan tidak valid.";
    } else {
        try {
            if ($action === "save") {
                $saveStmt = $pdo->prepare("
                    INSERT IGNORE INTO saved_jobs (student_id, job_id)
                    SELECT :student_id, j.id FROM job_vacancies j
                    WHERE j.id = :job_id
                ");
                $saveStmt->execute([
                    ":student_id" => (int)$_SESSION["user_id"],
                    ":job_id" => $postedJobId
                ]);
                $_SESSION["saved_job_notice"] = $saveStmt->rowCount() > 0
                    ? "Lowongan berhasil disimpan."
                    : "Lowongan sudah ada di daftar simpanan.";
            } elseif ($action === "remove") {
                $removeStmt = $pdo->prepare("
                    DELETE FROM saved_jobs WHERE student_id = :student_id AND job_id = :job_id
                ");
                $removeStmt->execute([
                    ":student_id" => (int)$_SESSION["user_id"],
                    ":job_id" => $postedJobId
                ]);
                $_SESSION["saved_job_notice"] = "Lowongan dihapus dari simpanan.";
            }
        } catch (PDOException $ex) {
            error_log("Saved jobs detail error: " . $ex->getMessage());
            $_SESSION["saved_job_notice"] = "Tidak dapat memperbarui simpanan. Pastikan profil mahasiswa tersedia.";
        }
    }

    header("Location: detail_lowongan.php?id=" . (int)$jobId);
    exit;
}

$savedNotice = $_SESSION["saved_job_notice"] ?? "";
unset($_SESSION["saved_job_notice"]);

if ($jobId) {
    $stmt = $pdo->prepare("
        SELECT
            j.id,
            j.title AS position,
            j.job_type AS type,
            j.duration,
            j.location,
            j.description,
            j.qualifications,
            j.status,
            cp.company_name AS company,
            cp.industry,
            cp.address AS company_address,
            cp.description AS company_description,
            cp.website,
            cp.logo_path,
            jc.category_name AS category
        FROM job_vacancies j
        INNER JOIN company_profiles cp ON cp.user_id = j.company_id
        INNER JOIN job_categories jc ON jc.id = j.category_id
        WHERE j.id = :id
        LIMIT 1
    ");
    $stmt->execute(["id" => $jobId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $row["initial"] = strtoupper(mb_substr($row["company"], 0, 1));
        $row["country"] = "Indonesia";
        $row["qualifications"] = preg_split(
            '/\\r\\n|\\r|\\n/',
            trim((string)$row["qualifications"]),
            -1,
            PREG_SPLIT_NO_EMPTY
        );
        $job = $row;
    }
}

if (!$job) {
    http_response_code(404);
}

$isJobSaved = false;
if ($job) {
    $checkSavedStmt = $pdo->prepare("SELECT id FROM saved_jobs WHERE student_id = :student_id AND job_id = :job_id LIMIT 1");
    $checkSavedStmt->execute([
        ":student_id" => (int)$_SESSION["user_id"],
        ":job_id" => (int)$job["id"]
    ]);
    $isJobSaved = (bool)$checkSavedStmt->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        <?php echo $job ? e($job["position"]) : "Lowongan Tidak Ditemukan"; ?>
        | CareerMap
    </title>

    <link rel="stylesheet" href="../assets/css/detail-lowongan.css">

</head>

<body>

<div class="layout">

    <!-- =================================
         SIDEBAR
    ================================== -->

    <aside class="sidebar">

        <div class="brand">

            <img
                src="../assets/images/logo%20uajy.png"
                alt="Logo Universitas Atma Jaya Yogyakarta"
            >

            <span>CareerMap</span>

        </div>

        <div class="role-label">
            MAHASISWA
        </div>

        <nav class="navigation">

            <a href="dashboard.php" class="nav-link">
                Dashboard
            </a>

            <a href="cari_lowongan.php" class="nav-link active">
                Cari Lowongan
            </a>

            <a href="lamaran_saya.php" class="nav-link">
                Lamaran Saya
            </a>

            <a href="lowongan_disimpan.php" class="nav-link">
                Lowongan Disimpan
            </a>

            <a href="profile.php" class="nav-link">
                Profil Saya
            </a>

        </nav>

        <div class="sidebar-footer">

            <a href="../bantuan/index.php">
                Bantuan & Pusat Dukungan
            </a>

            <a href="../auth/logout.php" class="logout">
                Keluar
            </a>

        </div>

    </aside>


    <!-- =================================
         MAIN CONTENT
    ================================== -->

    <main class="main-content">

        <?php if ($savedNotice !== ""): ?>
            <div class="saved-notice"><?php echo e($savedNotice); ?></div>
        <?php endif; ?>

        <?php if (!$job): ?>

            <section class="not-found">

                <h1>Lowongan Tidak Ditemukan</h1>

                <p>
                    Lowongan yang kamu cari tidak tersedia
                    atau sudah tidak dapat diakses.
                </p>

                <a href="cari_lowongan.php" class="primary-button">
                    Kembali ke Cari Lowongan
                </a>

            </section>

        <?php else: ?>

            <!-- PAGE HEADER -->

            <header class="page-header">

                <div>

                    <h1>Detail Lowongan</h1>

                    <p>
                        Informasi pekerjaan dan lokasi perusahaan.
                    </p>

                </div>

                <div class="user-avatar">
                    <?php echo e($initialUser); ?>
                </div>

            </header>


            <!-- =================================
                 DETAIL CONTENT GRID
            ================================== -->

            <div class="detail-layout">


                <!-- LEFT: JOB INFORMATION -->

                <section class="job-information-card">

                    <!-- JOB HEADER -->

                    <div class="job-header">

                        <div class="company-logo">
                            <?php echo e($job["initial"]); ?>
                        </div>

                        <div class="job-title">

                            <h2>
                                <?php echo e($job["position"]); ?>
                            </h2>

                            <p>
                                <?php echo e($job["company"]); ?>
                                •
                                <?php echo e($job["location"]); ?>
                            </p>

                        </div>

                        <div class="job-header-actions">
                            <form method="POST" action="detail_lowongan.php?id=<?php echo (int)$job["id"]; ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION["csrf_saved_jobs"]); ?>">
                                <input type="hidden" name="job_id" value="<?php echo (int)$job["id"]; ?>">
                                <button type="submit" name="saved_action" value="<?php echo $isJobSaved ? "remove" : "save"; ?>" class="detail-save-button <?php echo $isJobSaved ? "is-saved" : ""; ?>">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 4.75A2.75 2.75 0 0 1 8.75 2h6.5A2.75 2.75 0 0 1 18 4.75V22l-6-3.8L6 22V4.75Z"/></svg>
                                    <?php echo $isJobSaved ? "Tersimpan" : "Simpan Lowongan"; ?>
                                </button>
                            </form>
                            <a href="laporkan_lowongan.php?id=<?php echo (int)$job["id"]; ?>" class="report-button">
                                Laporkan Lowongan
                            </a>
                        </div>

                    </div>


                    <!-- DESCRIPTION -->

                    <section class="information-section">

                        <h3>Deskripsi Pekerjaan</h3>

                        <p class="description">
                            <?php echo e($job["description"]); ?>
                        </p>

                    </section>


                    <!-- QUALIFICATIONS -->

                    <section class="information-section">

                        <h3>Kualifikasi</h3>

                        <ul class="qualification-list">

                            <?php foreach ($job["qualifications"] as $qualification): ?>

                                <li>
                                    <?php echo e($qualification); ?>
                                </li>

                            <?php endforeach; ?>

                        </ul>

                    </section>


                    <!-- JOB INFORMATION -->

                    <section class="information-section job-meta-section">

                        <h3>Informasi Lowongan</h3>

                        <div class="job-meta-grid">

                            <p>
                                <span>Tipe:</span>
                                <?php echo e($job["type"]); ?>
                            </p>

                            <p>
                                <span>Durasi:</span>
                                <?php echo e($job["duration"]); ?>
                            </p>

                            <p>
                                <span>Lokasi:</span>
                                <?php echo e($job["location"]); ?>
                            </p>

                        </div>

                    </section>

                </section>


                <!-- RIGHT: COMPANY LOCATION -->

                <aside class="right-column">

                    <section class="company-location-card">

                        <h3>Lokasi Perusahaan</h3>


                        <!-- MAP ILLUSTRATION -->

                        <div class="mini-map">

                            <div class="mini-map-block block-a"></div>
                            <div class="mini-map-block block-b"></div>
                            <div class="mini-map-block block-c"></div>
                            <div class="mini-map-block block-d"></div>

                            <div class="mini-road road-a"></div>
                            <div class="mini-road road-b"></div>
                            <div class="mini-road road-c"></div>

                            <div class="mini-map-marker"></div>

                        </div>


                        <div class="company-location-info">

                            <h4>
                                <?php echo e($job["company"]); ?>
                            </h4>

                            <p>
                                <?php echo e($job["location"]); ?>,
                                <?php echo e($job["country"]); ?>
                            </p>

                            <a
                                href="https://www.google.com/maps/search/?api=1&query=<?php echo rawurlencode($job["company"] . ", " . $job["location"] . ", " . $job["country"]); ?>"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="direction-link"
                            >
                                Lihat petunjuk arah →
                            </a>

                        </div>

                    </section>


                    <!-- APPLY BUTTON -->

                    <a
                        href="form_lamaran.php?job_id=<?php echo (int)$job["id"]; ?>"
                        class="apply-button"
                    >
                        Lamar Sekarang
                    </a>

                </aside>

            </div>

        <?php endif; ?>

    </main>

</div>

</body>
</html>
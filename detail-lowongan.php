
<?php
require_once "config/database.php";

session_start();

// Mengambil ID lowongan dari URL
$job_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

$job = null;
$error_message = "";

// Validasi ID
if (!$job_id || $job_id <= 0) {
    $error_message = "ID lowongan tidak valid.";
} else {
    try {
        // Mengambil detail lowongan dari database
        $sql = "SELECT
                    jv.*,
                    cp.company_name,
                    cp.logo_path,
                    cp.industry,
                    cp.address AS company_address,
                    cp.phone AS company_phone,
                    cp.website,
                    jc.category_name,
                    u.email AS company_email
                FROM job_vacancies jv
                INNER JOIN company_profiles cp
                    ON jv.company_id = cp.user_id
                INNER JOIN users u
                    ON cp.user_id = u.id
                LEFT JOIN job_categories jc
                    ON jv.category_id = jc.id
                WHERE jv.id = :job_id
                  AND jv.status = 'published'
                  AND cp.verification_status = 'verified'
                LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute(['job_id' => $job_id]);

        $job = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$job) {
            $error_message = "Lowongan tidak ditemukan atau sudah tidak tersedia.";
        }

    } catch (PDOException $e) {
        error_log("Gagal mengambil detail lowongan: " . $e->getMessage());
        $error_message = "Terjadi kesalahan saat mengambil data lowongan.";
    }
}

// Format tanggal publikasi
$published_date = "";

if ($job) {
    $date = $job['published_at'] ?: $job['created_at'];
    $published_date = date('d F Y', strtotime($date));
}

// Format tanggal deadline
$deadline_date = "";

if ($job && !empty($job['deadline'])) {
    $deadline_date = date(
        'd F Y',
        strtotime($job['deadline'])
    );
}

// Menentukan tujuan tombol lamaran
$is_student = isset($_SESSION['user_id'])
    && isset($_SESSION['role'])
    && $_SESSION['role'] === 'student';

$apply_url = $is_student
    ? "auth/login.php"
    : "auth/login.php";
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        <?php
        echo $job
            ? htmlspecialchars($job['title']) . " - CareerMap"
            : "Detail Lowongan - CareerMap";
        ?>
    </title>

    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <!-- NAVBAR -->
    <header class="navbar">
        <div class="navbar-container">

            <a href="index.php" class="logo">
                <img src="assets/images/logo-uajy.jpg" alt="Logo UAJY">

                <span class="logo-text">
                    <strong>CareerMap</strong>
                    <small>UNIVERSITAS ATMA JAYA YOGYAKARTA</small>
                </span>
            </a>

            <nav class="nav-menu">
                <a href="index.php">Beranda</a>
                <a href="lowongan.php" class="active">Cari Lowongan</a>
                <a href="perusahaan.php">Perusahaan</a>
                <a href="tentang.php">Tentang</a>
            </nav>

            <div class="nav-auth">
                <a href="auth/login.php" class="btn-login">Masuk</a>
                <a href="auth/register.php" class="btn-register">Daftar</a>
            </div>

        </div>
    </header>


    <main class="job-detail-page">

        <?php if (!$job): ?>

            <!-- LOWONGAN TIDAK DITEMUKAN -->
            <section class="job-detail-error">

                <div class="job-error-icon">📋</div>

                <h1>Lowongan Tidak Ditemukan</h1>

                <p>
                    <?php echo htmlspecialchars($error_message); ?>
                </p>

                <a href="lowongan.php" class="job-back-button">
                    ← Kembali ke Daftar Lowongan
                </a>

            </section>

        <?php else: ?>

            <div class="job-detail-container">

                <!-- KEMBALI KE PENCARIAN -->
                <a href="lowongan.php" class="job-back-link">
                    ← Kembali ke hasil pencarian
                </a>


                <div class="job-detail-layout">

                    <!-- KONTEN UTAMA -->
                    <section class="job-detail-main">

                        <!-- HEADER LOWONGAN -->
                        <div class="job-detail-header">

                            <div class="job-detail-company-logo">

                                <?php if (!empty($job['logo_path'])): ?>

                                    <img
                                        src="uploads/perusahaan/logos/<?php
                                            echo htmlspecialchars($job['logo_path']);
                                        ?>"
                                        alt="Logo <?php echo htmlspecialchars($job['company_name']); ?>"
                                        onerror="this.style.display='none';"
                                    >

                                <?php else: ?>

                                    <span>
                                        <?php
                                        echo strtoupper(
                                            substr($job['company_name'], 0, 1)
                                        );
                                        ?>
                                    </span>

                                <?php endif; ?>

                            </div>


                            <div class="job-detail-title">

                                <h1>
                                    <?php echo htmlspecialchars($job['title']); ?>
                                </h1>

                                <p>
                                    <?php echo htmlspecialchars($job['company_name']); ?>
                                    <span>•</span>
                                    <?php echo htmlspecialchars($job['location']); ?>
                                </p>

                                <span class="job-detail-type">
                                    <?php echo htmlspecialchars($job['job_type']); ?>
                                </span>

                            </div>

                        </div>


                        <!-- DESKRIPSI -->
                        <div class="job-detail-section">

                            <h2>Deskripsi Pekerjaan</h2>

                            <div class="job-description-text">
                                <?php
                                echo nl2br(
                                    htmlspecialchars($job['description'])
                                );
                                ?>
                            </div>

                        </div>


                        <!-- KUALIFIKASI -->
                        <div class="job-detail-section">

                            <h2>Kualifikasi</h2>

                            <div class="job-description-text">
                                <?php
                                if (!empty($job['qualifications'])) {
                                    echo nl2br(
                                        htmlspecialchars($job['qualifications'])
                                    );
                                } else {
                                    echo "Informasi kualifikasi belum tersedia.";
                                }
                                ?>
                            </div>

                        </div>


                        <!-- INFORMASI LOWONGAN -->
                        <div class="job-detail-section">

                            <h2>Informasi Lowongan</h2>

                            <div class="job-information-grid">

                                <div class="job-information-item">
                                    <span>Tipe Pekerjaan</span>
                                    <strong>
                                        <?php echo htmlspecialchars($job['job_type']); ?>
                                    </strong>
                                </div>

                                <div class="job-information-item">
                                    <span>Lokasi</span>
                                    <strong>
                                        <?php echo htmlspecialchars($job['location']); ?>
                                    </strong>
                                </div>

                                <div class="job-information-item">
                                    <span>Durasi</span>
                                    <strong>
                                        <?php
                                        echo !empty($job['duration'])
                                            ? htmlspecialchars($job['duration'])
                                            : "Tidak disebutkan";
                                        ?>
                                    </strong>
                                </div>

                                <div class="job-information-item">
                                    <span>Kategori</span>
                                    <strong>
                                        <?php
                                        echo htmlspecialchars(
                                            $job['category_name'] ?? 'Umum'
                                        );
                                        ?>
                                    </strong>
                                </div>

                                <div class="job-information-item">
                                    <span>Dipublikasikan</span>
                                    <strong>
                                        <?php echo htmlspecialchars($published_date); ?>
                                    </strong>
                                </div>

                                <?php if ($deadline_date): ?>
                                    <div class="job-information-item">
                                        <span>Batas Akhir Lamaran</span>
                                        <strong>
                                            <?php echo htmlspecialchars($deadline_date); ?>
                                        </strong>
                                    </div>
                                <?php endif; ?>

                            </div>

                        </div>

                    </section>


                    <!-- SIDEBAR RINGKASAN -->
                    <aside class="job-detail-sidebar">

                        <div class="job-summary-card">

                            <h2>Ringkasan</h2>

                            <div class="job-summary-item">
                                <span>Perusahaan</span>
                                <strong>
                                    <?php echo htmlspecialchars($job['company_name']); ?>
                                </strong>
                            </div>

                            <div class="job-summary-item">
                                <span>Lokasi</span>
                                <strong>
                                    <?php echo htmlspecialchars($job['location']); ?>
                                </strong>
                            </div>

                            <div class="job-summary-item">
                                <span>Jenis Pekerjaan</span>
                                <strong>
                                    <?php echo htmlspecialchars($job['job_type']); ?>
                                </strong>
                            </div>

                            <div class="job-summary-item">
                                <span>Durasi</span>
                                <strong>
                                    <?php
                                    echo !empty($job['duration'])
                                        ? htmlspecialchars($job['duration'])
                                        : "Tidak disebutkan";
                                    ?>
                                </strong>
                            </div>

                            <div class="job-summary-divider"></div>

                            <a
                                href="<?php echo htmlspecialchars($apply_url); ?>"
                                class="job-apply-button"
                            >
                                Lamar Sekarang
                            </a>

                            <p class="job-apply-note">
                                Masuk ke akun mahasiswa untuk melanjutkan proses lamaran.
                            </p>

                        </div>


                        <!-- INFORMASI PERUSAHAAN -->
                        <div class="job-company-card">

                            <h3>Tentang Perusahaan</h3>

                            <strong>
                                <?php echo htmlspecialchars($job['company_name']); ?>
                            </strong>

                            <?php if (!empty($job['industry'])): ?>
                                <p>
                                    <?php echo htmlspecialchars($job['industry']); ?>
                                </p>
                            <?php endif; ?>

                            <?php if (!empty($job['company_address'])): ?>
                                <p>
                                    📍
                                    <?php echo htmlspecialchars($job['company_address']); ?>
                                </p>
                            <?php endif; ?>

                            <?php if (!empty($job['website'])): ?>
                                <a
                                    href="<?php echo htmlspecialchars($job['website']); ?>"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    Kunjungi Website →
                                </a>
                            <?php endif; ?>

                        </div>

                    </aside>

                </div>

            </div>

        <?php endif; ?>

    </main>


    <!-- FOOTER -->
    <footer class="site-footer">

        <div class="footer-container">

            <div class="footer-brand">

                <div class="footer-logo">
                    <img src="assets/images/logo-uajy.jpg" alt="Logo UAJY">

                    <div>
                        <h3>CareerMap</h3>
                        <span>UNIVERSITAS ATMA JAYA YOGYAKARTA</span>
                    </div>
                </div>

                <p>
                    Platform informasi karier yang membantu mahasiswa UAJY
                    menemukan peluang magang dan pekerjaan serta membangun
                    masa depan profesional.
                </p>

            </div>


            <div class="footer-links">
                <h4>Navigasi</h4>
                <a href="index.php">Beranda</a>
                <a href="lowongan.php">Cari Lowongan</a>
                <a href="perusahaan.php">Perusahaan</a>
                <a href="tentang.php">Tentang CareerMap</a>
            </div>


            <div class="footer-links">
                <h4>Akun</h4>
                <a href="auth/login.php">Masuk</a>
                <a href="auth/register.php">Daftar Akun</a>
            </div>

        </div>


        <div class="footer-bottom">
            <p>
                &copy; <?php echo date('Y'); ?> CareerMap UAJY.
                Seluruh hak cipta dilindungi.
            </p>

            <span>Dari kampus menuju kesempatan profesional.</span>
        </div>

    </footer>

</body>
</html>

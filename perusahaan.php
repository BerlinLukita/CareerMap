
<?php
require_once "config/database.php";

// Kata kunci pencarian
$keyword = trim($_GET['keyword'] ?? '');

// ID perusahaan untuk halaman detail
$company_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

$companies = [];
$company = null;
$error_message = "";

// Jika ID perusahaan tersedia, tampilkan detail perusahaan
if ($company_id && $company_id > 0) {

    try {
        $sql = "SELECT
                    cp.*,
                    u.email AS company_email,
                    (
                        SELECT COUNT(*)
                        FROM job_vacancies jv
                        WHERE jv.company_id = cp.user_id
                          AND jv.status = 'published'
                    ) AS total_jobs
                FROM company_profiles cp
                INNER JOIN users u
                    ON cp.user_id = u.id
                WHERE cp.user_id = :company_id
                  AND cp.verification_status = 'verified'
                LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute(['company_id' => $company_id]);

        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            $error_message = "Perusahaan tidak ditemukan atau belum terverifikasi.";
        }

    } catch (PDOException $e) {
        error_log("Gagal mengambil detail perusahaan: " . $e->getMessage());
        $error_message = "Terjadi kesalahan saat mengambil data perusahaan.";
    }

// Jika tidak ada ID, tampilkan direktori perusahaan
} else {

    try {
        $sql = "SELECT
                    cp.*,
                    (
                        SELECT COUNT(*)
                        FROM job_vacancies jv
                        WHERE jv.company_id = cp.user_id
                          AND jv.status = 'published'
                    ) AS total_jobs
                FROM company_profiles cp
                WHERE cp.verification_status = 'verified'";

        $params = [];

        // Filter pencarian
        if ($keyword !== '') {
            $sql .= " AND (
                        cp.company_name LIKE :keyword1
                        OR cp.industry LIKE :keyword2
                        OR cp.address LIKE :keyword3
                    )";

            $search = "%" . $keyword . "%";

            $params['keyword1'] = $search;
            $params['keyword2'] = $search;
            $params['keyword3'] = $search;
        }

        $sql .= " ORDER BY cp.company_name ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Gagal mengambil daftar perusahaan: " . $e->getMessage());
        $error_message = "Terjadi kesalahan saat mengambil daftar perusahaan.";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        <?php echo $company
            ? htmlspecialchars($company['company_name']) . " - CareerMap"
            : "Direktori Perusahaan - CareerMap";
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
                <a href="lowongan.php">Cari Lowongan</a>
                <a href="perusahaan.php" class="active">Perusahaan</a>
                <a href="tentang.php">Tentang</a>
            </nav>

            <div class="nav-auth">
                <a href="auth/login.php" class="btn-login">Masuk</a>
                <a href="auth/register.php" class="btn-register">Daftar</a>
            </div>

        </div>
    </header>


    <main class="company-page">

        <?php if ($company): ?>

            <!-- DETAIL PERUSAHAAN -->
            <section class="company-detail-container">

                <a href="perusahaan.php" class="company-back-link">
                    ← Kembali ke daftar perusahaan
                </a>

                <div class="company-detail-card">

                    <div class="company-detail-header">

                        <div class="company-detail-logo">

                            <?php if (!empty($company['logo_path'])): ?>

                                <img
                                    src="uploads/perusahaan/logos/<?php
                                        echo htmlspecialchars($company['logo_path']);
                                    ?>"
                                    alt="Logo <?php echo htmlspecialchars($company['company_name']); ?>"
                                    onerror="this.style.display='none';"
                                >

                            <?php else: ?>

                                <span>
                                    <?php
                                    echo strtoupper(
                                        substr($company['company_name'], 0, 1)
                                    );
                                    ?>
                                </span>

                            <?php endif; ?>

                        </div>

                        <div class="company-detail-heading">

                            <span class="company-verified-label">
                                ✓ Perusahaan Terverifikasi
                            </span>

                            <h1>
                                <?php echo htmlspecialchars($company['company_name']); ?>
                            </h1>

                            <p>
                                <?php
                                echo htmlspecialchars(
                                    $company['industry'] ?: 'Industri belum dicantumkan'
                                );
                                ?>
                            </p>

                        </div>

                    </div>


                    <div class="company-detail-content">

                        <h2>Tentang Perusahaan</h2>

                        <p>
                            <?php
                            echo !empty($company['description'])
                                ? nl2br(htmlspecialchars($company['description']))
                                : "Informasi mengenai perusahaan belum tersedia.";
                            ?>
                        </p>

                    </div>


                    <div class="company-detail-information">

                        <h2>Informasi Perusahaan</h2>

                        <div class="company-information-grid">

                            <div>
                                <span>Industri</span>
                                <strong>
                                    <?php
                                    echo htmlspecialchars(
                                        $company['industry'] ?: 'Belum dicantumkan'
                                    );
                                    ?>
                                </strong>
                            </div>

                            <div>
                                <span>Lokasi</span>
                                <strong>
                                    <?php
                                    echo htmlspecialchars(
                                        $company['address'] ?: 'Belum dicantumkan'
                                    );
                                    ?>
                                </strong>
                            </div>

                            <div>
                                <span>Email</span>
                                <strong>
                                    <?php echo htmlspecialchars($company['company_email']); ?>
                                </strong>
                            </div>

                            <div>
                                <span>Nomor Telepon</span>
                                <strong>
                                    <?php
                                    echo htmlspecialchars(
                                        $company['phone'] ?: 'Belum dicantumkan'
                                    );
                                    ?>
                                </strong>
                            </div>

                            <div>
                                <span>Tahun Berdiri</span>
                                <strong>
                                    <?php
                                    echo !empty($company['year_founded'])
                                        ? htmlspecialchars($company['year_founded'])
                                        : 'Belum dicantumkan';
                                    ?>
                                </strong>
                            </div>

                            <div>
                                <span>Total Lowongan Aktif</span>
                                <strong>
                                    <?php echo (int) $company['total_jobs']; ?> Lowongan
                                </strong>
                            </div>

                        </div>

                        <?php if (!empty($company['website'])): ?>
                            <a
                                href="<?php echo htmlspecialchars($company['website']); ?>"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="company-website-button"
                            >
                                Kunjungi Website Perusahaan →
                            </a>
                        <?php endif; ?>

                    </div>


                    <!-- LOWONGAN MILIK PERUSAHAAN -->
                    <?php
                    $jobStmt = $pdo->prepare(
                        "SELECT id, title, job_type, location, duration
                         FROM job_vacancies
                         WHERE company_id = ?
                           AND status = 'published'
                         ORDER BY created_at DESC"
                    );

                    $jobStmt->execute([$company['user_id']]);
                    $companyJobs = $jobStmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>

                    <div class="company-detail-jobs">

                        <h2>Lowongan dari Perusahaan Ini</h2>

                        <?php if (!empty($companyJobs)): ?>

                            <div class="company-detail-job-list">

                                <?php foreach ($companyJobs as $job): ?>

                                    <div class="company-detail-job-item">

                                        <div>
                                            <h3>
                                                <?php echo htmlspecialchars($job['title']); ?>
                                            </h3>

                                            <p>
                                                <?php echo htmlspecialchars($job['job_type']); ?>
                                                ·
                                                <?php echo htmlspecialchars($job['location']); ?>
                                            </p>
                                        </div>

                                        <a href="detail-lowongan.php?id=<?php
                                            echo (int) $job['id'];
                                        ?>">
                                            Lihat Detail →
                                        </a>

                                    </div>

                                <?php endforeach; ?>

                            </div>

                        <?php else: ?>

                            <p class="company-no-jobs">
                                Perusahaan ini belum memiliki lowongan aktif.
                            </p>

                        <?php endif; ?>

                    </div>

                </div>

            </section>


        <?php elseif ($company_id || $error_message !== ''): ?>

            <!-- PERUSAHAAN TIDAK DITEMUKAN -->
            <section class="company-not-found">

                <div class="company-empty-icon">🏢</div>

                <h1>Perusahaan Tidak Ditemukan</h1>

                <p>
                    <?php echo htmlspecialchars($error_message); ?>
                </p>

                <a href="perusahaan.php" class="company-search-button">
                    Kembali ke Direktori Perusahaan
                </a>

            </section>


        <?php else: ?>

            <!-- HERO DIREKTORI PERUSAHAAN -->
            <section class="company-hero">

                <div class="company-hero-container">

                    <span class="company-hero-label">
                        JELAJAHI PERUSAHAAN
                    </span>

                    <h1>
                        Kenali perusahaan,<br>
                        temukan tempat berkembang.
                    </h1>

                    <p>
                        Lihat profil perusahaan dan peluang karier
                        yang mereka tawarkan.
                    </p>

                    <form action="perusahaan.php" method="GET" class="company-search">

                        <input
                            type="text"
                            name="keyword"
                            value="<?php echo htmlspecialchars($keyword); ?>"
                            placeholder="Cari nama perusahaan atau bidang industri..."
                        >

                        <button type="submit">
                            Cari Perusahaan
                        </button>

                    </form>

                </div>

            </section>


            <!-- DAFTAR PERUSAHAAN -->
            <section class="company-directory-section">

                <div class="company-directory-container">

                    <div class="company-directory-heading">

                        <div>
                            <span class="section-label">
                                PARTNER CAREERMAP
                            </span>

                            <h2>Perusahaan Terdaftar</h2>

                            <p>
                                Temukan perusahaan yang telah bergabung
                                dengan CareerMap.
                            </p>
                        </div>

                        <span class="company-total">
                            <?php echo count($companies); ?> Perusahaan
                        </span>

                    </div>


                    <?php if (!empty($companies)): ?>

                        <div class="company-directory-grid">

                            <?php foreach ($companies as $item): ?>

                                <article class="company-directory-card">

                                    <div class="company-directory-logo">

                                        <?php if (!empty($item['logo_path'])): ?>

                                            <img
                                                src="uploads/perusahaan/logos/<?php
                                                    echo htmlspecialchars($item['logo_path']);
                                                ?>"
                                                alt="Logo <?php echo htmlspecialchars($item['company_name']); ?>"
                                                onerror="this.style.display='none';"
                                            >

                                        <?php else: ?>

                                            <span>
                                                <?php
                                                echo strtoupper(
                                                    substr($item['company_name'], 0, 1)
                                                );
                                                ?>
                                            </span>

                                        <?php endif; ?>

                                    </div>


                                    <h3>
                                        <?php echo htmlspecialchars($item['company_name']); ?>
                                    </h3>

                                    <p class="company-industry">
                                        <?php
                                        echo htmlspecialchars(
                                            $item['industry'] ?: 'Industri belum dicantumkan'
                                        );
                                        ?>
                                    </p>

                                    <p class="company-location">
                                        📍
                                        <?php
                                        echo htmlspecialchars(
                                            $item['address'] ?: 'Lokasi belum dicantumkan'
                                        );
                                        ?>
                                    </p>


                                    <div class="company-directory-footer">

                                        <span>
                                            <?php echo (int) $item['total_jobs']; ?>
                                            Lowongan
                                        </span>

                                        <a href="perusahaan.php?id=<?php
                                            echo (int) $item['user_id'];
                                        ?>">
                                            Lihat Profil →
                                        </a>

                                    </div>

                                </article>

                            <?php endforeach; ?>

                        </div>

                    <?php else: ?>

                        <div class="company-empty-state">

                            <div class="company-empty-icon">🏢</div>

                            <h3>Belum Ada Perusahaan</h3>

                            <p>
                                <?php if ($keyword !== ''): ?>
                                    Tidak ditemukan perusahaan yang sesuai dengan pencarianmu.
                                <?php else: ?>
                                    Saat ini belum ada perusahaan terverifikasi yang tersedia.
                                <?php endif; ?>
                            </p>

                            <?php if ($keyword !== ''): ?>
                                <a href="perusahaan.php" class="company-search-button">
                                    Tampilkan Semua Perusahaan
                                </a>
                            <?php endif; ?>

                        </div>

                    <?php endif; ?>

                </div>

            </section>

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

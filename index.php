
<?php
require_once "config/database.php";

// Mengambil maksimal 3 lowongan terbaru yang sudah dipublikasikan
// dan berasal dari perusahaan yang sudah terverifikasi.
$latestJobs = [];

try {
    $sql = "SELECT
                jv.id,
                jv.title,
                jv.department,
                jv.job_type,
                jv.location,
                jv.duration,
                jv.deadline,
                jv.description,
                cp.company_name,
                cp.logo_path,
                jc.category_name
            FROM job_vacancies jv
            INNER JOIN company_profiles cp
                ON jv.company_id = cp.user_id
            LEFT JOIN job_categories jc
                ON jv.category_id = jc.id
            WHERE jv.status = 'published'
              AND cp.verification_status = 'verified'
            ORDER BY COALESCE(jv.published_at, jv.created_at) DESC
            LIMIT 3";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $latestJobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Gagal mengambil lowongan terbaru: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>CareerMap - Portal Karier Mahasiswa UAJY</title>

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
                <a href="index.php" class="active">Beranda</a>
                <a href="lowongan.php">Cari Lowongan</a>
                <a href="perusahaan.php">Perusahaan</a>
                <a href="tentang.php">Tentang</a>
            </nav>

            <div class="nav-auth">
                <a href="auth/login.php" class="btn-login">Masuk</a>
                <a href="auth/register.php" class="btn-register">Daftar</a>
            </div>

        </div>
    </header>


    <main>

        <!-- HERO SECTION -->
        <section class="hero">

            <div class="hero-container">

                <div class="hero-content">

                    <h1>
                        Jelajahi Peluang Karier
                        <span>bersama CareerMap UAJY</span>
                    </h1>

                    <p>
                        Temukan lowongan, magang, dan proyek terbaik
                        sesuai minat dan kompetensimu.
                    </p>

                </div>

                <form action="lowongan.php" method="GET" class="hero-search">

                    <div class="search-input">

                        <span class="search-icon">&#128269;</span>

                        <input
                            type="text"
                            name="keyword"
                            placeholder="Cari posisi, keahlian, atau perusahaan..."
                        >

                    </div>

                    <button type="submit">
                        Cari Lowongan
                    </button>

                </form>

            </div>

        </section>


        <!-- KATEGORI BIDANG KARIER -->
        <section class="categories-section">

            <div class="section-container">

                <div class="section-heading">

                    <div>
                        <span class="section-label">
                            JELAJAHI PELUANG
                        </span>

                        <h2>Temukan Bidang Kariermu</h2>

                        <p>
                            Pilih bidang yang sesuai dengan minat
                            dan kemampuanmu.
                        </p>
                    </div>

                    <a href="lowongan.php" class="see-all">
                        Lihat Semua Lowongan →
                    </a>

                </div>


                <div class="categories-grid">

                    <a href="lowongan.php" class="category-card">
                        <div class="category-icon">💻</div>
                        <h3>Software Development</h3>
                        <p>Pengembangan aplikasi dan teknologi.</p>
                    </a>

                    <a href="lowongan.php" class="category-card">
                        <div class="category-icon">🎨</div>
                        <h3>UI/UX Design</h3>
                        <p>Desain pengalaman dan antarmuka.</p>
                    </a>

                    <a href="lowongan.php" class="category-card">
                        <div class="category-icon">📊</div>
                        <h3>Data & Analytics</h3>
                        <p>Analisis data dan informasi.</p>
                    </a>

                    <a href="lowongan.php" class="category-card">
                        <div class="category-icon">📢</div>
                        <h3>Marketing</h3>
                        <p>Pemasaran dan strategi digital.</p>
                    </a>

                    <a href="lowongan.php" class="category-card">
                        <div class="category-icon">👥</div>
                        <h3>Human Resources</h3>
                        <p>Pengelolaan sumber daya manusia.</p>
                    </a>

                    <a href="lowongan.php" class="category-card">
                        <div class="category-icon">💰</div>
                        <h3>Finance & Accounting</h3>
                        <p>Keuangan dan akuntansi.</p>
                    </a>

                </div>

            </div>

        </section>


        <!-- LOWONGAN TERBARU -->
        <section class="latest-jobs-section">

            <div class="section-container">

                <div class="section-heading">

                    <div>
                        <span class="section-label">
                            PELUANG TERBARU
                        </span>

                        <h2>Lowongan Terbaru</h2>

                        <p>
                            Temukan kesempatan magang dan pekerjaan
                            terbaru dari perusahaan terpercaya.
                        </p>
                    </div>

                    <a href="lowongan.php" class="see-all">
                        Lihat Semua Lowongan →
                    </a>

                </div>


                <?php if (!empty($latestJobs)): ?>

                    <div class="latest-jobs-grid">

                        <?php foreach ($latestJobs as $job): ?>

                            <article class="latest-job-card">

                                <div class="latest-job-top">

                                    <div class="latest-job-logo">

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

                                    <span class="latest-job-type">
                                        <?php echo htmlspecialchars($job['job_type']); ?>
                                    </span>

                                </div>


                                <div class="latest-job-content">

                                    <span class="latest-job-category">
                                        <?php
                                        echo htmlspecialchars(
                                            $job['category_name'] ?? 'Umum'
                                        );
                                        ?>
                                    </span>

                                    <h3>
                                        <?php echo htmlspecialchars($job['title']); ?>
                                    </h3>

                                    <p class="latest-job-company">
                                        <?php echo htmlspecialchars($job['company_name']); ?>
                                    </p>

                                    <div class="latest-job-info">

                                        <span>
                                            📍
                                            <?php echo htmlspecialchars($job['location']); ?>
                                        </span>

                                        <?php if (!empty($job['duration'])): ?>
                                            <span>
                                                🕒
                                                <?php echo htmlspecialchars($job['duration']); ?>
                                            </span>
                                        <?php endif; ?>

                                    </div>

                                </div>


                                <div class="latest-job-footer">

                                    <a href="detail-lowongan.php?id=<?php
                                        echo (int) $job['id'];
                                    ?>">
                                        Lihat Detail Lowongan →
                                    </a>

                                </div>

                            </article>

                        <?php endforeach; ?>

                    </div>

                <?php else: ?>

                    <div class="latest-jobs-empty">

                        <div class="empty-icon">📋</div>

                        <h3>Belum Ada Lowongan Terbaru</h3>

                        <p>
                            Saat ini belum tersedia lowongan yang dapat ditampilkan.
                            Silakan kunjungi kembali halaman ini nanti.
                        </p>

                        <a href="lowongan.php">
                            Jelajahi Lowongan
                        </a>

                    </div>

                <?php endif; ?>

            </div>

        </section>

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

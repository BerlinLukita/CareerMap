
<?php
require_once "config/database.php";

// Mengambil keyword pencarian
$keyword = isset($_GET['keyword'])
    ? trim($_GET['keyword'])
    : "";

// Mengambil filter kategori
$category_id = isset($_GET['category'])
    ? $_GET['category']
    : "";

// Mengambil filter jenis pekerjaan
$job_type = isset($_GET['type'])
    ? $_GET['type']
    : "";

// Mengambil daftar kategori dari database
$sqlCategories = "SELECT * FROM job_categories ORDER BY category_name ASC";
$stmtCategories = $pdo->query($sqlCategories);
$categories = $stmtCategories->fetchAll(PDO::FETCH_ASSOC);

// Query utama lowongan
$sql = "SELECT
            j.*,
            cp.company_name,
            cp.logo_path,
            jc.category_name
        FROM job_vacancies j
        JOIN company_profiles cp
            ON j.company_id = cp.user_id
        LEFT JOIN job_categories jc
            ON j.category_id = jc.id
        WHERE j.status = 'published'
        AND cp.verification_status = 'verified'";

// Filter pencarian
$params = [];

if ($keyword !== "") {
    $sql .= " AND (
        j.title LIKE ?
        OR cp.company_name LIKE ?
        OR j.location LIKE ?
    )";

    $search = "%" . $keyword . "%";

    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
}

// Filter kategori
if ($category_id !== "") {
    $sql .= " AND j.category_id = ?";
    $params[] = $category_id;
}

// Filter jenis pekerjaan
if ($job_type !== "") {
    $sql .= " AND j.job_type = ?";
    $params[] = $job_type;
}

// Urutkan berdasarkan lowongan terbaru
$sql .= " ORDER BY j.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Cari Lowongan - CareerMap UAJY</title>

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


<!-- HALAMAN LOWONGAN -->
<main class="jobs-page">

    <section class="jobs-header">

        <div class="section-container">

            <span class="section-label">PELUANG KARIER UAJY</span>

            <h1>Temukan Lowongan Impianmu</h1>

            <p>
                Jelajahi berbagai kesempatan magang dan pekerjaan
                dari perusahaan yang telah terverifikasi.
            </p>

        </div>

    </section>


    <!-- PENCARIAN DAN FILTER -->
    <section class="jobs-content">

        <div class="section-container">

            <form action="lowongan.php" method="GET" class="jobs-filter">

                <input
                    type="text"
                    name="keyword"
                    placeholder="Cari posisi, perusahaan, atau lokasi..."
                    value="<?= htmlspecialchars($keyword) ?>"
                >

                <select name="category">
                    <option value="">Semua Kategori</option>

                    <?php foreach ($categories as $category): ?>
                        <option
                            value="<?= $category['id'] ?>"
                            <?= $category_id == $category['id'] ? 'selected' : '' ?>
                        >
                            <?= htmlspecialchars($category['category_name']) ?>
                        </option>
                    <?php endforeach; ?>

                </select>

                <select name="type">
                    <option value="">Semua Jenis</option>
                    <option value="Magang" <?= $job_type == 'Magang' ? 'selected' : '' ?>>
                        Magang
                    </option>
                    <option value="Full-time" <?= $job_type == 'Full-time' ? 'selected' : '' ?>>
                        Full-time
                    </option>
                </select>

                <button type="submit">Cari Lowongan</button>

            </form>


            <!-- HASIL LOWONGAN -->
            <div class="jobs-result-heading">

                <div>
                    <h2>Daftar Lowongan</h2>
                    <p>
                        Ditemukan <?= count($jobs) ?> lowongan pekerjaan.
                    </p>
                </div>

            </div>


            <div class="jobs-grid">

                <?php if (count($jobs) > 0): ?>

                    <?php foreach ($jobs as $job): ?>

                        <article class="job-card">

                            <div class="job-card-top">

                                <div class="job-company-logo">

                                    <?php if (!empty($job['logo'])): ?>

                                        <img
                                            src="<?= htmlspecialchars($job['logo_path']) ?>"
                                            alt="Logo perusahaan"
                                        >

                                    <?php else: ?>

                                        <span>
                                            <?= strtoupper(substr($job['company_name'], 0, 1)) ?>
                                        </span>

                                    <?php endif; ?>

                                </div>

                                <span class="job-type">
                                    <?= htmlspecialchars($job['job_type']) ?>
                                </span>

                            </div>


                            <h3>
                                <?= htmlspecialchars($job['title']) ?>
                            </h3>

                            <p class="job-company">
                                <?= htmlspecialchars($job['company_name']) ?>
                            </p>

                            <div class="job-information">

                                <span>
                                    📍 <?= htmlspecialchars($job['location']) ?>
                                </span>

                                <span>
                                    🏷️ <?= htmlspecialchars($job['category_name'] ?? 'Lainnya') ?>
                                </span>

                            </div>

                            <a
                                href="detail-lowongan.php?id=<?= $job['id'] ?>"
                                class="job-detail-button"
                            >
                                Lihat Detail
                            </a>

                        </article>

                    <?php endforeach; ?>

                <?php else: ?>

                    <div class="jobs-empty">

                        <div>🔎</div>

                        <h3>Belum Ada Lowongan</h3>

                        <p>
                            Belum ada lowongan yang sesuai dengan pencarianmu.
                            Coba gunakan kata kunci atau filter lainnya.
                        </p>

                    </div>

                <?php endif; ?>

            </div>

        </div>

    </section>

</main>

</body>
</html>

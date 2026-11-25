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

$name = e($_SESSION["name"] ?? "Mahasiswa");
$initial = strtoupper(mb_substr($_SESSION["name"] ?? "M", 0, 1));

// ==========================================
// SIMPAN / HAPUS LOWONGAN DARI DAFTAR SIMPANAN
// ==========================================
if (empty($_SESSION["csrf_saved_jobs"])) {
    $_SESSION["csrf_saved_jobs"] = bin2hex(random_bytes(32));
}

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
                    SELECT :student_id, j.id
                    FROM job_vacancies j
                    WHERE j.id = :job_id AND j.status = 'published'
                ");
                $saveStmt->execute([
                    ":student_id" => (int)$_SESSION["user_id"],
                    ":job_id" => $postedJobId
                ]);
                $_SESSION["saved_job_notice"] = $saveStmt->rowCount() > 0
                    ? "Lowongan berhasil disimpan."
                    : "Lowongan sudah tersimpan atau tidak tersedia.";
            } elseif ($action === "remove") {
                $removeStmt = $pdo->prepare("
                    DELETE FROM saved_jobs
                    WHERE student_id = :student_id AND job_id = :job_id
                ");
                $removeStmt->execute([
                    ":student_id" => (int)$_SESSION["user_id"],
                    ":job_id" => $postedJobId
                ]);
                $_SESSION["saved_job_notice"] = "Lowongan dihapus dari simpanan.";
            }
        } catch (PDOException $ex) {
            error_log("Saved jobs error: " . $ex->getMessage());
            $_SESSION["saved_job_notice"] = "Tidak dapat memperbarui daftar simpanan. Pastikan profil mahasiswa sudah tersedia.";
        }
    }

    $queryString = $_SERVER["QUERY_STRING"] ?? "";
    header("Location: cari_lowongan.php" . ($queryString !== "" ? "?" . $queryString : ""));
    exit;
}

$savedNotice = $_SESSION["saved_job_notice"] ?? "";
unset($_SESSION["saved_job_notice"]);

$savedStmt = $pdo->prepare("SELECT job_id FROM saved_jobs WHERE student_id = :student_id");
$savedStmt->execute([":student_id" => (int)$_SESSION["user_id"]]);
$savedJobIds = array_map("intval", $savedStmt->fetchAll(PDO::FETCH_COLUMN));

$keyword = trim($_GET["keyword"] ?? "");
$type = trim($_GET["type"] ?? "");
$category = trim($_GET["category"] ?? "");

$allowedDistances = [5, 10, 25, 50];
$distance = (int)($_GET["distance"] ?? 10);
if (!in_array($distance, $allowedDistances, true)) {
    $distance = 10;
}

// Kategori berasal dari tabel job_categories.
$categoryStmt = $pdo->query("
    SELECT category_name
    FROM job_categories
    ORDER BY category_name
");
$categories = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);

// Jarak belum dapat dihitung karena SQL belum memiliki koordinat pengguna.
// Filter keyword, tipe, dan kategori tetap diterapkan ke database.
$where = ["j.status = 'published'"];
$params = [];

if ($keyword !== "") {
    $where[] = "(j.title LIKE :keyword OR j.description LIKE :keyword
                 OR j.department LIKE :keyword OR cp.company_name LIKE :keyword
                 OR j.location LIKE :keyword OR jc.category_name LIKE :keyword)";
    $params["keyword"] = "%" . $keyword . "%";
}

if ($type !== "") {
    $where[] = "j.job_type = :job_type";
    $params["job_type"] = $type;
}

if ($category !== "") {
    $where[] = "jc.category_name = :category";
    $params["category"] = $category;
}

$sql = "
    SELECT j.id, j.title AS position, cp.company_name AS company,
           j.location, j.job_type AS type, jc.category_name AS category,
           cp.logo_path
    FROM job_vacancies j
    INNER JOIN company_profiles cp ON cp.user_id = j.company_id
    INNER JOIN job_categories jc ON jc.id = j.category_id
    WHERE " . implode(" AND ", $where) . "
    ORDER BY j.published_at DESC, j.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$filteredJobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

function distanceUrl($value, $keyword, $type, $category) {
    return "?" . http_build_query([
        "keyword" => $keyword,
        "type" => $type,
        "category" => $category,
        "distance" => $value
    ]);
}
?>
<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Cari Lowongan | CareerMap</title>

    <link rel="stylesheet" href="../assets/css/cari-lowongan.css">

</head>

<body>

<div class="layout">

    <!-- =================================
         SIDEBAR
    ================================== -->

    <aside class="sidebar">

        <div class="brand">

            <img
                src="../assets/images/logo-uajy.png"
                alt="Logo UAJY"
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

        <!-- HEADER -->

        <header class="page-header">

            <div>

                <h1>Cari Lowongan</h1>

                <p>
                    Temukan peluang kerja dan magang berdasarkan minat serta lokasi.
                </p>

            </div>

            <div class="user-avatar">
                <?php echo htmlspecialchars($initial); ?>
            </div>

        </header>


        <!-- =================================
             SEARCH FILTER
        ================================== -->

        <section class="search-section">

            <form method="GET" action="cari_lowongan.php" class="search-form">

                <div class="form-group">

                    <label for="keyword">
                        Kata kunci
                    </label>

                    <input
                        type="text"
                        id="keyword"
                        name="keyword"
                        placeholder="Posisi, keahlian, atau perusahaan..."
                        value="<?php echo htmlspecialchars($keyword); ?>"
                    >

                </div>


                <div class="form-group">

                    <label for="type">
                        Tipe pekerjaan
                    </label>

                    <select name="type" id="type">

                        <option value="">Semua Tipe</option>

                        <option value="Magang"
                            <?php echo $type === "Magang" ? "selected" : ""; ?>>
                            Magang
                        </option>

                        <option value="Full-time"
                            <?php echo $type === "Full-time" ? "selected" : ""; ?>>
                            Full-time
                        </option>

                        <option value="Part-time"
                            <?php echo $type === "Part-time" ? "selected" : ""; ?>>
                            Part-time
                        </option>

                    </select>

                </div>


                <div class="form-group">

                    <label for="category">
                        Kategori
                    </label>

                    <select name="category" id="category">

                        <option value="">Semua Kategori</option>

                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo e($cat["category_name"]); ?>"
                                <?php echo $category === $cat["category_name"] ? "selected" : ""; ?>>
                                <?php echo e($cat["category_name"]); ?>
                            </option>
                        <?php endforeach; ?>

                    </select>

                </div>


                <button type="submit" class="search-button">
                    Cari
                </button>

            </form>

        </section>


        <!-- =================================
             LOCATION SECTION
        ================================== -->

        <section class="location-section">

            <div class="location-icon">
                ⌖
            </div>

            <div class="location-information">

                <h2>Cari Lowongan di Sekitarmu</h2>

                <p>
                    Gunakan lokasi perangkatmu untuk menemukan perusahaan
                    dan lowongan terdekat.
                </p>

                <span id="location-status">
                    Lokasi belum diaktifkan • Kamu juga bisa memilih lokasi secara manual.
                </span>

            </div>

            <button
                type="button"
                class="location-button"
                id="location-button"
            >
                Gunakan Lokasi Saya
            </button>

        </section>


        <!-- =================================
             RESULT HEADER
        ================================== -->

        <section class="result-section">

            <div class="result-heading">

                <div>

                    <h2>Hasil Lowongan Terdekat</h2>

                    <p>
                        Atur jarak pencarian dari lokasimu.
                    </p>

                </div>

            </div>


            <!-- DISTANCE FILTER -->

            <div class="filter-toolbar">

                <div class="distance-filter">

                    <span class="distance-label">
                        Jarak
                    </span>

                    <?php foreach ($allowedDistances as $km): ?>

                        <a
                            href="<?php echo htmlspecialchars(
                                distanceUrl($km, $keyword, $type, $category)
                            ); ?>"
                            class="distance-button <?php echo $distance === $km ? 'active' : ''; ?>"
                        >
                            <?php echo $km; ?> km
                        </a>

                    <?php endforeach; ?>

                </div>


                <button
                    type="button"
                    class="map-toggle"
                    id="map-toggle"
                >
                    Sembunyikan Peta
                </button>

            </div>


            <!-- =================================
                 MAP AND JOB LIST
            ================================== -->

            <div class="results-layout" id="results-layout">


                <!-- MAP -->

                <div class="map-container" id="map-container">

                    <div class="map-area">

                        <div class="map-block block-one"></div>
                        <div class="map-block block-two"></div>
                        <div class="map-block block-three"></div>
                        <div class="map-block block-four"></div>
                        <div class="map-block block-five"></div>
                        <div class="map-block block-six"></div>

                        <div class="road road-one"></div>
                        <div class="road road-two"></div>
                        <div class="road road-three"></div>
                        <div class="road road-four"></div>

                        <div class="map-marker marker-one">1</div>
                        <div class="map-marker marker-two">2</div>
                        <div class="map-marker marker-three">3</div>

                        <div class="current-location"></div>

                        <div class="map-caption">
                            Peta ilustrasi • Lokasi lowongan ditampilkan berdasarkan data perusahaan.
                        </div>

                    </div>

                </div>


                <!-- JOB LIST -->

                <div class="nearby-jobs">

                    <div class="nearby-header">

                        <h2>Lowongan di Sekitarmu</h2>

                        <p>
                            <?php echo count($filteredJobs); ?>
                            peluang ditemukan dalam radius
                            <?php echo $distance; ?> km
                        </p>

                    </div>


                    <div class="job-list">

                        <?php if (count($filteredJobs) > 0): ?>

                            <?php foreach ($filteredJobs as $job): ?>

                                <article class="job-item">

                                    <div class="job-detail">

                                        <h3>
                                            <?php echo htmlspecialchars($job["position"]); ?>
                                        </h3>

                                        <p>
                                            <?php echo htmlspecialchars($job["company"]); ?>
                                            •
                                            <?php echo htmlspecialchars($job["location"]); ?>

                                        </p>

                                        <span class="job-type <?php echo $job["type"] === "Full-time" ? "fulltime" : "internship"; ?>">
                                            <?php echo htmlspecialchars($job["type"]); ?>
                                        </span>

                                    </div>

                                    <div class="job-item-actions">
                                        <form method="POST" action="cari_lowongan.php<?php echo !empty($_SERVER["QUERY_STRING"]) ? "?" . e($_SERVER["QUERY_STRING"]) : ""; ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION["csrf_saved_jobs"]); ?>">
                                            <input type="hidden" name="job_id" value="<?php echo (int)$job["id"]; ?>">
                                            <?php $isSaved = in_array((int)$job["id"], $savedJobIds, true); ?>
                                            <button type="submit" name="saved_action" value="<?php echo $isSaved ? "remove" : "save"; ?>" class="save-job-button <?php echo $isSaved ? "is-saved" : ""; ?>" aria-label="<?php echo $isSaved ? "Hapus dari simpanan" : "Simpan lowongan"; ?>" title="<?php echo $isSaved ? "Tersimpan - klik untuk menghapus" : "Simpan lowongan"; ?>">
                                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 4.75A2.75 2.75 0 0 1 8.75 2h6.5A2.75 2.75 0 0 1 18 4.75V22l-6-3.8L6 22V4.75Z"/></svg>
                                            </button>
                                        </form>
                                        <a href="detail_lowongan.php?id=<?php echo (int)$job["id"]; ?>" class="job-detail-button">Detail →</a>
                                    </div>

                                </article>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <div class="empty-state">

                                <div class="empty-icon">⌕</div>

                                <h3>Lowongan tidak ditemukan</h3>

                                <p>
                                    Coba ubah kata kunci, kategori,
                                    tipe pekerjaan, atau jarak pencarian.
                                </p>

                                <a href="cari_lowongan.php">
                                    Reset Pencarian
                                </a>

                            </div>

                        <?php endif; ?>

                    </div>

                </div>

            </div>

        </section>

    </main>

</div>


<!-- =================================
     JAVASCRIPT
================================== -->

<script>

// =====================================
// TOGGLE MAP
// =====================================

const mapToggle = document.getElementById("map-toggle");
const mapContainer = document.getElementById("map-container");
const resultsLayout = document.getElementById("results-layout");

mapToggle.addEventListener("click", function () {

    mapContainer.classList.toggle("hidden");

    if (mapContainer.classList.contains("hidden")) {

        mapToggle.textContent = "Tampilkan Peta";
        resultsLayout.classList.add("list-only");

    } else {

        mapToggle.textContent = "Sembunyikan Peta";
        resultsLayout.classList.remove("list-only");

    }

});


// =====================================
// LOCATION PERMISSION
// =====================================

const locationButton = document.getElementById("location-button");
const locationStatus = document.getElementById("location-status");

locationButton.addEventListener("click", function () {

    if (!navigator.geolocation) {

        locationStatus.textContent =
            "Browser tidak mendukung fitur lokasi.";

        return;
    }

    locationButton.disabled = true;
    locationButton.textContent = "Memeriksa lokasi...";

    navigator.geolocation.getCurrentPosition(

        function (position) {

            locationStatus.textContent =
                "Lokasi perangkat berhasil dibaca. Jarak aktual belum tersedia karena data koordinat lowongan belum diisi.";

            locationButton.textContent = "Lokasi Ditemukan";
            locationButton.disabled = false;

        },

        function () {

            locationStatus.textContent =
                "Lokasi belum dapat diakses. Periksa izin lokasi browser.";

            locationButton.textContent = "Coba Lagi";
            locationButton.disabled = false;

        }

    );

});

</script>

</body>
</html>

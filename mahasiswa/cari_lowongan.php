<?php
session_start();

// =====================================
// PROTEKSI LOGIN
// =====================================

if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "student"
) {
    header("Location: ../auth/login.php");
    exit;
}

$name = htmlspecialchars(
    $_SESSION["name"] ?? "Mahasiswa",
    ENT_QUOTES,
    "UTF-8"
);

$initial = strtoupper(substr($_SESSION["name"] ?? "M", 0, 1));


// =====================================
// DATA LOWONGAN CONTOH
// Nantinya dapat diganti dengan database
// =====================================

$jobs = [
    [
        "id" => 1,
        "position" => "UI/UX Design Intern",
        "company" => "Kreasi Digital",
        "location" => "Yogyakarta",
        "type" => "Magang",
        "category" => "Desain",
        "distance" => 1.2,
        "initial" => "K"
    ],
    [
        "id" => 2,
        "position" => "Frontend Developer",
        "company" => "Nusa Teknologi",
        "location" => "Yogyakarta",
        "type" => "Full-time",
        "category" => "Teknologi",
        "distance" => 3.4,
        "initial" => "N"
    ],
    [
        "id" => 3,
        "position" => "QA Engineer Intern",
        "company" => "Solusi Cerdas",
        "location" => "Yogyakarta",
        "type" => "Magang",
        "category" => "Teknologi",
        "distance" => 7.8,
        "initial" => "S"
    ],
    [
        "id" => 4,
        "position" => "Data Analyst Intern",
        "company" => "Mitra Inovasi",
        "location" => "Yogyakarta",
        "type" => "Magang",
        "category" => "Data",
        "distance" => 12.5,
        "initial" => "M"
    ]
];


// =====================================
// AMBIL FILTER PENCARIAN
// =====================================

$keyword = trim($_GET["keyword"] ?? "");
$type = trim($_GET["type"] ?? "");
$category = trim($_GET["category"] ?? "");

$allowedDistances = [5, 10, 25, 50];

$distance = (int)($_GET["distance"] ?? 10);

if (!in_array($distance, $allowedDistances, true)) {
    $distance = 10;
}


// =====================================
// FILTER LOWONGAN
// =====================================

$filteredJobs = array_filter($jobs, function ($job) use (
    $keyword,
    $type,
    $category,
    $distance
) {

    $matchKeyword = true;

    if ($keyword !== "") {
        $searchText = strtolower(
            $job["position"] . " " .
            $job["company"] . " " .
            $job["category"] . " " .
            $job["location"]
        );

        $matchKeyword = strpos(
            $searchText,
            strtolower($keyword)
        ) !== false;
    }

    $matchType = (
        $type === "" ||
        $job["type"] === $type
    );

    $matchCategory = (
        $category === "" ||
        $job["category"] === $category
    );

    $matchDistance = $job["distance"] <= $distance;

    return (
        $matchKeyword &&
        $matchType &&
        $matchCategory &&
        $matchDistance
    );
});


// =====================================
// URL FILTER JARAK
// =====================================

function distanceUrl($value, $keyword, $type, $category)
{
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

                        <option value="Teknologi"
                            <?php echo $category === "Teknologi" ? "selected" : ""; ?>>
                            Teknologi
                        </option>

                        <option value="Desain"
                            <?php echo $category === "Desain" ? "selected" : ""; ?>>
                            Desain
                        </option>

                        <option value="Data"
                            <?php echo $category === "Data" ? "selected" : ""; ?>>
                            Data
                        </option>

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
                            Peta ilustrasi • Lokasi akan tampil setelah izin GPS diberikan.
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
                                            •
                                            <?php echo number_format($job["distance"], 1); ?> km
                                        </p>

                                        <span class="job-type <?php echo $job["type"] === "Full-time" ? "fulltime" : "internship"; ?>">
                                            <?php echo htmlspecialchars($job["type"]); ?>
                                        </span>

                                    </div>

                                    <a
                                        href="detail_lowongan.php?id=<?php echo (int)$job["id"]; ?>"
                                        class="job-detail-button"
                                    >
                                        Detail →
                                    </a>

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
                "Lokasi perangkat berhasil dibaca. Pencarian berbasis koordinat belum dihubungkan ke database.";

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

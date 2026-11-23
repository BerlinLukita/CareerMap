<?php
session_start();

// ==========================================
// 1. PROTEKSI LOGIN
// ==========================================

if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "student"
) {
    header("Location: ../auth/login.php");
    exit;
}

// ==========================================
// 2. DATA PENGGUNA
// ==========================================

$name = $_SESSION["name"] ?? "Mahasiswa";
$initialUser = strtoupper(substr($name, 0, 1));

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

// ==========================================
// 3. DATA LOWONGAN CONTOH
// Nantinya diambil dari database jobs
// ==========================================

$jobs = [
    1 => [
        "id" => 1,
        "position" => "UI/UX Design Intern",
        "company" => "Kreasi Digital",
        "initial" => "K",
        "location" => "Yogyakarta",
        "country" => "Indonesia",
        "description" => "Membantu tim produk melakukan riset pengguna, menyusun wireframe, dan merancang antarmuka digital yang mudah digunakan.",
        "qualifications" => [
            "Mahasiswa aktif minimal semester 5",
            "Memahami Figma dan prinsip UI/UX",
            "Memiliki portofolio menjadi nilai tambah"
        ],
        "type" => "Magang",
        "duration" => "3 bulan",
        "industry" => "Teknologi Informasi",
        "distance" => "1,2 km",
        "company_description" => "Kreasi Digital bergerak di bidang pengembangan produk digital dan teknologi.",
        "website" => "https://kreasidigital.co.id"
    ],

    2 => [
        "id" => 2,
        "position" => "Frontend Developer",
        "company" => "Nusa Teknologi",
        "initial" => "N",
        "location" => "Yogyakarta",
        "country" => "Indonesia",
        "description" => "Membantu pengembangan antarmuka website yang responsif, interaktif, dan mudah digunakan oleh pengguna.",
        "qualifications" => [
            "Memahami HTML, CSS, dan JavaScript",
            "Memahami dasar PHP menjadi nilai tambah",
            "Mampu bekerja dalam tim"
        ],
        "type" => "Full-time",
        "duration" => "Tetap",
        "industry" => "Teknologi Informasi",
        "distance" => "3,4 km",
        "company_description" => "Nusa Teknologi menyediakan layanan pengembangan perangkat lunak.",
        "website" => "#"
    ],

    3 => [
        "id" => 3,
        "position" => "QA Engineer Intern",
        "company" => "Solusi Cerdas",
        "initial" => "S",
        "location" => "Yogyakarta",
        "country" => "Indonesia",
        "description" => "Membantu proses pengujian aplikasi, menemukan bug, dan memastikan kualitas produk digital.",
        "qualifications" => [
            "Mahasiswa aktif minimal semester 5",
            "Memahami dasar software testing",
            "Teliti dan mampu bekerja sama"
        ],
        "type" => "Magang",
        "duration" => "3 bulan",
        "industry" => "Teknologi Informasi",
        "distance" => "7,8 km",
        "company_description" => "Solusi Cerdas bergerak dalam pengembangan solusi teknologi digital.",
        "website" => "#"
    ],

    4 => [
        "id" => 4,
        "position" => "Data Analyst Intern",
        "company" => "Mitra Inovasi",
        "initial" => "M",
        "location" => "Yogyakarta",
        "country" => "Indonesia",
        "description" => "Membantu mengolah data, membuat visualisasi, dan menyusun laporan analisis untuk kebutuhan perusahaan.",
        "qualifications" => [
            "Memahami Microsoft Excel",
            "Memahami dasar pengolahan data",
            "Memiliki kemampuan analisis yang baik"
        ],
        "type" => "Magang",
        "duration" => "3 bulan",
        "industry" => "Data dan Analitik",
        "distance" => "12,5 km",
        "company_description" => "Mitra Inovasi bergerak dalam bidang analisis dan pengembangan bisnis.",
        "website" => "#"
    ]
];

// ==========================================
// 4. AMBIL ID LOWONGAN
// ==========================================

$jobId = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$jobId || !isset($jobs[$jobId])) {
    http_response_code(404);
    $job = null;
} else {
    $job = $jobs[$jobId];
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

                        <a
                            href="laporkan_lowongan.php?id=<?php echo (int)$job["id"]; ?>"
                            class="report-button"
                        >
                            Laporkan Lowongan
                        </a>

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

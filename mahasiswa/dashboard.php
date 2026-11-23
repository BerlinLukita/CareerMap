<?php
session_start();

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
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard Mahasiswa | CareerMap</title>

    <link rel="stylesheet" href="../assets/css/dashboard-mahasiswa.css">
</head>

<body>

<div class="layout">

    <!-- SIDEBAR -->
    <aside class="sidebar">

        <div class="brand">
            <img src="../assets/images/logo-uajy.png" alt="Logo UAJY">
            <span>CareerMap</span>
        </div>

        <div class="role-label">MAHASISWA</div>

        <nav class="navigation">

            <a href="dashboard.php" class="nav-link active">
                Dashboard
            </a>

            <a href="cari_lowongan.php" class="nav-link">
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

            <a href="../bantuan/index.php">Bantuan & Pusat Dukungan</a>

            <a href="../auth/logout.php" class="logout">Keluar</a>

        </div>

    </aside>


    <!-- MAIN CONTENT -->
    <main class="main-content">

        <!-- HEADER -->
        <header class="page-header">

            <div>
                <h1>Halo, <?php echo $name; ?>! 👋</h1>

                <p>
                    Temukan peluang karier yang sesuai dengan minat
                    dan kemampuanmu.
                </p>
            </div>

            <div class="user-avatar">
                <?php echo $initial; ?>
            </div>

        </header>


        <!-- SEARCH -->
        <section class="search-box">

            <h3>CARI LOWONGAN</h3>

            <form action="cari_lowongan.php" method="GET" class="search-form">

                <input
                    type="text"
                    name="keyword"
                    placeholder="Cari posisi, keahlian, atau perusahaan..."
                >

                <select name="location">
                    <option value="">Semua Lokasi</option>
                    <option value="Yogyakarta">Yogyakarta</option>
                    <option value="Jakarta">Jakarta</option>
                    <option value="Remote">Remote</option>
                </select>

                <select name="type">
                    <option value="">Tipe Pekerjaan</option>
                    <option value="Magang">Magang</option>
                    <option value="Full-time">Full-time</option>
                    <option value="Part-time">Part-time</option>
                </select>

                <button type="submit">Cari Lowongan</button>

            </form>

        </section>


        <!-- STATISTICS -->
        <section class="statistics">

            <div class="stat-card">
                <h2>12</h2>
                <p>Lowongan dilihat</p>
            </div>

            <div class="stat-card">
                <h2>4</h2>
                <p>Lamaran terkirim</p>
            </div>

            <div class="stat-card">
                <h2>2</h2>
                <p>Dalam proses</p>
            </div>

            <div class="stat-card">
                <h2>1</h2>
                <p>Interview</p>
            </div>

        </section>


        <!-- RECOMMENDATION -->
        <section class="content-grid">

            <div class="recommendations">

                <div class="section-header">
                    <h2>Rekomendasi untukmu</h2>

                    <a href="cari_lowongan.php">
                        Lihat semua →
                    </a>
                </div>


                <!-- JOB 1 -->
                <article class="job-card">

                    <div class="company-logo blue-logo">N</div>

                    <div class="job-info">

                        <h3>Frontend Developer Intern</h3>

                        <p>Nusa Teknologi • Remote</p>

                        <span class="badge internship">Magang</span>

                    </div>

                    <a href="detail_lowongan.php?id=1" class="detail-button">
                        Lihat Detail →
                    </a>

                </article>


                <!-- JOB 2 -->
                <article class="job-card">

                    <div class="company-logo blue-logo">K</div>

                    <div class="job-info">

                        <h3>UI/UX Design Intern</h3>

                        <p>Kreasi Digital • Yogyakarta</p>

                        <span class="badge internship">Magang</span>

                    </div>

                    <a href="detail_lowongan.php?id=2" class="detail-button">
                        Lihat Detail →
                    </a>

                </article>


                <!-- JOB 3 -->
                <article class="job-card">

                    <div class="company-logo blue-logo">S</div>

                    <div class="job-info">

                        <h3>QA Engineer</h3>

                        <p>Solusi Cerdas • Yogyakarta</p>

                        <span class="badge fulltime">Full-time</span>

                    </div>

                    <a href="detail_lowongan.php?id=3" class="detail-button">
                        Lihat Detail →
                    </a>

                </article>

            </div>


            <!-- PROFILE PANEL -->
            <aside class="profile-panel">

                <div class="profile-card">

                    <h3>Lengkapi Profil</h3>

                    <p class="percentage">Profilmu 80% lengkap</p>

                    <div class="progress">
                        <div class="progress-value"></div>
                    </div>

                    <h4>Tingkatkan peluang dilirik</h4>

                    <p class="profile-description">
                        Tambahkan portofolio dan CV
                    </p>

                    <h3 class="document-title">Dokumen Karier</h3>

                    <div class="document">
                        CV_Berlin_Lukita.pdf
                    </div>

                    <a href="profile.php" class="edit-button">
                        Edit Profil
                    </a>

                </div>

            </aside>

        </section>

    </main>

</div>

</body>
</html>

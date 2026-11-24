
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>CareerMap - Portal Karier Mahasiswa UAJY</title>

    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <!-- =================================
         NAVBAR
    ================================== -->

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


    <!-- =================================
         MAIN CONTENT
    ================================== -->

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

                <!-- FORM PENCARIAN -->

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


        <!-- =================================
             KATEGORI BIDANG KARIER
        ================================== -->

        <section class="categories-section">

            <div class="section-container">

                <!-- JUDUL SECTION -->

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


                <!-- DAFTAR KATEGORI -->

                <div class="categories-grid">

                    <a href="lowongan.php" class="category-card">

                        <div class="category-icon">💻</div>

                        <h3>Software Development</h3>

                        <p>
                            Pengembangan aplikasi dan teknologi.
                        </p>

                    </a>


                    <a href="lowongan.php" class="category-card">

                        <div class="category-icon">🎨</div>

                        <h3>UI/UX Design</h3>

                        <p>
                            Desain pengalaman dan antarmuka.
                        </p>

                    </a>


                    <a href="lowongan.php" class="category-card">

                        <div class="category-icon">📊</div>

                        <h3>Data & Analytics</h3>

                        <p>
                            Analisis data dan informasi.
                        </p>

                    </a>


                    <a href="lowongan.php" class="category-card">

                        <div class="category-icon">📢</div>

                        <h3>Marketing</h3>

                        <p>
                            Pemasaran dan strategi digital.
                        </p>

                    </a>


                    <a href="lowongan.php" class="category-card">

                        <div class="category-icon">👥</div>

                        <h3>Human Resources</h3>

                        <p>
                            Pengelolaan sumber daya manusia.
                        </p>

                    </a>


                    <a href="lowongan.php" class="category-card">

                        <div class="category-icon">💰</div>

                        <h3>Finance & Accounting</h3>

                        <p>
                            Keuangan dan akuntansi.
                        </p>

                    </a>

                </div>

            </div>

        </section>

    </main>


    <!-- =================================
         FOOTER (AKAN DITAMBAHKAN)
    ================================== -->


</body>
</html>

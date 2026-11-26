
<?php
$halaman_aktif = basename($_SERVER['PHP_SELF']);
?>

<aside class="sidebar">

    <!-- LOGO DAN IDENTITAS -->

    <div class="sidebar-logo">
        <img src="../assets/images/logo-uajy.jpg" alt="Logo UAJY">

        <div>
            <h2>CareerMap</h2>
            <span>ADMINISTRATOR</span>
        </div>
    </div>


    <!-- LABEL MENU -->

    <div class="sidebar-label">ADMIN</div>


    <!-- MENU NAVIGASI -->

    <nav class="sidebar-menu">

        <a href="dashboard.php"
           class="<?= $halaman_aktif == 'dashboard.php' ? 'active' : '' ?>">
            <span>▦</span> Dashboard
        </a>

        <a href="pengguna.php"
           class="<?= in_array($halaman_aktif, ['pengguna.php', 'detail-pengguna.php']) ? 'active' : '' ?>">
            <span>♙</span> Manajemen Pengguna
        </a>

        <a href="verifikasi-perusahaan.php"
           class="<?= in_array($halaman_aktif, ['verifikasi-perusahaan.php', 'detail-perusahaan.php']) ? 'active' : '' ?>">
            <span>✓</span> Verifikasi Perusahaan
        </a>

        <a href="lowongan.php"
           class="<?= in_array($halaman_aktif, ['lowongan.php', 'detail-lowongan.php']) ? 'active' : '' ?>">
            <span>▤</span> Moderasi Lowongan
        </a>

        <a href="laporan.php"
           class="<?= in_array($halaman_aktif, ['laporan.php', 'detail-laporan.php']) ? 'active' : '' ?>">
            <span>⚑</span> Laporan
        </a>

        <a href="kategori.php"
           class="<?= $halaman_aktif == 'kategori.php' ? 'active' : '' ?>">
            <span>▦</span> Kategori
        </a>

    </nav>


    <!-- BAGIAN BAWAH SIDEBAR -->

   <div class="sidebar-bottom">
    <a href="../auth/logout.php" class="company-logout-link">
        <span class="company-menu-icon">⇥</span>
        <span>Keluar</span>
    </a>
</div>

</aside>

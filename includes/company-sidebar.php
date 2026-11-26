
<?php
$current_page = basename($_SERVER['PHP_SELF']);

$base_path = (basename(dirname($_SERVER['PHP_SELF'])) === 'perusahaan')
    ? '../'
    : '';

$dashboard_active = $current_page === 'dashboard.php';
$jobs_active = $current_page === 'jobs.php';
$applicants_active = $current_page === 'applicants.php';
$profile_active = $current_page === 'profile.php';
$support_active = $current_page === 'support.php';
?>

<aside class="sidebar company-sidebar">

    <!-- IDENTITAS CAREERMAP -->

    <div class="company-sidebar-top">

        <a href="<?= $base_path ?>perusahaan/dashboard.php"
           class="company-sidebar-brand">

            <img
                src="<?= $base_path ?>assets/images/logo-uajy.jpg"
                alt="Logo UAJY"
                class="company-brand-logo"
            >

            <div class="company-brand-text">
                <h2>CareerMap</h2>
                <span>MITRA PERUSAHAAN</span>
            </div>

        </a>

        <div class="company-sidebar-divider"></div>


        <!-- MENU PERUSAHAAN -->

        <div class="company-sidebar-label">
            PERUSAHAAN
        </div>

        <nav class="company-sidebar-menu">

            <a href="<?= $base_path ?>perusahaan/dashboard.php"
               class="<?= $dashboard_active ? 'active' : '' ?>">

                <span class="company-menu-icon">▦</span>
                <span>Dashboard</span>

            </a>


            <a href="<?= $base_path ?>perusahaan/jobs.php"
               class="<?= $jobs_active ? 'active' : '' ?>">

                <span class="company-menu-icon">▤</span>
                <span>Kelola Lowongan</span>

            </a>


            <a href="<?= $base_path ?>perusahaan/applicants.php"
               class="<?= $applicants_active ? 'active' : '' ?>">

                <span class="company-menu-icon">♙</span>
                <span>Pelamar</span>

            </a>


            <a href="<?= $base_path ?>perusahaan/profile.php"
               class="<?= $profile_active ? 'active' : '' ?>">

                <span class="company-menu-icon">◎</span>
                <span>Profil Perusahaan</span>

            </a>

        </nav>

    </div>


    <!-- BAGIAN BAWAH SIDEBAR -->

    <div class="company-sidebar-bottom">

        <div class="company-sidebar-divider"></div>

        <a href="<?= $base_path ?>support.php"
           class="company-support-link <?= $support_active ? 'active' : '' ?>">

            <span class="company-menu-icon">?</span>
            <span>Bantuan &amp; Pusat Dukungan</span>

        </a>

        <a href="<?= $base_path ?>auth/logout.php"
           class="company-logout-link">

            <span class="company-menu-icon">⇥</span>
            <span>Keluar</span>

        </a>

    </div>

</aside>

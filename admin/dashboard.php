<?php
session_start();

if (
    !isset($_SESSION["user_id"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: ../auth/login.php");
    exit;
}

require_once "../config/database.php";

// Menghitung total pengguna mahasiswa
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM users
    WHERE role = 'student'
");
$stmt->execute();
$total_mahasiswa = $stmt->fetchColumn();

// Menghitung total perusahaan
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM users
    WHERE role = 'company'
");
$stmt->execute();
$total_perusahaan = $stmt->fetchColumn();

// Menghitung perusahaan yang menunggu verifikasi
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM company_profiles
    WHERE verification_status = 'pending'
");
$stmt->execute();
$pending_perusahaan = $stmt->fetchColumn();

// Menghitung total lowongan
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM job_vacancies
");
$stmt->execute();
$total_lowongan = $stmt->fetchColumn();

// Menghitung total lamaran
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM applications
");
$stmt->execute();
$total_lamaran = $stmt->fetchColumn();

// Mengambil perusahaan terbaru yang menunggu verifikasi
$stmt = $pdo->prepare("
    SELECT company_name, industry, verification_status
    FROM company_profiles
    WHERE verification_status = 'pending'
    ORDER BY user_id DESC
    LIMIT 5
");
$stmt->execute();
$daftar_perusahaan = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard Admin - CareerMap</title>

    <link rel="stylesheet" href="../assets/admin.css">
</head>
<body>

<div class="admin-layout">

    <!-- SIDEBAR -->
   <?php include "../includes/admin-sidebar.php"; ?>


    <!-- MAIN CONTENT -->
    <main class="main-content">

        <header class="page-header">
            <div>
                <h1>Dashboard Admin</h1>
                <p>Kelola dan pantau aktivitas platform CareerMap UAJY.</p>
            </div>
        </header>


        <!-- STATISTIK -->
        <section class="stats-grid">

            <div class="stat-card">
                <div class="stat-icon blue">♙</div>
                <div>
                    <p>Total Mahasiswa</p>
                    <h2><?= number_format($total_mahasiswa) ?></h2>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon purple">▣</div>
                <div>
                    <p>Total Perusahaan</p>
                    <h2><?= number_format($total_perusahaan) ?></h2>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon yellow">✓</div>
                <div>
                    <p>Menunggu Verifikasi</p>
                    <h2><?= number_format($pending_perusahaan) ?></h2>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon green">▤</div>
                <div>
                    <p>Total Lowongan</p>
                    <h2><?= number_format($total_lowongan) ?></h2>
                </div>
            </div>

        </section>


        <!-- AKTIVITAS ADMIN -->
        <section class="content-card">

            <div class="card-heading">
                <div>
                    <h2>Perlu Tindakan</h2>
                    <p>Aktivitas yang membutuhkan perhatian administrator.</p>
                </div>
            </div>

            <div class="action-list">

                <div class="action-item">
                    <div class="action-info">
                        <div class="action-icon yellow">✓</div>
                        <div>
                            <h3>Verifikasi Perusahaan</h3>
                            <p>Perusahaan menunggu pemeriksaan dokumen.</p>
                        </div>
                    </div>

                    <div class="action-right">
                        <strong><?= number_format($pending_perusahaan) ?></strong>
                        <a href="verifikasi-perusahaan.php">Lihat →</a>
                    </div>
                </div>

                <div class="action-item">
                    <div class="action-info">
                        <div class="action-icon blue">▤</div>
                        <div>
                            <h3>Aktivitas Lowongan</h3>
                            <p>Total lowongan yang terdaftar di CareerMap.</p>
                        </div>
                    </div>

                    <div class="action-right">
                        <strong><?= number_format($total_lowongan) ?></strong>
                        <a href="lowongan.php">Lihat →</a>
                    </div>
                </div>

                <div class="action-item">
                    <div class="action-info">
                        <div class="action-icon green">♙</div>
                        <div>
                            <h3>Total Lamaran</h3>
                            <p>Lamaran pekerjaan yang masuk ke platform.</p>
                        </div>
                    </div>

                    <div class="action-right">
                        <strong><?= number_format($total_lamaran) ?></strong>
                    </div>
                </div>

            </div>

        </section>


        <!-- PERUSAHAAN MENUNGGU VERIFIKASI -->
        <section class="content-card">

            <div class="card-heading">
                <div>
                    <h2>Perusahaan Menunggu Verifikasi</h2>
                    <p>Daftar perusahaan yang perlu diperiksa.</p>
                </div>

                <a href="verifikasi-perusahaan.php" class="text-link">
                    Lihat Semua →
                </a>
            </div>

            <?php if (count($daftar_perusahaan) > 0): ?>

                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Nama Perusahaan</th>
                                <th>Industri</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($daftar_perusahaan as $perusahaan): ?>
                                <tr>
                                    <td>
                                        <strong>
                                            <?= htmlspecialchars($perusahaan["company_name"]) ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($perusahaan["industry"] ?? "-") ?>
                                    </td>

                                    <td>
                                        <span class="status-pending">
                                            Menunggu
                                        </span>
                                    </td>

                                    <td>
                                        <a href="verifikasi-perusahaan.php" class="table-link">
                                            Periksa →
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php else: ?>

                <div class="empty-state">
                    <div>✓</div>
                    <h3>Tidak Ada Verifikasi Tertunda</h3>
                    <p>Belum ada perusahaan yang menunggu verifikasi.</p>
                </div>

            <?php endif; ?>

        </section>

    </main>

</div>

</body>
</html>

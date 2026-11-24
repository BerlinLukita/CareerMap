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

// Mengambil daftar perusahaan
$sql = "
    SELECT
        cp.user_id,
        cp.company_name,
        cp.industry,
        cp.address,
        cp.verification_status,
        u.email
    FROM company_profiles cp
    JOIN users u ON cp.user_id = u.id
    ORDER BY
        CASE
            WHEN cp.verification_status = 'pending' THEN 1
            ELSE 2
        END,
        cp.company_name ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$perusahaan = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Menghitung jumlah berdasarkan status
$total_pending = 0;
$total_verified = 0;
$total_rejected = 0;

foreach ($perusahaan as $item) {
    if ($item["verification_status"] === "pending") {
        $total_pending++;
    } elseif ($item["verification_status"] === "verified") {
        $total_verified++;
    } elseif ($item["verification_status"] === "rejected") {
        $total_rejected++;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Verifikasi Perusahaan - CareerMap</title>

    <link rel="stylesheet" href="../assets/admin.css">
</head>
<body>

<div class="admin-layout">

    <!-- SIDEBAR -->
    <?php include "../includes/admin-sidebar.php"; ?>


    <!-- KONTEN UTAMA -->
    <main class="main-content">

        <header class="page-header">
            <div>
                <h1>Verifikasi Perusahaan</h1>
                <p>
                    Periksa dan kelola permohonan verifikasi
                    perusahaan yang terdaftar di CareerMap.
                </p>
            </div>
        </header>


        <!-- RINGKASAN STATUS -->
        <section class="stats-grid">

            <div class="stat-card">
                <div class="stat-icon yellow">◷</div>
                <div>
                    <p>Menunggu Verifikasi</p>
                    <h2><?= $total_pending ?></h2>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon green">✓</div>
                <div>
                    <p>Terverifikasi</p>
                    <h2><?= $total_verified ?></h2>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon purple">×</div>
                <div>
                    <p>Ditolak</p>
                    <h2><?= $total_rejected ?></h2>
                </div>
            </div>

        </section>


        <!-- TABEL PERUSAHAAN -->
        <section class="content-card">

            <div class="card-heading">
                <div>
                    <h2>Daftar Perusahaan</h2>
                    <p>
                        Total <?= count($perusahaan) ?> perusahaan terdaftar.
                    </p>
                </div>
            </div>

            <?php if (count($perusahaan) > 0): ?>

                <div class="table-wrapper">

                    <table>
                        <thead>
                            <tr>
                                <th>Perusahaan</th>
                                <th>Industri</th>
                                <th>Lokasi</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($perusahaan as $item): ?>

                                <tr>
                                    <td>
                                        <strong>
                                            <?= htmlspecialchars($item["company_name"]) ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($item["industry"] ?? "-") ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($item["address"] ?? "-") ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($item["email"]) ?>
                                    </td>

                                    <td>
                                        <?php if ($item["verification_status"] === "pending"): ?>
                                            <span class="status-pending">Menunggu</span>

                                        <?php elseif ($item["verification_status"] === "verified"): ?>
                                            <span class="status-verified">Terverifikasi</span>

                                        <?php else: ?>
                                            <span class="status-rejected">Ditolak</span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <a
                                            href="detail-perusahaan.php?id=<?= $item["user_id"] ?>"
                                            class="table-link"
                                        >
                                            Detail →
                                        </a>
                                    </td>
                                </tr>

                            <?php endforeach; ?>

                        </tbody>
                    </table>

                </div>

            <?php else: ?>

                <div class="empty-state">
                    <div>▤</div>
                    <h3>Belum Ada Perusahaan</h3>
                    <p>
                        Belum ada perusahaan yang terdaftar di CareerMap.
                    </p>
                </div>

            <?php endif; ?>

        </section>

    </main>

</div>

</body>
</html>

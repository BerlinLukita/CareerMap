
<?php
session_start();

if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: ../auth/login.php");
    exit;
}

require_once "../config/database.php";

// Pencarian lowongan
$keyword = trim($_GET["keyword"] ?? "");

$sql = "
    SELECT
        j.id,
        j.title,
        j.department,
        j.job_type,
        j.location,
        j.status,
        cp.company_name
    FROM job_vacancies j
    JOIN company_profiles cp ON j.company_id = cp.user_id
    WHERE j.status != 'draft'
";

$params = [];

if ($keyword !== "") {
    $sql .= "
        AND (
            j.title LIKE :keyword
            OR cp.company_name LIKE :keyword
        )
    ";

    $params["keyword"] = "%" . $keyword . "%";
}

$sql .= " ORDER BY j.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$lowongan = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_lowongan = count($lowongan);

function e($value) {
    return htmlspecialchars((string) ($value ?? "-"), ENT_QUOTES, "UTF-8");
}

function statusLabel($status) {
    $labels = [
        "published" => "Aktif",
        "disabled" => "Nonaktif",
        "closed" => "Ditutup"
    ];

    return $labels[$status] ?? ucfirst($status);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manajemen Lowongan - CareerMap</title>

    <link rel="stylesheet" href="../assets/admin.css">
</head>

<body>

<div class="admin-layout">

    <!-- SIDEBAR -->
    <?php include "../includes/admin-sidebar.php"; ?>


    <!-- MAIN CONTENT -->
    <main class="main-content">

        <header class="page-header">
            <h1>Manajemen Lowongan</h1>

            <p>
                Pantau dan moderasi semua lowongan yang dipublikasikan perusahaan.
            </p>
        </header>


        <section class="content-card">

            <div class="card-heading">
                <div>
                    <h2>Daftar Semua Lowongan</h2>

                    <p>
                        Kelola lowongan yang aktif, ditinjau, atau dilaporkan.
                    </p>
                </div>

                <strong><?= $total_lowongan ?> Lowongan</strong>
            </div>


            <!-- PENCARIAN -->
            <form method="GET" class="search-form">
                <input
                    type="text"
                    name="keyword"
                    placeholder="Cari posisi atau nama perusahaan..."
                    value="<?= e($keyword) ?>"
                >

                <button type="submit">Cari</button>

                <?php if ($keyword !== ""): ?>
                    <a href="lowongan.php">Reset</a>
                <?php endif; ?>
            </form>


            <!-- TABEL LOWONGAN -->
            <div class="table-wrapper">

                <table>
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Posisi</th>
                            <th>Perusahaan</th>
                            <th>Tipe</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if ($total_lowongan > 0): ?>

                            <?php $no = 1; ?>

                            <?php foreach ($lowongan as $job): ?>

                                <tr>
                                    <td><?= $no++ ?></td>

                                    <td>
                                        <strong><?= e($job["title"]) ?></strong>
                                    </td>

                                    <td><?= e($job["company_name"]) ?></td>

                                    <td><?= e($job["job_type"]) ?></td>

                                    <td>
                                        <?php if ($job["status"] === "published"): ?>
                                            <span class="status-verified">Aktif</span>

                                        <?php elseif ($job["status"] === "disabled"): ?>
                                            <span class="status-rejected">Nonaktif</span>

                                        <?php else: ?>
                                            <span class="status-pending">Ditutup</span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <a
                                            href="detail-lowongan.php?id=<?= (int) $job["id"] ?>"
                                            class="table-link"
                                        >
                                            Detail
                                        </a>
                                    </td>
                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="6">
                                    <div class="empty-state">
                                        <h3>Lowongan tidak ditemukan</h3>

                                        <p>
                                            Belum ada lowongan yang sesuai dengan pencarian.
                                        </p>
                                    </div>
                                </td>
                            </tr>

                        <?php endif; ?>

                    </tbody>
                </table>

            </div>

        </section>

    </main>

</div>

</body>
</html>

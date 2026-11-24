<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

require_once "../config/database.php";

// Pengaturan pagination
$per_page = 5;
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$offset = ($page - 1) * $per_page;

// Pencarian dan filter
$keyword = trim($_GET['keyword'] ?? '');
$status = $_GET['status'] ?? '';

$where = [];
$params = [];

if ($keyword !== '') {
    $where[] = "(
        reporter.name LIKE :reporter
        OR reporter.email LIKE :email
        OR r.reason LIKE :reason
        OR r.description LIKE :description
        OR reported_user.name LIKE :reported_user
        OR reported_company.company_name LIKE :company
        OR j.title LIKE :job
    )";

    $search = "%$keyword%";

    $params = [
        'reporter' => $search,
        'email' => $search,
        'reason' => $search,
        'description' => $search,
        'reported_user' => $search,
        'company' => $search,
        'job' => $search
    ];
}

$allowed_status = ['new', 'in_review', 'completed', 'dismissed'];

if (in_array($status, $allowed_status, true)) {
    $where[] = "r.status = :status";
    $params['status'] = $status;
}

$where_sql = '';

if (!empty($where)) {
    $where_sql = "WHERE " . implode(" AND ", $where);
}

// Menghitung total laporan
$count_sql = "
    SELECT COUNT(*)
    FROM reports r
    LEFT JOIN users reporter
        ON reporter.id = r.reporter_id
    LEFT JOIN users reported_user
        ON reported_user.id = r.reported_user_id
    LEFT JOIN company_profiles reported_company
        ON reported_company.user_id = r.reported_company_id
    LEFT JOIN job_vacancies j
        ON j.id = r.reported_job_id
    $where_sql
";

$stmt_count = $pdo->prepare($count_sql);
$stmt_count->execute($params);

$total_reports = (int) $stmt_count->fetchColumn();
$total_pages = max(1, (int) ceil($total_reports / $per_page));

if ($page > $total_pages) {
    $page = $total_pages;
    $offset = ($page - 1) * $per_page;
}

// Mengambil data laporan
$sql = "
    SELECT
        r.id,
        r.reporter_id,
        r.reported_user_id,
        r.reported_company_id,
        r.reported_job_id,
        r.reason,
        r.description,
        r.status,
        r.created_at,
        reporter.name AS reporter_name,
        reporter.email AS reporter_email,
        reported_user.name AS reported_user_name,
        reported_company.company_name AS reported_company_name,
        j.title AS job_title

    FROM reports r

    LEFT JOIN users reporter
        ON reporter.id = r.reporter_id

    LEFT JOIN users reported_user
        ON reported_user.id = r.reported_user_id

    LEFT JOIN company_profiles reported_company
        ON reported_company.user_id = r.reported_company_id

    LEFT JOIN job_vacancies j
        ON j.id = r.reported_job_id

    $where_sql

    ORDER BY r.created_at DESC

    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($sql);

foreach ($params as $key => $value) {
    $stmt->bindValue(":$key", $value);
}

$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

$stmt->execute();

$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Menghitung jumlah laporan berdasarkan status
$status_count_stmt = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) AS new_total,
        SUM(CASE WHEN status = 'in_review' THEN 1 ELSE 0 END) AS review_total,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_total,
        SUM(CASE WHEN status = 'dismissed' THEN 1 ELSE 0 END) AS dismissed_total
    FROM reports
");

$status_counts = $status_count_stmt->fetch(PDO::FETCH_ASSOC);

// Label status
function statusLabel($status) {
    $labels = [
        'new' => 'Baru',
        'in_review' => 'Dalam Peninjauan',
        'completed' => 'Selesai',
        'dismissed' => 'Ditolak'
    ];

    return $labels[$status] ?? $status;
}

// Fungsi keamanan output
function e($value) {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manajemen Laporan - CareerMap</title>

    <link rel="stylesheet" href="../assets/admin.css">
</head>

<body>

<div class="admin-layout">

    <!-- SIDEBAR -->
    <?php include "../includes/admin-sidebar.php"; ?>


    <!-- MAIN CONTENT -->
    <main class="main-content">

        <div class="page-header">

            <div>
                <h1>Manajemen Laporan</h1>

                <p>
                    Lihat dan tinjau laporan yang dikirim pengguna CareerMap.
                </p>
            </div>

        </div>


        <!-- RINGKASAN STATUS -->
        <section class="content-card">

            <div class="card-heading">
                <h2>Ringkasan Laporan</h2>
            </div>

            <div class="company-detail-grid">

                <div>
                    <label>Total Laporan</label>
                    <h2><?= (int) $status_counts['total'] ?></h2>
                </div>

                <div>
                    <label>Laporan Baru</label>
                    <h2><?= (int) $status_counts['new_total'] ?></h2>
                </div>

                <div>
                    <label>Dalam Peninjauan</label>
                    <h2><?= (int) $status_counts['review_total'] ?></h2>
                </div>

                <div>
                    <label>Selesai</label>
                    <h2><?= (int) $status_counts['completed_total'] ?></h2>
                </div>

                <div>
                    <label>Ditolak</label>
                    <h2><?= (int) $status_counts['dismissed_total'] ?></h2>
                </div>

            </div>

        </section>


        <!-- DAFTAR LAPORAN -->
        <section class="content-card">

            <div class="card-heading">

                <div>
                    <h2>Daftar Laporan</h2>

                    <p>
                        Total <?= number_format($total_reports, 0, ',', '.') ?>
                        laporan ditemukan.
                    </p>
                </div>

            </div>


            <!-- PENCARIAN DAN FILTER -->
            <form method="GET" class="filter-form">

                <input
                    type="text"
                    name="keyword"
                    placeholder="Cari pelapor, alasan, atau objek laporan..."
                    value="<?= e($keyword) ?>"
                >

                <select name="status">

                    <option value="">Semua Status</option>

                    <option value="new"
                        <?= $status === 'new' ? 'selected' : '' ?>>
                        Baru
                    </option>

                    <option value="in_review"
                        <?= $status === 'in_review' ? 'selected' : '' ?>>
                        Dalam Peninjauan
                    </option>

                    <option value="completed"
                        <?= $status === 'completed' ? 'selected' : '' ?>>
                        Selesai
                    </option>

                    <option value="dismissed"
                        <?= $status === 'dismissed' ? 'selected' : '' ?>>
                        Ditolak
                    </option>

                </select>

                <button type="submit" class="btn-approve">
                    Cari
                </button>

                <a href="laporan.php" class="table-link">
                    Reset
                </a>

            </form>


            <!-- TABEL LAPORAN -->
            <div class="table-wrapper">

                <table>

                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Pelapor</th>
                            <th>Objek Laporan</th>
                            <th>Alasan</th>
                            <th>Tanggal</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if (count($reports) > 0): ?>

                        <?php foreach ($reports as $report): ?>

                            <?php

                            if ($report['reported_user_id']) {
                                $object_name = $report['reported_user_name'] ?: 'Pengguna';
                                $object_type = 'Pengguna';

                            } elseif ($report['reported_company_id']) {
                                $object_name = $report['reported_company_name'] ?: 'Perusahaan';
                                $object_type = 'Perusahaan';

                            } elseif ($report['reported_job_id']) {
                                $object_name = $report['job_title'] ?: 'Lowongan';
                                $object_type = 'Lowongan';

                            } else {
                                $object_name = 'Tidak diketahui';
                                $object_type = '-';
                            }

                            $status_class = [
                                'new' => 'status-pending',
                                'in_review' => 'status-pending',
                                'completed' => 'status-verified',
                                'dismissed' => 'status-rejected'
                            ][$report['status']] ?? 'status-pending';

                            ?>

                            <tr>

                                <td>
                                    #<?= (int) $report['id'] ?>
                                </td>

                                <td>
                                    <strong>
                                        <?= e($report['reporter_name'] ?: 'Pengguna') ?>
                                    </strong>

                                    <br>

                                    <small>
                                        <?= e($report['reporter_email'] ?: '-') ?>
                                    </small>
                                </td>

                                <td>
                                    <strong>
                                        <?= e($object_name) ?>
                                    </strong>

                                    <br>

                                    <small>
                                        <?= e($object_type) ?>
                                    </small>
                                </td>

                                <td>
                                    <?= e($report['reason']) ?>
                                </td>

                                <td>
                                    <?= date('d/m/Y', strtotime($report['created_at'])) ?>
                                </td>

                                <td>
                                    <span class="<?= $status_class ?>">
                                        <?= e(statusLabel($report['status'])) ?>
                                    </span>
                                </td>

                                <td>
                                    <a
                                        href="detail-laporan.php?id=<?= (int) $report['id'] ?>"
                                        class="table-link"
                                    >
                                        Detail
                                    </a>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="7" style="text-align:center; padding:30px;">
                                Belum ada laporan yang ditemukan.
                            </td>
                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>


            <!-- PAGINATION -->
            <div class="pagination">

                <p>
                    Menampilkan

                    <?= $total_reports > 0 ? $offset + 1 : 0 ?>

                    -

                    <?= min($offset + $per_page, $total_reports) ?>

                    dari

                    <?= number_format($total_reports, 0, ',', '.') ?>

                    laporan
                </p>

                <div>

                    <?php if ($page > 1): ?>

                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                            Sebelumnya
                        </a>

                    <?php endif; ?>


                    <?php if ($page < $total_pages): ?>

                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                            Selanjutnya
                        </a>

                    <?php endif; ?>

                </div>

            </div>

        </section>

    </main>

</div>

</body>
</html>

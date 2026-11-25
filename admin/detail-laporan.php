
<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

require_once "../config/database.php";

// Mengambil ID laporan
$report_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($report_id <= 0) {
    header("Location: laporan.php");
    exit;
}

// Token keamanan form
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Memproses perubahan laporan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        die("Permintaan tidak valid.");
    }

    $new_status = $_POST['status'] ?? '';
    $admin_note = trim($_POST['admin_note'] ?? '');

    $allowed_status = ['in_review', 'completed', 'dismissed'];

    if (!in_array($new_status, $allowed_status, true)) {

        $_SESSION['message'] = "Status laporan tidak valid.";

    } else {

        $update = $pdo->prepare("
            UPDATE reports
            SET
                status = :status,
                admin_note = :admin_note,
                reviewed_by = :admin_id,
                reviewed_at = NOW()
            WHERE id = :id
        ");

        $update->execute([
            'status' => $new_status,
            'admin_note' => $admin_note !== '' ? $admin_note : null,
            'admin_id' => $_SESSION['user_id'],
            'id' => $report_id
        ]);

        $_SESSION['message'] = "Laporan berhasil diperbarui.";
    }

    header("Location: detail-laporan.php?id=" . $report_id);
    exit;
}

// Mengambil informasi laporan
$stmt = $pdo->prepare("
    SELECT
        r.*,

        reporter.name AS reporter_name,
        reporter.email AS reporter_email,

        reported_user.name AS reported_user_name,
        reported_user.email AS reported_user_email,

        reported_company.company_name,
        company_user.email AS company_email,

        j.title AS job_title,
        j.location AS job_location,
        j.status AS job_status

    FROM reports r

    LEFT JOIN users reporter
        ON reporter.id = r.reporter_id

    LEFT JOIN users reported_user
        ON reported_user.id = r.reported_user_id

    LEFT JOIN company_profiles reported_company
        ON reported_company.user_id = r.reported_company_id

    LEFT JOIN users company_user
        ON company_user.id = reported_company.user_id

    LEFT JOIN job_vacancies j
        ON j.id = r.reported_job_id

    WHERE r.id = :id
");

$stmt->execute(['id' => $report_id]);

$report = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$report) {
    header("Location: laporan.php");
    exit;
}

// Mengambil nama admin yang meninjau laporan
$reviewer_name = null;

if (!empty($report['reviewed_by'])) {

    $reviewer_stmt = $pdo->prepare("
        SELECT name
        FROM users
        WHERE id = :id
    ");

    $reviewer_stmt->execute([
        'id' => $report['reviewed_by']
    ]);

    $reviewer_name = $reviewer_stmt->fetchColumn();
}

// Menentukan objek laporan
if ($report['reported_user_id']) {

    $object_type = 'Pengguna';
    $object_name = $report['reported_user_name'] ?: 'Pengguna tidak ditemukan';
    $object_email = $report['reported_user_email'] ?: '-';

} elseif ($report['reported_company_id']) {

    $object_type = 'Perusahaan';
    $object_name = $report['company_name'] ?: 'Perusahaan tidak ditemukan';
    $object_email = $report['company_email'] ?: '-';

} elseif ($report['reported_job_id']) {

    $object_type = 'Lowongan';
    $object_name = $report['job_title'] ?: 'Lowongan tidak ditemukan';
    $object_email = '-';

} else {

    $object_type = 'Tidak diketahui';
    $object_name = '-';
    $object_email = '-';
}

// Label status
$status_labels = [
    'new' => 'Baru',
    'in_review' => 'Dalam Peninjauan',
    'completed' => 'Selesai',
    'dismissed' => 'Ditolak'
];

$status_label = $status_labels[$report['status']] ?? $report['status'];

$status_class = [
    'new' => 'status-pending',
    'in_review' => 'status-pending',
    'completed' => 'status-verified',
    'dismissed' => 'status-rejected'
][$report['status']] ?? 'status-pending';

// Fungsi keamanan output
function e($value) {
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Detail Laporan - CareerMap</title>

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
                <h1>Detail Laporan #<?= (int) $report['id'] ?></h1>

                <p>
                    Periksa informasi dan tentukan tindak lanjut laporan.
                </p>
            </div>

        </div>


        <!-- PESAN -->
        <?php if (!empty($_SESSION['message'])): ?>

            <section class="content-card">

                <p>
                    <?= e($_SESSION['message']) ?>
                </p>

            </section>

            <?php unset($_SESSION['message']); ?>

        <?php endif; ?>


        <!-- INFORMASI LAPORAN -->
        <section class="content-card">

            <div class="card-heading">

                <div>
                    <h2>Informasi Laporan</h2>
                    <p>
                        Laporan #<?= (int) $report['id'] ?>
                    </p>
                </div>

                <span class="<?= $status_class ?>">
                    <?= e($status_label) ?>
                </span>

            </div>

            <div class="company-detail-grid">

                <div>
                    <label>Tanggal Laporan</label>
                    <p>
                        <?= date('d F Y H:i', strtotime($report['created_at'])) ?>
                    </p>
                </div>

                <div>
                    <label>Alasan Laporan</label>
                    <p><?= e($report['reason']) ?></p>
                </div>

            </div>

            <div style="margin-top:20px;">

                <label>Deskripsi Laporan</label>

                <p style="margin-top:10px; line-height:1.7;">
                    <?= nl2br(e($report['description'])) ?>
                </p>

            </div>

        </section>


        <!-- INFORMASI PELAPOR -->
        <section class="content-card">

            <div class="card-heading">
                <h2>Informasi Pelapor</h2>
            </div>

            <div class="company-detail-grid">

                <div>
                    <label>Nama Pelapor</label>
                    <p><?= e($report['reporter_name'] ?: 'Pengguna tidak ditemukan') ?></p>
                </div>

                <div>
                    <label>Email Pelapor</label>
                    <p><?= e($report['reporter_email'] ?: '-') ?></p>
                </div>

                <div>
                    <label>ID Pelapor</label>
                    <p><?= (int) $report['reporter_id'] ?></p>
                </div>

            </div>

        </section>


        <!-- OBJEK YANG DILAPORKAN -->
        <section class="content-card">

            <div class="card-heading">
                <h2>Objek yang Dilaporkan</h2>
            </div>

            <div class="company-detail-grid">

                <div>
                    <label>Jenis Objek</label>
                    <p><?= e($object_type) ?></p>
                </div>

                <div>
                    <label>Nama Objek</label>
                    <p><?= e($object_name) ?></p>
                </div>

                <div>
                    <label>Email Objek</label>
                    <p><?= e($object_email) ?></p>
                </div>

                <?php if ($report['reported_job_id']): ?>

                    <div>
                        <label>Lokasi Lowongan</label>
                        <p><?= e($report['job_location'] ?: '-') ?></p>
                    </div>

                    <div>
                        <label>Status Lowongan</label>
                        <p><?= e($report['job_status'] ?: '-') ?></p>
                    </div>

                <?php endif; ?>

            </div>

        </section>


        <!-- TINDAKAN ADMIN -->
        <section class="content-card">

            <div class="card-heading">
                <h2>Tindakan Admin</h2>
            </div>

            <form method="POST">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e($_SESSION['csrf_token']) ?>"
                >

                <div style="margin-bottom:15px;">

                    <label for="status">
                        Status Laporan
                    </label>

                    <select
                        name="status"
                        id="status"
                        required
                        style="width:100%; padding:10px; margin-top:8px;"
                    >

                        <option value="in_review"
                            <?= $report['status'] === 'in_review' ? 'selected' : '' ?>>
                            Dalam Peninjauan
                        </option>

                        <option value="completed"
                            <?= $report['status'] === 'completed' ? 'selected' : '' ?>>
                            Selesai
                        </option>

                        <option value="dismissed"
                            <?= $report['status'] === 'dismissed' ? 'selected' : '' ?>>
                            Ditolak
                        </option>

                    </select>

                </div>


                <div style="margin-bottom:15px;">

                    <label for="admin_note">
                        Catatan Admin
                    </label>

                    <textarea
                        name="admin_note"
                        id="admin_note"
                        rows="4"
                        placeholder="Tuliskan hasil peninjauan atau alasan keputusan..."
                        style="width:100%; padding:10px; margin-top:8px;"
                    ><?= e($report['admin_note']) ?></textarea>

                </div>


                <button
                    type="submit"
                    class="btn-approve"
                >
                    Simpan Perubahan
                </button>

            </form>

        </section>


        <!-- INFORMASI PENINJAUAN -->
        <section class="content-card">

            <div class="card-heading">
                <h2>Informasi Peninjauan</h2>
            </div>

            <div class="company-detail-grid">

                <div>
                    <label>Ditinjau Oleh</label>
                    <p><?= e($reviewer_name ?: '-') ?></p>
                </div>

                <div>
                    <label>Waktu Peninjauan</label>
                    <p>
                        <?= $report['reviewed_at']
                            ? date('d F Y H:i', strtotime($report['reviewed_at']))
                            : '-' ?>
                    </p>
                </div>

            </div>

        </section>


        <!-- KEMBALI -->
        <div style="margin-top:20px;">

            <a href="laporan.php" class="table-link">
                Kembali ke Laporan
            </a>

        </div>

    </main>

</div>

</body>
</html>

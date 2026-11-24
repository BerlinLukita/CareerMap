<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

require_once "../config/database.php";

// Mengambil ID pengguna
$user_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($user_id <= 0) {
    header("Location: pengguna.php");
    exit;
}

// Token keamanan form
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Mengambil data pengguna
$stmt = $pdo->prepare("
    SELECT
        u.id,
        u.name,
        u.email,
        u.username,
        u.role,
        u.status,
        u.created_at,
        sp.nim,
        sp.study_program,
        sp.photo_path,
        cp.company_name,
        cp.verification_status
    FROM users u
    LEFT JOIN student_profiles sp ON sp.user_id = u.id
    LEFT JOIN company_profiles cp ON cp.user_id = u.id
    WHERE u.id = :id
");

$stmt->execute(['id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header("Location: pengguna.php");
    exit;
}

// Memproses aktivasi atau penonaktifan akun
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        die("Permintaan tidak valid.");
    }

    // Admin tidak boleh menonaktifkan akunnya sendiri
    if ((int) $user['id'] === (int) $_SESSION['user_id']) {
        $_SESSION['message'] = "Anda tidak dapat mengubah status akun sendiri.";
    } else {

        $new_status = $_POST['new_status'] ?? '';

        if (in_array($new_status, ['active', 'inactive'], true)) {

            $update = $pdo->prepare("
                UPDATE users
                SET status = :status
                WHERE id = :id
                AND role != 'admin'
            ");

            $update->execute([
                'status' => $new_status,
                'id' => $user_id
            ]);

            if ($update->rowCount() > 0) {
                $_SESSION['message'] = "Status akun berhasil diperbarui.";
            } else {
                $_SESSION['message'] = "Tidak ada perubahan status akun.";
            }

        } else {
            $_SESSION['message'] = "Status akun tidak valid.";
        }
    }

    header("Location: detail-pengguna.php?id=" . $user_id);
    exit;
}

// Menghitung aktivitas mahasiswa
$total_applications = 0;
$total_saved_jobs = 0;

if ($user['role'] === 'student') {

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM applications
        WHERE student_id = :id
    ");

    $stmt->execute(['id' => $user_id]);
    $total_applications = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM saved_jobs
        WHERE student_id = :id
    ");

    $stmt->execute(['id' => $user_id]);
    $total_saved_jobs = (int) $stmt->fetchColumn();
}

// Menghitung laporan yang dibuat pengguna
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM reports
    WHERE reporter_id = :id
");

$stmt->execute(['id' => $user_id]);
$total_reports = (int) $stmt->fetchColumn();

// Label role
$role_labels = [
    'student' => 'Mahasiswa',
    'company' => 'Perusahaan',
    'admin' => 'Admin'
];

$role_label = $role_labels[$user['role']] ?? $user['role'];

// Label status
$status_labels = [
    'active' => 'Aktif',
    'inactive' => 'Nonaktif',
    'pending' => 'Menunggu'
];

$status_label = $status_labels[$user['status']] ?? $user['status'];

$status_class = [
    'active' => 'status-verified',
    'inactive' => 'status-rejected',
    'pending' => 'status-pending'
][$user['status']] ?? 'status-pending';

// Nama yang ditampilkan
$display_name = $user['role'] === 'company'
    ? ($user['company_name'] ?: $user['name'])
    : $user['name'];

function e($value) {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Detail Pengguna - CareerMap</title>

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
                <h1>Detail Pengguna</h1>

                <p>
                    Informasi akun dan aktivitas pengguna CareerMap.
                </p>
            </div>

        </div>


        <?php if (!empty($_SESSION['message'])): ?>

            <div class="content-card">
                <?= e($_SESSION['message']) ?>
            </div>

            <?php unset($_SESSION['message']); ?>

        <?php endif; ?>


        <!-- RINGKASAN PENGGUNA -->
        <section class="content-card">

            <div class="card-heading">

                <div>
                    <h2><?= e($display_name) ?></h2>

                    <p>
                        <?= e($role_label) ?> CareerMap
                    </p>
                </div>

                <span class="<?= $status_class ?>">
                    <?= e($status_label) ?>
                </span>

            </div>

            <p>
                Terdaftar sejak:
                <strong>
                    <?= date('d F Y', strtotime($user['created_at'])) ?>
                </strong>
            </p>

        </section>


        <!-- INFORMASI AKUN -->
        <section class="content-card">

            <div class="card-heading">
                <h2>Informasi Akun</h2>
            </div>

            <div class="company-detail-grid">

                <div>
                    <label>Nama Lengkap / Nama Perusahaan</label>
                    <p><?= e($display_name) ?></p>
                </div>

                <div>
                    <label>Email</label>
                    <p><?= e($user['email']) ?></p>
                </div>

                <div>
                    <label>Username</label>
                    <p><?= e($user['username']) ?></p>
                </div>

                <div>
                    <label>Role</label>
                    <p><?= e($role_label) ?></p>
                </div>


                <?php if ($user['role'] === 'student'): ?>

                    <div>
                        <label>NIM</label>
                        <p><?= e($user['nim'] ?: '-') ?></p>
                    </div>

                    <div>
                        <label>Program Studi</label>
                        <p><?= e($user['study_program'] ?: '-') ?></p>
                    </div>

                <?php endif; ?>


                <?php if ($user['role'] === 'company'): ?>

                    <div>
                        <label>Status Verifikasi Perusahaan</label>
                        <p>
                            <?= e($user['verification_status'] ?: '-') ?>
                        </p>
                    </div>

                <?php endif; ?>

            </div>

        </section>


        <!-- AKTIVITAS PENGGUNA -->
        <section class="content-card">

            <div class="card-heading">
                <h2>Aktivitas Pengguna</h2>
            </div>

            <div class="company-detail-grid">

                <?php if ($user['role'] === 'student'): ?>

                    <div>
                        <label>Lamaran Dikirim</label>
                        <h2><?= $total_applications ?></h2>
                    </div>

                    <div>
                        <label>Lowongan Disimpan</label>
                        <h2><?= $total_saved_jobs ?></h2>
                    </div>

                <?php endif; ?>

                <div>
                    <label>Laporan Dibuat</label>
                    <h2><?= $total_reports ?></h2>
                </div>

            </div>

        </section>


        <!-- TINDAKAN ADMIN -->
        <section class="content-card">

            <div class="card-heading">
                <h2>Tindakan Admin</h2>
            </div>

            <?php if ($user['role'] === 'admin'): ?>

                <p>
                    Akun administrator tidak dapat dinonaktifkan melalui halaman ini.
                </p>

            <?php elseif ($user['status'] === 'active'): ?>

                <p>
                    Akun ini sedang aktif dan dapat menggunakan CareerMap.
                </p>

                <form method="POST"
                    onsubmit="return confirm('Apakah kamu yakin ingin menonaktifkan akun ini?');">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e($_SESSION['csrf_token']) ?>"
                    >

                    <input
                        type="hidden"
                        name="new_status"
                        value="inactive"
                    >

                    <button type="submit" class="btn-reject">
                        Nonaktifkan Akun
                    </button>

                </form>

            <?php else: ?>

                <p>
                    Akun ini sedang nonaktif atau menunggu aktivasi.
                </p>

                <form method="POST"
                    onsubmit="return confirm('Apakah kamu yakin ingin mengaktifkan akun ini?');">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e($_SESSION['csrf_token']) ?>"
                    >

                    <input
                        type="hidden"
                        name="new_status"
                        value="active"
                    >

                    <button type="submit" class="btn-approve">
                        Aktifkan Akun
                    </button>

                </form>

            <?php endif; ?>

        </section>


        <!-- KEMBALI -->
        <div style="margin-top: 20px;">
            <a href="pengguna.php" class="table-link">
                Kembali ke Pengguna
            </a>
        </div>

    </main>

</div>

</body>
</html>

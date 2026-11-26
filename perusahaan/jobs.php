
<?php
session_start();
require_once '../config/database.php';

// ========================================
// CEK LOGIN DAN ROLE
// ========================================

if (empty($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$company_id = (int) $_SESSION['user_id'];

try {
    $roleStmt = $pdo->prepare("
        SELECT role
        FROM users
        WHERE id = :id
        LIMIT 1
    ");

    $roleStmt->execute([':id' => $company_id]);

    if ($roleStmt->fetchColumn() !== 'company') {
        header("Location: ../auth/login.php");
        exit;
    }
} catch (PDOException $e) {
    die("Gagal memeriksa akses pengguna.");
}


// ========================================
// VARIABEL
// ========================================

$error = '';

$success = isset($_GET['created'])
    ? 'Lowongan berhasil dipublikasikan.'
    : '';

$is_form_mode = (
    isset($_GET['action']) &&
    $_GET['action'] === 'create'
);

$edit_job = null;


// ========================================
// AMBIL DATA LOWONGAN UNTUK EDIT
// ========================================

if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {

    $edit_id = (int) $_GET['edit'];

    try {
        $stmt = $pdo->prepare("
            SELECT *
            FROM job_vacancies
            WHERE id = :id
            AND company_id = :cid
            LIMIT 1
        ");

        $stmt->execute([
            ':id' => $edit_id,
            ':cid' => $company_id
        ]);

        $edit_job = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($edit_job) {
            $is_form_mode = true;
        }

    } catch (Throwable $e) {
        $error = 'Gagal memuat data lowongan: ' . $e->getMessage();
    }
}


// ========================================
// PROSES TAMBAH DAN EDIT LOWONGAN
// ========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $job_id = !empty($_POST['job_id'])
        ? (int) $_POST['job_id']
        : 0;

    $title = trim($_POST['title'] ?? '');

    $department = trim($_POST['department'] ?? '');

    $job_type = trim($_POST['job_type'] ?? '');

    $location = trim($_POST['location'] ?? '');

    $deadline = trim($_POST['deadline'] ?? '');

    $desc_qual = trim(
        $_POST['description_qualifications'] ?? ''
    );


    // Validasi tipe pekerjaan
    $allowed_types = ['Magang', 'Full-time'];

    if (!in_array($job_type, $allowed_types, true)) {
        $job_type = 'Magang';
    }


    // Konversi tanggal
    $deadline_sql = null;

    if ($deadline !== '') {

        if (
            preg_match(
                '/^(\d{2})\/(\d{2})\/(\d{4})$/',
                $deadline,
                $m
            )
        ) {
            $deadline_sql = $m[3] . '-' . $m[2] . '-' . $m[1];

        } elseif (
            preg_match('/^\d{4}-\d{2}-\d{2}$/', $deadline)
        ) {
            $deadline_sql = $deadline;
        } else {
            $error = 'Format tanggal harus DD/MM/YYYY.';
            $is_form_mode = true;
        }
    }


    // Validasi input
    if ($title === '' || $location === '' || $desc_qual === '') {

        $error = 'Judul lowongan, lokasi, dan deskripsi & kualifikasi wajib diisi.';

        $is_form_mode = true;

    } elseif ($error === '') {

        try {

            // Ambil kategori pertama yang tersedia
            $catStmt = $pdo->query("
                SELECT id
                FROM job_categories
                ORDER BY id ASC
                LIMIT 1
            ");

            $catRow = $catStmt->fetch(PDO::FETCH_ASSOC);

            if (!$catRow) {
                throw new Exception(
                    'Kategori lowongan belum tersedia. Hubungi administrator.'
                );
            }

            $category_id = (int) $catRow['id'];


            // ========================================
            // UPDATE LOWONGAN
            // ========================================

            if ($job_id > 0) {

                $stmt = $pdo->prepare("
                    UPDATE job_vacancies
                    SET
                        title = :title,
                        department = :department,
                        job_type = :job_type,
                        location = :location,
                        deadline = :deadline,
                        description = :description,
                        qualifications = :qualifications
                    WHERE id = :id
                    AND company_id = :cid
                ");

                $stmt->execute([

                    ':title' => $title,

                    ':department' => $department !== ''
                        ? $department
                        : null,

                    ':job_type' => $job_type,

                    ':location' => $location,

                    ':deadline' => $deadline_sql,

                    ':description' => $desc_qual,

                    ':qualifications' => $desc_qual,

                    ':id' => $job_id,

                    ':cid' => $company_id

                ]);

                if ($stmt->rowCount() === 0) {
                    $check = $pdo->prepare("
                        SELECT id
                        FROM job_vacancies
                        WHERE id = :id
                        AND company_id = :cid
                    ");

                    $check->execute([
                        ':id' => $job_id,
                        ':cid' => $company_id
                    ]);

                    if (!$check->fetchColumn()) {
                        throw new Exception(
                            'Lowongan tidak ditemukan atau bukan milik perusahaan ini.'
                        );
                    }
                }

                header("Location: jobs.php?updated=1");
                exit;

            } else {

                // ========================================
                // INSERT LOWONGAN BARU
                // ========================================

                $stmt = $pdo->prepare("
                    INSERT INTO job_vacancies
                    (
                        company_id,
                        category_id,
                        title,
                        department,
                        job_type,
                        location,
                        deadline,
                        description,
                        qualifications,
                        status,
                        published_at
                    )
                    VALUES
                    (
                        :company_id,
                        :category_id,
                        :title,
                        :department,
                        :job_type,
                        :location,
                        :deadline,
                        :description,
                        :qualifications,
                        'published',
                        NOW()
                    )
                ");

                $stmt->execute([

                    ':company_id' => $company_id,

                    ':category_id' => $category_id,

                    ':title' => $title,

                    ':department' => $department !== ''
                        ? $department
                        : null,

                    ':job_type' => $job_type,

                    ':location' => $location,

                    ':deadline' => $deadline_sql,

                    ':description' => $desc_qual,

                    ':qualifications' => $desc_qual

                ]);

                header("Location: jobs.php?created=1");
                exit;
            }

        } catch (Throwable $e) {

            $error = 'Gagal menyimpan lowongan: ' . $e->getMessage();

            $is_form_mode = true;
        }
    }
}


// ========================================
// PESAN BERHASIL
// ========================================

if (isset($_GET['updated'])) {
    $success = 'Lowongan berhasil diperbarui.';
}


// ========================================
// AMBIL DAFTAR LOWONGAN
// ========================================

$jobs = [];

if (!$is_form_mode) {

    try {

        $stmt = $pdo->prepare("
            SELECT
                j.*,
                COUNT(a.id) AS total_applicants
            FROM job_vacancies j
            LEFT JOIN applications a
                ON a.job_id = j.id
            WHERE j.company_id = :cid
            GROUP BY j.id
            ORDER BY j.created_at DESC
        ");

        $stmt->execute([
            ':cid' => $company_id
        ]);

        $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (Throwable $e) {

        $error = 'Gagal memuat daftar lowongan: ' . $e->getMessage();
    }
}


// ========================================
// FORMAT TANGGAL
// ========================================

$deadline_display = '';

if (!empty($edit_job['deadline'])) {

    $dt = DateTime::createFromFormat(
        'Y-m-d',
        $edit_job['deadline']
    );

    $deadline_display = $dt
        ? $dt->format('d/m/Y')
        : $edit_job['deadline'];
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        <?= $is_form_mode
            ? ($edit_job ? 'Edit Lowongan' : 'Tambah Lowongan')
            : 'Kelola Lowongan'
        ?> - CareerMap
    </title>

    <link
        rel="stylesheet"
        href="../assets/css/kelola-lowongan.css?v=2"
    >

    <link
        rel="stylesheet"
        href="../assets/css/company-sidebar.css?v=1"
    >

</head>

<body>

    <!-- SIDEBAR PERUSAHAAN -->

    <?php require_once '../includes/company-sidebar.php'; ?>


    <!-- KONTEN UTAMA -->

    <main class="main-content">

        <?php if ($is_form_mode): ?>

            <!-- ==================================
                 FORM TAMBAH / EDIT LOWONGAN
                 ================================== -->

            <div class="page-header">

                <h1 class="page-title">
                    <?= $edit_job
                        ? 'Edit Lowongan'
                        : 'Tambah Lowongan'
                    ?>
                </h1>

                <p class="page-subtitle">
                    Isi informasi lowongan secara lengkap dan jelas.
                </p>

            </div>


            <?php if ($error !== ''): ?>

                <div class="alert alert-error">
                    <?= htmlspecialchars($error) ?>
                </div>

            <?php endif; ?>


            <section class="form-card">

                <h2 class="section-title">
                    Informasi Lowongan
                </h2>


                <form
                    action="jobs.php<?= $edit_job
                        ? '?edit=' . (int) $edit_job['id']
                        : '?action=create'
                    ?>"
                    method="POST"
                >

                    <?php if ($edit_job): ?>

                        <input
                            type="hidden"
                            name="job_id"
                            value="<?= (int) $edit_job['id'] ?>"
                        >

                    <?php endif; ?>


                    <div class="form-grid">

                        <!-- Judul Lowongan -->

                        <div class="form-group">

                            <label
                                for="title"
                                class="form-label"
                            >
                                Judul Lowongan
                            </label>

                            <input
                                type="text"
                                id="title"
                                name="title"
                                class="form-control"
                                placeholder="Contoh: UI/UX Design Intern"
                                value="<?= htmlspecialchars(
                                    $_POST['title']
                                    ?? ($edit_job['title'] ?? '')
                                ) ?>"
                                required
                            >

                        </div>


                        <!-- Departemen -->

                        <div class="form-group">

                            <label
                                for="department"
                                class="form-label"
                            >
                                Departemen
                            </label>

                            <input
                                type="text"
                                id="department"
                                name="department"
                                class="form-control"
                                placeholder="Contoh: Product &amp; Design"
                                value="<?= htmlspecialchars(
                                    $_POST['department']
                                    ?? ($edit_job['department'] ?? '')
                                ) ?>"
                            >

                        </div>


                        <!-- Tipe Pekerjaan -->

                        <div class="form-group">

                            <label
                                for="job_type"
                                class="form-label"
                            >
                                Tipe Pekerjaan
                            </label>

                            <?php

                            $selectedType = $_POST['job_type']
                                ?? ($edit_job['job_type'] ?? '');

                            ?>

                            <select
                                id="job_type"
                                name="job_type"
                                class="form-control"
                                required
                            >

                                <option
                                    value=""
                                    disabled
                                    <?= $selectedType === ''
                                        ? 'selected'
                                        : ''
                                    ?>
                                >
                                    Pilih tipe pekerjaan
                                </option>

                                <option
                                    value="Magang"
                                    <?= $selectedType === 'Magang'
                                        ? 'selected'
                                        : ''
                                    ?>
                                >
                                    Magang
                                </option>

                                <option
                                    value="Full-time"
                                    <?= $selectedType === 'Full-time'
                                        ? 'selected'
                                        : ''
                                    ?>
                                >
                                    Full-time
                                </option>

                            </select>

                        </div>


                        <!-- Lokasi -->

                        <div class="form-group">

                            <label
                                for="location"
                                class="form-label"
                            >
                                Lokasi
                            </label>

                            <input
                                type="text"
                                id="location"
                                name="location"
                                class="form-control"
                                placeholder="Kota / Remote"
                                value="<?= htmlspecialchars(
                                    $_POST['location']
                                    ?? ($edit_job['location'] ?? '')
                                ) ?>"
                                required
                            >

                        </div>


                        <!-- Deadline -->

                        <div class="form-group">

                            <label
                                for="deadline"
                                class="form-label"
                            >
                                Batas Akhir Lamaran
                            </label>

                            <input
                                type="text"
                                id="deadline"
                                name="deadline"
                                class="form-control"
                                placeholder="DD/MM/YYYY"
                                value="<?= htmlspecialchars(
                                    $_POST['deadline']
                                    ?? $deadline_display
                                ) ?>"
                            >

                        </div>


                        <!-- Deskripsi dan Kualifikasi -->

                        <div class="form-group full">

                            <label
                                for="description_qualifications"
                                class="form-label"
                            >
                                Deskripsi &amp; Kualifikasi
                            </label>

                            <textarea
                                id="description_qualifications"
                                name="description_qualifications"
                                class="form-control"
                                placeholder="Jelaskan tanggung jawab, persyaratan, dan benefit..."
                                required
                            ><?= htmlspecialchars(
                                $_POST['description_qualifications']
                                ?? ($edit_job['description'] ?? '')
                            ) ?></textarea>

                        </div>

                    </div>


                    <!-- Tombol Form -->

                    <div class="form-footer">

                        <a
                            href="jobs.php"
                            class="btn-secondary"
                        >
                            Batal
                        </a>

                        <button
                            type="submit"
                            class="btn-publish"
                        >
                            <?= $edit_job
                                ? 'Simpan Perubahan'
                                : 'Publikasikan'
                            ?>
                        </button>

                    </div>

                </form>

            </section>


        <?php else: ?>


            <!-- ==================================
                 DAFTAR LOWONGAN
                 ================================== -->

            <div class="top-bar">

                <div>

                    <h1 class="page-title">
                        Kelola Lowongan
                    </h1>

                    <p class="page-subtitle">
                        Buat, edit, dan pantau lowongan perusahaan.
                    </p>

                </div>


                <a
                    href="jobs.php?action=create"
                    class="btn-primary"
                >
                    + Tambah Lowongan
                </a>

            </div>


            <?php if ($error !== ''): ?>

                <div class="alert alert-error">
                    <?= htmlspecialchars($error) ?>
                </div>

            <?php endif; ?>


            <?php if ($success !== ''): ?>

                <div class="alert alert-success">
                    <?= htmlspecialchars($success) ?>
                </div>

            <?php endif; ?>


            <section class="jobs-card">

                <h2 class="jobs-card-title">
                    Lowongan Saya
                </h2>


                <?php if (empty($jobs)): ?>

                    <div class="empty-state">

                        Belum ada lowongan yang dibuat.
                        Klik
                        <strong>+ Tambah Lowongan</strong>
                        untuk membuat lowongan pertama Anda.

                    </div>

                <?php else: ?>


                    <ul class="jobs-list">

                        <?php foreach ($jobs as $job): ?>

                            <?php

                            $st = $job['status'] ?? 'draft';

                            if ($st === 'published') {

                                $badgeClass = 'badge-aktif';
                                $badgeLabel = 'Aktif';

                            } elseif (
                                $st === 'closed' ||
                                $st === 'disabled'
                            ) {

                                $badgeClass = 'badge-ditutup';
                                $badgeLabel = 'Ditutup';

                            } else {

                                $badgeClass = 'badge-draft';
                                $badgeLabel = 'Draft';
                            }

                            ?>


                            <li class="job-row">

                                <div>

                                    <div class="job-title">

                                        <?= htmlspecialchars($job['title']) ?>

                                    </div>

                                    <div class="job-meta">

                                        <?= htmlspecialchars($job['job_type']) ?>

                                        &bull;

                                        <?= htmlspecialchars($job['location']) ?>

                                    </div>

                                </div>


                                <span class="job-badge <?= $badgeClass ?>">

                                    <?= htmlspecialchars($badgeLabel) ?>

                                </span>


                                <div class="applicant-count">

                                    <?= (int) ($job['total_applicants'] ?? 0) ?>

                                    pelamar

                                </div>


                                <div>

                                    <a
                                        href="jobs.php?edit=<?= (int) $job['id'] ?>"
                                        class="btn-edit"
                                    >
                                        Edit &#9998;
                                    </a>

                                </div>

                            </li>


                        <?php endforeach; ?>

                    </ul>


                <?php endif; ?>

            </section>


        <?php endif; ?>

    </main>

</body>
</html>


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

// Memeriksa ID lowongan
if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    header("Location: lowongan.php");
    exit;
}

$job_id = (int) $_GET["id"];
$pesan = "";
$error = "";

// Mengambil data lowongan dan perusahaan
$stmt = $pdo->prepare("
    SELECT
        j.*,
        cp.company_name,
        cp.verification_status
    FROM job_vacancies j
    JOIN company_profiles cp ON j.company_id = cp.user_id
    WHERE j.id = :id
");

$stmt->execute(["id" => $job_id]);
$job = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$job) {
    die("Data lowongan tidak ditemukan.");
}

// Proses moderasi
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $aksi = $_POST["aksi"] ?? "";

    if ($aksi === "nonaktifkan") {

        $catatan = trim($_POST["moderation_note"] ?? "");

        if ($job["status"] !== "published") {
            $error = "Lowongan ini tidak dapat dinonaktifkan.";
        } elseif ($catatan === "") {
            $error = "Catatan moderasi wajib diisi.";
        } else {

            $stmt = $pdo->prepare("
                UPDATE job_vacancies
                SET status = 'disabled',
                    moderation_note = :catatan,
                    moderated_by = :admin
                WHERE id = :id
                  AND status = 'published'
            ");

            $stmt->execute([
                "catatan" => $catatan,
                "admin" => $_SESSION["user_id"],
                "id" => $job_id
            ]);

            header("Location: detail-lowongan.php?id=" . $job_id . "&status=nonaktif");
            exit;
        }

    } elseif ($aksi === "aktifkan") {

        if (
            $job["status"] !== "disabled" ||
            $job["verification_status"] !== "verified"
        ) {
            $error = "Lowongan tidak dapat diaktifkan. Periksa status perusahaan.";
        } else {

            $stmt = $pdo->prepare("
                UPDATE job_vacancies
                SET status = 'published',
                    moderation_note = NULL,
                    moderated_by = :admin,
                    published_at = COALESCE(published_at, NOW())
                WHERE id = :id
                  AND status = 'disabled'
            ");

            $stmt->execute([
                "admin" => $_SESSION["user_id"],
                "id" => $job_id
            ]);

            header("Location: detail-lowongan.php?id=" . $job_id . "&status=aktif");
            exit;
        }
    }
}


// Mengambil laporan terkait lowongan
$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total_laporan,
        SUM(
            CASE
                WHEN status IN ('new', 'in_review') THEN 1
                ELSE 0
            END
        ) AS laporan_aktif
    FROM reports
    WHERE reported_job_id = :job_id
");

$stmt->execute(["job_id" => $job_id]);
$data_laporan = $stmt->fetch(PDO::FETCH_ASSOC);


// Pesan setelah proses moderasi
if (isset($_GET["status"])) {
    if ($_GET["status"] === "aktif") {
        $pesan = "Lowongan berhasil diaktifkan.";
    } elseif ($_GET["status"] === "nonaktif") {
        $pesan = "Lowongan berhasil dinonaktifkan.";
    }
}

// Mengambil ulang data terbaru
$stmt = $pdo->prepare("
    SELECT
        j.*,
        cp.company_name,
        cp.verification_status
    FROM job_vacancies j
    JOIN company_profiles cp ON j.company_id = cp.user_id
    WHERE j.id = :id
");

$stmt->execute(["id" => $job_id]);
$job = $stmt->fetch(PDO::FETCH_ASSOC);

function e($value) {
    return htmlspecialchars(
        (string) ($value ?? "-"),
        ENT_QUOTES,
        "UTF-8"
    );
}

function statusLabel($status) {
    $labels = [
        "published" => "Aktif",
        "disabled" => "Nonaktif",
        "closed" => "Ditutup",
        "draft" => "Draft"
    ];

    return $labels[$status] ?? ucfirst($status);
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Detail Lowongan - CareerMap</title>

    <link rel="stylesheet" href="../assets/admin.css">
</head>

<body>

<div class="admin-layout">

    <!-- SIDEBAR -->
    <?php include "../includes/admin-sidebar.php"; ?>


    <!-- MAIN CONTENT -->
    <main class="main-content">

        <header class="page-header">

            <a href="lowongan.php" class="table-link">
                ← Kembali ke Manajemen Lowongan
            </a>

            <h1>Detail Lowongan</h1>

            <p>
                Periksa informasi lowongan dan lakukan moderasi jika diperlukan.
            </p>

        </header>


        <?php if ($pesan !== ""): ?>
            <div class="content-card">
                <p style="color: #168653;">
                    <?= e($pesan) ?>
                </p>
            </div>
        <?php endif; ?>


        <?php if ($error !== ""): ?>
            <div class="alert-error">
                <?= e($error) ?>
            </div>
        <?php endif; ?>


        <!-- INFORMASI LOWONGAN -->
        
<!-- INFORMASI LOWONGAN -->
<section class="content-card">

    <div class="card-heading">
        <div>
            <h2><?= e($job["title"]) ?></h2>

            <p>
                <?= e($job["company_name"]) ?>
                • <?= e($job["job_type"]) ?>
                • <?= e($job["location"]) ?>
            </p>
        </div>

        <?php if ($job["status"] === "published"): ?>
            <span class="status-verified">Aktif</span>
        <?php elseif ($job["status"] === "disabled"): ?>
            <span class="status-rejected">Nonaktif</span>
        <?php else: ?>
            <span class="status-pending">
                <?= e(statusLabel($job["status"])) ?>
            </span>
        <?php endif; ?>
    </div>

    <div class="company-detail-grid">

        <div>
            <label>Perusahaan</label>
            <p><?= e($job["company_name"]) ?></p>
        </div>

        <div>
            <label>Departemen</label>
            <p><?= e($job["department"]) ?></p>
        </div>

        <div>
            <label>Tipe Pekerjaan</label>
            <p><?= e($job["job_type"]) ?></p>
        </div>

        <div>
            <label>Durasi</label>
            <p><?= e($job["duration"]) ?></p>
        </div>

        <div>
            <label>Lokasi</label>
            <p><?= e($job["location"]) ?></p>
        </div>

        <div>
            <label>Batas Pendaftaran</label>
            <p>
                <?= $job["deadline"]
                    ? date("d M Y", strtotime($job["deadline"]))
                    : "-" ?>
            </p>
        </div>

        <div class="full-width">
            <label>Deskripsi Lowongan</label>
            <p><?= nl2br(e($job["description"])) ?></p>
        </div>

        <div class="full-width">
            <label>Kualifikasi</label>
            <p><?= nl2br(e($job["qualifications"])) ?></p>
        </div>

    </div>

</section>


        <!-- TINDAKAN ADMIN -->
        <section class="content-card">

            <div class="card-heading">
                <div>
                    <h2>Tindakan Admin</h2>
                    <p>Kelola status publikasi lowongan ini.</p>
                </div>
            </div>


            <div class="verification-actions">

                <div>
                    <label>Status saat ini</label>

                    <strong>
                        <?= e(statusLabel($job["status"])) ?>
                    </strong>
                </div>


                <?php if ($job["status"] === "published"): ?>

                    <form method="POST" class="reject-form">

                        <label>Catatan moderasi</label>

                        <textarea
                            name="moderation_note"
                            placeholder="Tambahkan catatan..."
                            required
                        ></textarea>

                        <button
                            type="submit"
                            name="aksi"
                            value="nonaktifkan"
                            class="btn-reject"
                        >
                            Nonaktifkan
                        </button>

                    </form>

                <?php elseif ($job["status"] === "disabled"): ?>

                    <?php if ($job["verification_status"] === "verified"): ?>

                        <form method="POST">
                            <button
                                type="submit"
                                name="aksi"
                                value="aktifkan"
                                class="btn-approve"
                            >
                                Aktifkan
                            </button>
                        </form>

                    <?php else: ?>

                        <p class="alert-error">
                            Perusahaan belum terverifikasi. Lowongan belum dapat diaktifkan.
                        </p>

                    <?php endif; ?>

                <?php else: ?>

                    <p>
                        Tidak ada tindakan moderasi untuk status ini.
                    </p>

                <?php endif; ?>

            </div>

            <?php if (!empty($job["moderation_note"])): ?>
                <div style="margin-top: 25px;">
                    <label>Catatan moderasi sebelumnya</label>
                    <p><?= nl2br(e($job["moderation_note"])) ?></p>
                </div>
            <?php endif; ?>

        </section>


        <!-- INFORMASI MODERASI -->
        
<!-- INFORMASI MODERASI -->
<section class="content-card">

    <div class="card-heading">
        <h2>Informasi Moderasi</h2>
    </div>

    <div class="company-detail-grid">

        <div>
            <label>Dipublikasikan</label>
            <p>
                <?= $job["published_at"]
                    ? date("d M Y H:i", strtotime($job["published_at"]))
                    : "Belum dipublikasikan" ?>
            </p>
        </div>

        <div>
            <label>Dibuat</label>
            <p>
                <?= date("d M Y H:i", strtotime($job["created_at"])) ?>
            </p>
        </div>

        <div>
            <label>Laporan terkait</label>
            <p>
                <?= (int) $data_laporan["total_laporan"] ?>
                laporan pengguna
            </p>
        </div>

        <div>
            <label>Laporan belum selesai</label>
            <p>
                <?= (int) $data_laporan["laporan_aktif"] ?>
                laporan
            </p>
        </div>

    </div>

</section>


    </main>

</div>

</body>
</html>

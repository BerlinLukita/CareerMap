<?php
session_start();

require_once "../config/database.php";

if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "student"
) {
    header("Location: ../auth/login.php");
    exit;
}

$userId = (int) $_SESSION["user_id"];

function e($value)
{
    return htmlspecialchars(
        (string)($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

if (empty($_SESSION["csrf_saved_jobs"])) {
    $_SESSION["csrf_saved_jobs"] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION["csrf_saved_jobs"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = $_POST["action"] ?? "";

    if ($action === "delete_saved_job") {

        $postedToken = $_POST["csrf_token"] ?? "";
        $savedId = filter_input(INPUT_POST, "saved_id", FILTER_VALIDATE_INT);

        if (
            !is_string($postedToken) ||
            !hash_equals($csrfToken, $postedToken)
        ) {
            $_SESSION["saved_job_error"] = "Sesi tidak valid. Silakan coba kembali.";

        } elseif (!$savedId || $savedId <= 0) {

            $_SESSION["saved_job_error"] = "Data lowongan tidak valid.";

        } else {

            try {

                $deleteStmt = $pdo->prepare("
                    DELETE FROM saved_jobs
                    WHERE id = :saved_id
                    AND student_id = :student_id
                ");

                $deleteStmt->execute([
                    ":saved_id" => $savedId,
                    ":student_id" => $userId
                ]);

                if ($deleteStmt->rowCount() > 0) {
                    $_SESSION["saved_job_success"] =
                        "Lowongan berhasil dihapus dari daftar simpanan.";
                } else {
                    $_SESSION["saved_job_error"] =
                        "Lowongan tidak ditemukan atau sudah dihapus.";
                }

            } catch (PDOException $e) {

                error_log("Gagal menghapus lowongan tersimpan: " . $e->getMessage());

                $_SESSION["saved_job_error"] =
                    "Terjadi kesalahan saat menghapus lowongan.";
            }
        }

        header("Location: lowongan_disimpan.php");
        exit;
    }
}

$successMessage = $_SESSION["saved_job_success"] ?? "";
$errorMessage = $_SESSION["saved_job_error"] ?? "";

unset(
    $_SESSION["saved_job_success"],
    $_SESSION["saved_job_error"]
);

try {

    $savedStmt = $pdo->prepare("
        SELECT
            sj.id AS saved_id,
            sj.job_id,
            sj.saved_at,
            j.title,
            j.job_type,
            j.location,
            j.status,
            j.deadline,
            cp.company_name
        FROM saved_jobs sj
        INNER JOIN job_vacancies j
            ON sj.job_id = j.id
        LEFT JOIN company_profiles cp
            ON j.company_id = cp.user_id
        WHERE sj.student_id = :student_id
        ORDER BY sj.saved_at DESC
    ");

    $savedStmt->execute([
        ":student_id" => $userId
    ]);

    $savedJobs = $savedStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    error_log("Gagal mengambil lowongan tersimpan: " . $e->getMessage());

    $savedJobs = [];
    $errorMessage = "Data lowongan tersimpan gagal dimuat.";
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Lowongan Disimpan | CareerMap</title>

    <link rel="stylesheet" href="../assets/css/lowongan-disimpan.css">

</head>

<body>

<div class="dashboard-layout">

    <aside class="sidebar">
        <div class="brand">
            <img src="../assets/images/logo-uajy.jpg" alt="Logo UAJY">
            <span>CareerMap</span>
        </div>

        <div class="role-label">MAHASISWA</div>

        <nav class="navigation">
            <a href="dashboard.php" class="nav-link">Dashboard</a>
            <a href="cari_lowongan.php" class="nav-link">Cari Lowongan</a>
            <a href="lamaran_saya.php" class="nav-link">Lamaran Saya</a>
            <a href="lowongan_disimpan.php" class="nav-link active" aria-current="page">Lowongan Disimpan</a>
            <a href="profile.php" class="nav-link">Profil Saya</a>
        </nav>

        <div class="sidebar-footer">
            <a href="index.php">Bantuan &amp; Pusat Dukungan</a>
            <a href="../auth/logout.php" class="logout">Keluar</a>
        </div>
    </aside>

    <main class="main-content">

        <header class="page-header">

            <h1>Lowongan Disimpan</h1>

            <p>
                Kumpulan peluang yang ingin kamu tinjau kembali.
            </p>

        </header>

        <?php if ($successMessage !== ""): ?>

            <div class="alert alert-success">
                <?php echo e($successMessage); ?>
            </div>

        <?php endif; ?>

        <?php if ($errorMessage !== ""): ?>

            <div class="alert alert-error">
                <?php echo e($errorMessage); ?>
            </div>

        <?php endif; ?>

        <?php if (!empty($savedJobs)): ?>

            <section class="saved-jobs-grid">

                <?php foreach ($savedJobs as $job): ?>

                    <article class="saved-job-card">

                        <div class="job-card-top">

                            <div class="company-initial">

                                <?php
                                $company = $job["company_name"] ?: "Perusahaan";
                                echo e(mb_strtoupper(mb_substr($company, 0, 1)));
                                ?>

                            </div>

                            <div class="job-information">

                                <h2>
                                    <?php echo e($job["title"]); ?>
                                </h2>

                                <p>
                                    <?php echo e($company); ?>
                                    •
                                    <?php echo e($job["location"]); ?>
                                </p>

                            </div>

                            <button
                                type="button"
                                class="delete-button"
                                aria-label="Hapus dari simpanan"
                                title="Hapus dari simpanan"
                                onclick="openDeleteModal(
                                    <?php echo (int)$job['saved_id']; ?>,
                                    <?php echo htmlspecialchars(
                                        json_encode($job['title']),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>
                                )"
                            >

                                <svg
                                    viewBox="0 0 24 24"
                                    width="20"
                                    height="20"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    aria-hidden="true"
                                >
                                    <path d="M3 6h18"></path>
                                    <path d="M8 6V4h8v2"></path>
                                    <path d="M19 6l-1 14H6L5 6"></path>
                                    <path d="M10 11v5"></path>
                                    <path d="M14 11v5"></path>
                                </svg>

                            </button>

                        </div>

                        <div class="job-card-bottom">

                            <span class="saved-badge">
                                Disimpan
                            </span>

                            <a
                                href="detail_lowongan.php?id=<?php echo (int)$job['job_id']; ?>"
                                class="detail-button"
                            >
                                Lihat Detail
                            </a>

                        </div>

                    </article>

                <?php endforeach; ?>

            </section>

        <?php else: ?>

            <section class="empty-state">

                <div class="empty-icon">

                    <svg
                        viewBox="0 0 24 24"
                        width="38"
                        height="38"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.5"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path d="M6 4.5A2.5 2.5 0 0 1 8.5 2h7A2.5 2.5 0 0 1 18 4.5V22l-6-4-6 4V4.5Z"></path>
                    </svg>

                </div>

                <h2>Belum Ada Lowongan Disimpan</h2>

                <p>
                    Simpan lowongan yang menarik agar kamu bisa
                    melihatnya kembali dengan mudah.
                </p>

                <a href="cari_lowongan.php" class="browse-button">
                    Cari Lowongan
                </a>

            </section>

        <?php endif; ?>

    </main>

</div>

<div class="modal-overlay" id="deleteModal">

    <div class="delete-modal">

        <div class="modal-icon">

            <svg
                viewBox="0 0 24 24"
                width="29"
                height="29"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linecap="round"
                stroke-linejoin="round"
            >
                <path d="M3 6h18"></path>
                <path d="M8 6V4h8v2"></path>
                <path d="M19 6l-1 14H6L5 6"></path>
                <path d="M10 11v5"></path>
                <path d="M14 11v5"></path>
            </svg>

        </div>

        <h2>Hapus dari Simpanan?</h2>

        <p>
            Apakah kamu yakin ingin menghapus lowongan ini
            dari daftar lowongan tersimpan?
        </p>

        <div class="modal-actions">

            <button
                type="button"
                class="cancel-button"
                onclick="closeDeleteModal()"
            >
                Batal
            </button>

            <form method="POST" action="lowongan_disimpan.php">

                <input
                    type="hidden"
                    name="action"
                    value="delete_saved_job"
                >

                <input
                    type="hidden"
                    name="saved_id"
                    id="deleteSavedId"
                    value=""
                >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?php echo e($csrfToken); ?>"
                >

                <button type="submit" class="confirm-delete-button">
                    Ya, Hapus
                </button>

            </form>

        </div>

    </div>

</div>

<script>

function openDeleteModal(savedId, jobTitle) {

    document.getElementById("deleteSavedId").value = savedId;

    document.getElementById("deleteModal").classList.add("show");

    document.body.classList.add("modal-open");
}

function closeDeleteModal() {

    document.getElementById("deleteModal").classList.remove("show");

    document.body.classList.remove("modal-open");
}

document.getElementById("deleteModal").addEventListener("click", function(event) {

    if (event.target === this) {
        closeDeleteModal();
    }

});

document.addEventListener("keydown", function(event) {

    if (event.key === "Escape") {
        closeDeleteModal();
    }

});

</script>

</body>
</html>

<?php
session_start();

require_once "../config/database.php";

// ==========================================
// 1. CEK LOGIN MAHASISWA
// ==========================================

if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "student"
) {
    header("Location: ../auth/login.php");
    exit;
}

$userId = (int) $_SESSION["user_id"];

// ==========================================
// 2. FUNGSI PENGAMAN OUTPUT
// ==========================================

function e($value)
{
    return htmlspecialchars(
        (string)($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

// ==========================================
// 3. AMBIL ID LOWONGAN
// ==========================================

$jobId = 0;

if (isset($_GET["id"])) {
    $jobId = (int) $_GET["id"];
} elseif (isset($_POST["job_id"])) {
    $jobId = (int) $_POST["job_id"];
}

if ($jobId <= 0) {
    die("ID lowongan tidak valid.");
}

// ==========================================
// 4. AMBIL DATA LOWONGAN DAN PERUSAHAAN
// ==========================================

try {
    $jobStmt = $pdo->prepare("
        SELECT
            j.id,
            j.title,
            j.company_id,
            cp.company_name
        FROM job_vacancies j
        LEFT JOIN company_profiles cp
            ON cp.user_id = j.company_id
        WHERE j.id = :job_id
        LIMIT 1
    ");

    $jobStmt->execute([
        ":job_id" => $jobId
    ]);

    $job = $jobStmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log($e->getMessage());
    die("Terjadi kesalahan saat mengambil data lowongan.");
}

if (!$job) {
    die("Lowongan yang ingin dilaporkan tidak ditemukan.");
}

$companyName = $job["company_name"] ?: "Perusahaan";

// ==========================================
// 5. DAFTAR ALASAN LAPORAN
// ==========================================

$reasons = [
    "Informasi lowongan tidak sesuai",
    "Lowongan palsu atau penipuan",
    "Perusahaan mencurigakan",
    "Persyaratan tidak wajar",
    "Informasi perusahaan tidak jelas",
    "Lowongan sudah tidak tersedia",
    "Lainnya"
];

// ==========================================
// 6. TOKEN KEAMANAN FORM
// ==========================================

if (empty($_SESSION["csrf_report"])) {
    $_SESSION["csrf_report"] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION["csrf_report"];

// ==========================================
// 7. VARIABEL FORM
// ==========================================

$reason = "";
$description = "";
$errors = [];
$success = false;

// ==========================================
// 8. PROSES PENGIRIMAN LAPORAN
// ==========================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Validasi token keamanan
    $postedToken = $_POST["csrf_token"] ?? "";

    if (
        !is_string($postedToken) ||
        !hash_equals($csrfToken, $postedToken)
    ) {
        $errors[] = "Sesi formulir tidak valid. Silakan muat ulang halaman.";
    }

    // Ambil data form
    $reason = trim($_POST["reason"] ?? "");
    $description = trim($_POST["description"] ?? "");

    // Validasi alasan laporan
    if (!in_array($reason, $reasons, true)) {
        $errors[] = "Silakan pilih alasan laporan.";
    }

    // Validasi keterangan
    if ($description === "") {
        $errors[] = "Keterangan laporan wajib diisi.";
    } elseif (mb_strlen($description) < 10) {
        $errors[] = "Keterangan laporan minimal 10 karakter.";
    }

    // Validasi panjang alasan sesuai varchar(150)
    if (mb_strlen($reason) > 150) {
        $errors[] = "Alasan laporan terlalu panjang.";
    }

    // ======================================
    // 9. SIMPAN KE TABEL REPORTS
    // ======================================

    if (empty($errors)) {

        try {

            $insertStmt = $pdo->prepare("
                INSERT INTO reports (
                    reporter_id,
                    reported_company_id,
                    reported_job_id,
                    reason,
                    description
                ) VALUES (
                    :reporter_id,
                    :company_id,
                    :job_id,
                    :reason,
                    :description
                )
            ");

            $insertStmt->execute([
                ":reporter_id" => $userId,
                ":company_id" => (int) $job["company_id"],
                ":job_id" => (int) $job["id"],
                ":reason" => $reason,
                ":description" => $description
            ]);

            // Laporan berhasil disimpan
            $success = true;

            // Buat token baru
            $_SESSION["csrf_report"] = bin2hex(random_bytes(32));
            $csrfToken = $_SESSION["csrf_report"];

            // Kosongkan isian setelah berhasil
            $reason = "";
            $description = "";

        } catch (PDOException $e) {

            error_log("Gagal menyimpan laporan: " . $e->getMessage());

            $errors[] = "Laporan gagal dikirim. Silakan coba kembali.";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Laporkan Lowongan | CareerMap</title>

    <!-- CSS KHUSUS FORM LAPORAN -->
    <link rel="stylesheet" href="../assets/css/form-laporan.css">

</head>

<body>

<div class="layout">

    <!-- ==================================
         SIDEBAR
    =================================== -->

    <aside class="sidebar">

        <div class="brand">

            <img
                src="../assets/images/logo-uajy.png"
                alt="Logo CareerMap"
            >

            <span>CareerMap</span>

        </div>

        <div class="role-label">
            MAHASISWA
        </div>

        <nav class="sidebar-menu">

            <a href="dashboard.php" class="menu-item">
                <span>Dashboard</span>
            </a>

            <a href="cari_lowongan.php" class="menu-item">
                <span>Cari Lowongan</span>
            </a>

            <a href="lamaran_saya.php" class="menu-item">
                <span>Lamaran Saya</span>
            </a>

            <a href="profile.php" class="menu-item">
                <span>Profil Saya</span>
            </a>

        </nav>

        <div class="sidebar-bottom">

            <a href="../auth/logout.php" class="menu-item">
                <span>Keluar</span>
            </a>

        </div>

    </aside>


    <!-- ==================================
         KONTEN UTAMA
    =================================== -->

    <main class="main-content">

        <!-- HEADER -->

        <header class="page-header">

            <div>

                <h1>Laporkan Lowongan</h1>

                <p>
                    Bantu menjaga informasi lowongan di CareerMap
                    tetap terpercaya.
                </p>

            </div>

        </header>


        <!-- CONTAINER FORM -->

        <div class="report-container">

            <div class="report-card">

                <div class="report-card-header">

                    <h2>Formulir Laporan</h2>

                    <p>
                        Sampaikan laporan apabila menemukan
                        informasi lowongan yang mencurigakan.
                    </p>

                </div>


                <!-- ==================================
                     PESAN ERROR
                =================================== -->

                <?php if (!empty($errors)): ?>

                    <div class="error-message">

                        <?php foreach ($errors as $error): ?>

                            <p><?php echo e($error); ?></p>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>


                <!-- ==================================
                     PESAN BERHASIL
                =================================== -->

                <?php if ($success): ?>

                    <div class="success-message">

                        <h3>Laporan Berhasil Dikirim!</h3>

                        <p>
                            Terima kasih telah membantu menjaga
                            kualitas informasi CareerMap.
                        </p>

                        <p>
                            Laporan kamu sudah tersimpan dan akan
                            ditinjau oleh administrator.
                        </p>

                    </div>

                    <div class="form-actions">

                        <a
                            href="detail_lowongan.php?id=<?php echo (int)$job["id"]; ?>"
                            class="cancel-button"
                        >
                            Kembali ke Lowongan
                        </a>

                        <a
                            href="cari_lowongan.php"
                            class="submit-button"
                        >
                            Cari Lowongan
                        </a>

                    </div>


                <?php else: ?>


                    <!-- ==================================
                         FORM LAPORAN
                    =================================== -->

                    <form method="POST" action="">

                        <!-- TOKEN KEAMANAN -->

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?php echo e($csrfToken); ?>"
                        >

                        <!-- ID LOWONGAN -->

                        <input
                            type="hidden"
                            name="job_id"
                            value="<?php echo (int)$job["id"]; ?>"
                        >


                        <!-- LOWONGAN YANG DILAPORKAN -->

                        <div class="form-group">

                            <label for="job_title">
                                Lowongan yang Dilaporkan
                            </label>

                            <input
                                type="text"
                                id="job_title"
                                value="<?php
                                    echo e($job["title"] . " - " . $companyName);
                                ?>"
                                readonly
                            >

                        </div>


                        <!-- ALASAN LAPORAN -->

                        <div class="form-group">

                            <label for="reason">
                                Alasan Laporan
                            </label>

                            <select
                                name="reason"
                                id="reason"
                                required
                            >

                                <option value="">
                                    Pilih alasan laporan
                                </option>

                                <?php foreach ($reasons as $item): ?>

                                    <option
                                        value="<?php echo e($item); ?>"
                                        <?php
                                            echo ($reason === $item)
                                                ? "selected"
                                                : "";
                                        ?>
                                    >
                                        <?php echo e($item); ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <!-- KETERANGAN -->

                        <div class="form-group">

                            <label for="description">
                                Keterangan / Bukti Pendukung
                            </label>

                            <textarea
                                name="description"
                                id="description"
                                rows="6"
                                placeholder="Jelaskan alasan kamu melaporkan lowongan ini..."
                                required
                            ><?php echo e($description); ?></textarea>

                            <small>
                                Berikan keterangan secara jelas
                                agar laporan dapat ditinjau dengan baik.
                            </small>

                        </div>


                        <!-- INFORMASI PRIVASI -->

                        <div class="privacy-note">

                            <strong>Informasi Privasi</strong>

                            <p>
                                Identitas pelapor digunakan untuk
                                keperluan pemeriksaan laporan dan
                                tidak ditampilkan secara publik.
                            </p>

                        </div>


                        <!-- TOMBOL FORM -->

                        <div class="form-actions">

                            <a
                                href="detail_lowongan.php?id=<?php echo (int)$job["id"]; ?>"
                                class="cancel-button"
                            >
                                Batal
                            </a>

                            <button
                                type="submit"
                                class="submit-button"
                            >
                                Kirim Laporan
                            </button>

                        </div>

                    </form>

                <?php endif; ?>

            </div>

        </div>

    </main>

</div>

</body>
</html>

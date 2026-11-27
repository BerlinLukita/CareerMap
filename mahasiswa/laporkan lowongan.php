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

$jobId = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$jobId) {
    $jobId = filter_input(INPUT_POST, "job_id", FILTER_VALIDATE_INT);
}

if (!$jobId || $jobId <= 0) {
    exit("ID lowongan tidak valid.");
}

// ==========================================
// 4. AMBIL DATA LOWONGAN DARI DATABASE
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
    exit("Terjadi kesalahan saat mengambil data lowongan.");
}

if (!$job) {
    http_response_code(404);
    exit("Lowongan tidak ditemukan.");
}

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
// 6. TOKEN KEAMANAN
// ==========================================

if (empty($_SESSION["csrf_report"])) {
    $_SESSION["csrf_report"] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION["csrf_report"];

// ==========================================
// 7. VARIABEL FORM
// ==========================================

$error = "";
$success = false;

$reason = "";
$description = "";

// ==========================================
// 8. PROSES PENGIRIMAN FORM
// ==========================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $postedToken = $_POST["csrf_token"] ?? "";

    $reason = trim($_POST["reason"] ?? "");
    $description = trim($_POST["description"] ?? "");

    // Validasi token
    if (
        !is_string($postedToken) ||
        !hash_equals($csrfToken, $postedToken)
    ) {
        $error = "Sesi formulir tidak valid. Silakan muat ulang halaman.";
    }

    // Validasi alasan
    elseif (!in_array($reason, $reasons, true)) {
        $error = "Silakan pilih alasan laporan.";
    }

    // Validasi keterangan
    elseif ($description === "") {
        $error = "Keterangan laporan wajib diisi.";
    }

    elseif (mb_strlen($description) < 10) {
        $error = "Keterangan laporan minimal 10 karakter.";
    }

    else {

        // ==================================
        // 9. SIMPAN KE TABEL REPORTS
        // ==================================

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

            // Pastikan ada ID laporan yang berhasil dibuat
            if ($insertStmt->rowCount() > 0) {

                $success = true;

                // Perbarui token keamanan
                $_SESSION["csrf_report"] = bin2hex(random_bytes(32));
                $csrfToken = $_SESSION["csrf_report"];

                // Kosongkan formulir
                $reason = "";
                $description = "";

            } else {
                $error = "Laporan belum berhasil disimpan.";
            }

        } catch (PDOException $e) {

            error_log("Gagal menyimpan laporan: " . $e->getMessage());

            $error = "Laporan gagal disimpan ke database. Periksa koneksi dan struktur tabel.";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Laporkan Lowongan - CareerMap</title>

    <link rel="stylesheet" href="../assets/css/laporkan-lowongan.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@400;500;600;700&display=swap"
        rel="stylesheet"
    >

</head>

<body>

<div class="dashboard-layout">

    <!-- SIDEBAR -->

    <aside class="sidebar">

        <div class="sidebar-logo">

            <img
                src="../assets/images/logo-uajy.jpg"
                alt="CareerMap"
            >

            <span>CareerMap</span>

        </div>

        <div class="sidebar-label">
            MAHASISWA
        </div>

        <nav class="sidebar-menu">

            <a href="dashboard.php" class="menu-item">
                <span>▦</span>
                Dashboard
            </a>

            <a href="cari_lowongan.php" class="menu-item">
                <span>⌕</span>
                Cari Lowongan
            </a>

            <a href="lamaran_saya.php" class="menu-item">
                <span>▤</span>
                Lamaran Saya
            </a>

            <a href="lowongan_disimpan.php" class="menu-item">
                <span>♡</span>
                Lowongan Disimpan
            </a>

            <a href="profile.php" class="menu-item">
                <span>♙</span>
                Profil Saya
            </a>

        </nav>

        <div class="sidebar-bottom">

            <a href="index.php" class="menu-item">
    Bantuan & Pusat Dukungan
</a>

            <a href="../auth/logout.php" class="menu-item logout">
                <span>↪</span>
                Keluar
            </a>

        </div>

    </aside>


    <!-- MAIN CONTENT -->

    <main class="main-content">

        <header class="page-header">

            <h1>Laporkan Lowongan</h1>

            <p>
                Bantu menjaga informasi lowongan di CareerMap tetap terpercaya.
            </p>

        </header>


        <section class="report-container">

            <div class="report-card">

                <div class="report-card-header">

                    <h2>Laporkan Lowongan</h2>

                    <p>
                        Sampaikan laporan jika menemukan informasi lowongan
                        yang tidak sesuai atau mencurigakan.
                    </p>

                </div>


                <!-- PESAN ERROR -->

                <?php if ($error !== ""): ?>

                    <div class="error-message">
                        <?php echo e($error); ?>
                    </div>

                <?php endif; ?>


                <!-- PESAN BERHASIL -->

                <?php if ($success): ?>

                    <div class="success-message">

                        <strong>Laporan berhasil dikirim!</strong>

                        <p>
                            Data laporan kamu sudah berhasil disimpan
                            ke database CareerMap.
                        </p>

                        <p>
                            Laporan akan ditinjau oleh administrator.
                        </p>

                        <a href="detail_lowongan.php?id=<?php echo (int)$jobId; ?>">
                            Kembali ke Detail Lowongan
                        </a>

                    </div>

                <?php else: ?>


                    <!-- FORM LAPORAN -->

                    <form
                        method="POST"
                        action="laporkan_lowongan.php?id=<?php echo (int)$jobId; ?>"
                    >

                        <input
                            type="hidden"
                            name="job_id"
                            value="<?php echo (int)$jobId; ?>"
                        >

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?php echo e($csrfToken); ?>"
                        >


                        <!-- LOWONGAN -->

                        <div class="form-group">

                            <label for="job">
                                Lowongan yang Dilaporkan
                            </label>

                            <input
                                type="text"
                                id="job"
                                value="<?php
                                    echo e(
                                        $job["title"] . " • " .
                                        ($job["company_name"] ?? "Perusahaan")
                                    );
                                ?>"
                                readonly
                            >

                        </div>


                        <!-- ALASAN -->

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
                                        <?php echo ($reason === $item) ? "selected" : ""; ?>
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
                                rows="5"
                                placeholder="Jelaskan masalah yang kamu temukan secara rinci..."
                                required
                            ><?php echo e($description); ?></textarea>

                        </div>


                        <!-- PRIVASI -->

                        <div class="privacy-note">

                            Laporan akan ditinjau oleh Admin CareerMap.
                            Identitas pelapor tidak ditampilkan kepada perusahaan.

                        </div>


                        <!-- TOMBOL -->

                        <div class="form-actions">

                            <a
                                href="detail_lowongan.php?id=<?php echo (int)$jobId; ?>"
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

        </section>

    </main>

</div>

</body>
</html>
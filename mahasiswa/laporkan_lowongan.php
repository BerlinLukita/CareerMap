<?php
session_start();
$configPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . "config" . DIRECTORY_SEPARATOR . "database.php";
if (!is_file($configPath)) {
    http_response_code(500);
    exit("File konfigurasi database tidak ditemukan. Pastikan folder config berada satu tingkat di atas folder mahasiswa.");
}
require_once $configPath;

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

$rawJobId = $_GET["id"] ?? $_GET["job_id"] ??
            $_POST["job_id"] ?? $_POST["id"] ?? null;

$jobId = filter_var($rawJobId, FILTER_VALIDATE_INT);

if ($jobId === false || $jobId === null || $jobId <= 0) {
    http_response_code(400);
    exit("ID lowongan tidak valid. Silakan kembali ke halaman detail lowongan dan klik tombol Laporkan dari sana.");
}

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
    exit("Lowongan dengan ID " . (int)$jobId . " tidak ditemukan. Pastikan tombol Laporkan mengirim ID lowongan yang benar.");
}

$reasons = [
    "Informasi lowongan tidak sesuai",
    "Lowongan palsu atau penipuan",
    "Perusahaan mencurigakan",
    "Persyaratan tidak wajar",
    "Informasi perusahaan tidak jelas",
    "Lowongan sudah tidak tersedia",
    "Lainnya"
];

if (empty($_SESSION["csrf_report"])) {
    $_SESSION["csrf_report"] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION["csrf_report"];

$error = "";
$success = false;

$reason = "";
$description = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $postedToken = $_POST["csrf_token"] ?? "";

    $reason = trim($_POST["reason"] ?? "");
    $description = trim($_POST["description"] ?? "");
    if (
        !is_string($postedToken) ||
        !hash_equals($csrfToken, $postedToken)
    ) {
        $error = "Sesi formulir tidak valid. Silakan muat ulang halaman.";
    }
    elseif (!in_array($reason, $reasons, true)) {
        $error = "Silakan pilih alasan laporan.";
    }
    elseif ($description === "") {
        $error = "Keterangan laporan wajib diisi.";
    }

    elseif (mb_strlen($description) < 10) {
        $error = "Keterangan laporan minimal 10 karakter.";
    }

    else {

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
            if ($insertStmt->rowCount() > 0) {

                $success = true;
                $_SESSION["csrf_report"] = bin2hex(random_bytes(32));
                $csrfToken = $_SESSION["csrf_report"];
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

<div class="layout dashboard-layout">
    <aside class="sidebar">

        <div class="brand">
            <img src="../assets/images/logo-uajy.jpg"
                 alt="Logo Universitas Atma Jaya Yogyakarta">
            <span>CareerMap</span>
        </div>

        <div class="role-label">
            MAHASISWA
        </div>

        <nav class="navigation">

            <a href="dashboard.php" class="nav-link">
                Dashboard
            </a>

            <a href="cari_lowongan.php" class="nav-link">
                Cari Lowongan
            </a>

            <a href="lamaran_saya.php" class="nav-link">
                Lamaran Saya
            </a>

            <a href="lowongan_disimpan.php" class="nav-link">
                Lowongan Disimpan
            </a>

            <a href="profile.php" class="nav-link">
                Profil Saya
            </a>

        </nav>

        <div class="sidebar-footer">

            <a href="index.php" class="menu-item">
    Bantuan & Pusat Dukungan
</a>

            <a href="../auth/logout.php" class="logout">
                Keluar
            </a>

        </div>

    </aside>

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

                

                <?php if ($error !== ""): ?>

                    <div class="error-message">
                        <?php echo e($error); ?>
                    </div>

                <?php endif; ?>

                

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

                    

                    <form
                        method="POST"
                        action="laporkan_lowongan.php?id=<?php echo (int)$jobId; ?>&amp;job_id=<?php echo (int)$jobId; ?>"
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

                        

                        <div class="privacy-note">

                            Laporan akan ditinjau oleh Admin CareerMap.
                            Identitas pelapor tidak ditampilkan kepada perusahaan.

                        </div>

                        

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
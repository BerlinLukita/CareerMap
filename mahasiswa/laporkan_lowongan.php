<?php
session_start();

if (!isset($_SESSION["user_id"]) || ($_SESSION["role"] ?? "") !== "student") {
    header("Location: ../auth/login.php");
    exit;
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

// Data contoh disamakan dengan halaman detail lowongan
$jobs = [
    1 => [
        "title" => "UI/UX Design Intern",
        "company" => "Kreasi Digital"
    ],
    2 => [
        "title" => "Frontend Developer",
        "company" => "Nusa Teknologi"
    ],
    3 => [
        "title" => "Product Analyst Intern",
        "company" => "Mitra Inovasi"
    ],
    4 => [
        "title" => "QA Engineer Intern",
        "company" => "Solusi Cerdas"
    ]
];

$jobId = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$jobId) {
    $jobId = filter_input(INPUT_POST, "job_id", FILTER_VALIDATE_INT);
}

$job = $jobs[$jobId] ?? null;

if (!$job) {
    http_response_code(404);
    exit("Lowongan tidak ditemukan.");
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

$error = "";
$success = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $reason = trim($_POST["reason"] ?? "");
    $description = trim($_POST["description"] ?? "");

    if (!in_array($reason, $reasons, true)) {
        $error = "Silakan pilih alasan laporan terlebih dahulu.";
    } elseif ($description === "") {
        $error = "Keterangan atau bukti pendukung wajib diisi.";
    } elseif (strlen($description) < 10) {
        $error = "Keterangan laporan minimal 10 karakter.";
    } else {
        /*
         * Validasi berhasil.
         * Penyimpanan permanen ke database perlu dihubungkan
         * dengan tabel laporan sesuai database CareerMap.
         */
        $success = true;
    }
}

$userName = $_SESSION["name"] ?? "Mahasiswa";
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

    <link href="https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>

<body>

<div class="dashboard-layout">

    <!-- SIDEBAR -->
    <aside class="sidebar">

        <div class="sidebar-logo">
            <img src="../assets/images/logo%20uajy.png" alt="CareerMap">
            <span>CareerMap</span>
        </div>

        <div class="sidebar-label">MAHASISWA</div>

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

            <a href="../bantuan/index.php" class="menu-item">
                <span>?</span>
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
            <p>Bantu menjaga informasi lowongan di CareerMap tetap terpercaya.</p>
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

                <?php if ($success): ?>

                    <div class="success-message">
                        <strong>Validasi laporan berhasil!</strong>
                        <p>
                            Data laporan sudah lolos pemeriksaan formulir.
                            Penyimpanan laporan ke database belum diaktifkan.
                        </p>
                        <a href="detail_lowongan.php?id=<?php echo (int)$jobId; ?>">
                            Kembali ke Detail Lowongan
                        </a>
                    </div>

                <?php else: ?>

                    <?php if ($error !== ""): ?>
                        <div class="error-message">
                            <?php echo e($error); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="laporkan_lowongan.php?id=<?php echo (int)$jobId; ?>">

                        <input type="hidden" name="job_id" value="<?php echo (int)$jobId; ?>">

                        <!-- LOWONGAN -->
                        <div class="form-group">

                            <label for="job">
                                Lowongan yang Dilaporkan
                            </label>

                            <input
                                type="text"
                                id="job"
                                value="<?php echo e($job["title"] . " • " . $job["company"]); ?>"
                                readonly
                            >

                        </div>

                        <!-- ALASAN -->
                        <div class="form-group">

                            <label for="reason">
                                Alasan Laporan
                            </label>

                            <select name="reason" id="reason" required>

                                <option value="">Pilih alasan laporan</option>

                                <?php foreach ($reasons as $item): ?>

                                    <option
                                        value="<?php echo e($item); ?>"
                                        <?php echo (($_POST["reason"] ?? "") === $item) ? "selected" : ""; ?>
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
                            ><?php echo e($_POST["description"] ?? ""); ?></textarea>

                        </div>

                        <!-- PRIVASI -->
                        <div class="privacy-note">
                            Laporan akan ditinjau oleh Admin CareerMap.
                            Identitas pelapor tidak ditampilkan kepada perusahaan.
                        </div>

                        <!-- BUTTON -->
                        <div class="form-actions">

                            <a
                                href="detail_lowongan.php?id=<?php echo (int)$jobId; ?>"
                                class="cancel-button"
                            >
                                Batal
                            </a>

                            <button type="submit" class="submit-button">
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

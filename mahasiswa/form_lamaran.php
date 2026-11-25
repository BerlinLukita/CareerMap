<?php
session_start();

if (!isset($_SESSION["user_id"]) || ($_SESSION["role"] ?? "") !== "student") {
    header("Location: ../auth/login.php");
    exit;
}

require_once "../config/database.php";

function e($value) {
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

$studentId = (int) $_SESSION["user_id"];
$jobId = filter_input(INPUT_GET, "job_id", FILTER_VALIDATE_INT);

if (!$jobId) {
    header("Location: cari_lowongan.php");
    exit;
}

// Token keamanan formulir
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// Ambil data lowongan dan perusahaan
$sql = "
    SELECT
        j.id,
        j.title,
        j.job_type,
        j.location,
        j.status AS job_status,
        cp.company_name
    FROM job_vacancies j
    INNER JOIN company_profiles cp
        ON j.company_id = cp.user_id
    WHERE j.id = :job_id
    LIMIT 1
";

$stmt = $pdo->prepare($sql);
$stmt->execute(["job_id" => $jobId]);
$job = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$job) {
    http_response_code(404);
    exit("Lowongan tidak ditemukan.");
}

// Ambil CV mahasiswa yang sudah tersimpan
$profileStmt = $pdo->prepare("
    SELECT cv_path
    FROM student_profiles
    WHERE user_id = :student_id
    LIMIT 1
");

$profileStmt->execute(["student_id" => $studentId]);
$profile = $profileStmt->fetch(PDO::FETCH_ASSOC);

$currentCv = $profile["cv_path"] ?? "";

$error = "";
$success = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Validasi token
    if (
        !isset($_POST["csrf_token"]) ||
        !hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"])
    ) {
        $error = "Permintaan tidak valid. Silakan muat ulang halaman.";
    } else {

        $coverLetter = trim($_POST["cover_letter"] ?? "");
        $newCvPath = $currentCv;
        $uploadedFilePath = null;

        // Periksa apakah mahasiswa memilih CV baru
        if (
            isset($_FILES["cv"]) &&
            $_FILES["cv"]["error"] !== UPLOAD_ERR_NO_FILE
        ) {

            if ($_FILES["cv"]["error"] !== UPLOAD_ERR_OK) {
                $error = "Terjadi kesalahan saat mengunggah CV.";
            } elseif ($_FILES["cv"]["size"] > 5 * 1024 * 1024) {
                $error = "Ukuran CV maksimal 5 MB.";
            } else {

                $tmpFile = $_FILES["cv"]["tmp_name"];
                $originalName = $_FILES["cv"]["name"];

                $extension = strtolower(
                    pathinfo($originalName, PATHINFO_EXTENSION)
                );

                $mimeType = (new finfo(FILEINFO_MIME_TYPE))
                    ->file($tmpFile);

                if ($extension !== "pdf" || $mimeType !== "application/pdf") {
                    $error = "CV harus menggunakan format PDF.";
                } else {

                    $uploadDirectory = __DIR__ . "/../uploads/cvs/";

                    if (
                        !is_dir($uploadDirectory) &&
                        !mkdir($uploadDirectory, 0755, true)
                    ) {
                        $error = "Folder penyimpanan CV tidak dapat dibuat.";
                    } else {

                        $newFileName = bin2hex(random_bytes(16)) . ".pdf";

                        $destination = $uploadDirectory . $newFileName;

                        if (move_uploaded_file($tmpFile, $destination)) {
                            $newCvPath = "uploads/cvs/" . $newFileName;
                            $uploadedFilePath = $destination;
                        } else {
                            $error = "CV gagal disimpan. Silakan coba kembali.";
                        }
                    }
                }
            }
        }

        // CV wajib tersedia, baik CV lama maupun CV baru
        if ($error === "" && empty($newCvPath)) {
            $error = "Silakan unggah Curriculum Vitae (CV) terlebih dahulu.";
        }

        // Periksa status lowongan saat pengajuan
        if ($error === "" && $job["job_status"] !== "published") {
            $error = "Lowongan ini sudah tidak menerima lamaran.";
        }

        if ($error === "") {
            try {
                $pdo->beginTransaction();

                // Pastikan mahasiswa belum pernah melamar lowongan ini
                $duplicateStmt = $pdo->prepare("
                    SELECT id
                    FROM applications
                    WHERE job_id = :job_id
                      AND student_id = :student_id
                    LIMIT 1
                ");

                $duplicateStmt->execute([
                    "job_id" => $jobId,
                    "student_id" => $studentId
                ]);

                if ($duplicateStmt->fetch()) {
                    throw new RuntimeException(
                        "Kamu sudah pernah melamar lowongan ini."
                    );
                }

                // Simpan atau perbarui CV di profil mahasiswa
                $updateProfile = $pdo->prepare("
                    UPDATE student_profiles
                    SET cv_path = :cv_path
                    WHERE user_id = :student_id
                ");

                $updateProfile->execute([
                    "cv_path" => $newCvPath,
                    "student_id" => $studentId
                ]);

                // Masukkan data lamaran
                $insertApplication = $pdo->prepare("
                    INSERT INTO applications
                        (job_id, student_id, cv_path, cover_letter, status)
                    VALUES
                        (:job_id, :student_id, :cv_path, :cover_letter, 'submitted')
                ");

                $insertApplication->execute([
                    "job_id" => $jobId,
                    "student_id" => $studentId,
                    "cv_path" => $newCvPath,
                    "cover_letter" => $coverLetter !== "" ? $coverLetter : null
                ]);

                $applicationId = (int) $pdo->lastInsertId();

                // Catat riwayat status awal
                $insertHistory = $pdo->prepare("
                    INSERT INTO application_status_history
                        (application_id, changed_by, status, note)
                    VALUES
                        (:application_id, :changed_by, 'submitted', :note)
                ");

                $insertHistory->execute([
                    "application_id" => $applicationId,
                    "changed_by" => $studentId,
                    "note" => "Mahasiswa mengirimkan lamaran."
                ]);

                $pdo->commit();

                // Arahkan ke daftar lamaran setelah berhasil
                header("Location: lamaran_saya.php?success=1");
                exit;

            } catch (RuntimeException $ex) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                if ($uploadedFilePath && file_exists($uploadedFilePath)) {
                    unlink($uploadedFilePath);
                }

                $error = $ex->getMessage();

            } catch (PDOException $ex) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                if ($uploadedFilePath && file_exists($uploadedFilePath)) {
                    unlink($uploadedFilePath);
                }

                if ($ex->getCode() === "23000") {
                    $error = "Kamu sudah pernah melamar lowongan ini.";
                } else {
                    error_log($ex->getMessage());
                    $error = "Terjadi kesalahan saat menyimpan lamaran.";
                }
            }
        }
    }
}

// Nama file CV untuk ditampilkan
$cvFileName = $currentCv
    ? basename($currentCv)
    : "Belum ada CV. Silakan unggah file PDF.";

$userName = $_SESSION["name"] ?? "Mahasiswa";
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Kirim Lamaran - CareerMap</title>

    <link rel="stylesheet" href="../assets/css/form-lamaran.css">

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
                Dashboard
            </a>

            <a href="cari_lowongan.php" class="menu-item active">
                Cari Lowongan
            </a>

            <a href="lamaran_saya.php" class="menu-item">
                Lamaran Saya
            </a>

            <a href="lowongan_disimpan.php" class="menu-item">
                Lowongan Disimpan
            </a>

            <a href="profile.php" class="menu-item">
                Profil Saya
            </a>

        </nav>

        <div class="sidebar-bottom">

            <a href="../bantuan/index.php" class="menu-item">
                Bantuan & Pusat Dukungan
            </a>

            <a href="../auth/logout.php" class="menu-item logout">
                Keluar
            </a>

        </div>

    </aside>

    <!-- KONTEN UTAMA -->
    <main class="main-content">

        <header class="page-header">
            <h1>Kirim Lamaran</h1>
            <p>
                Ajukan lamaran pekerjaan dengan dokumen dan informasi yang sesuai.
            </p>
        </header>

        <section class="form-container">

            <div class="form-card">

                <div class="form-card-header">
                    <h2>Formulir Lamaran</h2>
                    <p>
                        Lengkapi informasi sebelum mengirim lamaran.
                    </p>
                </div>

                <?php if ($error !== ""): ?>
                    <div class="error-message">
                        <?php echo e($error); ?>
                    </div>
                <?php endif; ?>

                <form
                    action="form_lamaran.php?job_id=<?php echo (int)$jobId; ?>"
                    method="POST"
                    enctype="multipart/form-data"
                >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?php echo e($_SESSION["csrf_token"]); ?>"
                    >

                    <!-- POSISI -->
                    <div class="form-group">

                        <label for="position">
                            Posisi yang Dilamar
                        </label>

                        <input
                            type="text"
                            id="position"
                            value="<?php echo e($job["title"] . " • " . $job["company_name"]); ?>"
                            readonly
                        >

                    </div>

                    <!-- CV -->
                    <div class="form-group">

                        <label for="cv">
                            Curriculum Vitae (CV)
                        </label>

                        <div class="cv-upload-box">

                            <span
                                class="cv-file-name"
                                id="cvFileName"
                            >
                                <?php echo e($cvFileName); ?>
                            </span>

                            <label for="cv" class="change-file-button">
                                Ganti File
                            </label>

                            <input
                                type="file"
                                name="cv"
                                id="cv"
                                accept=".pdf,application/pdf"
                                hidden
                            >

                        </div>

                        <small class="file-note">
                            Format PDF, maksimal 5 MB.
                        </small>

                    </div>

                    <!-- SURAT PENGANTAR -->
                    <div class="form-group">

                        <label for="cover_letter">
                            Surat Pengantar (Opsional)
                        </label>

                        <textarea
                            name="cover_letter"
                            id="cover_letter"
                            placeholder="Tuliskan alasan dan ketertarikanmu pada posisi ini..."
                        ><?php echo e($_POST["cover_letter"] ?? ""); ?></textarea>

                    </div>

                    <p class="form-note">
                        Pastikan CV dan informasi yang dikirim sudah benar.
                    </p>

                    <!-- TOMBOL -->
                    <div class="form-actions">

                        <a
                            href="detail_lowongan.php?id=<?php echo (int)$jobId; ?>"
                            class="cancel-button"
                        >
                            Batal
                        </a>

                        <button type="submit" class="submit-button">
                            Kirim Lamaran
                        </button>

                    </div>

                </form>

            </div>

        </section>

    </main>

</div>

<script>
const cvInput = document.getElementById("cv");
const cvFileName = document.getElementById("cvFileName");

cvInput.addEventListener("change", function () {
    if (this.files.length > 0) {
        cvFileName.textContent = this.files[0].name;
    }
});
</script>

</body>
</html>

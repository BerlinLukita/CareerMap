
<?php
session_start();

if (!isset($_SESSION["user_id"]) || ($_SESSION["role"] ?? "") !== "student") {
    header("Location: ../auth/login.php");
    exit;
}

require_once "../config/database.php";
$userId = (int) $_SESSION["user_id"];

function e($v): string {
    return htmlspecialchars((string)($v ?? ""), ENT_QUOTES, "UTF-8");
}

function loadProfile(PDO $pdo, int $id): array {
    $q = $pdo->prepare("SELECT
        u.name, u.email,
        sp.nim, sp.study_program,
        sp.photo_path, sp.cv_path, sp.portfolio_url
        FROM users u
        JOIN student_profiles sp ON sp.user_id = u.id
        WHERE u.id = ?");

    $q->execute([$id]);
    $row = $q->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        http_response_code(404);
        exit("Data profil mahasiswa tidak ditemukan.");
    }

    return $row;
}

$profile = loadProfile($pdo, $userId);
$error = "";

$success = isset($_GET["cv"]) && $_GET["cv"] === "success"
    ? "CV berhasil diunggah atau diperbarui."
    : "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["upload_cv"])) {

    if (!isset($_FILES["cv"]) || $_FILES["cv"]["error"] === UPLOAD_ERR_NO_FILE) {
        $error = "Pilih file CV terlebih dahulu.";

    } elseif ($_FILES["cv"]["error"] !== UPLOAD_ERR_OK) {
        $error = "Terjadi kesalahan saat mengunggah CV.";

    } elseif ($_FILES["cv"]["size"] > 5 * 1024 * 1024) {
        $error = "Ukuran CV maksimal 5 MB.";

    } else {
        $tmp = $_FILES["cv"]["tmp_name"];
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
        $ext = strtolower(pathinfo($_FILES["cv"]["name"], PATHINFO_EXTENSION));

        if ($mime !== "application/pdf" || $ext !== "pdf") {
            $error = "CV harus berupa file PDF.";

        } else {
            $dir = dirname(__DIR__) . "/uploads/cvs/";

            if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
                $error = "Folder penyimpanan CV tidak dapat dibuat.";

            } else {
                $filename = bin2hex(random_bytes(16)) . ".pdf";
                $destination = $dir . $filename;

                if (!move_uploaded_file($tmp, $destination)) {
                    $error = "Gagal menyimpan file CV.";

                } else {
                    $newPath = "uploads/cvs/" . $filename;

                    try {
                        $up = $pdo->prepare(
                            "UPDATE student_profiles
                             SET cv_path = ?
                             WHERE user_id = ?"
                        );

                        $up->execute([$newPath, $userId]);

                        if (
                            !empty($profile["cv_path"]) &&
                            strpos($profile["cv_path"], "uploads/cvs/") === 0
                        ) {
                            $old = dirname(__DIR__) . "/" . $profile["cv_path"];

                            if (is_file($old)) {
                                unlink($old);
                            }
                        }

                        header("Location: profile.php?cv=success");
                        exit;

                    } catch (PDOException $ex) {
                        if (is_file($destination)) {
                            unlink($destination);
                        }

                        $error = "CV gagal disimpan ke database.";
                    }
                }
            }
        }
    }

    $profile = loadProfile($pdo, $userId);
}

$name = $profile["name"] ?: "Mahasiswa";

$initials = mb_strtoupper(
    mb_substr(trim($name), 0, 2, "UTF-8"),
    "UTF-8"
);

$fields = [
    $profile["name"],
    $profile["email"],
    $profile["nim"],
    $profile["study_program"],
    $profile["portfolio_url"],
    $profile["cv_path"]
];

$percent = (int) round(
    count(array_filter($fields, fn($v) => trim((string)$v) !== ""))
    / count($fields) * 100
);

$photo = !empty($profile["photo_path"])
    ? "../" . e($profile["photo_path"])
    : "";

$cv = !empty($profile["cv_path"])
    ? "../" . e($profile["cv_path"])
    : "";
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Profil Saya | CareerMap</title>

<link rel="stylesheet" href="../assets/css/profile_mahasiswa.css">
</head>

<body>

<div class="cm-layout">

    <!-- SIDEBAR -->
    <aside class="cm-sidebar">

        <a href="dashboard.php" class="cm-brand">
            <img src="../assets/images/logo-uajy.jpg" alt="Logo Atma Jaya">
            <span>CareerMap</span>
        </a>

        <div class="cm-role">MAHASISWA</div>

        <nav class="cm-menu">
            <a href="dashboard.php">Dashboard</a>
            <a href="cari_lowongan.php">Cari Lowongan</a>
            <a href="lamaran_saya.php">Lamaran Saya</a>
            <a href="lowongan_disimpan.php">Lowongan Disimpan</a>
            <a href="profile.php" class="active">Profil Saya</a>
        </nav>

        <div class="cm-sidebar-bottom">
           
           <a href="index.php" class="menu-item">
    Bantuan & Pusat Dukungan
</a>
            <a href="../auth/logout.php">Keluar</a>
        </div>

    </aside>


    <!-- KONTEN UTAMA -->
    <main class="cm-main">

        <header class="cm-page-header">
            <h1>Profil Saya</h1>
            <p>Kelola informasi pribadi, foto profil, dan dokumen kariermu.</p>
        </header>

        <?php if ($success): ?>
            <div class="cm-alert success">
                <?= e($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="cm-alert error">
                <?= e($error) ?>
            </div>
        <?php endif; ?>


        <section class="cm-profile-panel">

            <div class="cm-profile-grid">

                <!-- KARTU PROFIL KIRI -->
                <section class="cm-card cm-summary-card">

                    <div class="cm-avatar">

                        <?php if ($photo): ?>
                            <img src="<?= $photo ?>" alt="Foto profil">
                        <?php else: ?>
                            <span><?= e($initials) ?></span>
                        <?php endif; ?>

                    </div>

                    <h2><?= e($profile["name"]) ?></h2>

                    <p class="cm-study">
                        <?= e($profile["study_program"]) ?> • Mahasiswa UAJY
                    </p>


                    <div class="cm-progress-wrap">

                        <div class="cm-progress-label">
                            Profil <?= $percent ?>% Lengkap
                        </div>

                        <div class="cm-progress">
                            <span style="width:<?= $percent ?>%"></span>
                        </div>

                    </div>


                    <div class="cm-cv-heading">
                        Curriculum Vitae (CV)
                    </div>


                    <div class="cm-cv-file">

                        <?php if ($cv): ?>

                            <a href="<?= $cv ?>" target="_blank" rel="noopener">
                                <?= e(basename($profile["cv_path"])) ?>
                            </a>

                            <small>PDF • Maks. 5 MB</small>

                        <?php else: ?>

                            <strong>CV belum diunggah</strong>
                            <small>Unggah CV dalam format PDF</small>

                        <?php endif; ?>

                    </div>


                    <form method="POST" enctype="multipart/form-data" class="cm-cv-form">

                        <label for="cv" class="cm-outline-button">
                            <?= $cv ? "Ganti CV" : "Unggah CV" ?>
                        </label>

                        <input
                            type="file"
                            name="cv"
                            id="cv"
                            accept=".pdf,application/pdf"
                            hidden
                        >

                        <button
                            type="submit"
                            name="upload_cv"
                            class="cm-cv-submit"
                            id="cv-submit"
                            hidden
                        >
                            Simpan CV
                        </button>

                    </form>

                </section>


                <!-- KARTU INFORMASI KANAN -->
                <section class="cm-card cm-info-card">

                    <div class="cm-info-heading">
                        <h2>Informasi Pribadi</h2>

                        <a href="edit_profile.php" class="cm-outline-button">
                            Edit Profil
                        </a>
                    </div>


                    <div class="cm-info-list">

                        <div class="cm-info-item">
                            <span>Nama Lengkap</span>
                            <div><?= e($profile["name"]) ?></div>
                        </div>

                        <div class="cm-info-item">
                            <span>Email</span>
                            <div><?= e($profile["email"]) ?></div>
                        </div>

                        <div class="cm-info-item">
                            <span>NIM</span>
                            <div><?= e($profile["nim"]) ?></div>
                        </div>

                        <div class="cm-info-item">
                            <span>Program Studi</span>
                            <div><?= e($profile["study_program"]) ?></div>
                        </div>

                        <div class="cm-info-item">

                            <span>LinkedIn / Portofolio</span>

                            <div>

                                <?php if (!empty($profile["portfolio_url"])): ?>

                                    <a
                                        href="<?= e($profile["portfolio_url"]) ?>"
                                        target="_blank"
                                        rel="noopener"
                                    >
                                        <?= e($profile["portfolio_url"]) ?>
                                    </a>

                                <?php else: ?>

                                    <span class="cm-empty">Belum diisi</span>

                                <?php endif; ?>

                            </div>

                        </div>

                    </div>


                    <div class="cm-photo-row">
                        <span>Foto Profil</span>
                        <small>JPG / PNG • Maks. 2 MB</small>

                        <a href="edit_profile.php#foto" class="cm-outline-button">
                            Ganti Foto
                        </a>
                    </div>

                </section>

            </div>

        </section>

    </main>

</div>


<script>
const cvInput = document.getElementById("cv");
const cvSubmit = document.getElementById("cv-submit");

cvInput.addEventListener("change", function () {

    if (!this.files.length) {
        cvSubmit.hidden = true;
        return;
    }

    const file = this.files[0];

    if (
        file.type !== "application/pdf" ||
        file.size > 5 * 1024 * 1024
    ) {
        alert("Pilih file PDF dengan ukuran maksimal 5 MB.");

        this.value = "";
        cvSubmit.hidden = true;
        return;
    }

    cvSubmit.hidden = false;

});
</script>

</body>
</html>

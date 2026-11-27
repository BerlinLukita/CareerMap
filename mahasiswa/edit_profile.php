
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

$sql = "SELECT
            u.name,
            u.email,
            sp.nim,
            sp.study_program,
            sp.photo_path,
            sp.cv_path,
            sp.portfolio_url
        FROM users u
        JOIN student_profiles sp ON sp.user_id = u.id
        WHERE u.id = ?";

$stmt = $pdo->prepare($sql);
$stmt->execute([$userId]);

$profile = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$profile) {
    http_response_code(404);
    exit("Data profil mahasiswa tidak ditemukan.");
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"] ?? "");
    $nim = trim($_POST["nim"] ?? "");
    $study = trim($_POST["study_program"] ?? "");
    $portfolio = trim($_POST["portfolio_url"] ?? "");

    $photoPath = $profile["photo_path"];
    $newPhotoPath = "";
    $destination = "";

    if ($name === "" || $nim === "" || $study === "") {

        $error = "Nama lengkap, NIM, dan program studi wajib diisi.";

    } elseif (
        $portfolio !== "" &&
        !filter_var($portfolio, FILTER_VALIDATE_URL)
    ) {

        $error = "Masukkan tautan LinkedIn/portofolio yang valid.";

    }


    // UPLOAD FOTO PROFIL
    if (
        $error === "" &&
        isset($_FILES["photo"]) &&
        $_FILES["photo"]["error"] !== UPLOAD_ERR_NO_FILE
    ) {

        if ($_FILES["photo"]["error"] !== UPLOAD_ERR_OK) {

            $error = "Terjadi kesalahan saat mengunggah foto.";

        } elseif ($_FILES["photo"]["size"] > 2 * 1024 * 1024) {

            $error = "Ukuran foto maksimal 2 MB.";

        } else {

            $tmp = $_FILES["photo"]["tmp_name"];

            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);

            $ext = $mime === "image/jpeg"
                ? "jpg"
                : ($mime === "image/png" ? "png" : "");

            if ($ext === "" || getimagesize($tmp) === false) {

                $error = "Foto harus berupa JPG atau PNG yang valid.";

            } else {

                $dir = dirname(__DIR__) . "/uploads/profiles/";

                if (!is_dir($dir) && !mkdir($dir, 0755, true)) {

                    $error = "Folder penyimpanan foto tidak dapat dibuat.";

                } else {

                    $filename = bin2hex(random_bytes(16)) . "." . $ext;

                    $destination = $dir . $filename;

                    if (!move_uploaded_file($tmp, $destination)) {

                        $error = "Gagal menyimpan foto profil.";

                    } else {

                        $newPhotoPath = "uploads/profiles/" . $filename;
                        $photoPath = $newPhotoPath;

                    }
                }
            }
        }
    }


    // SIMPAN PERUBAHAN PROFIL
    if ($error === "") {

        try {

            $pdo->beginTransaction();

            $u = $pdo->prepare(
                "UPDATE users SET name = ? WHERE id = ?"
            );

            $u->execute([$name, $userId]);


            $sp = $pdo->prepare(
                "UPDATE student_profiles
                 SET nim = ?,
                     study_program = ?,
                     portfolio_url = ?,
                     photo_path = ?
                 WHERE user_id = ?"
            );

            $sp->execute([
                $nim,
                $study,
                $portfolio,
                $photoPath,
                $userId
            ]);

            $pdo->commit();


            // HAPUS FOTO LAMA JIKA ADA FOTO BARU
            if (
                $newPhotoPath !== "" &&
                !empty($profile["photo_path"]) &&
                strpos($profile["photo_path"], "uploads/profiles/") === 0
            ) {

                $old = dirname(__DIR__) . "/" . $profile["photo_path"];

                if (is_file($old)) {
                    unlink($old);
                }

            }

            $_SESSION["name"] = $name;

            header("Location: profile.php?updated=success");
            exit;


        } catch (PDOException $ex) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ($newPhotoPath !== "" && is_file($destination)) {
                unlink($destination);
            }

            $error = $ex->getCode() === "23000"
                ? "NIM sudah digunakan akun lain."
                : "Profil gagal diperbarui. Silakan coba lagi.";

        }

    } elseif ($newPhotoPath !== "" && is_file($destination)) {

        unlink($destination);

    }


    // PERTAHANKAN INPUT JIKA TERJADI ERROR
    $profile["name"] = $name;
    $profile["nim"] = $nim;
    $profile["study_program"] = $study;
    $profile["portfolio_url"] = $portfolio;

    if ($newPhotoPath !== "") {
        $profile["photo_path"] = $newPhotoPath;
    }

}


$initials = mb_strtoupper(
    mb_substr(trim($profile["name"] ?: "Mahasiswa"), 0, 2, "UTF-8"),
    "UTF-8"
);

$photo = !empty($profile["photo_path"])
    ? "../" . e($profile["photo_path"])
    : "";

?>

<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Edit Profil Saya | CareerMap</title>

<link rel="stylesheet" href="../assets/css/profile_mahasiswa.css">

</head>

<body>

<div class="cm-layout">


    <!-- SIDEBAR -->
    <aside class="cm-sidebar">

        <a href="dashboard.php" class="cm-brand">

            <img
                src="../assets/images/logo-uajy-transparan.png"
                alt="Logo Atma Jaya"
            >

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

            <!-- LINK BANTUAN SUDAH DIPERBAIKI -->
            <a href="index.php">
                Bantuan &amp; Pusat Dukungan
            </a>

            <a href="../auth/logout.php">
                Keluar
            </a>

        </div>

    </aside>



    <!-- KONTEN UTAMA -->
    <main class="cm-main">


        <header class="cm-page-header">

            <h1>Edit Profil Saya</h1>

            <p>
                Ubah informasi profil yang ditampilkan kepada perusahaan.
            </p>

        </header>


        <?php if ($error): ?>

            <div class="cm-alert error">
                <?= e($error) ?>
            </div>

        <?php endif; ?>



        <section class="cm-edit-panel">


            <form
                method="POST"
                enctype="multipart/form-data"
                class="cm-edit-card"
            >


                <div class="cm-edit-heading">

                    <h2>Edit Informasi Profil</h2>

                    <p>
                        Perbarui informasi pribadi dan tautan portofoliomu.
                    </p>

                </div>



                <!-- FORM INFORMASI PROFIL -->
                <div class="cm-edit-grid">


                    <div class="cm-field">

                        <label for="name">
                            Nama Lengkap
                        </label>

                        <input
                            type="text"
                            id="name"
                            name="name"
                            value="<?= e($profile["name"]) ?>"
                            required
                        >

                    </div>



                    <div class="cm-field">

                        <label for="email">
                            Email
                        </label>

                        <input
                            type="email"
                            id="email"
                            value="<?= e($profile["email"]) ?>"
                            disabled
                        >

                    </div>



                    <div class="cm-field">

                        <label for="nim">
                            NIM
                        </label>

                        <input
                            type="text"
                            id="nim"
                            name="nim"
                            value="<?= e($profile["nim"]) ?>"
                            required
                        >

                    </div>



                    <div class="cm-field">

                        <label for="study_program">
                            Program Studi
                        </label>

                        <input
                            type="text"
                            id="study_program"
                            name="study_program"
                            value="<?= e($profile["study_program"]) ?>"
                            required
                        >

                    </div>



                    <div class="cm-field full">

                        <label for="portfolio_url">
                            LinkedIn / Portofolio
                        </label>

                        <input
                            type="url"
                            id="portfolio_url"
                            name="portfolio_url"
                            placeholder="https://linkedin.com/in/nama"
                            value="<?= e($profile["portfolio_url"]) ?>"
                        >

                    </div>


                </div>



                <!-- FOTO PROFIL -->
                <div class="cm-photo-edit" id="foto">

                    <label>Foto Profil</label>


                    <div class="cm-photo-edit-row">


                        <div
                            class="cm-avatar cm-avatar-small"
                            id="photo-preview"
                        >

                            <?php if ($photo): ?>

                                <img
                                    src="<?= $photo ?>"
                                    alt="Foto profil"
                                >

                            <?php else: ?>

                                <span><?= e($initials) ?></span>

                            <?php endif; ?>

                        </div>



                        <div class="cm-photo-control">

                            <label
                                for="photo"
                                class="cm-outline-button"
                            >
                                Ganti Foto
                            </label>


                            <input
                                type="file"
                                id="photo"
                                name="photo"
                                accept=".jpg,.jpeg,.png,image/jpeg,image/png"
                                hidden
                            >


                            <small>
                                JPG / PNG • Maks. 2 MB
                            </small>


                            <span id="photo-name"></span>

                        </div>


                    </div>

                </div>



                <!-- TOMBOL -->
                <div class="cm-edit-actions">

                    <a
                        href="profile.php"
                        class="cm-button cm-button-cancel"
                    >
                        Batal
                    </a>


                    <button
                        type="submit"
                        class="cm-button cm-button-primary"
                    >
                        Simpan
                    </button>

                </div>


            </form>

        </section>


    </main>

</div>



<!-- JAVASCRIPT PREVIEW FOTO -->
<script>

const photoInput = document.getElementById("photo");

const preview = document.getElementById("photo-preview");

const photoName = document.getElementById("photo-name");


photoInput.addEventListener("change", function () {

    if (!this.files.length) return;

    const file = this.files[0];

    if (
        !["image/jpeg", "image/png"].includes(file.type) ||
        file.size > 2 * 1024 * 1024
    ) {

        alert("Foto harus JPG atau PNG dengan ukuran maksimal 2 MB.");

        this.value = "";

        photoName.textContent = "";

        return;

    }


    photoName.textContent = file.name;


    const reader = new FileReader();

    reader.onload = e => {

        preview.innerHTML = "";

        const img = document.createElement("img");

        img.src = e.target.result;

        img.alt = "Pratinjau foto profil";

        preview.appendChild(img);

    };

    reader.readAsDataURL(file);

});

</script>

</body>
</html>

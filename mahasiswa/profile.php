
<?php
session_start();

if (
    !isset($_SESSION["user_id"]) ||
    $_SESSION["role"] != "student"
) {
    header("Location: ../auth/login.php");
    exit;
}

require_once "../config/database.php";

$user_id = $_SESSION["user_id"];
$message = "";

$sql = "SELECT users.name, users.email,
               student_profiles.nim,
               student_profiles.study_program,
               student_profiles.photo_path,
               student_profiles.cv_path,
               student_profiles.portfolio_url
        FROM users
        JOIN student_profiles
        ON users.id = student_profiles.user_id
        WHERE users.id = ?";

$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$profile = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $name = trim($_POST["name"]);
    $nim = trim($_POST["nim"]);
    $study_program = trim($_POST["study_program"]);
    $portfolio_url = trim($_POST["portfolio_url"]);

    $photo_path = $profile["photo_path"];
    $cv_path = $profile["cv_path"];

    $new_photo = "";
    $new_cv = "";

    if (empty($name) || empty($nim) || empty($study_program)) {

        $message = "Nama, NIM, dan program studi wajib diisi!";

    } else {

        // Validasi upload foto profil
        if (isset($_FILES["photo"]) &&
            $_FILES["photo"]["error"] != UPLOAD_ERR_NO_FILE) {

            if ($_FILES["photo"]["error"] != UPLOAD_ERR_OK) {
                $message = "Terjadi kesalahan saat mengupload foto.";

            } elseif ($_FILES["photo"]["size"] > 2 * 1024 * 1024) {
                $message = "Ukuran foto maksimal 2 MB.";

            } else {

                $photo_mime = mime_content_type($_FILES["photo"]["tmp_name"]);

                if ($photo_mime == "image/jpeg") {
                    $photo_extension = "jpg";
                } elseif ($photo_mime == "image/png") {
                    $photo_extension = "png";
                } else {
                    $message = "Foto harus berformat JPG atau PNG.";
                }

                if ($message == "") {

                    if (getimagesize($_FILES["photo"]["tmp_name"]) === false) {
                        $message = "File foto tidak valid.";
                    } else {

                        $photo_filename = bin2hex(random_bytes(16))
                                        . "." . $photo_extension;

                        $photo_destination = "../uploads/profiles/"
                                           . $photo_filename;

                        if (move_uploaded_file(
                            $_FILES["photo"]["tmp_name"],
                            $photo_destination
                        )) {
                            $new_photo = "uploads/profiles/" . $photo_filename;
                            $photo_path = $new_photo;
                        } else {
                            $message = "Gagal menyimpan foto profil.";
                        }
                    }
                }
            }
        }

        // Validasi upload CV
        if ($message == "" &&
            isset($_FILES["cv"]) &&
            $_FILES["cv"]["error"] != UPLOAD_ERR_NO_FILE) {

            if ($_FILES["cv"]["error"] != UPLOAD_ERR_OK) {
                $message = "Terjadi kesalahan saat mengupload CV.";

            } elseif ($_FILES["cv"]["size"] > 5 * 1024 * 1024) {
                $message = "Ukuran CV maksimal 5 MB.";

            } else {

                $cv_mime = mime_content_type($_FILES["cv"]["tmp_name"]);

                if (
                    $cv_mime != "application/pdf" ||
                    strtolower(pathinfo(
                        $_FILES["cv"]["name"],
                        PATHINFO_EXTENSION
                    )) != "pdf"
                ) {
                    $message = "CV harus berformat PDF.";

                } else {

                    $cv_filename = bin2hex(random_bytes(16)) . ".pdf";

                    $cv_destination = "../uploads/cvs/" . $cv_filename;

                    if (move_uploaded_file(
                        $_FILES["cv"]["tmp_name"],
                        $cv_destination
                    )) {
                        $new_cv = "uploads/cvs/" . $cv_filename;
                        $cv_path = $new_cv;
                    } else {
                        $message = "Gagal menyimpan CV.";
                    }
                }
            }
        }

        // Simpan perubahan ke database
        if ($message == "") {

            try {

                $pdo->beginTransaction();

                $updateUser = $pdo->prepare(
                    "UPDATE users
                     SET name = ?
                     WHERE id = ?"
                );

                $updateUser->execute([
                    $name,
                    $user_id
                ]);

                $updateProfile = $pdo->prepare(
                    "UPDATE student_profiles
                     SET nim = ?,
                         study_program = ?,
                         portfolio_url = ?,
                         photo_path = ?,
                         cv_path = ?
                     WHERE user_id = ?"
                );

                $updateProfile->execute([
                    $nim,
                    $study_program,
                    $portfolio_url,
                    $photo_path,
                    $cv_path,
                    $user_id
                ]);

                $pdo->commit();

                // Hapus file lama jika file baru berhasil disimpan
                if ($new_photo != "" &&
                    !empty($profile["photo_path"]) &&
                    strpos($profile["photo_path"], "uploads/profiles/") === 0) {

                    $old_photo = "../" . $profile["photo_path"];

                    if (is_file($old_photo)) {
                        unlink($old_photo);
                    }
                }

                if ($new_cv != "" &&
                    !empty($profile["cv_path"]) &&
                    strpos($profile["cv_path"], "uploads/cvs/") === 0) {

                    $old_cv = "../" . $profile["cv_path"];

                    if (is_file($old_cv)) {
                        unlink($old_cv);
                    }
                }

                $_SESSION["name"] = $name;

                $message = "Profil berhasil diperbarui!";

                $stmt->execute([$user_id]);
                $profile = $stmt->fetch(PDO::FETCH_ASSOC);

            } catch (PDOException $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                // Hapus file baru jika penyimpanan database gagal
                if ($new_photo != "" && is_file("../" . $new_photo)) {
                    unlink("../" . $new_photo);
                }

                if ($new_cv != "" && is_file("../" . $new_cv)) {
                    unlink("../" . $new_cv);
                }

                if ($e->getCode() == "23000") {
                    $message = "NIM sudah digunakan akun lain!";
                } else {
                    $message = "Gagal memperbarui profil.";
                }
            }
        }

        // Jika validasi upload gagal, hapus file baru yang sudah terlanjur dipindahkan
        if ($message != "Profil berhasil diperbarui!") {

            if ($new_photo != "" && is_file("../" . $new_photo)) {
                unlink("../" . $new_photo);
            }

            if ($new_cv != "" && is_file("../" . $new_cv)) {
                unlink("../" . $new_cv);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Profil Mahasiswa CareerMap</title>
</head>

<body>

    <h2>Profil Saya</h2>

    <p><?php echo htmlspecialchars($message); ?></p>

    <form method="POST" enctype="multipart/form-data">

        <label>Nama Lengkap</label><br>
        <input
            type="text"
            name="name"
            value="<?php echo htmlspecialchars($profile["name"] ?? ""); ?>"
            required
        >
        <br><br>

        <label>Email</label><br>
        <input
            type="email"
            value="<?php echo htmlspecialchars($profile["email"] ?? ""); ?>"
            disabled
        >
        <br><br>

        <label>NIM</label><br>
        <input
            type="text"
            name="nim"
            value="<?php echo htmlspecialchars($profile["nim"] ?? ""); ?>"
            required
        >
        <br><br>

        <label>Program Studi</label><br>
        <input
            type="text"
            name="study_program"
            value="<?php echo htmlspecialchars($profile["study_program"] ?? ""); ?>"
            required
        >
        <br><br>

        <label>LinkedIn / Portofolio</label><br>
        <input
            type="url"
            name="portfolio_url"
            value="<?php echo htmlspecialchars($profile["portfolio_url"] ?? ""); ?>"
        >
        <br><br>

        <label>Foto Profil (JPG/PNG, maksimal 2 MB)</label><br>

        <?php if (!empty($profile["photo_path"])): ?>
            <img
                src="../<?php echo htmlspecialchars($profile["photo_path"]); ?>"
                width="120"
                alt="Foto Profil"
            >
            <br>
        <?php endif; ?>

        <input
            type="file"
            name="photo"
            accept=".jpg,.jpeg,.png"
        >
        <br><br>

        <label>Curriculum Vitae (PDF, maksimal 5 MB)</label><br>

        <?php if (!empty($profile["cv_path"])): ?>
            <a
                href="../<?php echo htmlspecialchars($profile["cv_path"]); ?>"
                target="_blank"
            >
                Lihat CV saat ini
            </a>
            <br>
        <?php endif; ?>

        <input
            type="file"
            name="cv"
            accept=".pdf"
        >
        <br><br>

        <button type="submit">Simpan Perubahan</button>

    </form>

    <br>

    <a href="dashboard.php">Kembali ke Dashboard</a>

</body>
</html>

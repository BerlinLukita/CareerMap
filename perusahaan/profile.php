<?php
session_start();

if (
    !isset($_SESSION["user_id"]) ||
    $_SESSION["role"] != "company"
) {
    header("Location: ../auth/login.php");
    exit;
}

require_once "../config/database.php";

$user_id = $_SESSION["user_id"];
$message = "";

// Mengambil data profil perusahaan dan dokumen terbaru
$sql = "SELECT
            users.email,
            company_profiles.company_name,
            company_profiles.industry,
            company_profiles.address,
            company_profiles.phone,
            company_profiles.website,
            company_profiles.description,
            company_profiles.year_founded,
            company_profiles.logo_path,
            company_profiles.verification_status,
            company_profiles.verification_note,
            company_documents.document_name,
            company_documents.file_path
        FROM company_profiles
        JOIN users ON company_profiles.user_id = users.id
        LEFT JOIN company_documents
            ON company_documents.id = (
                SELECT MAX(id)
                FROM company_documents
                WHERE company_id = company_profiles.user_id
            )
        WHERE company_profiles.user_id = ?";

$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$profile = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$profile) {
    die("Profil perusahaan tidak ditemukan.");
}

// Proses form
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $company_name = trim($_POST["company_name"]);
    $industry = trim($_POST["industry"]);
    $phone = trim($_POST["phone"]);
    $website = trim($_POST["website"]);
    $address = trim($_POST["address"]);
    $description = trim($_POST["description"]);
    $year_founded = trim($_POST["year_founded"]);

    $logo_path = $profile["logo_path"];
    $new_logo = "";
    $new_document = "";

    // Validasi data
    if (empty($company_name)) {
        $message = "Nama perusahaan wajib diisi!";

    } elseif (
        $website != "" &&
        !filter_var($website, FILTER_VALIDATE_URL)
    ) {
        $message = "Format website tidak valid!";

    } elseif (
        $year_founded != "" &&
        (
            !ctype_digit($year_founded) ||
            (int)$year_founded < 1800 ||
            (int)$year_founded > (int)date("Y")
        )
    ) {
        $message = "Tahun berdiri tidak valid!";
    }

    // Upload logo perusahaan
    if (
        $message == "" &&
        isset($_FILES["logo"]) &&
        $_FILES["logo"]["error"] != UPLOAD_ERR_NO_FILE
    ) {

        if ($_FILES["logo"]["error"] != UPLOAD_ERR_OK) {
            $message = "Terjadi kesalahan saat mengupload logo.";

        } elseif ($_FILES["logo"]["size"] > 2 * 1024 * 1024) {
            $message = "Ukuran logo maksimal 2 MB.";

        } else {

            $logo_mime = mime_content_type($_FILES["logo"]["tmp_name"]);

            if ($logo_mime == "image/jpeg") {
                $logo_extension = "jpg";
            } elseif ($logo_mime == "image/png") {
                $logo_extension = "png";
            } else {
                $message = "Logo harus berformat JPG atau PNG.";
            }

            if ($message == "") {

                if (getimagesize($_FILES["logo"]["tmp_name"]) === false) {
                    $message = "File logo tidak valid.";

                } else {

                    $logo_filename = bin2hex(random_bytes(16))
                                   . "." . $logo_extension;

                    $logo_destination = "../uploads/perusahaan/logos/"
                                     . $logo_filename;

                    if (move_uploaded_file(
                        $_FILES["logo"]["tmp_name"],
                        $logo_destination
                    )) {
                        $new_logo = "uploads/perusahaan/logos/"
                                  . $logo_filename;

                        $logo_path = $new_logo;

                    } else {
                        $message = "Gagal menyimpan logo perusahaan.";
                    }
                }
            }
        }
    }

    // Upload dokumen verifikasi
    if (
        $message == "" &&
        isset($_FILES["document"]) &&
        $_FILES["document"]["error"] != UPLOAD_ERR_NO_FILE
    ) {

        if ($_FILES["document"]["error"] != UPLOAD_ERR_OK) {
            $message = "Terjadi kesalahan saat mengupload dokumen.";

        } elseif ($_FILES["document"]["size"] > 5 * 1024 * 1024) {
            $message = "Ukuran dokumen maksimal 5 MB.";

        } else {

            $document_mime = mime_content_type(
                $_FILES["document"]["tmp_name"]
            );

            $document_extension = strtolower(
                pathinfo(
                    $_FILES["document"]["name"],
                    PATHINFO_EXTENSION
                )
            );

            if (
                $document_mime != "application/pdf" ||
                $document_extension != "pdf"
            ) {
                $message = "Dokumen verifikasi harus berformat PDF.";

            } else {

                $document_filename = bin2hex(random_bytes(16)) . ".pdf";

                $document_destination =
                    "../uploads/perusahaan/documents/"
                    . $document_filename;

                if (move_uploaded_file(
                    $_FILES["document"]["tmp_name"],
                    $document_destination
                )) {
                    $new_document = "uploads/perusahaan/documents/"
                                  . $document_filename;

                } else {
                    $message = "Gagal menyimpan dokumen verifikasi.";
                }
            }
        }
    }

    // Simpan perubahan ke database
    if ($message == "") {

        try {

            $pdo->beginTransaction();

            $updateProfile = $pdo->prepare(
                "UPDATE company_profiles
                 SET company_name = ?,
                     industry = ?,
                     address = ?,
                     phone = ?,
                     website = ?,
                     description = ?,
                     year_founded = ?,
                     logo_path = ?
                 WHERE user_id = ?"
            );

            $updateProfile->execute([
                $company_name,
                $industry,
                $address,
                $phone,
                $website,
                $description,
                $year_founded == "" ? null : $year_founded,
                $logo_path,
                $user_id
            ]);

            // Jika ada dokumen baru, simpan sebagai dokumen pending
            if ($new_document != "") {

                $document_name = basename($_FILES["document"]["name"]);

                $insertDocument = $pdo->prepare(
                    "INSERT INTO company_documents
                     (company_id, document_name, file_path, document_status)
                     VALUES (?, ?, ?, 'pending')"
                );

                $insertDocument->execute([
                    $user_id,
                    $document_name,
                    $new_document
                ]);

                // Perusahaan perlu diverifikasi kembali
                $updateVerification = $pdo->prepare(
                    "UPDATE company_profiles
                     SET verification_status = 'pending',
                         verification_note = NULL,
                         verified_at = NULL
                     WHERE user_id = ?"
                );

                $updateVerification->execute([$user_id]);
            }

            $pdo->commit();

            // Hapus logo lama jika berhasil diganti
            if (
                $new_logo != "" &&
                !empty($profile["logo_path"]) &&
                strpos($profile["logo_path"], "uploads/perusahaan/logos/") === 0
            ) {

                $old_logo = "../" . $profile["logo_path"];

                if (is_file($old_logo)) {
                    unlink($old_logo);
                }
            }

            $_SESSION["name"] = $company_name;

            $message = "Profil perusahaan berhasil diperbarui!";

            // Ambil ulang data terbaru
            $stmt->execute([$user_id]);
            $profile = $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            // Hapus file baru jika database gagal menyimpan
            if ($new_logo != "" && is_file("../" . $new_logo)) {
                unlink("../" . $new_logo);
            }

            if ($new_document != "" && is_file("../" . $new_document)) {
                unlink("../" . $new_document);
            }

            $message = "Gagal memperbarui profil perusahaan.";
        }
    }

    // Bersihkan file baru jika validasi gagal
    if ($message != "Profil perusahaan berhasil diperbarui!") {

        if ($new_logo != "" && is_file("../" . $new_logo)) {
            unlink("../" . $new_logo);
        }

        if ($new_document != "" && is_file("../" . $new_document)) {
            unlink("../" . $new_document);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Profil Perusahaan CareerMap</title>
</head>

<body>

    <h2>Profil Perusahaan</h2>

    <p>
        <?php echo htmlspecialchars($message); ?>
    </p>

    <h3>Status Verifikasi</h3>

    <?php
    if ($profile["verification_status"] == "verified") {
        echo "<p>Perusahaan sudah terverifikasi.</p>";
    } elseif ($profile["verification_status"] == "rejected") {
        echo "<p>Verifikasi perusahaan ditolak.</p>";
    } else {
        echo "<p>Perusahaan sedang menunggu verifikasi admin.</p>";
    }
    ?>

    <?php if (!empty($profile["verification_note"])): ?>
        <p>
            Catatan Admin:
            <?php echo htmlspecialchars($profile["verification_note"]); ?>
        </p>
    <?php endif; ?>

    <hr>

    <form method="POST" enctype="multipart/form-data">

        <h3>Informasi Umum</h3>

        <label>Nama Perusahaan</label><br>
        <input
            type="text"
            name="company_name"
            value="<?php echo htmlspecialchars($profile["company_name"] ?? ""); ?>"
            required
        >
        <br><br>

        <label>Email Perusahaan</label><br>
        <input
            type="email"
            value="<?php echo htmlspecialchars($profile["email"] ?? ""); ?>"
            disabled
        >
        <br><br>

        <label>Bidang Industri</label><br>
        <input
            type="text"
            name="industry"
            value="<?php echo htmlspecialchars($profile["industry"] ?? ""); ?>"
        >
        <br><br>

        <label>Nomor Telepon</label><br>
        <input
            type="text"
            name="phone"
            value="<?php echo htmlspecialchars($profile["phone"] ?? ""); ?>"
        >
        <br><br>

        <label>Website</label><br>
        <input
            type="url"
            name="website"
            value="<?php echo htmlspecialchars($profile["website"] ?? ""); ?>"
        >
        <br><br>

        <label>Tahun Berdiri</label><br>
        <input
            type="number"
            name="year_founded"
            min="1800"
            max="<?php echo date("Y"); ?>"
            value="<?php echo htmlspecialchars($profile["year_founded"] ?? ""); ?>"
        >
        <br><br>

        <label>Alamat Perusahaan</label><br>
        <textarea name="address" rows="3"><?php
            echo htmlspecialchars($profile["address"] ?? "");
        ?></textarea>
        <br><br>

        <label>Deskripsi Perusahaan</label><br>
        <textarea name="description" rows="5"><?php
            echo htmlspecialchars($profile["description"] ?? "");
        ?></textarea>
        <br><br>

        <hr>

        <h3>Logo Perusahaan</h3>

        <?php if (!empty($profile["logo_path"])): ?>
            <img
                src="../<?php echo htmlspecialchars($profile["logo_path"]); ?>"
                width="120"
                alt="Logo Perusahaan"
            >
            <br>
        <?php endif; ?>

        <label>Upload Logo (JPG/PNG, maksimal 2 MB)</label><br>

        <input
            type="file"
            name="logo"
            accept=".jpg,.jpeg,.png"
        >
        <br><br>

        <hr>

        <h3>Dokumen Verifikasi</h3>

        <?php if (!empty($profile["file_path"])): ?>

            <p>
                Dokumen saat ini:
                <a
                    href="../<?php echo htmlspecialchars($profile["file_path"]); ?>"
                    target="_blank"
                >
                    <?php echo htmlspecialchars($profile["document_name"]); ?>
                </a>
            </p>

        <?php else: ?>

            <p>Belum ada dokumen verifikasi yang diunggah.</p>

        <?php endif; ?>

        <label>Upload Dokumen Legal (PDF, maksimal 5 MB)</label><br>

        <input
            type="file"
            name="document"
            accept=".pdf"
        >
        <br><br>

        <button type="submit">Simpan Perubahan</button>

    </form>

    <br>

    <a href="dashboard.php">Kembali ke Dashboard</a>

</body>
</html>

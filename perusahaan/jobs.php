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
$edit_job = null;

// Mengambil status verifikasi perusahaan
$stmtCompany = $pdo->prepare(
    "SELECT verification_status
     FROM company_profiles
     WHERE user_id = ?"
);
$stmtCompany->execute([$user_id]);
$company = $stmtCompany->fetch(PDO::FETCH_ASSOC);

if (!$company) {
    die("Profil perusahaan tidak ditemukan.");
}

$is_verified = ($company["verification_status"] == "verified");

// Mengambil kategori pekerjaan
$stmtCategories = $pdo->query(
    "SELECT * FROM job_categories ORDER BY category_name ASC"
);
$categories = $stmtCategories->fetchAll(PDO::FETCH_ASSOC);

// Proses form
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $action = $_POST["action"] ?? "";

    // Menyimpan lowongan
    if ($action == "save") {

        $job_id = $_POST["job_id"] ?? "";
        $title = trim($_POST["title"]);
        $category_id = $_POST["category_id"];
        $department = trim($_POST["department"]);
        $job_type = $_POST["job_type"];
        $duration = trim($_POST["duration"]);
        $location = trim($_POST["location"]);
        $deadline = $_POST["deadline"];
        $description = trim($_POST["description"]);
        $qualifications = trim($_POST["qualifications"]);
        $status = $_POST["status"];

        if (
            empty($title) ||
            empty($category_id) ||
            empty($location) ||
            empty($description) ||
            empty($qualifications)
        ) {
            $message = "Mohon lengkapi semua data wajib!";

        } elseif (!in_array($job_type, ["Magang", "Full-time"])) {
            $message = "Tipe pekerjaan tidak valid!";

        } elseif (!in_array($status, ["draft", "published"])) {
            $message = "Status lowongan tidak valid!";

        } elseif (
            $deadline != "" &&
            $deadline < date("Y-m-d")
        ) {
            $message = "Deadline tidak boleh menggunakan tanggal yang sudah lewat!";

        } elseif ($status == "published" && !$is_verified) {
            $message = "Perusahaan belum terverifikasi. Simpan lowongan sebagai Draft terlebih dahulu!";

        } else {

            // Memastikan kategori tersedia
            $checkCategory = $pdo->prepare(
                "SELECT id FROM job_categories WHERE id = ?"
            );
            $checkCategory->execute([$category_id]);

            if (!$checkCategory->fetch()) {
                $message = "Kategori pekerjaan tidak ditemukan!";
            } else {

                try {

                    if ($job_id != "") {

                        // Memastikan lowongan milik perusahaan sendiri
                        $checkJob = $pdo->prepare(
                            "SELECT id FROM job_vacancies
                             WHERE id = ? AND company_id = ?"
                        );

                        $checkJob->execute([$job_id, $user_id]);

                        if (!$checkJob->fetch()) {
                            throw new Exception("Lowongan tidak ditemukan.");
                        }

                        $updateJob = $pdo->prepare(
                            "UPDATE job_vacancies
                             SET category_id = ?,
                                 title = ?,
                                 department = ?,
                                 job_type = ?,
                                 duration = ?,
                                 location = ?,
                                 deadline = ?,
                                 description = ?,
                                 qualifications = ?,
                                 status = ?,
                                 published_at = CASE
                                     WHEN ? = 'published'
                                     THEN COALESCE(published_at, NOW())
                                     ELSE published_at
                                 END
                             WHERE id = ? AND company_id = ?"
                        );

                        $updateJob->execute([
                            $category_id,
                            $title,
                            $department,
                            $job_type,
                            $duration,
                            $location,
                            $deadline == "" ? null : $deadline,
                            $description,
                            $qualifications,
                            $status,
                            $status,
                            $job_id,
                            $user_id
                        ]);

                        $message = "Lowongan berhasil diperbarui!";

                    } else {

                        // Menambahkan lowongan baru
                        $insertJob = $pdo->prepare(
                            "INSERT INTO job_vacancies
                             (company_id, category_id, title,
                              department, job_type, duration,
                              location, deadline, description,
                              qualifications, status, published_at)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                                CASE WHEN ? = 'published'
                                THEN NOW() ELSE NULL END)"
                        );

                        $insertJob->execute([
                            $user_id,
                            $category_id,
                            $title,
                            $department,
                            $job_type,
                            $duration,
                            $location,
                            $deadline == "" ? null : $deadline,
                            $description,
                            $qualifications,
                            $status,
                            $status
                        ]);

                        $message = "Lowongan berhasil ditambahkan!";
                    }

                } catch (Exception $e) {
                    $message = $e->getMessage();

                } catch (PDOException $e) {
                    $message = "Gagal menyimpan lowongan.";
                }
            }
        }
    }

    // Menutup lowongan
    if ($action == "close") {

        $job_id = $_POST["job_id"];

        $closeJob = $pdo->prepare(
            "UPDATE job_vacancies
             SET status = 'closed'
             WHERE id = ? AND company_id = ?
             AND status IN ('draft', 'published')"
        );

        $closeJob->execute([$job_id, $user_id]);

        if ($closeJob->rowCount() > 0) {
            $message = "Lowongan berhasil ditutup!";
        } else {
            $message = "Lowongan tidak ditemukan atau sudah ditutup.";
        }
    }
}

// Mengambil data lowongan untuk diedit
if (isset($_GET["edit"])) {

    $edit_id = $_GET["edit"];

    $stmtEdit = $pdo->prepare(
        "SELECT * FROM job_vacancies
         WHERE id = ? AND company_id = ?"
    );

    $stmtEdit->execute([$edit_id, $user_id]);
    $edit_job = $stmtEdit->fetch(PDO::FETCH_ASSOC);

    if (!$edit_job) {
        $message = "Lowongan tidak ditemukan.";
    }
}

// Mengambil semua lowongan milik perusahaan
$sqlJobs = "SELECT
                job_vacancies.*,
                job_categories.category_name
            FROM job_vacancies
            JOIN job_categories
                ON job_vacancies.category_id = job_categories.id
            WHERE job_vacancies.company_id = ?
            ORDER BY job_vacancies.created_at DESC";

$stmtJobs = $pdo->prepare($sqlJobs);
$stmtJobs->execute([$user_id]);
$jobs = $stmtJobs->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Kelola Lowongan CareerMap</title>
</head>

<body>

    <h2>Kelola Lowongan</h2>

    <p><?php echo htmlspecialchars($message); ?></p>

    <p>
        Status verifikasi:
        <strong>
            <?php echo htmlspecialchars($company["verification_status"]); ?>
        </strong>
    </p>

    <?php if (!$is_verified): ?>
        <p>
            Perusahaan belum terverifikasi.
            Kamu hanya dapat menyimpan lowongan sebagai Draft.
        </p>
    <?php endif; ?>

    <hr>

    <h3>
        <?php echo $edit_job ? "Edit Lowongan" : "Tambah Lowongan"; ?>
    </h3>

    <form method="POST">

        <input type="hidden" name="action" value="save">

        <input
            type="hidden"
            name="job_id"
            value="<?php echo htmlspecialchars($edit_job["id"] ?? ""); ?>"
        >

        <label>Judul Lowongan</label><br>
        <input
            type="text"
            name="title"
            value="<?php echo htmlspecialchars($edit_job["title"] ?? ""); ?>"
            required
        >
        <br><br>

        <label>Kategori Pekerjaan</label><br>
        <select name="category_id" required>

            <option value="">Pilih Kategori</option>

            <?php foreach ($categories as $category): ?>

                <option
                    value="<?php echo $category["id"]; ?>"
                    <?php
                    if (
                        isset($edit_job["category_id"]) &&
                        $edit_job["category_id"] == $category["id"]
                    ) {
                        echo "selected";
                    }
                    ?>
                >
                    <?php echo htmlspecialchars($category["category_name"]); ?>
                </option>

            <?php endforeach; ?>

        </select>
        <br><br>

        <label>Departemen</label><br>
        <input
            type="text"
            name="department"
            value="<?php echo htmlspecialchars($edit_job["department"] ?? ""); ?>"
        >
        <br><br>

        <label>Tipe Pekerjaan</label><br>
        <select name="job_type" required>

            <option value="Magang"
                <?php
                if (($edit_job["job_type"] ?? "") == "Magang") {
                    echo "selected";
                }
                ?>
            >
                Magang
            </option>

            <option value="Full-time"
                <?php
                if (($edit_job["job_type"] ?? "") == "Full-time") {
                    echo "selected";
                }
                ?>
            >
                Full-time
            </option>

        </select>
        <br><br>

        <label>Durasi</label><br>
        <input
            type="text"
            name="duration"
            placeholder="Contoh: 3 bulan"
            value="<?php echo htmlspecialchars($edit_job["duration"] ?? ""); ?>"
        >
        <br><br>

        <label>Lokasi</label><br>
        <input
            type="text"
            name="location"
            value="<?php echo htmlspecialchars($edit_job["location"] ?? ""); ?>"
            required
        >
        <br><br>

        <label>Deadline Lamaran</label><br>
        <input
            type="date"
            name="deadline"
            value="<?php echo htmlspecialchars($edit_job["deadline"] ?? ""); ?>"
        >
        <br><br>

        <label>Deskripsi Pekerjaan</label><br>
        <textarea name="description" rows="5" required><?php
            echo htmlspecialchars($edit_job["description"] ?? "");
        ?></textarea>
        <br><br>

        <label>Kualifikasi</label><br>
        <textarea name="qualifications" rows="5" required><?php
            echo htmlspecialchars($edit_job["qualifications"] ?? "");
        ?></textarea>
        <br><br>

        <label>Status Lowongan</label><br>
        <select name="status" required>

            <option value="draft"
                <?php
                if (($edit_job["status"] ?? "draft") == "draft") {
                    echo "selected";
                }
                ?>
            >
                Draft
            </option>

            <?php if ($is_verified): ?>

                <option value="published"
                    <?php
                    if (($edit_job["status"] ?? "") == "published") {
                        echo "selected";
                    }
                    ?>
                >
                    Aktif / Terbitkan
                </option>

            <?php endif; ?>

        </select>
        <br><br>

        <button type="submit">
            <?php echo $edit_job ? "Simpan Perubahan" : "Tambah Lowongan"; ?>
        </button>

        <?php if ($edit_job): ?>
            <a href="jobs.php">Batal Edit</a>
        <?php endif; ?>

    </form>

    <hr>

    <h3>Daftar Lowongan Saya</h3>

    <?php if (count($jobs) > 0): ?>

        <?php foreach ($jobs as $job): ?>

            <div>

                <h4>
                    <?php echo htmlspecialchars($job["title"]); ?>
                </h4>

                <p>
                    Kategori:
                    <?php echo htmlspecialchars($job["category_name"]); ?>
                </p>

                <p>
                    Tipe:
                    <?php echo htmlspecialchars($job["job_type"]); ?>
                </p>

                <p>
                    Lokasi:
                    <?php echo htmlspecialchars($job["location"]); ?>
                </p>

                <p>
                    Status:
                    <?php echo htmlspecialchars($job["status"]); ?>
                </p>

                <a href="jobs.php?edit=<?php echo $job["id"]; ?>">
                    Edit
                </a>

                <?php if (in_array($job["status"], ["draft", "published"])): ?>

                    <form method="POST">

                        <input type="hidden" name="action" value="close">

                        <input
                            type="hidden"
                            name="job_id"
                            value="<?php echo $job["id"]; ?>"
                        >

                        <button type="submit">
                            Tutup Lowongan
                        </button>

                    </form>

                <?php endif; ?>

                <hr>

            </div>

        <?php endforeach; ?>

    <?php else: ?>

        <p>Belum ada lowongan pekerjaan.</p>

    <?php endif; ?>

    <br>

    <a href="dashboard.php">Kembali ke Dashboard</a>

</body>
</html>


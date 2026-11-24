
<?php
session_start();

if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: ../auth/login.php");
    exit;
}

require_once "../config/database.php";

// Memeriksa ID perusahaan
if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    header("Location: verifikasi-perusahaan.php");
    exit;
}

$company_id = (int) $_GET["id"];
$pesan = "";
$error = "";

// Mengambil data perusahaan
$sql = "
    SELECT
        cp.*,
        u.email,
        u.username
    FROM company_profiles cp
    JOIN users u ON cp.user_id = u.id
    WHERE cp.user_id = :id
";

$stmt = $pdo->prepare($sql);
$stmt->execute(["id" => $company_id]);
$perusahaan = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$perusahaan) {
    die("Data perusahaan tidak ditemukan.");
}

// Proses verifikasi
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $aksi = $_POST["aksi"] ?? "";

    if ($aksi === "setujui") {

        $stmt = $pdo->prepare("
            UPDATE company_profiles
            SET verification_status = 'verified',
                verification_note = NULL,
                verified_at = NOW()
            WHERE user_id = :id
        ");

        $stmt->execute(["id" => $company_id]);

        header("Location: verifikasi-perusahaan.php?status=berhasil");
        exit;

    } elseif ($aksi === "tolak") {

        $alasan = trim($_POST["alasan"] ?? "");

        if ($alasan === "") {
            $error = "Alasan penolakan wajib diisi.";
        } else {

            $stmt = $pdo->prepare("
                UPDATE company_profiles
                SET verification_status = 'rejected',
                    verification_note = :alasan,
                    verified_at = NULL
                WHERE user_id = :id
            ");

            $stmt->execute([
                "alasan" => $alasan,
                "id" => $company_id
            ]);

            header("Location: verifikasi-perusahaan.php?status=ditolak");
            exit;
        }
    }
}

// Mengambil dokumen perusahaan
$stmt = $pdo->prepare("
    SELECT *
    FROM company_documents
    WHERE company_id = :id
    ORDER BY uploaded_at DESC
");

$stmt->execute(["id" => $company_id]);
$dokumen = $stmt->fetchAll(PDO::FETCH_ASSOC);

function e($value) {
    return htmlspecialchars((string) ($value ?? "-"), ENT_QUOTES, "UTF-8");
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Detail Perusahaan - CareerMap</title>

    <link rel="stylesheet" href="../assets/admin.css">
</head>

<body>

<div class="admin-layout">

    <?php include "../includes/admin-sidebar.php"; ?>


    <main class="main-content">

        <header class="page-header">

            <div>
                <a href="verifikasi-perusahaan.php" class="table-link">
                    ← Kembali ke Verifikasi Perusahaan
                </a>

                <h1>Detail Perusahaan</h1>

                <p>
                    Periksa informasi dan dokumen perusahaan sebelum
                    memberikan persetujuan.
                </p>
            </div>

        </header>


        <?php if ($error !== ""): ?>
            <div class="alert-error">
                <?= e($error) ?>
            </div>
        <?php endif; ?>


        <section class="content-card">

            <div class="card-heading">
                <h2>Informasi Perusahaan</h2>
            </div>

            <div class="company-detail-grid">

                <div>
                    <label>Nama Perusahaan</label>
                    <p><?= e($perusahaan["company_name"]) ?></p>
                </div>

                <div>
                    <label>Email</label>
                    <p><?= e($perusahaan["email"]) ?></p>
                </div>

                <div>
                    <label>Username</label>
                    <p><?= e($perusahaan["username"]) ?></p>
                </div>

                <div>
                    <label>Industri</label>
                    <p><?= e($perusahaan["industry"]) ?></p>
                </div>

                <div>
                    <label>Nomor Telepon</label>
                    <p><?= e($perusahaan["phone"]) ?></p>
                </div>

                <div>
                    <label>Website</label>
                    <p><?= e($perusahaan["website"]) ?></p>
                </div>

                <div>
                    <label>Tahun Berdiri</label>
                    <p><?= e($perusahaan["year_founded"]) ?></p>
                </div>

                <div>
                    <label>Alamat</label>
                    <p><?= e($perusahaan["address"]) ?></p>
                </div>

                <div class="full-width">
                    <label>Deskripsi Perusahaan</label>
                    <p><?= nl2br(e($perusahaan["description"])) ?></p>
                </div>

            </div>

        </section>


        <section class="content-card">

            <div class="card-heading">
                <h2>Dokumen Verifikasi</h2>
            </div>

            <?php if (!empty($perusahaan["logo_path"])): ?>

                <div class="document-item">
                    <div>
                        <strong>Logo Perusahaan</strong>
                    </div>

                    <a
                        href="../<?= e($perusahaan["logo_path"]) ?>"
                        target="_blank"
                        class="table-link"
                    >
                        Lihat Logo →
                    </a>
                </div>

            <?php endif; ?>


            <?php if (count($dokumen) > 0): ?>

                <?php foreach ($dokumen as $file): ?>

                    <div class="document-item">

                        <div>
                            <strong>
    <?= e($file["document_name"]) ?>
</strong>

<p>
    Status dokumen:
    <?= e($file["document_status"]) ?>
</p>

<?php if (!empty($file["admin_note"])): ?>
    <p>
        Catatan admin:
        <?= e($file["admin_note"]) ?>
    </p>
<?php endif; ?>
                        </div>

                        <a
                            href="../<?= e($file["file_path"]) ?>"
                            target="_blank"
                            class="table-link"
                        >
                            Lihat Dokumen →
                        </a>

                    </div>

                <?php endforeach; ?>

            <?php else: ?>

                <p>Belum ada dokumen verifikasi yang diunggah.</p>

            <?php endif; ?>

        </section>


        <section class="content-card">

            <div class="card-heading">
                <h2>Status Verifikasi</h2>
            </div>

            <p>
                Status saat ini:
                <strong>
                    <?= e($perusahaan["verification_status"]) ?>
                </strong>
            </p>

            <?php if ($perusahaan["verification_status"] === "pending"): ?>

                <div class="verification-actions">

                    <form method="POST">

                        <input
                            type="hidden"
                            name="aksi"
                            value="setujui"
                        >

                        <button
                            type="submit"
                            class="btn-approve"
                            onclick="return confirm('Setujui perusahaan ini?')"
                        >
                            Setujui Perusahaan
                        </button>

                    </form>


                    <form method="POST" class="reject-form">

                        <input
                            type="hidden"
                            name="aksi"
                            value="tolak"
                        >

                        <label for="alasan">Alasan Penolakan</label>

                        <textarea
                            name="alasan"
                            id="alasan"
                            rows="3"
                            placeholder="Tuliskan alasan penolakan..."
                            required
                        ></textarea>

                        <button type="submit" class="btn-reject">
                            Tolak Perusahaan
                        </button>

                    </form>

                </div>

            <?php else: ?>

                <p>
                    Perusahaan ini sudah diproses.
                    Status: <?= e($perusahaan["verification_status"]) ?>.
                </p>

                <?php if (!empty($perusahaan["verification_note"])): ?>
                    <p>
                        Alasan penolakan:
                        <?= e($perusahaan["verification_note"]) ?>
                    </p>
                <?php endif; ?>

            <?php endif; ?>

        </section>

    </main>

</div>

</body>
</html>


<?php
session_start();
require_once '../config/database.php';

// ========================================
// CEK LOGIN DAN ROLE
// ========================================

if (empty($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$user_id = (int) $_SESSION['user_id'];

try {
    $roleStmt = $pdo->prepare("
        SELECT role
        FROM users
        WHERE id = :uid
        LIMIT 1
    ");

    $roleStmt->execute([':uid' => $user_id]);

    if ($roleStmt->fetchColumn() !== 'company') {
        header("Location: ../auth/login.php");
        exit;
    }
} catch (PDOException $e) {
    die("Gagal memeriksa akses pengguna.");
}


// ========================================
// VARIABEL
// ========================================

$error = '';

$success = isset($_GET['saved'])
    ? 'Profil dan dokumen perusahaan berhasil diperbarui.'
    : '';

$is_edit_mode = isset($_GET['edit']) && $_GET['edit'] === '1';


// ========================================
// FUNGSI INISIAL PERUSAHAAN
// ========================================

function makeCompanyInitials(string $name): string
{
    $clean = trim($name);

    if ($clean === '') {
        return 'PR';
    }

    $parts = preg_split('/\s+/', $clean);

    $first = isset($parts[0][0])
        ? strtoupper($parts[0][0])
        : 'P';

    $second = isset($parts[1][0])
        ? strtoupper($parts[1][0])
        : '';

    return $first . $second;
}


// ========================================
// FUNGSI URL FILE
// ========================================

function getPublicFileSrc(string $relPath): string
{
    $trim = trim($relPath);

    if ($trim === '') {
        return '';
    }

    if (
        str_starts_with($trim, 'http://') ||
        str_starts_with($trim, 'https://')
    ) {
        return $trim;
    }

    return '../' . ltrim($trim, '/');
}


// ========================================
// UPLOAD LOGO PERUSAHAAN
// ========================================

function uploadCompanyLogo(array $file, int $userId): array
{
    if (
        !isset($file['error']) ||
        $file['error'] === UPLOAD_ERR_NO_FILE
    ) {
        return [
            'ok' => true,
            'path' => null
        ];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return [
            'ok' => false,
            'message' => 'Gagal mengunggah file logo.'
        ];
    }

    if (($file['size'] ?? 0) > 2 * 1024 * 1024) {
        return [
            'ok' => false,
            'message' => 'Ukuran logo maksimal 2 MB.'
        ];
    }

    $ext = strtolower(
        pathinfo($file['name'] ?? '', PATHINFO_EXTENSION)
    );

    $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];

    if (!in_array($ext, $allowedExt, true)) {
        return [
            'ok' => false,
            'message' => 'Format logo harus JPG, JPEG, PNG, atau WEBP.'
        ];
    }

    $uploadDir = dirname(__DIR__)
        . DIRECTORY_SEPARATOR . 'uploads'
        . DIRECTORY_SEPARATOR . 'perusahaan'
        . DIRECTORY_SEPARATOR . 'logos';

    if (
        !is_dir($uploadDir) &&
        !mkdir($uploadDir, 0775, true)
    ) {
        return [
            'ok' => false,
            'message' => 'Folder penyimpanan logo tidak dapat dibuat.'
        ];
    }

    $fileName = 'logo_company_' . $userId . '_' . time() . '.' . $ext;

    $destPath = $uploadDir . DIRECTORY_SEPARATOR . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        return [
            'ok' => false,
            'message' => 'Gagal memindahkan file logo ke folder tujuan.'
        ];
    }

    return [
        'ok' => true,
        'path' => 'uploads/perusahaan/logos/' . $fileName
    ];
}


// ========================================
// UPLOAD DOKUMEN VERIFIKASI
// ========================================

function uploadVerificationDocument(array $file, int $userId): array
{
    if (
        !isset($file['error']) ||
        $file['error'] === UPLOAD_ERR_NO_FILE
    ) {
        return [
            'ok' => true,
            'path' => null,
            'original_name' => null
        ];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return [
            'ok' => false,
            'message' => 'Gagal mengunggah dokumen verifikasi.'
        ];
    }

    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        return [
            'ok' => false,
            'message' => 'Ukuran dokumen verifikasi maksimal 5 MB.'
        ];
    }

    $origName = basename($file['name'] ?? 'dokumen.pdf');

    $ext = strtolower(
        pathinfo($origName, PATHINFO_EXTENSION)
    );

    $allowedExt = [
        'pdf',
        'jpg',
        'jpeg',
        'png',
        'doc',
        'docx'
    ];

    if (!in_array($ext, $allowedExt, true)) {
        return [
            'ok' => false,
            'message' => 'Format dokumen harus PDF, JPG, JPEG, PNG, DOC, atau DOCX.'
        ];
    }

    $uploadDir = dirname(__DIR__)
        . DIRECTORY_SEPARATOR . 'uploads'
        . DIRECTORY_SEPARATOR . 'perusahaan'
        . DIRECTORY_SEPARATOR . 'documents';

    if (
        !is_dir($uploadDir) &&
        !mkdir($uploadDir, 0775, true)
    ) {
        return [
            'ok' => false,
            'message' => 'Folder penyimpanan dokumen tidak dapat dibuat.'
        ];
    }

    $safeBase = preg_replace(
        '/[^A-Za-z0-9_-]/',
        '_',
        pathinfo($origName, PATHINFO_FILENAME)
    );

    $storedName = 'doc_company_'
        . $userId . '_'
        . time() . '_'
        . ($safeBase ?: 'file')
        . '.' . $ext;

    $destPath = $uploadDir . DIRECTORY_SEPARATOR . $storedName;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        return [
            'ok' => false,
            'message' => 'Gagal memindahkan file dokumen ke folder tujuan.'
        ];
    }

    return [
        'ok' => true,
        'path' => 'uploads/perusahaan/documents/' . $storedName,
        'original_name' => $origName
    ];
}


// ========================================
// SIMPAN RECORD DOKUMEN
// ========================================

function saveCompanyDocumentRecord(
    PDO $pdo,
    int $companyId,
    string $filePath,
    string $originalName
): void {

    $colsStmt = $pdo->query("
        SHOW COLUMNS FROM company_documents
    ");

    $cols = $colsStmt
        ? $colsStmt->fetchAll(PDO::FETCH_COLUMN)
        : [];

    if (empty($cols)) {
        return;
    }

    $ownerCol = in_array('company_id', $cols, true)
        ? 'company_id'
        : (
            in_array('user_id', $cols, true)
                ? 'user_id'
                : null
        );

    if ($ownerCol === null) {
        return;
    }

    $data = [
        $ownerCol => $companyId
    ];

    if (in_array('file_name', $cols, true)) {
        $data['file_name'] = $originalName;
    } elseif (in_array('document_name', $cols, true)) {
        $data['document_name'] = $originalName;
    } elseif (in_array('title', $cols, true)) {
        $data['title'] = $originalName;
    }

    if (in_array('file_path', $cols, true)) {
        $data['file_path'] = $filePath;
    } elseif (in_array('document_path', $cols, true)) {
        $data['document_path'] = $filePath;
    } elseif (in_array('path', $cols, true)) {
        $data['path'] = $filePath;
    }

    $placeholders = [];

    $params = [];

    foreach ($data as $k => $v) {
        $placeholders[] = ':' . $k;
        $params[':' . $k] = $v;
    }

    $sql = "
        INSERT INTO company_documents
        (" . implode(', ', array_keys($data)) . ")
        VALUES
        (" . implode(', ', $placeholders) . ")
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute($params);
}


// ========================================
// AMBIL DOKUMEN TERBARU
// ========================================

function getLatestCompanyDocument(
    PDO $pdo,
    int $companyId
): array {

    $result = [
        'label' => '',
        'path' => ''
    ];

    try {

        $colsStmt = $pdo->query("
            SHOW COLUMNS FROM company_documents
        ");

        $cols = $colsStmt
            ? $colsStmt->fetchAll(PDO::FETCH_COLUMN)
            : [];

        if (empty($cols)) {
            return $result;
        }

        $ownerCol = in_array('company_id', $cols, true)
            ? 'company_id'
            : (
                in_array('user_id', $cols, true)
                    ? 'user_id'
                    : null
            );

        if ($ownerCol === null) {
            return $result;
        }

        $orderCol = in_array('id', $cols, true)
            ? 'id DESC'
            : (
                in_array('created_at', $cols, true)
                    ? 'created_at DESC'
                    : ''
            );

        $orderSql = $orderCol !== ''
            ? " ORDER BY $orderCol"
            : "";

        $stmt = $pdo->prepare("
            SELECT *
            FROM company_documents
            WHERE $ownerCol = :cid
            $orderSql
            LIMIT 1
        ");

        $stmt->execute([
            ':cid' => $companyId
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {

            $pathVal = $row['file_path']
                ?? $row['document_path']
                ?? $row['path']
                ?? '';

            $nameVal = $row['file_name']
                ?? $row['document_name']
                ?? $row['title']
                ?? (
                    $pathVal !== ''
                        ? basename($pathVal)
                        : ''
                );

            $result['label'] = (string) $nameVal;

            $result['path'] = (string) $pathVal;
        }

    } catch (Throwable $e) {
        // Gunakan nilai kosong jika tabel belum tersedia
    }

    return $result;
}


// ========================================
// UPLOAD LOGO CEPAT
// ========================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['action']) &&
    $_POST['action'] === 'upload_logo_quick'
) {

    $uploadRes = uploadCompanyLogo(
        $_FILES['quick_logo'] ?? [],
        $user_id
    );

    if (!$uploadRes['ok']) {

        $error = $uploadRes['message'];

    } elseif (!empty($uploadRes['path'])) {

        try {

            $chk = $pdo->prepare("
                SELECT user_id
                FROM company_profiles
                WHERE user_id = :uid
                LIMIT 1
            ");

            $chk->execute([
                ':uid' => $user_id
            ]);

            if ($chk->fetchColumn()) {

                $upd = $pdo->prepare("
                    UPDATE company_profiles
                    SET logo_path = :logo
                    WHERE user_id = :uid
                ");

                $upd->execute([
                    ':logo' => $uploadRes['path'],
                    ':uid' => $user_id
                ]);

            } else {

                $ins = $pdo->prepare("
                    INSERT INTO company_profiles
                    (
                        user_id,
                        company_name,
                        logo_path
                    )
                    VALUES
                    (
                        :uid,
                        'Perusahaan',
                        :logo
                    )
                ");

                $ins->execute([
                    ':uid' => $user_id,
                    ':logo' => $uploadRes['path']
                ]);
            }

            header("Location: profile.php?saved=1");
            exit;

        } catch (Throwable $e) {

            $error = 'Gagal menyimpan logo: ' . $e->getMessage();

        }
    }
}


// ========================================
// SIMPAN PERUBAHAN PROFIL
// ========================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['action']) &&
    $_POST['action'] === 'save_profile'
) {

    $is_edit_mode = true;

    $company_name = trim($_POST['company_name'] ?? '');

    $email = trim($_POST['email'] ?? '');

    $website = trim($_POST['website'] ?? '');

    $industry = trim($_POST['industry'] ?? '');

    $description = trim($_POST['description'] ?? '');

    $address = trim($_POST['address'] ?? '');

    $phone = trim($_POST['phone'] ?? '');


    if ($company_name === '') {

        $error = 'Nama perusahaan wajib diisi.';

    } elseif (
        $email !== '' &&
        !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {

        $error = 'Format email perusahaan tidak valid.';

    } else {

        $logoRes = uploadCompanyLogo(
            $_FILES['logo_file'] ?? [],
            $user_id
        );

        $docRes = uploadVerificationDocument(
            $_FILES['verification_doc'] ?? [],
            $user_id
        );


        if (!$logoRes['ok']) {

            $error = $logoRes['message'];

        } elseif (!$docRes['ok']) {

            $error = $docRes['message'];

        } else {

            try {

                // Ambil logo lama
                $logoStmt = $pdo->prepare("
                    SELECT logo_path
                    FROM company_profiles
                    WHERE user_id = :uid
                    LIMIT 1
                ");

                $logoStmt->execute([
                    ':uid' => $user_id
                ]);

                $existingRow = $logoStmt->fetch(PDO::FETCH_ASSOC);

                $finalLogo = !empty($logoRes['path'])
                    ? $logoRes['path']
                    : ($existingRow['logo_path'] ?? null);


                // Update atau insert profil
                if ($existingRow) {

                    $stmt = $pdo->prepare("
                        UPDATE company_profiles
                        SET
                            company_name = :cname,
                            industry = :industry,
                            address = :address,
                            phone = :phone,
                            website = :website,
                            description = :description,
                            logo_path = :logo_path
                        WHERE user_id = :uid
                    ");

                    $stmt->execute([

                        ':cname' => $company_name,

                        ':industry' => $industry !== ''
                            ? $industry
                            : null,

                        ':address' => $address !== ''
                            ? $address
                            : null,

                        ':phone' => $phone !== ''
                            ? $phone
                            : null,

                        ':website' => $website !== ''
                            ? $website
                            : null,

                        ':description' => $description !== ''
                            ? $description
                            : null,

                        ':logo_path' => $finalLogo,

                        ':uid' => $user_id

                    ]);

                } else {

                    $stmt = $pdo->prepare("
                        INSERT INTO company_profiles
                        (
                            user_id,
                            company_name,
                            industry,
                            address,
                            phone,
                            website,
                            description,
                            logo_path
                        )
                        VALUES
                        (
                            :uid,
                            :cname,
                            :industry,
                            :address,
                            :phone,
                            :website,
                            :description,
                            :logo_path
                        )
                    ");

                    $stmt->execute([

                        ':uid' => $user_id,

                        ':cname' => $company_name,

                        ':industry' => $industry !== ''
                            ? $industry
                            : null,

                        ':address' => $address !== ''
                            ? $address
                            : null,

                        ':phone' => $phone !== ''
                            ? $phone
                            : null,

                        ':website' => $website !== ''
                            ? $website
                            : null,

                        ':description' => $description !== ''
                            ? $description
                            : null,

                        ':logo_path' => $finalLogo

                    ]);
                }


                // Update email pada tabel users
                if ($email !== '') {

                    $uStmt = $pdo->prepare("
                        UPDATE users
                        SET email = :email
                        WHERE id = :uid
                    ");

                    $uStmt->execute([
                        ':email' => $email,
                        ':uid' => $user_id
                    ]);
                }


                // Simpan dokumen verifikasi
                if (!empty($docRes['path'])) {

                    saveCompanyDocumentRecord(
                        $pdo,
                        $user_id,
                        $docRes['path'],
                        $docRes['original_name']
                            ?? basename($docRes['path'])
                    );
                }


                header("Location: profile.php?saved=1");
                exit;

            } catch (Throwable $e) {

                $error = 'Gagal menyimpan perubahan profil: ' . $e->getMessage();

            }
        }
    }
}


// ========================================
// DATA AWAL PROFIL
// ========================================

$profile = [
    'company_name'        => '',
    'email'               => '',
    'website'             => '',
    'industry'            => '',
    'document_name'       => '',
    'document_path'       => '',
    'description'         => '',
    'address'             => '',
    'phone'               => '',
    'logo_path'           => '',
    'verification_status' => 'pending',
];


// ========================================
// AMBIL DATA PROFIL
// ========================================

try {

    // Data akun
    $uStmt = $pdo->prepare("
        SELECT name, email
        FROM users
        WHERE id = :uid
        LIMIT 1
    ");

    $uStmt->execute([
        ':uid' => $user_id
    ]);

    if ($uRow = $uStmt->fetch(PDO::FETCH_ASSOC)) {

        $profile['email'] = $uRow['email'] ?? '';

        $profile['company_name'] = $uRow['name'] ?? '';
    }


    // Data perusahaan
    $cStmt = $pdo->prepare("
        SELECT *
        FROM company_profiles
        WHERE user_id = :uid
        LIMIT 1
    ");

    $cStmt->execute([
        ':uid' => $user_id
    ]);

    if ($cRow = $cStmt->fetch(PDO::FETCH_ASSOC)) {

        $profile = array_merge($profile, $cRow);
    }


    // Dokumen terbaru
    $latestDoc = getLatestCompanyDocument(
        $pdo,
        $user_id
    );

    $profile['document_name'] = $latestDoc['label'];

    $profile['document_path'] = $latestDoc['path'];

} catch (Throwable $e) {

    $error = 'Gagal memuat profil perusahaan: ' . $e->getMessage();

}


// ========================================
// DATA UNTUK TAMPILAN
// ========================================

$displayName = $profile['company_name'] !== ''
    ? $profile['company_name']
    : 'Perusahaan';

$initials = makeCompanyInitials($displayName);

$logoSrc = getPublicFileSrc(
    $profile['logo_path'] ?? ''
);

$docSrc = getPublicFileSrc(
    $profile['document_path'] ?? ''
);

$vStatus = $profile['verification_status'] ?? 'pending';


if ($vStatus === 'verified') {

    $badgeClass = 'badge-verified';

    $badgeLabel = 'Terverifikasi';

} elseif ($vStatus === 'rejected') {

    $badgeClass = 'badge-rejected';

    $badgeLabel = 'Ditolak';

} else {

    $badgeClass = 'badge-pending';

    $badgeLabel = 'Menunggu Verifikasi';

}

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        <?= $is_edit_mode
            ? 'Edit Profil Saya'
            : 'Profil Saya'
        ?> - CareerMap
    </title>

    <link
        rel="stylesheet"
        href="../assets/css/profile-perusahaan.css?v=2"
    >

    <link
        rel="stylesheet"
        href="../assets/css/company-sidebar.css?v=1"
    >

</head>

<body>

    <!-- SIDEBAR PERUSAHAAN -->

    <?php require_once '../includes/company-sidebar.php'; ?>


    <!-- KONTEN UTAMA -->

    <main class="main-content">

        <?php if ($is_edit_mode): ?>

            <!-- ==================================
                 EDIT PROFIL
                 ================================== -->

            <div class="page-header">

                <h1 class="page-title">
                    Edit Profil Saya
                </h1>

                <p class="page-subtitle">
                    Ubah informasi profil yang ditampilkan.
                </p>

            </div>


            <?php if ($error !== ''): ?>

                <div class="alert alert-error">
                    <?= htmlspecialchars($error) ?>
                </div>

            <?php endif; ?>


            <section class="profile-outer-card">

                <div class="edit-inner-card">

                    <h2 class="edit-card-heading">
                        Edit Informasi Profil
                    </h2>

                    <p class="edit-card-sub">
                        Perbarui informasi perusahaanmu.
                    </p>


                    <form
                        action="profile.php?edit=1"
                        method="POST"
                        enctype="multipart/form-data"
                    >

                        <input
                            type="hidden"
                            name="action"
                            value="save_profile"
                        >


                        <div class="edit-grid">

                            <!-- Nama perusahaan -->

                            <div class="form-group">

                                <label
                                    for="company_name"
                                    class="form-label"
                                >
                                    Nama Perusahaan
                                </label>

                                <input
                                    type="text"
                                    id="company_name"
                                    name="company_name"
                                    class="form-control"
                                    value="<?= htmlspecialchars(
                                        $_POST['company_name']
                                        ?? $profile['company_name']
                                    ) ?>"
                                    required
                                >

                            </div>


                            <!-- Email -->

                            <div class="form-group">

                                <label
                                    for="email"
                                    class="form-label"
                                >
                                    Email Perusahaan
                                </label>

                                <input
                                    type="email"
                                    id="email"
                                    name="email"
                                    class="form-control"
                                    value="<?= htmlspecialchars(
                                        $_POST['email']
                                        ?? $profile['email']
                                    ) ?>"
                                >

                            </div>


                            <!-- Website -->

                            <div class="form-group">

                                <label
                                    for="website"
                                    class="form-label"
                                >
                                    Website
                                </label>

                                <input
                                    type="text"
                                    id="website"
                                    name="website"
                                    class="form-control"
                                    value="<?= htmlspecialchars(
                                        $_POST['website']
                                        ?? ($profile['website'] ?? '')
                                    ) ?>"
                                >

                            </div>


                            <!-- Industri -->

                            <div class="form-group">

                                <label
                                    for="industry"
                                    class="form-label"
                                >
                                    Bidang Industri
                                </label>

                                <input
                                    type="text"
                                    id="industry"
                                    name="industry"
                                    class="form-control"
                                    value="<?= htmlspecialchars(
                                        $_POST['industry']
                                        ?? ($profile['industry'] ?? '')
                                    ) ?>"
                                >

                            </div>


                            <!-- Dokumen verifikasi -->

                            <div class="form-group">

                                <span class="form-label">
                                    Dokumen Verifikasi
                                </span>

                                <input
                                    type="file"
                                    id="verificationDocInput"
                                    name="verification_doc"
                                    accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                                    style="display:none;"
                                >

                                <div class="doc-upload-row">

                                    <button
                                        type="button"
                                        class="btn-outline-action"
                                        id="btnPilihDokumen"
                                    >
                                        Pilih File Dokumen
                                    </button>

                                    <span
                                        class="doc-file-name"
                                        id="selectedDocName"
                                    >
                                        <?= htmlspecialchars(
                                            $profile['document_name']
                                            ?: 'Belum ada file dipilih (PDF/JPG/PNG/DOC)'
                                        ) ?>
                                    </span>

                                </div>


                                <?php if ($docSrc !== ''): ?>

                                    <a
                                        href="<?= htmlspecialchars($docSrc) ?>"
                                        target="_blank"
                                        class="doc-current-link"
                                    >
                                        Lihat dokumen tersimpan saat ini
                                    </a>

                                <?php endif; ?>

                            </div>


                            <!-- Deskripsi -->

                            <div class="form-group">

                                <label
                                    for="description"
                                    class="form-label"
                                >
                                    Deskripsi Perusahaan
                                </label>

                                <input
                                    type="text"
                                    id="description"
                                    name="description"
                                    class="form-control"
                                    value="<?= htmlspecialchars(
                                        $_POST['description']
                                        ?? ($profile['description'] ?? '')
                                    ) ?>"
                                >

                            </div>


                            <!-- Alamat -->

                            <div class="form-group">

                                <label
                                    for="address"
                                    class="form-label"
                                >
                                    Alamat
                                </label>

                                <input
                                    type="text"
                                    id="address"
                                    name="address"
                                    class="form-control"
                                    value="<?= htmlspecialchars(
                                        $_POST['address']
                                        ?? ($profile['address'] ?? '')
                                    ) ?>"
                                >

                            </div>


                            <!-- Telepon -->

                            <div class="form-group">

                                <label
                                    for="phone"
                                    class="form-label"
                                >
                                    Nomor Telepon
                                </label>

                                <input
                                    type="text"
                                    id="phone"
                                    name="phone"
                                    class="form-control"
                                    value="<?= htmlspecialchars(
                                        $_POST['phone']
                                        ?? ($profile['phone'] ?? '')
                                    ) ?>"
                                >

                            </div>

                        </div>


                        <!-- FOTO PROFIL -->

                        <div class="photo-edit-section">

                            <span class="form-label">
                                Foto Profil
                            </span>


                            <div class="edit-bottom-bar">

                                <div
                                    class="photo-edit-row"
                                    style="margin-top:0;"
                                >

                                    <div
                                        class="photo-square-box"
                                        id="editSquarePreview"
                                    >

                                        <?php if ($logoSrc !== ''): ?>

                                            <img
                                                src="<?= htmlspecialchars($logoSrc) ?>"
                                                alt="Logo Perusahaan"
                                            >

                                        <?php else: ?>

                                            <?= htmlspecialchars($initials) ?>

                                        <?php endif; ?>

                                    </div>


                                    <input
                                        type="file"
                                        id="logoFileInput"
                                        name="logo_file"
                                        accept=".jpg,.jpeg,.png,.webp"
                                        style="display:none;"
                                    >


                                    <button
                                        type="button"
                                        class="btn-outline-action"
                                        id="btnGantiFoto"
                                    >
                                        Ganti Foto
                                    </button>

                                </div>


                                <div class="edit-action-buttons">

                                    <a
                                        href="profile.php"
                                        class="btn-cancel"
                                    >
                                        Batal
                                    </a>

                                    <button
                                        type="submit"
                                        class="btn-save"
                                    >
                                        Simpan
                                    </button>

                                </div>

                            </div>

                        </div>

                    </form>

                </div>

            </section>


        <?php else: ?>


            <!-- ==================================
                 TAMPILAN PROFIL
                 ================================== -->

            <div class="page-header">

                <h1 class="page-title">
                    Profil Saya
                </h1>

                <p class="page-subtitle">
                    Kelola informasi pribadi, foto profil, dan dokumen perusahaan.
                </p>

            </div>


            <?php if ($error !== ''): ?>

                <div class="alert alert-error">
                    <?= htmlspecialchars($error) ?>
                </div>

            <?php endif; ?>


            <?php if ($success !== ''): ?>

                <div class="alert alert-success">
                    <?= htmlspecialchars($success) ?>
                </div>

            <?php endif; ?>


            <section class="profile-outer-card">

                <div class="profile-view-grid">


                    <!-- KARTU RINGKASAN PROFIL -->

                    <div class="profile-summary-card">

                        <div class="profile-avatar-circle">

                            <?php if ($logoSrc !== ''): ?>

                                <img
                                    src="<?= htmlspecialchars($logoSrc) ?>"
                                    alt="Logo Perusahaan"
                                >

                            <?php else: ?>

                                <?= htmlspecialchars($initials) ?>

                            <?php endif; ?>

                        </div>


                        <div class="summary-company-name">

                            <?= htmlspecialchars($displayName) ?>

                        </div>


                        <div class="summary-meta">

                            <?= htmlspecialchars($profile['industry'] ?: '-') ?>

                            <?php if (!empty($profile['address'])): ?>

                                &bull;

                                <?= htmlspecialchars($profile['address']) ?>

                            <?php endif; ?>

                        </div>


                        <span class="verify-badge <?= $badgeClass ?>">

                            <?= htmlspecialchars($badgeLabel) ?>

                        </span>


                        <div class="summary-details">

                            <div>

                                <div class="summary-item-label">
                                    Alamat
                                </div>

                                <div class="summary-item-val">
                                    <?= htmlspecialchars($profile['address'] ?: '-') ?>
                                </div>

                            </div>


                            <div>

                                <div class="summary-item-label">
                                    Nomor Telepon
                                </div>

                                <div class="summary-item-val">
                                    <?= htmlspecialchars($profile['phone'] ?: '-') ?>
                                </div>

                            </div>


                            <div>

                                <div class="summary-item-label">
                                    Deskripsi Perusahaan
                                </div>

                                <div class="summary-item-val">
                                    <?= htmlspecialchars($profile['description'] ?: '-') ?>
                                </div>

                            </div>

                        </div>

                    </div>


                    <!-- INFORMASI UMUM -->

                    <div class="profile-info-card">

                        <div class="info-card-top">

                            <h2 class="info-card-title">
                                Informasi Umum
                            </h2>

                            <a
                                href="profile.php?edit=1"
                                class="btn-outline-action"
                            >
                                Edit Profil
                            </a>

                        </div>


                        <div class="view-fields-stack">


                            <div class="view-field-group">

                                <span class="view-field-label">
                                    Nama Perusahaan
                                </span>

                                <div class="view-field-box">

                                    <?= htmlspecialchars($displayName) ?>

                                </div>

                            </div>


                            <div class="view-field-group">

                                <span class="view-field-label">
                                    Email Perusahaan
                                </span>

                                <div class="view-field-box">

                                    <?= htmlspecialchars($profile['email'] ?: '-') ?>

                                </div>

                            </div>


                            <div class="view-field-group">

                                <span class="view-field-label">
                                    Website
                                </span>

                                <div class="view-field-box <?= empty($profile['website']) ? 'muted-box' : '' ?>">

                                    <?= htmlspecialchars($profile['website'] ?: '-') ?>

                                </div>

                            </div>


                            <div class="view-field-group">

                                <span class="view-field-label">
                                    Bidang Industri
                                </span>

                                <div class="view-field-box">

                                    <?= htmlspecialchars($profile['industry'] ?: '-') ?>

                                </div>

                            </div>


                            <div class="view-field-group">

                                <span class="view-field-label">
                                    Dokumen Verifikasi
                                </span>

                                <div class="view-field-box <?= empty($profile['document_name']) ? 'muted-box' : '' ?>">

                                    <?php if (!empty($profile['document_name']) && $docSrc !== ''): ?>

                                        <a
                                            href="<?= htmlspecialchars($docSrc) ?>"
                                            target="_blank"
                                            class="doc-current-link"
                                        >
                                            <?= htmlspecialchars($profile['document_name']) ?>
                                        </a>

                                    <?php else: ?>

                                        <?= htmlspecialchars($profile['document_name'] ?: '-') ?>

                                    <?php endif; ?>

                                </div>

                            </div>

                        </div>


                        <!-- UPLOAD LOGO CEPAT -->

                        <form
                            action="profile.php"
                            method="POST"
                            enctype="multipart/form-data"
                            id="quickLogoForm"
                        >

                            <input
                                type="hidden"
                                name="action"
                                value="upload_logo_quick"
                            >

                            <input
                                type="file"
                                name="quick_logo"
                                id="quickLogoInput"
                                accept=".jpg,.jpeg,.png,.webp"
                                style="display:none;"
                            >

                            <div class="upload-logo-row">

                                <button
                                    type="button"
                                    class="btn-outline-action"
                                    id="btnUnggahLogo"
                                >
                                    Unggah Logo
                                </button>

                            </div>

                        </form>

                    </div>

                </div>

            </section>

        <?php endif; ?>

    </main>


    <!-- ==================================
         JAVASCRIPT
         ================================== -->

    <script>

        (function () {

            // Upload logo cepat
            const btnUnggahLogo =
                document.getElementById('btnUnggahLogo');

            const quickLogoInput =
                document.getElementById('quickLogoInput');

            const quickLogoForm =
                document.getElementById('quickLogoForm');


            if (
                btnUnggahLogo &&
                quickLogoInput &&
                quickLogoForm
            ) {

                btnUnggahLogo.addEventListener('click', () => {
                    quickLogoInput.click();
                });

                quickLogoInput.addEventListener('change', () => {

                    if (
                        quickLogoInput.files &&
                        quickLogoInput.files.length > 0
                    ) {
                        quickLogoForm.submit();
                    }

                });
            }


            // Ganti foto profil
            const btnGantiFoto =
                document.getElementById('btnGantiFoto');

            const logoFileInput =
                document.getElementById('logoFileInput');

            const editSquarePreview =
                document.getElementById('editSquarePreview');


            if (btnGantiFoto && logoFileInput) {

                btnGantiFoto.addEventListener('click', () => {
                    logoFileInput.click();
                });

                logoFileInput.addEventListener('change', () => {

                    const file = logoFileInput.files &&
                        logoFileInput.files[0];

                    if (!file || !editSquarePreview) {
                        return;
                    }

                    const reader = new FileReader();

                    reader.onload = (ev) => {

                        editSquarePreview.innerHTML =
                            '<img src="' +
                            ev.target.result +
                            '" alt="Preview Logo">';

                    };

                    reader.readAsDataURL(file);

                });

            }


            // Pilih dokumen verifikasi
            const btnPilihDokumen =
                document.getElementById('btnPilihDokumen');

            const verificationDocInput =
                document.getElementById('verificationDocInput');

            const selectedDocName =
                document.getElementById('selectedDocName');


            if (btnPilihDokumen && verificationDocInput) {

                btnPilihDokumen.addEventListener('click', () => {
                    verificationDocInput.click();
                });

                verificationDocInput.addEventListener('change', () => {

                    const file = verificationDocInput.files &&
                        verificationDocInput.files[0];

                    if (file && selectedDocName) {
                        selectedDocName.textContent = file.name;
                    }

                });

            }

        })();

    </script>

</body>
</html>

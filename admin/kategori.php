
<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

require_once "../config/database.php";

$message = "";
$message_type = "";

// Proses tambah kategori
if (isset($_POST['tambah'])) {

    $category_name = trim($_POST['category_name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($category_name === '') {
        $message = "Nama kategori wajib diisi.";
        $message_type = "error";
    } else {

        $check = $pdo->prepare("
            SELECT id
            FROM job_categories
            WHERE LOWER(category_name) = LOWER(:name)
        ");

        $check->execute(['name' => $category_name]);

        if ($check->fetch()) {
            $message = "Nama kategori sudah tersedia.";
            $message_type = "error";
        } else {

            $insert = $pdo->prepare("
                INSERT INTO job_categories
                    (category_name, description)
                VALUES
                    (:name, :description)
            ");

            $insert->execute([
                'name' => $category_name,
                'description' => $description !== '' ? $description : null
            ]);

            header("Location: kategori.php?success=tambah");
            exit;
        }
    }
}

// Proses edit kategori
if (isset($_POST['edit'])) {

    $id = (int) ($_POST['id'] ?? 0);
    $category_name = trim($_POST['category_name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($id <= 0 || $category_name === '') {
        $message = "Data kategori tidak valid.";
        $message_type = "error";
    } else {

        $check = $pdo->prepare("
            SELECT id
            FROM job_categories
            WHERE LOWER(category_name) = LOWER(:name)
            AND id != :id
        ");

        $check->execute([
            'name' => $category_name,
            'id' => $id
        ]);

        if ($check->fetch()) {
            $message = "Nama kategori sudah digunakan kategori lain.";
            $message_type = "error";
        } else {

            $update = $pdo->prepare("
                UPDATE job_categories
                SET category_name = :name,
                    description = :description
                WHERE id = :id
            ");

            $update->execute([
                'name' => $category_name,
                'description' => $description !== '' ? $description : null,
                'id' => $id
            ]);

            header("Location: kategori.php?success=edit");
            exit;
        }
    }
}

// Proses hapus kategori
if (isset($_POST['hapus'])) {

    $id = (int) ($_POST['id'] ?? 0);

    $check = $pdo->prepare("
        SELECT COUNT(*)
        FROM job_vacancies
        WHERE category_id = :id
    ");

    $check->execute(['id' => $id]);
    $total_jobs = (int) $check->fetchColumn();

    if ($total_jobs > 0) {

        $message = "Kategori tidak dapat dihapus karena masih digunakan oleh lowongan.";
        $message_type = "error";

    } else {

        $delete = $pdo->prepare("
            DELETE FROM job_categories
            WHERE id = :id
        ");

        $delete->execute(['id' => $id]);

        header("Location: kategori.php?success=hapus");
        exit;
    }
}

// Pesan keberhasilan
if (isset($_GET['success'])) {

    $success_messages = [
        'tambah' => 'Kategori berhasil ditambahkan.',
        'edit' => 'Kategori berhasil diperbarui.',
        'hapus' => 'Kategori berhasil dihapus.'
    ];

    $message = $success_messages[$_GET['success']] ?? '';
    $message_type = 'success';
}

// Pencarian kategori
$keyword = trim($_GET['keyword'] ?? '');

if ($keyword !== '') {

    $stmt = $pdo->prepare("
        SELECT *
        FROM job_categories
        WHERE category_name LIKE :keyword
        ORDER BY id DESC
    ");

    $stmt->execute([
        'keyword' => "%$keyword%"
    ]);

} else {

    $stmt = $pdo->query("
        SELECT *
        FROM job_categories
        ORDER BY id DESC
    ");
}

$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Mengambil jumlah lowongan setiap kategori
$job_count_stmt = $pdo->query("
    SELECT category_id, COUNT(*) AS total
    FROM job_vacancies
    GROUP BY category_id
");

$job_counts = [];

foreach ($job_count_stmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
    $job_counts[$item['category_id']] = $item['total'];
}

// Fungsi keamanan output
function e($value) {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manajemen Kategori - CareerMap</title>

    <link rel="stylesheet" href="../assets/admin.css">
</head>

<body>

<div class="admin-layout">

    <!-- SIDEBAR -->
    <?php include "../includes/admin-sidebar.php"; ?>


    <!-- MAIN CONTENT -->
    <main class="main-content">

        <div class="page-header">

            <div>
                <h1>Manajemen Kategori</h1>

                <p>
                    Kelola kategori lowongan pekerjaan CareerMap.
                </p>
            </div>

        </div>


        <!-- PESAN -->
        <?php if ($message !== ''): ?>

            <div class="content-card"
                style="margin-bottom:20px;">

                <p style="color: <?= $message_type === 'success' ? '#15803d' : '#dc2626' ?>;">
                    <?= e($message) ?>
                </p>

            </div>

        <?php endif; ?>


        <!-- FORM TAMBAH KATEGORI -->
        <section class="content-card">

            <div class="card-heading">
                <h2>Tambah Kategori</h2>
            </div>

            <form method="POST">

                <div style="margin-bottom:15px;">

                    <label for="category_name">
                        Nama Kategori
                    </label>

                    <input
                        type="text"
                        id="category_name"
                        name="category_name"
                        maxlength="100"
                        placeholder="Contoh: Teknologi Informasi"
                        required
                        style="width:100%; padding:10px; margin-top:8px;"
                    >

                </div>

                <div style="margin-bottom:15px;">

                    <label for="description">
                        Deskripsi
                    </label>

                    <textarea
                        id="description"
                        name="description"
                        rows="3"
                        placeholder="Deskripsi singkat kategori..."
                        style="width:100%; padding:10px; margin-top:8px;"
                    ></textarea>

                </div>

                <button
                    type="submit"
                    name="tambah"
                    class="btn-approve"
                >
                    Tambah Kategori
                </button>

            </form>

        </section>


        <!-- DAFTAR KATEGORI -->
        <section class="content-card">

            <div class="card-heading">

                <div>
                    <h2>Daftar Kategori</h2>

                    <p>
                        Total <?= count($categories) ?> kategori ditampilkan.
                    </p>
                </div>

            </div>


            <!-- PENCARIAN -->
            <form method="GET" class="filter-form">

                <input
                    type="text"
                    name="keyword"
                    placeholder="Cari nama kategori..."
                    value="<?= e($keyword) ?>"
                >

                <button type="submit" class="btn-approve">
                    Cari
                </button>

                <a href="kategori.php" class="table-link">
                    Reset
                </a>

            </form>


            <!-- TABEL KATEGORI -->
            <div class="table-wrapper">

                <table>

                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama Kategori</th>
                            <th>Deskripsi</th>
                            <th>Jumlah Lowongan</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if (count($categories) > 0): ?>

                        <?php $no = 1; ?>

                        <?php foreach ($categories as $category): ?>

                            <?php
                            $total = $job_counts[$category['id']] ?? 0;
                            ?>

                            <tr>

                                <td><?= $no++ ?></td>

                                <td>
                                    <strong>
                                        <?= e($category['category_name']) ?>
                                    </strong>
                                </td>

                                <td>
                                    <?= e($category['description'] ?: '-') ?>
                                </td>

                                <td>
                                    <?= $total ?> lowongan
                                </td>

                                <td>

                                    <button
                                        type="button"
                                        class="table-link"
                                        onclick="editCategory(
                                            <?= (int) $category['id'] ?>,
                                            <?= htmlspecialchars(json_encode($category['category_name']), ENT_QUOTES, 'UTF-8') ?>,
                                            <?= htmlspecialchars(json_encode($category['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                        )"
                                    >
                                        Edit
                                    </button>

                                    <form
                                        method="POST"
                                        style="display:inline;"
                                        onsubmit="return confirm('Yakin ingin menghapus kategori ini?');"
                                    >

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int) $category['id'] ?>"
                                        >

                                        <button
                                            type="submit"
                                            name="hapus"
                                            class="table-link"
                                            style="color:#dc2626;"
                                        >
                                            Hapus
                                        </button>

                                    </form>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="5" style="text-align:center; padding:30px;">
                                Belum ada kategori yang ditemukan.
                            </td>
                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>

    </main>

</div>


<!-- MODAL EDIT KATEGORI -->
<div
    id="editModal"
    style="
        display:none;
        position:fixed;
        inset:0;
        background:rgba(0,0,0,0.5);
        align-items:center;
        justify-content:center;
        padding:20px;
        z-index:1000;
    "
>

    <div
        class="content-card"
        style="width:100%; max-width:500px;"
    >

        <div class="card-heading">
            <h2>Edit Kategori</h2>
        </div>

        <form method="POST">

            <input
                type="hidden"
                name="id"
                id="edit_id"
            >

            <div style="margin-bottom:15px;">

                <label for="edit_name">
                    Nama Kategori
                </label>

                <input
                    type="text"
                    name="category_name"
                    id="edit_name"
                    maxlength="100"
                    required
                    style="width:100%; padding:10px; margin-top:8px;"
                >

            </div>

            <div style="margin-bottom:15px;">

                <label for="edit_description">
                    Deskripsi
                </label>

                <textarea
                    name="description"
                    id="edit_description"
                    rows="3"
                    style="width:100%; padding:10px; margin-top:8px;"
                ></textarea>

            </div>

            <button
                type="submit"
                name="edit"
                class="btn-approve"
            >
                Simpan Perubahan
            </button>

            <button
                type="button"
                onclick="closeModal()"
                class="btn-reject"
            >
                Batal
            </button>

        </form>

    </div>

</div>


<script>

function editCategory(id, name, description) {

    document.getElementById('edit_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_description').value = description;

    document.getElementById('editModal').style.display = 'flex';
}

function closeModal() {

    document.getElementById('editModal').style.display = 'none';
}

</script>

</body>
</html>

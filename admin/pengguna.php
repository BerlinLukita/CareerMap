<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

require_once "../config/database.php";

// Pengaturan pagination
$per_page = 4;
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$offset = ($page - 1) * $per_page;

// Filter pencarian
$keyword = trim($_GET['keyword'] ?? '');
$role = $_GET['role'] ?? '';
$status = $_GET['status'] ?? '';

$where = [];
$params = [];

// Pencarian nama, email, NIM, atau nama perusahaan
if ($keyword !== '') {
    $where[] = "(
        u.name LIKE :name
        OR u.email LIKE :email
        OR sp.nim LIKE :nim
        OR cp.company_name LIKE :company
    )";

    $search = "%$keyword%";

    $params['name'] = $search;
    $params['email'] = $search;
    $params['nim'] = $search;
    $params['company'] = $search;
}

// Filter role
if (in_array($role, ['student', 'company', 'admin'], true)) {
    $where[] = "u.role = :role";
    $params['role'] = $role;
}

// Filter status akun
if (in_array($status, ['active', 'inactive', 'pending'], true)) {
    $where[] = "u.status = :status";
    $params['status'] = $status;
}

$where_sql = '';

if (!empty($where)) {
    $where_sql = "WHERE " . implode(" AND ", $where);
}

// Menghitung total pengguna
$count_sql = "
    SELECT COUNT(*)
    FROM users u
    LEFT JOIN student_profiles sp ON sp.user_id = u.id
    LEFT JOIN company_profiles cp ON cp.user_id = u.id
    $where_sql
";

$stmt_count = $pdo->prepare($count_sql);
$stmt_count->execute($params);

$total_users = (int) $stmt_count->fetchColumn();
$total_pages = max(1, (int) ceil($total_users / $per_page));

if ($page > $total_pages) {
    $page = $total_pages;
    $offset = ($page - 1) * $per_page;
}

// Mengambil data pengguna
$sql = "
    SELECT
        u.id,
        u.name,
        u.email,
        u.username,
        u.role,
        u.status,
        u.created_at,
        sp.nim,
        sp.study_program,
        cp.company_name,
        cp.verification_status

    FROM users u

    LEFT JOIN student_profiles sp
        ON sp.user_id = u.id

    LEFT JOIN company_profiles cp
        ON cp.user_id = u.id

    $where_sql

    ORDER BY u.created_at DESC

    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($sql);

foreach ($params as $key => $value) {
    $stmt->bindValue(":$key", $value);
}

$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

$stmt->execute();

$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fungsi label
function roleLabel($role) {
    $labels = [
        'student' => 'Mahasiswa',
        'company' => 'Perusahaan',
        'admin' => 'Admin'
    ];

    return $labels[$role] ?? $role;
}

function statusLabel($status) {
    $labels = [
        'active' => 'Aktif',
        'inactive' => 'Nonaktif',
        'pending' => 'Menunggu'
    ];

    return $labels[$status] ?? $status;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manajemen Pengguna - CareerMap</title>

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
                <h1>Manajemen Pengguna</h1>

                <p>
                    Lihat, cari, dan kelola data pengguna CareerMap.
                </p>
            </div>

        </div>


        <!-- DAFTAR PENGGUNA -->
        <section class="content-card">

            <div class="card-heading">

                <div>
                    <h2>Daftar Pengguna</h2>

                    <p>
                        Total <?= number_format($total_users, 0, ',', '.') ?>
                        pengguna terdaftar
                    </p>
                </div>

            </div>


            <!-- SEARCH DAN FILTER -->
            <form method="GET" class="filter-form">

                <input
                    type="text"
                    name="keyword"
                    placeholder="Cari nama, email, atau NIM..."
                    value="<?= htmlspecialchars($keyword) ?>"
                >

                <select name="role">
                    <option value="">Semua Role</option>

                    <option value="student"
                        <?= $role === 'student' ? 'selected' : '' ?>>
                        Mahasiswa
                    </option>

                    <option value="company"
                        <?= $role === 'company' ? 'selected' : '' ?>>
                        Perusahaan
                    </option>

                    <option value="admin"
                        <?= $role === 'admin' ? 'selected' : '' ?>>
                        Admin
                    </option>
                </select>

                <select name="status">
                    <option value="">Semua Status</option>

                    <option value="active"
                        <?= $status === 'active' ? 'selected' : '' ?>>
                        Aktif
                    </option>

                    <option value="inactive"
                        <?= $status === 'inactive' ? 'selected' : '' ?>>
                        Nonaktif
                    </option>

                    <option value="pending"
                        <?= $status === 'pending' ? 'selected' : '' ?>>
                        Menunggu
                    </option>
                </select>

                <button type="submit" class="btn-approve">
                    Cari
                </button>

                <a href="pengguna.php" class="table-link">
                    Reset
                </a>

            </form>


            <!-- TABEL -->
            <div class="table-wrapper">

                <table>

                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if (count($users) > 0): ?>

                        <?php foreach ($users as $user): ?>

                            <?php
                            $display_name = $user['role'] === 'company'
                                ? ($user['company_name'] ?: $user['name'])
                                : $user['name'];

                            $status_class = [
                                'active' => 'status-verified',
                                'inactive' => 'status-rejected',
                                'pending' => 'status-pending'
                            ][$user['status']] ?? 'status-pending';
                            ?>

                            <tr>

                                <td>
                                    <strong>
                                        <?= htmlspecialchars($display_name) ?>
                                    </strong>

                                    <?php if ($user['role'] === 'student' && $user['nim']): ?>
                                        <br>
                                        <small>
                                            NIM: <?= htmlspecialchars($user['nim']) ?>
                                        </small>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($user['email']) ?>
                                </td>

                                <td>
                                    <?= roleLabel($user['role']) ?>

                                    <?php if (
                                        $user['role'] === 'company'
                                        && $user['verification_status']
                                    ): ?>
                                        <br>
                                        <small>
                                            Verifikasi:
                                            <?= htmlspecialchars($user['verification_status']) ?>
                                        </small>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <span class="<?= $status_class ?>">
                                        <?= statusLabel($user['status']) ?>
                                    </span>
                                </td>

                                <td>
                                    <a
                                        href="detail-pengguna.php?id=<?= (int) $user['id'] ?>"
                                        class="table-link"
                                    >
                                        Detail
                                    </a>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="5" style="text-align:center; padding:30px;">
                                Tidak ada pengguna yang ditemukan.
                            </td>
                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>


            <!-- PAGINATION -->
            <div class="pagination">

                <p>
                    Menampilkan

                    <?= $total_users > 0 ? $offset + 1 : 0 ?>

                    -

                    <?= min($offset + $per_page, $total_users) ?>

                    dari

                    <?= number_format($total_users, 0, ',', '.') ?>

                    pengguna
                </p>

                <div>

                    <?php if ($page > 1): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                            Sebelumnya
                        </a>
                    <?php endif; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                            Selanjutnya
                        </a>
                    <?php endif; ?>

                </div>

            </div>

        </section>

    </main>

</div>

</body>
</html>

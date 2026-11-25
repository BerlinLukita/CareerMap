<?php
session_start();
require_once '../config/database.php';

if (empty($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$error_db = '';

function resolveCompanyLogoUrl(string $logoPath): string {
    $clean = trim($logoPath);
    if ($clean === '') {
        return '';
    }
    if (str_starts_with($clean, 'http://') || str_starts_with($clean, 'https://')) {
        return $clean;
    }

    $normalized = ltrim(str_replace('\\', '/', $clean), '/');
    if (str_starts_with($normalized, '../')) {
        $normalized = substr($normalized, 3);
    }
    if (!str_starts_with($normalized, 'uploads/')) {
        $normalized = 'uploads/perusahaan/logos/' . basename($normalized);
    }

    $fsPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
    $version = is_file($fsPath) ? '?v=' . filemtime($fsPath) : '';

    return '../' . $normalized . $version;
}

$company = [
    'user_id'      => $user_id,
    'company_name' => '',
    'industry'     => '',
    'address'      => '',
    'logo_path'    => '',
    'description'  => '',
];

$stats = [
    'lowongan_aktif' => 0,
    'total_pelamar'  => 0,
    'pelamar_baru'   => 0,
    'perlu_ditinjau' => 0,
];

$recent_applicants = [];

try {
    if (isset($pdo)) {
        $stmt = $pdo->prepare("SELECT * FROM company_profiles WHERE user_id = :user_id LIMIT 1");
        $stmt->execute([':user_id' => $user_id]);
        $rowCompany = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($rowCompany) {
            $company = array_merge($company, $rowCompany);
            $companyId = (int) $rowCompany['user_id'];

            $q1 = $pdo->prepare("SELECT COUNT(*) FROM job_vacancies WHERE company_id = :cid AND status = 'published'");
            $q1->execute([':cid' => $companyId]);
            $stats['lowongan_aktif'] = (int) $q1->fetchColumn();

            $q2 = $pdo->prepare("
                SELECT COUNT(*)
                FROM applications a
                JOIN job_vacancies j ON a.job_id = j.id
                WHERE j.company_id = :cid
            ");
            $q2->execute([':cid' => $companyId]);
            $stats['total_pelamar'] = (int) $q2->fetchColumn();

            $q3 = $pdo->prepare("
                SELECT COUNT(*)
                FROM applications a
                JOIN job_vacancies j ON a.job_id = j.id
                WHERE j.company_id = :cid AND a.status = 'submitted'
            ");
            $q3->execute([':cid' => $companyId]);
            $stats['pelamar_baru'] = (int) $q3->fetchColumn();

            $q4 = $pdo->prepare("
                SELECT COUNT(*)
                FROM applications a
                JOIN job_vacancies j ON a.job_id = j.id
                WHERE j.company_id = :cid AND a.status IN ('submitted', 'under_review')
            ");
            $q4->execute([':cid' => $companyId]);
            $stats['perlu_ditinjau'] = (int) $q4->fetchColumn();

            $q5 = $pdo->prepare("
                SELECT a.id, a.status, u.name AS applicant_name, j.title AS job_title
                FROM applications a
                JOIN job_vacancies j ON a.job_id = j.id
                JOIN users u ON a.student_id = u.id
                WHERE j.company_id = :cid
                ORDER BY a.applied_at DESC
                LIMIT 5
            ");
            $q5->execute([':cid' => $companyId]);
            $recent_applicants = $q5->fetchAll(PDO::FETCH_ASSOC);
        }
    } elseif (isset($conn)) {
        $stmt = $conn->prepare("SELECT * FROM company_profiles WHERE user_id = ? LIMIT 1");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($rowCompany = $res->fetch_assoc()) {
            $company = array_merge($company, $rowCompany);
            $companyId = (int) $rowCompany['user_id'];

            $q1 = $conn->prepare("SELECT COUNT(*) AS total FROM job_vacancies WHERE company_id = ? AND status = 'published'");
            $q1->bind_param("i", $companyId);
            $q1->execute();
            $stats['lowongan_aktif'] = (int) ($q1->get_result()->fetch_assoc()['total'] ?? 0);
            $q1->close();

            $q2 = $conn->prepare("
                SELECT COUNT(*) AS total
                FROM applications a
                JOIN job_vacancies j ON a.job_id = j.id
                WHERE j.company_id = ?
            ");
            $q2->bind_param("i", $companyId);
            $q2->execute();
            $stats['total_pelamar'] = (int) ($q2->get_result()->fetch_assoc()['total'] ?? 0);
            $q2->close();

            $q3 = $conn->prepare("
                SELECT COUNT(*) AS total
                FROM applications a
                JOIN job_vacancies j ON a.job_id = j.id
                WHERE j.company_id = ? AND a.status = 'submitted'
            ");
            $q3->bind_param("i", $companyId);
            $q3->execute();
            $stats['pelamar_baru'] = (int) ($q3->get_result()->fetch_assoc()['total'] ?? 0);
            $q3->close();

            $q4 = $conn->prepare("
                SELECT COUNT(*) AS total
                FROM applications a
                JOIN job_vacancies j ON a.job_id = j.id
                WHERE j.company_id = ? AND a.status IN ('submitted', 'under_review')
            ");
            $q4->bind_param("i", $companyId);
            $q4->execute();
            $stats['perlu_ditinjau'] = (int) ($q4->get_result()->fetch_assoc()['total'] ?? 0);
            $q4->close();

            $q5 = $conn->prepare("
                SELECT a.id, a.status, u.name AS applicant_name, j.title AS job_title
                FROM applications a
                JOIN job_vacancies j ON a.job_id = j.id
                JOIN users u ON a.student_id = u.id
                WHERE j.company_id = ?
                ORDER BY a.applied_at DESC
                LIMIT 5
            ");
            $q5->bind_param("i", $companyId);
            $q5->execute();
            $r5 = $q5->get_result();
            while ($rowApp = $r5->fetch_assoc()) {
                $recent_applicants[] = $rowApp;
            }
            $q5->close();
        }
        $stmt->close();
    } else {
        $error_db = 'Koneksi database tidak ditemukan.';
    }
} catch (Throwable $e) {
    $error_db = 'Gagal memuat data dashboard: ' . $e->getMessage();
}

$companyName     = $company['company_name'] ?: 'Perusahaan';
$companyIndustry = $company['industry'] ?: '-';
$companyLocation = $company['address'] ?: '-';
$companyLogoUrl  = resolveCompanyLogoUrl((string) ($company['logo_path'] ?? ''));

$filled = 0;
foreach (['company_name', 'industry', 'address', 'description', 'logo_path'] as $field) {
    if (!empty($company[$field])) {
        $filled++;
    }
}
$completionPercent = (int) round(($filled / 5) * 100);

$words = explode(' ', trim($companyName));
$initials = strtoupper(substr($words[0], 0, 1) . (isset($words[1]) ? substr($words[1], 0, 1) : ''));
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard Perusahaan - CareerMap</title>
  <link rel="stylesheet" href="../assets/css/dashboard-perusahaan.css?v=1">
</head>
<body>

  <aside class="sidebar">
    <div>
      <a href="dashboard.php" class="sidebar-brand">
        <img
          src="../assets/images/logo-uajy.jpg"
          alt="Logo CareerMap"
          class="brand-logo"
          width="30"
          height="30"
        >
        <span>CareerMap</span>
      </a>

      <div class="sidebar-section-label">PERUSAHAAN</div>
      <ul class="nav-menu">
        <li><a href="dashboard.php" class="nav-link active">Dashboard</a></li>
        <li><a href="jobs.php" class="nav-link">Kelola Lowongan</a></li>
        <li><a href="applicants.php" class="nav-link">Pelamar</a></li>
        <li><a href="profile.php" class="nav-link">Profil Perusahaan</a></li>
      </ul>
    </div>

    <div class="sidebar-footer">
      <a href="../support.php" class="support-link">Bantuan &amp; Pusat Dukungan</a>
      <a href="../auth/logout.php" class="logout-link">Keluar</a>
    </div>
  </aside>

  <main class="main-content">
    <div class="page-header">
      <h1 class="page-title">Dashboard Perusahaan</h1>
      <p class="page-subtitle">
        Selamat datang, <?= htmlspecialchars($companyName) ?>. Kelola lowongan dan profil perusahaanmu.
      </p>
    </div>

    <?php if (!empty($error_db)): ?>
      <div class="alert-error"><?= htmlspecialchars($error_db) ?></div>
    <?php endif; ?>

    <section class="stats-grid">
      <div class="stat-card">
        <div class="stat-number"><?= $stats['lowongan_aktif'] ?></div>
        <div class="stat-label">Lowongan Aktif</div>
      </div>
      <div class="stat-card">
        <div class="stat-number"><?= $stats['total_pelamar'] ?></div>
        <div class="stat-label">Total Pelamar</div>
      </div>
      <div class="stat-card">
        <div class="stat-number"><?= $stats['pelamar_baru'] ?></div>
        <div class="stat-label">Pelamar Baru</div>
      </div>
      <div class="stat-card">
        <div class="stat-number"><?= $stats['perlu_ditinjau'] ?></div>
        <div class="stat-label">Perlu Ditinjau</div>
      </div>
    </section>

    <section class="profile-card">
      <div class="profile-left">
        <div class="company-avatar">
          <?php if ($companyLogoUrl !== ''): ?>
            <img src="<?= htmlspecialchars($companyLogoUrl) ?>" alt="Logo Perusahaan">
          <?php else: ?>
            <?= htmlspecialchars($initials) ?>
          <?php endif; ?>
        </div>
        <div>
          <h2 class="company-name">Profil Perusahaan</h2>
          <p class="company-meta">
            <?= htmlspecialchars($companyName) ?> &bull;
            <?= htmlspecialchars($companyIndustry) ?> &bull;
            <?= htmlspecialchars($companyLocation) ?>
          </p>
          <span class="completion-badge">Profil <?= $completionPercent ?>% Lengkap</span>
          <p class="profile-hint">Lengkapi deskripsi dan dokumen perusahaan agar profil lebih terpercaya.</p>
        </div>
      </div>

      <a href="profile.php" class="btn-primary">Lihat / Edit Profil</a>
    </section>

    <section>
      <div class="activity-header">
        <h2 class="section-heading">Aktivitas Terbaru</h2>
        <a href="jobs.php?action=create" class="btn-primary">+ Buat Lowongan</a>
      </div>

      <div class="applicants-card">
        <h3 class="applicants-title">Pelamar terbaru</h3>
        <ul class="applicant-list">
          <?php if (empty($recent_applicants)): ?>
            <li class="applicant-row">
              <span class="applicant-role" style="grid-column: 1 / -1;">Belum ada data pelamar terbaru.</span>
            </li>
          <?php else: ?>
            <?php foreach ($recent_applicants as $app): ?>
              <?php
                $isReview   = ($app['status'] === 'under_review' || $app['status'] === 'interview');
                $badgeClass = $isReview ? 'status-review' : 'status-baru';
                $badgeText  = $app['status'] === 'under_review'
                    ? 'Dalam Review'
                    : ($app['status'] === 'interview' ? 'Wawancara' : 'Baru');
              ?>
              <li class="applicant-row">
                <span class="applicant-name"><?= htmlspecialchars($app['applicant_name']) ?></span>
                <span class="applicant-role"><?= htmlspecialchars($app['job_title']) ?></span>
                <span class="status-badge <?= $badgeClass ?>"><?= $badgeText ?></span>
                <a href="applicants.php?process=<?= (int) $app['id'] ?>" class="detail-link">Lihat &rarr;</a>
              </li>
            <?php endforeach; ?>
          <?php endif; ?>
        </ul>
      </div>
    </section>
  </main>

</body>
</html>
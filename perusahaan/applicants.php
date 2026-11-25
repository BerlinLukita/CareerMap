<?php
session_start();
require_once '../config/database.php';

if (empty($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$company_id = (int) $_SESSION['user_id'];
$error = '';
$success = isset($_GET['updated']) ? 'Status lamaran kandidat berhasil diperbarui.' : '';

$status_options = [
    'submitted'    => 'Baru',
    'under_review' => 'Perlu Ditinjau',
    'waiting'      => 'Menunggu',
    'interview'    => 'Interview',
    'accepted'     => 'Diterima',
    'rejected'     => 'Ditolak',
];

function formatStatusLabel(string $status): string {
    $map = [
        'submitted'    => 'Baru',
        'under_review' => 'Dalam Review',
        'waiting'      => 'Menunggu',
        'interview'    => 'Interview',
        'accepted'     => 'Diterima',
        'rejected'     => 'Ditolak',
    ];
    return $map[$status] ?? ucfirst(str_replace('_', ' ', $status));
}

function getStatusPillClass(string $status): string {
    return match ($status) {
        'submitted'    => 'pill-baru',
        'under_review' => 'pill-review',
        'interview'    => 'pill-interview',
        'rejected'     => 'pill-reject',
        default        => 'pill-neutral',
    };
}

function makeInitials(string $name): string {
    $parts = preg_split('/\s+/', trim($name));
    $first = isset($parts[0][0]) ? strtoupper($parts[0][0]) : 'K';
    $second = isset($parts[1][0]) ? strtoupper($parts[1][0]) : '';
    return $first . $second;
}

// Proses simpan perubahan status pada mode detail/proses
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $app_id     = (int) ($_POST['application_id'] ?? 0);
    $new_status = trim($_POST['status'] ?? '');
    $note       = trim($_POST['note'] ?? '');

    if ($app_id <= 0 || !array_key_exists($new_status, $status_options)) {
        $error = 'Silakan pilih status kandidat terlebih dahulu.';
    } else {
        try {
            if (isset($pdo)) {
                $check = $pdo->prepare("
                    SELECT a.id, a.status AS old_status
                    FROM applications a
                    JOIN job_vacancies j ON a.job_id = j.id
                    WHERE a.id = :aid AND j.company_id = :cid
                    LIMIT 1
                ");
                $check->execute([':aid' => $app_id, ':cid' => $company_id]);
                $existing = $check->fetch(PDO::FETCH_ASSOC);

                if (!$existing) {
                    $error = 'Data lamaran tidak ditemukan atau bukan milik perusahaan Anda.';
                } else {
                    $upd = $pdo->prepare("UPDATE applications SET status = :status WHERE id = :aid");
                    $upd->execute([':status' => $new_status, ':aid' => $app_id]);

                    // Catat riwayat status jika tabel application_status_history tersedia
                    try {
                        $hist = $pdo->prepare("
                            INSERT INTO application_status_history
                                (application_id, old_status, new_status, changed_by, note)
                            VALUES
                                (:aid, :old_st, :new_st, :uid, :note)
                        ");
                        $hist->execute([
                            ':aid'    => $app_id,
                            ':old_st' => $existing['old_status'],
                            ':new_st' => $new_status,
                            ':uid'    => $company_id,
                            ':note'   => $note !== '' ? $note : null,
                        ]);
                    } catch (Throwable $eHist) {
                        // Abaikan jika struktur riwayat status berbeda
                    }

                    header("Location: applicants.php?process=" . $app_id . "&updated=1");
                    exit;
                }
            }
        } catch (Throwable $e) {
            $error = 'Gagal menyimpan perubahan: ' . $e->getMessage();
        }
    }
}

// Ambil daftar lowongan perusahaan untuk filter
$company_jobs = [];
try {
    if (isset($pdo)) {
        $jStmt = $pdo->prepare("SELECT id, title FROM job_vacancies WHERE company_id = :cid ORDER BY created_at DESC");
        $jStmt->execute([':cid' => $company_id]);
        $company_jobs = $jStmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    $company_jobs = [];
}

$selected_job_id = isset($_GET['job_id']) && is_numeric($_GET['job_id']) ? (int) $_GET['job_id'] : 0;
$process_id      = isset($_GET['process']) && is_numeric($_GET['process']) ? (int) $_GET['process'] : 0;
$page            = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$per_page        = 4;

// Mode proses / detail kandidat (Gambar 3 & 4)
$detail_candidate = null;
if ($process_id > 0 && isset($pdo)) {
    try {
        $dStmt = $pdo->prepare("
            SELECT a.*, j.title AS job_title, u.name AS applicant_name, u.email AS applicant_email
            FROM applications a
            JOIN job_vacancies j ON a.job_id = j.id
            JOIN users u ON a.student_id = u.id
            WHERE a.id = :aid AND j.company_id = :cid
            LIMIT 1
        ");
        $dStmt->execute([':aid' => $process_id, ':cid' => $company_id]);
        $detail_candidate = $dStmt->fetch(PDO::FETCH_ASSOC);

        if ($detail_candidate) {
            $detail_candidate['major'] = 'Mahasiswa';
            $detail_candidate['university'] = 'UAJY';
            $detail_candidate['bio'] = $detail_candidate['cover_letter'] ?? 'Profil singkat kandidat tersedia pada dokumen lamaran.';
            $detail_candidate['education'] = 'Perguruan Tinggi';
            $detail_candidate['skills'] = 'Komunikasi • Kerja Tim • Problem Solving';
            $detail_candidate['experience'] = $detail_candidate['cover_letter'] ?? 'Pengalaman proyek dan akademik sesuai posisi yang dilamar.';
            $detail_candidate['portfolio'] = '-';

            try {
                $spStmt = $pdo->prepare("SELECT * FROM student_profiles WHERE user_id = :uid LIMIT 1");
                $spStmt->execute([':uid' => (int) $detail_candidate['student_id']]);
                $sp = $spStmt->fetch(PDO::FETCH_ASSOC);
                if ($sp) {
                    $detail_candidate['major']      = $sp['major'] ?? $sp['study_program'] ?? $sp['prodi'] ?? $detail_candidate['major'];
                    $detail_candidate['university'] = $sp['university'] ?? $sp['institution'] ?? $detail_candidate['university'];
                    $detail_candidate['bio']        = $sp['bio'] ?? $sp['summary'] ?? $sp['about'] ?? $detail_candidate['bio'];
                    $detail_candidate['education']  = $sp['education'] ?? $detail_candidate['education'];
                    $detail_candidate['skills']     = $sp['skills'] ?? $detail_candidate['skills'];
                    $detail_candidate['experience'] = $sp['experience'] ?? $sp['projects'] ?? $detail_candidate['experience'];
                    $detail_candidate['portfolio']  = $sp['portfolio_url'] ?? $sp['portfolio'] ?? $detail_candidate['portfolio'];
                }
            } catch (Throwable $eSp) {
                // Gunakan fallback jika kolom profil mahasiswa berbeda
            }
        }
    } catch (Throwable $e) {
        $error = 'Gagal memuat detail kandidat: ' . $e->getMessage();
    }
}

// Mode daftar pelamar (Gambar 1)
$applicants = [];
$total_applicants = 0;
$total_pages = 1;
$active_job_title = 'Semua Lowongan';

if (!$detail_candidate && isset($pdo)) {
    try {
        if ($selected_job_id > 0) {
            foreach ($company_jobs as $cj) {
                if ((int) $cj['id'] === $selected_job_id) {
                    $active_job_title = $cj['title'];
                    break;
                }
            }
        } elseif (!empty($company_jobs)) {
            $active_job_title = $company_jobs[0]['title'];
        }

        $whereSql = "WHERE j.company_id = :cid";
        $params = [':cid' => $company_id];
        if ($selected_job_id > 0) {
            $whereSql .= " AND j.id = :jid";
            $params[':jid'] = $selected_job_id;
        }

        $cntStmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM applications a
            JOIN job_vacancies j ON a.job_id = j.id
            $whereSql
        ");
        $cntStmt->execute($params);
        $total_applicants = (int) $cntStmt->fetchColumn();
        $total_pages = max(1, (int) ceil($total_applicants / $per_page));
        $page = min($page, $total_pages);
        $offset = ($page - 1) * $per_page;

        $listStmt = $pdo->prepare("
            SELECT a.*, j.title AS job_title, u.name AS applicant_name, u.email AS applicant_email
            FROM applications a
            JOIN job_vacancies j ON a.job_id = j.id
            JOIN users u ON a.student_id = u.id
            $whereSql
            ORDER BY a.applied_at DESC
            LIMIT :limit OFFSET :offset
        ");
        foreach ($params as $k => $v) {
            $listStmt->bindValue($k, $v, PDO::PARAM_INT);
        }
        $listStmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
        $listStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $listStmt->execute();
        $rows = $listStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $r) {
            $r['major'] = 'Mahasiswa';
            $r['university'] = 'UAJY';
            $r['bio'] = $r['cover_letter'] ?? 'Profil singkat kandidat tersedia pada dokumen lamaran.';
            $r['education'] = 'Perguruan Tinggi';
            $r['skills'] = 'Komunikasi • Kerja Tim • Problem Solving';
            $r['experience'] = $r['cover_letter'] ?? 'Pengalaman proyek dan akademik sesuai posisi yang dilamar.';
            $r['portfolio'] = '-';

            try {
                $spStmt = $pdo->prepare("SELECT * FROM student_profiles WHERE user_id = :uid LIMIT 1");
                $spStmt->execute([':uid' => (int) $r['student_id']]);
                $sp = $spStmt->fetch(PDO::FETCH_ASSOC);
                if ($sp) {
                    $r['major']      = $sp['major'] ?? $sp['study_program'] ?? $sp['prodi'] ?? $r['major'];
                    $r['university'] = $sp['university'] ?? $sp['institution'] ?? $r['university'];
                    $r['bio']        = $sp['bio'] ?? $sp['summary'] ?? $sp['about'] ?? $r['bio'];
                    $r['education']  = $sp['education'] ?? $r['education'];
                    $r['skills']     = $sp['skills'] ?? $r['skills'];
                    $r['experience'] = $sp['experience'] ?? $sp['projects'] ?? $r['experience'];
                    $r['portfolio']  = $sp['portfolio_url'] ?? $sp['portfolio'] ?? $r['portfolio'];
                }
            } catch (Throwable $eSp) {
            }

            $applicants[] = $r;
        }
    } catch (Throwable $e) {
        $error = 'Gagal memuat data pelamar: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $detail_candidate ? 'Detail Kandidat' : 'Daftar Pelamar' ?> - CareerMap</title>
  <link rel="stylesheet" href="../assets/css/pelamar.css?v=1">
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
          style="width:30px;height:30px;max-width:30px;max-height:30px;border-radius:50%;object-fit:cover;background:#ffffff;padding:1px;flex-shrink:0;"
        >
        <span>CareerMap</span>
      </a>

      <div class="sidebar-section-label">PERUSAHAAN</div>
      <ul class="nav-menu">
        <li><a href="dashboard.php" class="nav-link">Dashboard</a></li>
        <li><a href="jobs.php" class="nav-link">Kelola Lowongan</a></li>
        <li><a href="applicants.php" class="nav-link active">Pelamar</a></li>
        <li><a href="profile.php" class="nav-link">Profil Perusahaan</a></li>
      </ul>
    </div>

    <div class="sidebar-footer">
      <a href="../support.php" class="support-link">Bantuan &amp; Pusat Dukungan</a>
      <a href="../auth/logout.php" class="logout-link">Keluar</a>
    </div>
  </aside>

  <main class="main-content">
    <?php if ($detail_candidate): ?>
      <!-- Tampilan Proses / Detail Kandidat (Gambar 3 & 4) -->
      <div class="page-header">
        <div>
          <h1 class="page-title">Detail Kandidat</h1>
          <p class="page-subtitle">Tinjau profil kandidat dan perbarui status proses lamaran.</p>
        </div>
        <a href="applicants.php<?= $selected_job_id > 0 ? '?job_id=' . $selected_job_id : '' ?>" class="btn-outline-cv">&larr; Kembali ke Daftar</a>
      </div>

      <?php if ($error !== ''): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php if ($success !== ''): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
      <?php endif; ?>

      <?php
        $dName = $detail_candidate['applicant_name'];
        $dStatus = $detail_candidate['status'] ?? 'submitted';
        $cvFile = basename($detail_candidate['cv_path'] ?? 'CV_Kandidat.pdf');
      ?>

      <section class="detail-outer-card">
        <div class="detail-inner-card">
          <div class="detail-card-top">
            <h2 class="detail-section-label">Detail Kandidat</h2>
            <span class="status-pill <?= getStatusPillClass($dStatus) ?>">
              <?= strtoupper(htmlspecialchars(formatStatusLabel($dStatus))) ?>
            </span>
          </div>

          <div class="detail-profile-header">
            <div class="candidate-avatar lg"><?= htmlspecialchars(makeInitials($dName)) ?></div>
            <div>
              <div class="detail-candidate-name"><?= htmlspecialchars($dName) ?></div>
              <div class="detail-candidate-meta">
                <?= htmlspecialchars($detail_candidate['major']) ?> &bull; <?= htmlspecialchars($detail_candidate['university']) ?><br>
                Melamar posisi <?= htmlspecialchars($detail_candidate['job_title']) ?>
              </div>
            </div>
          </div>

          <div class="detail-two-col">
            <div>
              <h3 class="detail-section-label">Dokumen &amp; Informasi</h3>
              <div class="info-list">
                <div class="info-item">
                  <span class="info-key">Email</span>
                  <span class="info-val"><?= htmlspecialchars($detail_candidate['applicant_email']) ?></span>
                </div>
                <div class="info-item">
                  <span class="info-key">CV</span>
                  <span class="info-val">
                    <button
                      type="button"
                      class="cv-inline-btn"
                      data-open-cv="1"
                      data-name="<?= htmlspecialchars($dName) ?>"
                      data-role="<?= htmlspecialchars($detail_candidate['job_title'] . ' Candidate') ?>"
                      data-contact="<?= htmlspecialchars($detail_candidate['university'] . ' • ' . $detail_candidate['applicant_email']) ?>"
                      data-bio="<?= htmlspecialchars($detail_candidate['bio']) ?>"
                      data-edu="<?= htmlspecialchars($detail_candidate['university']) ?>"
                      data-edu-sub="<?= htmlspecialchars($detail_candidate['major']) ?>"
                      data-skills="<?= htmlspecialchars($detail_candidate['skills']) ?>"
                      data-exp="<?= htmlspecialchars($detail_candidate['experience']) ?>"
                      data-cv-url="<?= htmlspecialchars('../' . ltrim($detail_candidate['cv_path'] ?? '', '/')) ?>"
                    ><?= htmlspecialchars($cvFile) ?></button>
                  </span>
                </div>
                <div class="info-item">
                  <span class="info-key">Portofolio</span>
                  <span class="info-val"><?= htmlspecialchars($detail_candidate['portfolio']) ?></span>
                </div>
              </div>
            </div>

            <div>
              <h3 class="detail-section-label">Proses Lamaran</h3>

              <form action="applicants.php?process=<?= (int) $detail_candidate['id'] ?>" method="POST">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="application_id" value="<?= (int) $detail_candidate['id'] ?>">
                <input type="hidden" name="status" id="selectedStatusInput" value="">

                <div class="status-field-group">
                  <span class="field-caption">Perbarui status kandidat</span>

                  <button type="button" class="status-select-trigger" id="statusTriggerBtn" aria-expanded="false">
                    <span id="statusTriggerText" class="placeholder-text">Pilih status</span>
                    <span aria-hidden="true">&#8964;</span>
                  </button>

                  <div class="status-dropdown-menu" id="statusDropdownMenu" role="listbox">
                    <div class="status-dropdown-header">PILIH STATUS</div>
                    <?php foreach ($status_options as $stKey => $stLabel): ?>
                      <button
                        type="button"
                        class="status-option-btn"
                        data-value="<?= htmlspecialchars($stKey) ?>"
                        data-label="<?= htmlspecialchars($stLabel) ?>"
                      ><?= htmlspecialchars($stLabel) ?></button>
                    <?php endforeach; ?>
                  </div>
                </div>

                <div class="note-group">
                  <label for="note" class="field-caption">Catatan untuk kandidat (opsional)</label>
                  <textarea
                    id="note"
                    name="note"
                    class="note-textarea"
                    placeholder="Tambahkan catatan..."
                  ></textarea>
                </div>

                <div class="detail-actions">
                  <button type="submit" class="btn-primary">Simpan Perubahan</button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </section>

    <?php else: ?>
      <!-- Tampilan Daftar Pelamar (Gambar 1) -->
      <div class="page-header">
        <div>
          <h1 class="page-title">Daftar Pelamar</h1>
          <p class="page-subtitle">Tinjau kandidat dan perbarui status lamaran.</p>
        </div>
      </div>

      <?php if ($error !== ''): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <section class="applicants-wrapper-card">
        <div class="card-top-header">
          <h2 class="card-job-heading">Pelamar &bull; <?= htmlspecialchars($active_job_title) ?></h2>

          <div class="filter-controls">
            <?php if (!empty($company_jobs)): ?>
              <form method="GET" action="applicants.php">
                <select name="job_id" class="job-filter-select" onchange="this.form.submit()">
                  <option value="0" <?= $selected_job_id === 0 ? 'selected' : '' ?>>Semua Lowongan</option>
                  <?php foreach ($company_jobs as $cj): ?>
                    <option value="<?= (int) $cj['id'] ?>" <?= $selected_job_id === (int) $cj['id'] ? 'selected' : '' ?>>
                      <?= htmlspecialchars($cj['title']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </form>
            <?php endif; ?>
            <span class="count-pill"><?= $total_applicants ?> Pelamar</span>
          </div>
        </div>

        <?php if (empty($applicants)): ?>
          <div class="empty-state">
            Belum ada pelamar yang masuk pada lowongan ini.
          </div>
        <?php else: ?>
          <div class="candidate-stack">
            <?php foreach ($applicants as $app): ?>
              <?php
                $st = $app['status'] ?? 'submitted';
                $name = $app['applicant_name'];
              ?>
              <div class="candidate-row-card">
                <div class="candidate-avatar"><?= htmlspecialchars(makeInitials($name)) ?></div>

                <div>
                  <div class="candidate-name"><?= htmlspecialchars($name) ?></div>
                  <div class="candidate-sub">
                    <?= htmlspecialchars($app['major']) ?> &bull; <?= htmlspecialchars($app['university']) ?>
                  </div>
                </div>

                <span class="status-pill <?= getStatusPillClass($st) ?>">
                  <?= htmlspecialchars(formatStatusLabel($st)) ?>
                </span>

                <button
                  type="button"
                  class="btn-outline-cv"
                  data-open-cv="1"
                  data-name="<?= htmlspecialchars($name) ?>"
                  data-role="<?= htmlspecialchars($app['job_title'] . ' Candidate') ?>"
                  data-contact="<?= htmlspecialchars($app['university'] . ' • ' . $app['applicant_email']) ?>"
                  data-bio="<?= htmlspecialchars($app['bio']) ?>"
                  data-edu="<?= htmlspecialchars($app['university']) ?>"
                  data-edu-sub="<?= htmlspecialchars($app['major']) ?>"
                  data-skills="<?= htmlspecialchars($app['skills']) ?>"
                  data-exp="<?= htmlspecialchars($app['experience']) ?>"
                  data-cv-url="<?= htmlspecialchars('../' . ltrim($app['cv_path'] ?? '', '/')) ?>"
                >Lihat CV</button>

                <a
                  href="applicants.php?process=<?= (int) $app['id'] ?><?= $selected_job_id > 0 ? '&job_id=' . $selected_job_id : '' ?>"
                  class="btn-primary"
                >Proses &rarr;</a>
              </div>
            <?php endforeach; ?>
          </div>

          <?php if ($total_pages > 1): ?>
            <div class="pagination-bar">
              <div class="pagination-pill">
                <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                  <a
                    href="applicants.php?page=<?= $p ?><?= $selected_job_id > 0 ? '&job_id=' . $selected_job_id : '' ?>"
                    class="page-num-link <?= $p === $page ? 'active' : '' ?>"
                  ><?= $p ?></a>
                <?php endfor; ?>
              </div>
            </div>
          <?php endif; ?>
        <?php endif; ?>
      </section>
    <?php endif; ?>
  </main>

  <!-- Modal Pratinjau CV (Gambar 2) -->
  <div class="cv-modal-backdrop" id="cvModalBackdrop" aria-hidden="true">
    <div class="cv-modal-panel" role="dialog" aria-modal="true" aria-labelledby="cvModalTitle">
      <div class="cv-modal-top">
        <div>
          <h2 class="cv-modal-title" id="cvModalTitle">CV — Kandidat</h2>
          <p class="cv-modal-sub">Pratinjau dokumen Curriculum Vitae</p>
        </div>
        <div class="cv-modal-actions">
          <a href="#" id="cvDownloadBtn" class="btn-primary" download>Unduh CV</a>
          <button type="button" class="cv-close-btn" id="cvCloseBtn" aria-label="Tutup">&times;</button>
        </div>
      </div>

      <div class="cv-sheet-bg">
        <div class="cv-sheet-paper">
          <div class="cv-paper-header">
            <div class="candidate-avatar lg" id="cvPaperAvatar">K</div>
            <div>
              <div class="cv-paper-name" id="cvPaperName">Nama Kandidat</div>
              <div class="cv-paper-role" id="cvPaperRole">Candidate</div>
              <div class="cv-paper-contact" id="cvPaperContact">email@example.com</div>
            </div>
          </div>

          <div class="cv-block">
            <div class="cv-block-title">Profil Singkat</div>
            <div class="cv-block-text" id="cvPaperBio"></div>
          </div>

          <div class="cv-two-cols">
            <div class="cv-block" style="margin-bottom:0;">
              <div class="cv-block-title">Pendidikan</div>
              <div class="cv-block-text">
                <strong id="cvPaperEdu"></strong><br>
                <span id="cvPaperEduSub"></span>
              </div>
            </div>

            <div class="cv-block" style="margin-bottom:0;">
              <div class="cv-block-title">Keahlian</div>
              <div class="cv-block-text" id="cvPaperSkills"></div>
            </div>
          </div>

          <div class="cv-block">
            <div class="cv-block-title">Pengalaman &amp; Proyek</div>
            <div class="cv-block-text" id="cvPaperExp"></div>
          </div>

          <div class="cv-block" style="margin-bottom:0;">
            <div class="cv-block-title">Informasi Tambahan</div>
            <div class="cv-block-text">Portofolio dan dokumen pendukung dapat diminta melalui proses rekrutmen.</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script>
    (function () {
      const modal = document.getElementById('cvModalBackdrop');
      const closeBtn = document.getElementById('cvCloseBtn');
      const downloadBtn = document.getElementById('cvDownloadBtn');

      document.querySelectorAll('[data-open-cv="1"]').forEach((btn) => {
        btn.addEventListener('click', () => {
          const name = btn.dataset.name || 'Kandidat';
          const parts = name.trim().split(/\s+/);
          const initials = ((parts[0] && parts[0][0]) || 'K') + ((parts[1] && parts[1][0]) || '');

          document.getElementById('cvModalTitle').textContent = 'CV — ' + name;
          document.getElementById('cvPaperAvatar').textContent = initials.toUpperCase();
          document.getElementById('cvPaperName').textContent = name;
          document.getElementById('cvPaperRole').textContent = btn.dataset.role || '';
          document.getElementById('cvPaperContact').textContent = btn.dataset.contact || '';
          document.getElementById('cvPaperBio').textContent = btn.dataset.bio || '';
          document.getElementById('cvPaperEdu').textContent = btn.dataset.edu || '';
          document.getElementById('cvPaperEduSub').textContent = btn.dataset.eduSub || '';
          document.getElementById('cvPaperSkills').textContent = btn.dataset.skills || '';
          document.getElementById('cvPaperExp').textContent = btn.dataset.exp || '';

          if (btn.dataset.cvUrl && btn.dataset.cvUrl !== '../') {
            downloadBtn.setAttribute('href', btn.dataset.cvUrl);
            downloadBtn.style.pointerEvents = 'auto';
            downloadBtn.style.opacity = '1';
          } else {
            downloadBtn.setAttribute('href', '#');
            downloadBtn.style.pointerEvents = 'none';
            downloadBtn.style.opacity = '0.6';
          }

          modal.classList.add('open');
          modal.setAttribute('aria-hidden', 'false');
        });
      });

      function closeModal() {
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
      }

      if (closeBtn) {
        closeBtn.addEventListener('click', closeModal);
      }
      if (modal) {
        modal.addEventListener('click', (e) => {
          if (e.target === modal) closeModal();
        });
      }

      const triggerBtn = document.getElementById('statusTriggerBtn');
      const dropdownMenu = document.getElementById('statusDropdownMenu');
      const triggerText = document.getElementById('statusTriggerText');
      const hiddenInput = document.getElementById('selectedStatusInput');

      if (triggerBtn && dropdownMenu && hiddenInput) {
        triggerBtn.addEventListener('click', (e) => {
          e.stopPropagation();
          const isOpen = dropdownMenu.classList.toggle('open');
          triggerBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });

        document.querySelectorAll('.status-option-btn').forEach((opt) => {
          opt.addEventListener('click', () => {
            document.querySelectorAll('.status-option-btn').forEach((o) => o.classList.remove('selected'));
            opt.classList.add('selected');
            hiddenInput.value = opt.dataset.value;
            triggerText.textContent = opt.dataset.label;
            triggerText.classList.remove('placeholder-text');
            dropdownMenu.classList.remove('open');
            triggerBtn.setAttribute('aria-expanded', 'false');
          });
        });

        document.addEventListener('click', () => {
          dropdownMenu.classList.remove('open');
          triggerBtn.setAttribute('aria-expanded', 'false');
        });
      }
    })();
  </script>

</body>
</html>
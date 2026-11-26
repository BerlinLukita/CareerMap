<?php
session_start();
require_once __DIR__ . '/config/database.php';

if (empty($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$error = '';
$success = isset($_GET['sent']) ? 'Permintaan bantuan Anda telah terkirim. Tim dukungan CareerMap akan menindaklanjutinya secepatnya.' : '';
$is_form_mode = isset($_GET['form']) && $_GET['form'] === '1';

$topics = [
    'akun_autentikasi' => [
        'title' => 'Akun & Autentikasi',
        'desc'  => 'Registrasi, login, lupa kata sandi, dan keamanan akun.',
        'faqs'  => [
            ['q' => 'Bagaimana cara mengganti kata sandi akun?', 'a' => 'Anda dapat mengatur ulang kata sandi melalui menu lupa kata sandi atau halaman pengaturan keamanan akun.'],
            ['q' => 'Mengapa sesi login terputus?', 'a' => 'Sesi dapat berakhir jika browser ditutup atau masa aktif sesi telah habis. Silakan login kembali.'],
        ],
    ],
    'profil_dokumen' => [
        'title' => 'Profil & Dokumen',
        'desc'  => 'Mengubah profil, foto, CV, dan portofolio.',
        'faqs'  => [
            ['q' => 'Bagaimana cara memperbarui logo dan dokumen perusahaan?', 'a' => 'Buka menu Profil Perusahaan, lalu gunakan tombol Unggah Logo atau klik Edit Profil untuk mengganti informasi serta file dokumen verifikasi.'],
            ['q' => 'Bagaimana cara melihat CV pelamar?', 'a' => 'Pada menu Pelamar, klik tombol Lihat CV pada baris kandidat untuk membuka pratinjau dokumen CV.'],
        ],
    ],
    'lowongan_lamaran' => [
        'title' => 'Lowongan & Lamaran',
        'desc'  => 'Mencari lowongan, mengirim lamaran, dan melihat status.',
        'faqs'  => [
            ['q' => 'Bagaimana cara mempublikasikan lowongan baru?', 'a' => 'Buka menu Kelola Lowongan, klik + Tambah Lowongan, lengkapi informasi lowongan, lalu klik Publikasikan.'],
            ['q' => 'Bagaimana cara memperbarui status kandidat?', 'a' => 'Masuk ke menu Pelamar, klik Proses pada kandidat, pilih status baru, lalu klik Simpan Perubahan.'],
        ],
    ],
    'akun_perusahaan' => [
        'title' => 'Akun Perusahaan',
        'desc'  => 'Verifikasi perusahaan dan pengelolaan lowongan.',
        'faqs'  => [
            ['q' => 'Berapa lama proses verifikasi akun perusahaan?', 'a' => 'Tim CareerMap akan meninjau dokumen verifikasi perusahaan yang telah diunggah dan memperbarui statusnya.'],
        ],
    ],
    'pelaporan_keamanan' => [
        'title' => 'Pelaporan & Keamanan',
        'desc'  => 'Melaporkan perusahaan, lowongan, atau pengguna.',
        'faqs'  => [
            ['q' => 'Bagaimana cara melaporkan kendala atau aktivitas mencurigakan?', 'a' => 'Klik tombol Hubungi Kami di bagian bawah Pusat Bantuan untuk mengisi Formulir Bantuan dan melampirkan bukti pendukung.'],
        ],
    ],
];

$default_email = '';
try {
    if (isset($pdo)) {
        $uStmt = $pdo->prepare("SELECT email FROM users WHERE id = :uid LIMIT 1");
        $uStmt->execute([':uid' => $user_id]);
        $default_email = (string) ($uStmt->fetchColumn() ?? '');
    }
} catch (Throwable $e) {
    $default_email = '';
}

$topic_val       = trim($_POST['topic'] ?? '');
$subject_val     = trim($_POST['subject'] ?? '');
$description_val = trim($_POST['description'] ?? '');
$email_val       = trim($_POST['email'] ?? $default_email);
$whatsapp_val    = trim($_POST['whatsapp'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_ticket') {
    $is_form_mode = true;

    if ($topic_val === '' || $subject_val === '' || $description_val === '' || $email_val === '') {
        $error = 'Mohon lengkapi seluruh field bertanda bintang (*).';
    } elseif (!filter_var($email_val, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email yang dimasukkan tidak valid.';
    } elseif (mb_strlen($description_val) > 500) {
        $error = 'Deskripsi permasalahan maksimal 500 karakter.';
    } else {
        $attachment_path = null;
        $attachment_name = null;

        if (isset($_FILES['attachment']) && ($_FILES['attachment']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['attachment']['error'] !== UPLOAD_ERR_OK) {
                $error = 'Gagal mengunggah file lampiran.';
            } elseif (($_FILES['attachment']['size'] ?? 0) > 5 * 1024 * 1024) {
                $error = 'Ukuran file lampiran maksimal 5 MB.';
            } else {
                $origName = basename($_FILES['attachment']['name'] ?? 'lampiran');
                $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'], true)) {
                    $error = 'Format lampiran harus JPG, PNG, atau PDF.';
                } else {
                    $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'bantuan';
                    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true)) {
                        $error = 'Folder lampiran tidak dapat dibuat.';
                    } else {
                        $safeName = 'ticket_' . $user_id . '_' . time() . '.' . $ext;
                        $dest = $uploadDir . DIRECTORY_SEPARATOR . $safeName;
                        if (!move_uploaded_file($_FILES['attachment']['tmp_name'], $dest)) {
                            $error = 'Gagal menyimpan file lampiran.';
                        } else {
                            $attachment_path = 'uploads/bantuan/' . $safeName;
                            $attachment_name = $origName;
                        }
                    }
                }
            }
        }

        if ($error === '') {
            try {
                if (isset($pdo)) {
                    $pdo->exec("
                        CREATE TABLE IF NOT EXISTS support_tickets (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            user_id INT NOT NULL,
                            topic VARCHAR(100) NOT NULL,
                            subject VARCHAR(255) NOT NULL,
                            description TEXT NOT NULL,
                            attachment_path VARCHAR(255) NULL,
                            email VARCHAR(150) NOT NULL,
                            whatsapp VARCHAR(30) NULL,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                        )
                    ");

                    $ins = $pdo->prepare("
                        INSERT INTO support_tickets
                            (user_id, topic, subject, description, attachment_path, email, whatsapp)
                        VALUES
                            (:uid, :topic, :subject, :description, :attachment_path, :email, :whatsapp)
                    ");
                    $ins->execute([
                        ':uid'             => $user_id,
                        ':topic'           => $topic_val,
                        ':subject'         => $subject_val,
                        ':description'     => $description_val,
                        ':attachment_path' => $attachment_path,
                        ':email'           => $email_val,
                        ':whatsapp'        => $whatsapp_val !== '' ? $whatsapp_val : null,
                    ]);
                }
                header("Location: support.php?sent=1");
                exit;
            } catch (Throwable $e) {
                $error = 'Gagal mengirim permintaan bantuan: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $is_form_mode ? 'Formulir Bantuan' : 'Bantuan & Pusat Dukungan' ?> - CareerMap</title>
  <link rel="stylesheet" href="assets/css/bantuan.css?v=1">
</head>
<body>

  <aside class="sidebar">
    <div>
      <a href="perusahaan/dashboard.php" class="sidebar-brand">
        <img
          src="assets/images/logo-uajy.jpg"
          alt="Logo CareerMap"
          class="brand-logo"
          width="30"
          height="30"
        >
        <span>CareerMap</span>
      </a>

      <div class="sidebar-section-label">PERUSAHAAN</div>
      <ul class="nav-menu">
        <li><a href="perusahaan/dashboard.php" class="nav-link">Dashboard</a></li>
        <li><a href="perusahaan/jobs.php" class="nav-link">Kelola Lowongan</a></li>
        <li><a href="perusahaan/applicants.php" class="nav-link">Pelamar</a></li>
        <li><a href="perusahaan/profile.php" class="nav-link">Profil Perusahaan</a></li>
      </ul>
    </div>

    <div class="sidebar-footer">
      <a href="support.php" class="support-link active">Bantuan &amp; Pusat Dukungan</a>
      <a href="auth/logout.php" class="logout-link">Keluar</a>
    </div>
  </aside>

  <main class="main-content">
    <?php if ($is_form_mode): ?>
      <div class="page-header">
        <div class="breadcrumb-nav">
          <a href="support.php" class="breadcrumb-link">Bantuan &amp; Pusat Dukungan</a>
          <span>&rsaquo;</span>
          <span class="breadcrumb-current">Formulir Bantuan</span>
        </div>
        <h1 class="page-title">Formulir Bantuan</h1>
        <p class="page-subtitle">Kirimkan pertanyaan, kendala, atau permintaan bantuan. Tim dukungan CareerMap akan menindaklanjutinya secepatnya.</p>
      </div>

      <?php if ($error !== ''): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <section class="ticket-outer-card">
        <div class="ticket-inner-card">
          <h2 class="ticket-section-title">Informasi Permintaan Bantuan</h2>

          <form action="support.php?form=1" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="submit_ticket">

            <div class="ticket-group">
              <label for="topic" class="ticket-label">Topik Bantuan <span class="req-star">*</span></label>
              <select id="topic" name="topic" class="ticket-control half-width" required>
                <option value="" disabled <?= $topic_val === '' ? 'selected' : '' ?>>Pilih topik bantuan</option>
                <?php foreach ($topics as $tKey => $tData): ?>
                  <option value="<?= htmlspecialchars($tKey) ?>" <?= $topic_val === $tKey ? 'selected' : '' ?>>
                    <?= htmlspecialchars($tData['title']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="ticket-group">
              <label for="subject" class="ticket-label">Judul Permohonan <span class="req-star">*</span></label>
              <input
                type="text"
                id="subject"
                name="subject"
                class="ticket-control"
                placeholder="Tuliskan ringkasan masalah atau kebutuhan bantuan..."
                value="<?= htmlspecialchars($subject_val) ?>"
                required
              >
            </div>

            <div class="ticket-group">
              <label for="description" class="ticket-label">Deskripsi Permasalahan <span class="req-star">*</span></label>
              <textarea
                id="description"
                name="description"
                class="ticket-control"
                maxlength="500"
                placeholder="Jelaskan secara rinci kendala yang Anda alami..."
                required
              ><?= htmlspecialchars($description_val) ?></textarea>
              <div class="char-counter"><span id="charCount">0</span>/500</div>
            </div>

            <div class="ticket-group">
              <span class="ticket-label">Lampiran (Opsional)</span>
              <input type="file" id="attachmentInput" name="attachment" accept=".jpg,.jpeg,.png,.pdf" style="display:none;">
              <div class="upload-dropzone" id="uploadDropzone">
                <div class="upload-icon-circle">&uarr;</div>
                <div class="upload-main-text" id="uploadMainText">Unggah file pendukung (screenshot, dokumen, dll.)</div>
                <div class="upload-sub-text">Format: JPG, PNG, PDF (Maks. 5 MB)</div>
              </div>
            </div>

            <h2 class="ticket-section-title contact-mt">Kontak yang Dapat Dihubungi</h2>

            <div class="contact-two-col">
              <div class="ticket-group" style="margin-bottom:0;">
                <label for="email" class="ticket-label">Email <span class="req-star">*</span></label>
                <input
                  type="email"
                  id="email"
                  name="email"
                  class="ticket-control"
                  placeholder="nama@contoh.com"
                  value="<?= htmlspecialchars($email_val) ?>"
                  required
                >
              </div>

              <div class="ticket-group" style="margin-bottom:0;">
                <label for="whatsapp" class="ticket-label">Nomor Whatsapp (Opsional)</label>
                <input
                  type="text"
                  id="whatsapp"
                  name="whatsapp"
                  class="ticket-control"
                  placeholder="08xxxxxxxxxxx"
                  value="<?= htmlspecialchars($whatsapp_val) ?>"
                >
              </div>
            </div>

            <div class="ticket-actions">
              <a href="support.php" class="btn-cancel">Batal</a>
              <button type="submit" class="btn-primary">Kirim Permintaan</button>
            </div>
          </form>
        </div>
      </section>

    <?php else: ?>
      <div class="page-header">
        <h1 class="page-title">Bantuan &amp; Pusat Dukungan</h1>
        <p class="page-subtitle">Temukan panduan penggunaan CareerMap dan bantuan saat mengalami kendala.</p>
      </div>

      <?php if ($success !== ''): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
      <?php endif; ?>

      <section class="help-outer-card">
        <div class="help-inner-card">
          <h2 class="help-hero-title">Ada yang bisa kami bantu?</h2>
          <p class="help-hero-sub">Temukan jawaban atau hubungi tim dukungan CareerMap.</p>

          <form class="help-search-box" id="helpSearchForm" onsubmit="return false;">
            <input
              type="text"
              id="helpSearchInput"
              class="help-search-input"
              placeholder="Cari topik bantuan, misalnya akun, lamaran, atau lowongan..."
            >
            <button type="submit" class="btn-search" id="helpSearchBtn">Cari</button>
          </form>

          <h3 class="topics-heading">Topik Bantuan</h3>

          <div class="topics-grid" id="topicsGrid">
            <?php foreach ($topics as $tKey => $tData): ?>
              <button
                type="button"
                class="topic-card"
                data-topic-key="<?= htmlspecialchars($tKey) ?>"
                data-search-text="<?= htmlspecialchars(strtolower($tData['title'] . ' ' . $tData['desc'])) ?>"
              >
                <div>
                  <div class="topic-title"><?= htmlspecialchars($tData['title']) ?></div>
                  <div class="topic-desc"><?= htmlspecialchars($tData['desc']) ?></div>
                </div>
                <span class="topic-chevron">&rsaquo;</span>
              </button>
            <?php endforeach; ?>
          </div>

          <div class="faq-panel" id="faqPanel">
            <div class="faq-panel-title" id="faqPanelTitle">Panduan Topik</div>
            <div id="faqPanelList"></div>
          </div>

          <div class="help-bottom-cta">
            <div>
              <div class="cta-title">Belum menemukan jawaban?</div>
              <div class="cta-sub">Hubungi tim dukungan melalui formulir bantuan.</div>
            </div>
            <a href="support.php?form=1" class="btn-primary">Hubungi Kami</a>
          </div>
        </div>
      </section>
    <?php endif; ?>
  </main>

  <script>
    (function () {
      const faqData = <?= json_encode($topics, JSON_UNESCAPED_UNICODE) ?>;

      const searchInput = document.getElementById('helpSearchInput');
      const searchBtn = document.getElementById('helpSearchBtn');
      const topicCards = document.querySelectorAll('.topic-card');
      const faqPanel = document.getElementById('faqPanel');
      const faqPanelTitle = document.getElementById('faqPanelTitle');
      const faqPanelList = document.getElementById('faqPanelList');

      function openTopicFaq(key) {
        if (!faqPanel || !faqData[key]) return;
        const item = faqData[key];
        faqPanelTitle.textContent = item.title;
        faqPanelList.innerHTML = (item.faqs || []).map((f) => (
          '<div class="faq-item"><div class="faq-q">' + f.q + '</div><div class="faq-a">' + f.a + '</div></div>'
        )).join('');
        faqPanel.classList.add('open');
      }

      topicCards.forEach((card) => {
        card.addEventListener('click', () => {
          const isAlreadyActive = card.classList.contains('active');
          topicCards.forEach((c) => c.classList.remove('active'));
          if (!isAlreadyActive) {
            card.classList.add('active');
            openTopicFaq(card.dataset.topicKey);
          } else if (faqPanel) {
            faqPanel.classList.remove('open');
          }
        });
      });

      function filterTopics() {
        if (!searchInput) return;
        const q = searchInput.value.trim().toLowerCase();
        topicCards.forEach((card) => {
          const hay = card.dataset.searchText || '';
          card.style.display = (q === '' || hay.includes(q)) ? 'flex' : 'none';
        });
      }

      if (searchInput) {
        searchInput.addEventListener('input', filterTopics);
      }
      if (searchBtn) {
        searchBtn.addEventListener('click', filterTopics);
      }

      const descArea = document.getElementById('description');
      const charCount = document.getElementById('charCount');
      if (descArea && charCount) {
        const updateCounter = () => {
          charCount.textContent = String(descArea.value.length);
        };
        descArea.addEventListener('input', updateCounter);
        updateCounter();
      }

      const dropzone = document.getElementById('uploadDropzone');
      const fileInput = document.getElementById('attachmentInput');
      const uploadMainText = document.getElementById('uploadMainText');

      if (dropzone && fileInput) {
        dropzone.addEventListener('click', () => fileInput.click());
        fileInput.addEventListener('change', () => {
          const f = fileInput.files && fileInput.files[0];
          if (f && uploadMainText) {
            uploadMainText.textContent = 'File dipilih: ' + f.name;
          }
        });
      }
    })();
  </script>

</body>
</html>
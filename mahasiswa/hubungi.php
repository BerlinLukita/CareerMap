<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit;
}

$pesan = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $topik = trim($_POST["topik"] ?? "");
    $judul = trim($_POST["judul"] ?? "");
    $deskripsi = trim($_POST["deskripsi"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $whatsapp = trim($_POST["whatsapp"] ?? "");

    if ($topik && $judul && $deskripsi && $email) {
        $pesan = "Permintaan bantuan berhasil dikirim.";
    } else {
        $pesan = "Mohon lengkapi seluruh kolom wajib.";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formulir Bantuan - CareerMap</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/CSS/form%20bantuan.css?v=117">
</head>
<body>
<div class="dashboard-layout">
    <aside class="sidebar">
        <div class="sidebar-logo">
            <span class="brand-logo-circle">
                <img src="../assets/images/logo-uajy.jpg" alt="Logo UAJY">
            </span>
            <span class="brand-name">CareerMap</span>
        </div>
        <div class="sidebar-label">MAHASISWA</div>
        <nav class="sidebar-menu">
            <a href="../mahasiswa/dashboard.php" class="menu-item">Dashboard</a>
            <a href="../mahasiswa/cari_lowongan.php" class="menu-item">Cari Lowongan</a>
            <a href="../mahasiswa/lamaran_saya.php" class="menu-item">Lamaran Saya</a>
            <a href="../mahasiswa/lowongan_disimpan.php" class="menu-item">Lowongan Disimpan</a>
            <a href="../mahasiswa/profile.php" class="menu-item">Profil Saya</a>
        </nav>
        <div class="sidebar-bottom">
            <a href="index.php" class="menu-item">Bantuan &amp; Pusat Dukungan</a>
            <a href="../auth/logout.php" class="menu-item logout">Keluar</a>
        </div>
    </aside>

    <main class="cm-main">
        <div class="cm-breadcrumb">
            <span>Bantuan &amp; Pusat Dukungan</span>
            <span class="cm-chevron">›</span>
            <strong>Formulir Bantuan</strong>
        </div>

        <header class="cm-page-header">
            <h1>Formulir Bantuan</h1>
            <p>Kirimkan pertanyaan, kendala, atau permintaan bantuan. Tim dukungan CareerMap akan menindaklanjuti secepatnya.</p>
        </header>

        <?php if ($pesan): ?>
            <div class="cm-alert">
                <?= htmlspecialchars($pesan, ENT_QUOTES, "UTF-8") ?>
            </div>
        <?php endif; ?>

        <section class="cm-form-card">
            <form method="POST" enctype="multipart/form-data">
                <h2>Informasi Permintaan Bantuan</h2>

                <div class="cm-field cm-topic-field">
                    <label for="topik">Topik Bantuan <span>*</span></label>
                    <div class="cm-select-wrap">
                        <select name="topik" id="topik" required>
                            <option value="">Pilih topik bantuan</option>
                            <option value="Akun dan Profil">Akun dan Profil</option>
                            <option value="Lowongan Pekerjaan">Lowongan Pekerjaan</option>
                            <option value="Lamaran Pekerjaan">Lamaran Pekerjaan</option>
                            <option value="Kendala Teknis">Kendala Teknis</option>
                            <option value="Lainnya">Lainnya</option>
                        </select>
                    </div>
                </div>

                <div class="cm-field">
                    <label for="judul">Judul Permohonan <span>*</span></label>
                    <input type="text" name="judul" id="judul" placeholder="Tuliskan ringkasan masalah atau kebutuhan bantuan..." required>
                </div>

                <div class="cm-field cm-description-field">
                    <label for="deskripsi">Deskripsi Permasalahan <span>*</span></label>
                    <textarea name="deskripsi" id="deskripsi" maxlength="500" placeholder="Jelaskan secara rinci kendala yang Anda alami..." required></textarea>
                    <div class="cm-counter"><span id="cm-count">0</span>/500</div>
                </div>

                <div class="cm-field cm-upload-field">
                    <label>Lampiran (Opsional)</label>
                    <label for="lampiran" class="cm-upload-box">
                        <span class="cm-upload-icon">
                            <span class="cm-upload-arrow"></span>
                            <span class="cm-upload-base"></span>
                        </span>
                        <span class="cm-upload-title" id="cm-file-label">Unggah file pendukung (screenshot, dokumen, dll.)</span>
                        <span class="cm-upload-info">Format: JPG, PNG, PDF (Maks. 5 MB)</span>
                    </label>
                    <input type="file" name="lampiran" id="lampiran" accept=".jpg,.jpeg,.png,.pdf" hidden>
                </div>

                <div class="cm-contact-section">
                    <h3>Kontak yang Dapat Dihubungi</h3>
                    <div class="cm-contact-grid">
                        <div class="cm-field">
                            <label for="email">Email <span>*</span></label>
                            <input type="email" name="email" id="email" placeholder="nama@contoh.com" required>
                        </div>
                        <div class="cm-field">
                            <label for="whatsapp">Nomor WhatsApp (Opsional)</label>
                            <input type="tel" name="whatsapp" id="whatsapp" placeholder="08xxxxxxxxxx">
                        </div>
                    </div>
                </div>

                <div class="cm-actions">
                    <a href="index.php" class="cm-btn cm-btn-cancel">Batal</a>
                    <button type="submit" class="cm-btn cm-btn-submit">Kirim Permintaan</button>
                </div>
            </form>
        </section>
    </main>
</div>

<script>
const description = document.getElementById("deskripsi");
const counter = document.getElementById("cm-count");

description.addEventListener("input", function () {
    counter.textContent = this.value.length;
});

const fileInput = document.getElementById("lampiran");
const fileLabel = document.getElementById("cm-file-label");

fileInput.addEventListener("change", function () {
    if (!this.files.length) return;

    const file = this.files[0];
    const allowedTypes = ["image/jpeg", "image/png", "application/pdf"];
    const maxSize = 5 * 1024 * 1024;

    if (!allowedTypes.includes(file.type) || file.size > maxSize) {
        alert("File harus JPG, PNG, atau PDF dengan ukuran maksimal 5 MB.");
        this.value = "";
        fileLabel.textContent = "Unggah file pendukung (screenshot, dokumen, dll.)";
        return;
    }

    fileLabel.textContent = file.name;
});
</script>
</body>
</html>

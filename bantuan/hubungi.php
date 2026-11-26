
<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit;
}

$pesan = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $topik     = trim($_POST["topik"] ?? "");
    $judul     = trim($_POST["judul"] ?? "");
    $deskripsi = trim($_POST["deskripsi"] ?? "");
    $email     = trim($_POST["email"] ?? "");
    $whatsapp  = trim($_POST["whatsapp"] ?? "");

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

    <link rel="stylesheet" href="../assets/CSS/form%20bantuan.css?v=107">
</head>

<body>

<div class="cm-layout">

    <!-- SIDEBAR -->
    <aside class="cm-sidebar">

        <!-- LOGO ATMA JAYA DAN CAREERMAP -->
        <div class="cm-brand">

            <img
                src="../assets/images/logo-uajy-transparan.png"
                alt="Logo Atma Jaya"
                class="cm-logo"
            >

            <span>CareerMap</span>

        </div>

        <div class="cm-role">MAHASISWA</div>

        <!-- MENU SIDEBAR -->
        <nav class="cm-menu">
            <a href="../mahasiswa/dashboard.php">Dashboard</a>
            <a href="../mahasiswa/cari_lowongan.php">Cari Lowongan</a>
            <a href="../mahasiswa/lamaran_saya.php">Lamaran Saya</a>
            <a href="../mahasiswa/lowongan_disimpan.php">Lowongan Disimpan</a>
            <a href="../mahasiswa/profile.php">Profil Saya</a>
        </nav>

        <!-- MENU BAWAH -->
        <div class="cm-sidebar-bottom">
            <a href="index.php">Bantuan &amp; Pusat Dukungan</a>
            <a href="../auth/logout.php">Keluar</a>
        </div>

    </aside>


    <!-- KONTEN UTAMA -->
    <main class="cm-main">

        <!-- BREADCRUMB -->
        <div class="cm-breadcrumb">
            <span>Bantuan &amp; Pusat Dukungan</span>
            <span class="cm-chevron">›</span>
            <strong>Formulir Bantuan</strong>
        </div>

        <!-- HEADER -->
        <header class="cm-page-header">
            <h1>Formulir Bantuan</h1>

            <p>
                Kirimkan pertanyaan, kendala, atau permintaan bantuan.
                Tim dukungan CareerMap akan menindaklanjuti secepatnya.
            </p>
        </header>

        <!-- NOTIFIKASI -->
        <?php if ($pesan): ?>
            <div class="cm-alert">
                <?= htmlspecialchars($pesan, ENT_QUOTES, "UTF-8") ?>
            </div>
        <?php endif; ?>


        <!-- KARTU FORMULIR -->
        <section class="cm-form-card">

            <form method="POST" enctype="multipart/form-data">

                <h2>Informasi Permintaan Bantuan</h2>

                <!-- TOPIK BANTUAN -->
                <div class="cm-field cm-topic-field">

                    <label for="topik">
                        Topik Bantuan <span>*</span>
                    </label>

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


                <!-- JUDUL PERMOHONAN -->
                <div class="cm-field">

                    <label for="judul">
                        Judul Permohonan <span>*</span>
                    </label>

                    <input
                        type="text"
                        name="judul"
                        id="judul"
                        placeholder="Tuliskan ringkasan masalah atau kebutuhan bantuan..."
                        required
                    >

                </div>


                <!-- DESKRIPSI -->
                <div class="cm-field cm-description-field">

                    <label for="deskripsi">
                        Deskripsi Permasalahan <span>*</span>
                    </label>

                    <textarea
                        name="deskripsi"
                        id="deskripsi"
                        maxlength="500"
                        placeholder="Jelaskan secara rinci kendala yang Anda alami..."
                        required
                    ></textarea>

                    <div class="cm-counter">
                        <span id="cm-count">0</span>/500
                    </div>

                </div>


                <!-- LAMPIRAN -->
                <div class="cm-field cm-upload-field">

                    <label>Lampiran (Opsional)</label>

                    <label for="lampiran" class="cm-upload-box">

                        <span class="cm-upload-icon">
                            <span class="cm-upload-arrow"></span>
                            <span class="cm-upload-base"></span>
                        </span>

                        <span class="cm-upload-title" id="cm-file-label">
                            Unggah file pendukung (screenshot, dokumen, dll.)
                        </span>

                        <span class="cm-upload-info">
                            Format: JPG, PNG, PDF (Maks. 5 MB)
                        </span>

                    </label>

                    <input
                        type="file"
                        name="lampiran"
                        id="lampiran"
                        accept=".jpg,.jpeg,.png,.pdf"
                        hidden
                    >

                </div>


                <!-- KONTAK -->
                <div class="cm-contact-section">

                    <h3>Kontak yang Dapat Dihubungi</h3>

                    <div class="cm-contact-grid">

                        <div class="cm-field">

                            <label for="email">
                                Email <span>*</span>
                            </label>

                            <input
                                type="email"
                                name="email"
                                id="email"
                                placeholder="nama@contoh.com"
                                required
                            >

                        </div>


                        <div class="cm-field">

                            <label for="whatsapp">
                                Nomor WhatsApp (Opsional)
                            </label>

                            <input
                                type="tel"
                                name="whatsapp"
                                id="whatsapp"
                                placeholder="08xxxxxxxxxx"
                            >

                        </div>

                    </div>

                </div>


                <!-- TOMBOL -->
                <div class="cm-actions">

                    <a href="index.php" class="cm-btn cm-btn-cancel">
                        Batal
                    </a>

                    <button type="submit" class="cm-btn cm-btn-submit">
                        Kirim Permintaan
                    </button>

                </div>

            </form>

        </section>

    </main>

</div>


<!-- JAVASCRIPT -->
<script>

    // Penghitung karakter
    const description = document.getElementById("deskripsi");
    const counter = document.getElementById("cm-count");

    description.addEventListener("input", function () {
        counter.textContent = this.value.length;
    });


    // Validasi lampiran
    const fileInput = document.getElementById("lampiran");
    const fileLabel = document.getElementById("cm-file-label");

    fileInput.addEventListener("change", function () {

        if (!this.files.length) return;

        const file = this.files[0];

        const allowedTypes = [
            "image/jpeg",
            "image/png",
            "application/pdf"
        ];

        const maxSize = 5 * 1024 * 1024;

        if (!allowedTypes.includes(file.type) || file.size > maxSize) {

            alert("File harus JPG, PNG, atau PDF dengan ukuran maksimal 5 MB.");

            this.value = "";

            fileLabel.textContent =
                "Unggah file pendukung (screenshot, dokumen, dll.)";

            return;
        }

        fileLabel.textContent = file.name;

    });

</script>

</body>
</html>

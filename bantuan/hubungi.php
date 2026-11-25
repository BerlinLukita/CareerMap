
<?php
session_start();

require_once "../config/database.php";

// CEK LOGIN MAHASISWA
if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "student"
) {
    header("Location: ../auth/login.php");
    exit;
}

$userName = $_SESSION["name"] ?? "Mahasiswa";

function e($value) {
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Formulir Bantuan | CareerMap</title>

    <!-- CSS SIDEBAR DAN LAYOUT YANG SUDAH ADA -->
    <link rel="stylesheet" href="../assets/css/bantuan.css">

    <!-- CSS KHUSUS FORMULIR -->
    <link rel="stylesheet" href="../assets/css/hubungi.css">
</head>

<body>

<div class="dashboard-layout">

    <!-- SIDEBAR -->
    <aside class="sidebar">

        <div class="sidebar-logo">
            <img src="../assets/images/logo-uajy.png"
                 alt="Logo CareerMap">
            <span>CareerMap</span>
        </div>

        <div class="sidebar-label">MAHASISWA</div>

        <nav class="sidebar-menu">

            <a href="../mahasiswa/dashboard.php" class="menu-item">
                Dashboard
            </a>

            <a href="../mahasiswa/cari_lowongan.php" class="menu-item">
                Cari Lowongan
            </a>

            <a href="../mahasiswa/lamaran_saya.php" class="menu-item">
                Lamaran Saya
            </a>

            <a href="../mahasiswa/lowongan_disimpan.php" class="menu-item">
                Lowongan Disimpan
            </a>

            <a href="../mahasiswa/profile.php" class="menu-item">
                Profil Saya
            </a>

        </nav>

        <div class="sidebar-bottom">

            <a href="index.php" class="menu-item active">
                Bantuan & Pusat Dukungan
            </a>

            <a href="../auth/logout.php" class="menu-item logout">
                Keluar
            </a>

        </div>

    </aside>


    <!-- KONTEN UTAMA -->
    <main class="main-content">

        <!-- BREADCRUMB -->
        <div class="contact-breadcrumb">
            <a href="index.php">Bantuan & Pusat Dukungan</a>
            <span>›</span>
            <strong>Formulir Bantuan</strong>
        </div>


        <!-- HEADER -->
        <header class="contact-header">

            <h1>Formulir Bantuan</h1>

            <p>
                Kirimkan pertanyaan, kendala, atau permintaan bantuan.
                Tim dukungan CareerMap akan menindaklanjuti secepatnya.
            </p>

        </header>


        <!-- FORM CONTAINER -->
        <section class="contact-wrapper">

            <form class="support-form"
                  action=""
                  method="POST"
                  enctype="multipart/form-data">

                <h2>Informasi Permintaan Bantuan</h2>


                <!-- TOPIK BANTUAN -->
                <div class="support-group">

                    <label for="topic">
                        Topik Bantuan <span>*</span>
                    </label>

                    <select name="topic" id="topic" required>

                        <option value="">Pilih topik bantuan</option>

                        <option value="Akun & Autentikasi">
                            Akun & Autentikasi
                        </option>

                        <option value="Profil & Dokumen">
                            Profil & Dokumen
                        </option>

                        <option value="Lowongan & Lamaran">
                            Lowongan & Lamaran
                        </option>

                        <option value="Perusahaan">
                            Perusahaan
                        </option>

                        <option value="Pelaporan & Keamanan">
                            Pelaporan & Keamanan
                        </option>

                        <option value="Lainnya">
                            Lainnya
                        </option>

                    </select>

                </div>


                <!-- JUDUL PERMOHONAN -->
                <div class="support-group">

                    <label for="subject">
                        Judul Permohonan <span>*</span>
                    </label>

                    <input
                        type="text"
                        id="subject"
                        name="subject"
                        placeholder="Tuliskan ringkasan masalah atau kebutuhan bantuan..."
                        maxlength="150"
                        required
                    >

                </div>


                <!-- DESKRIPSI -->
                <div class="support-group">

                    <label for="description">
                        Deskripsi Permasalahan <span>*</span>
                    </label>

                    <textarea
                        id="description"
                        name="description"
                        maxlength="500"
                        placeholder="Jelaskan secara rinci kendala yang Anda alami..."
                        required
                    ></textarea>

                    <div class="support-counter">
                        <span id="currentCount">0</span>/500
                    </div>

                </div>


                <!-- LAMPIRAN -->
                <div class="support-group">

                    <label>Lampiran (Opsional)</label>

                    <input
                        type="file"
                        name="attachment"
                        id="attachment"
                        accept=".jpg,.jpeg,.png,.pdf"
                        hidden
                    >

                    <label for="attachment" class="support-upload">

                        <div class="upload-icon">
                            <svg viewBox="0 0 24 24"
                                 width="25"
                                 height="25"
                                 fill="none"
                                 stroke="currentColor"
                                 stroke-width="2"
                                 stroke-linecap="round"
                                 stroke-linejoin="round">
                                <path d="M12 16V4"/>
                                <path d="m7 9 5-5 5 5"/>
                                <path d="M20 16.5v2A2.5 2.5 0 0 1 17.5 21h-11A2.5 2.5 0 0 1 4 18.5v-2"/>
                            </svg>
                        </div>

                        <strong>Unggah file pendukung</strong>

                        <small>
                            (screenshot, dokumen, dll.)
                        </small>

                        <small>
                            Format: JPG, PNG, PDF (Maks. 5 MB)
                        </small>

                        <span id="selectedFile"></span>

                    </label>

                </div>


                <!-- KONTAK -->
                <div class="support-contact-title">
                    Kontak yang Dapat Dihubungi
                </div>


                <div class="support-contact-grid">

                    <div class="support-group">

                        <label for="email">
                            Email <span>*</span>
                        </label>

                        <input
                            type="email"
                            id="email"
                            name="email"
                            placeholder="nama@contoh.com"
                            required
                        >

                    </div>


                    <div class="support-group">

                        <label for="whatsapp">
                            Nomor WhatsApp (Opsional)
                        </label>

                        <input
                            type="tel"
                            id="whatsapp"
                            name="whatsapp"
                            placeholder="08xxxxxxxxxx"
                        >

                    </div>

                </div>


                <!-- BUTTON -->
                <div class="support-actions">

                    <a href="index.php" class="support-cancel">
                        Batal
                    </a>

                    <button type="submit" class="support-submit">
                        Kirim Permintaan
                    </button>

                </div>

            </form>

        </section>

    </main>

</div>


<!-- JAVASCRIPT -->
<script>

// HITUNG KARAKTER DESKRIPSI
const description = document.getElementById("description");
const currentCount = document.getElementById("currentCount");

description.addEventListener("input", function() {
    currentCount.textContent = this.value.length;
});


// NAMA FILE LAMPIRAN
const attachment = document.getElementById("attachment");
const selectedFile = document.getElementById("selectedFile");

attachment.addEventListener("change", function() {

    if (this.files.length > 0) {

        const file = this.files[0];

        const allowedTypes = [
            "image/jpeg",
            "image/png",
            "application/pdf"
        ];

        if (!allowedTypes.includes(file.type)) {
            alert("Format file harus JPG, PNG, atau PDF.");
            this.value = "";
            selectedFile.textContent = "";
            return;
        }

        if (file.size > 5 * 1024 * 1024) {
            alert("Ukuran file maksimal 5 MB.");
            this.value = "";
            selectedFile.textContent = "";
            return;
        }

        selectedFile.textContent = "File dipilih: " + file.name;

    } else {
        selectedFile.textContent = "";
    }

});

</script>

</body>
</html>

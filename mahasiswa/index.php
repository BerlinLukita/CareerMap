<?php
session_start();

$loggedInUserId = $_SESSION["user_id"] ?? null;
$userRole = strtolower(trim((string) ($_SESSION["role"] ?? "")));

$allowedStudentRoles = ["student", "mahasiswa"];

if (empty($loggedInUserId) || !in_array($userRole, $allowedStudentRoles, true)) {
    header("Location: ../auth/login.php");
    exit;
}

require_once "../config/database.php";

$userId = (int) $loggedInUserId;
$userName = $_SESSION["name"] ?? "Mahasiswa";
$initial = function_exists("mb_substr")
    ? strtoupper(mb_substr($userName, 0, 1, "UTF-8"))
    : strtoupper(substr($userName, 0, 1));

function e($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

$helpCategories = [

    "akun" => [
        "title" => "Akun & Autentikasi",
        "description" => "Bantuan registrasi, login, lupa kata sandi, dan keamanan akun.",
        "short_description" => "Registrasi, login, lupa kata sandi, dan keamanan akun.",
        "faqs" => [
            [
                "question" => "Bagaimana cara membuat akun CareerMap?",
                "answer" => "Pilih jenis akun Mahasiswa atau Perusahaan, lalu lengkapi formulir pendaftaran dengan informasi yang diminta. Setelah itu, ikuti proses verifikasi jika diperlukan."
            ],
            [
                "question" => "Bagaimana jika saya lupa kata sandi?",
                "answer" => "Pilih Lupa Kata Sandi pada halaman Masuk, lalu masukkan alamat email yang terdaftar. Ikuti petunjuk pemulihan kata sandi yang diberikan."
            ],
            [
                "question" => "Mengapa saya tidak bisa masuk ke akun?",
                "answer" => "Pastikan email dan kata sandi sudah benar serta akun telah terdaftar. Periksa kembali penulisan email dan pastikan tidak ada spasi tambahan."
            ],
            [
                "question" => "Bagaimana cara menjaga keamanan akun?",
                "answer" => "Gunakan kata sandi yang kuat, jangan membagikan informasi login kepada orang lain, dan selalu keluar dari akun setelah menggunakan perangkat umum."
            ]
        ]
    ],

    "profil" => [
        "title" => "Profil & Dokumen",
        "description" => "Panduan mengubah profil, foto, CV, dan portofolio.",
        "short_description" => "Mengubah profil, foto, CV, dan portofolio.",
        "faqs" => [
            [
                "question" => "Bagaimana cara mengubah profil saya?",
                "answer" => "Buka menu Profil Saya, ubah informasi yang diperlukan, kemudian simpan perubahan agar data profil terbaru tersimpan."
            ],
            [
                "question" => "Bagaimana cara mengunggah CV?",
                "answer" => "Masuk ke bagian dokumen pada Profil Saya, pilih file CV dari perangkat, lalu unggah dokumen tersebut."
            ],
            [
                "question" => "Format dokumen apa yang dapat diunggah?",
                "answer" => "Gunakan format dokumen yang diminta pada formulir, seperti PDF untuk CV. Perhatikan batas ukuran file yang ditampilkan saat mengunggah."
            ],
            [
                "question" => "Apakah CV dapat diperbarui?",
                "answer" => "Ya. Unggah dokumen terbaru untuk menggantikan CV yang sebelumnya tersimpan."
            ]
        ]
    ],

    "lowongan" => [
        "title" => "Lowongan & Lamaran",
        "description" => "Panduan mencari lowongan, mengirim lamaran, dan melihat status.",
        "short_description" => "Mencari lowongan, mengirim lamaran, dan melihat status.",
        "faqs" => [
            [
                "question" => "Bagaimana cara mencari lowongan?",
                "answer" => "Buka menu Cari Lowongan, lalu gunakan kata kunci atau filter yang tersedia untuk menemukan pekerjaan yang sesuai."
            ],
            [
                "question" => "Bagaimana cara melamar pekerjaan?",
                "answer" => "Pilih lowongan yang sesuai, periksa persyaratan, lalu tekan tombol Lamar Sekarang dan lengkapi formulir lamaran."
            ],
            [
                "question" => "Di mana saya dapat melihat status lamaran?",
                "answer" => "Buka menu Lamaran Saya untuk melihat perkembangan proses rekrutmen dan status setiap lamaran yang telah dikirim."
            ],
            [
                "question" => "Bisakah lamaran yang sudah dikirim dibatalkan?",
                "answer" => "Pembatalan bergantung pada kebijakan perusahaan dan tahapan proses rekrutmen. Hubungi tim dukungan jika membutuhkan bantuan."
            ]
        ]
    ],

    "perusahaan" => [
        "title" => "Akun Perusahaan",
        "description" => "Panduan verifikasi perusahaan dan pengelolaan lowongan.",
        "short_description" => "Verifikasi perusahaan dan pengelolaan lowongan.",
        "faqs" => [
            [
                "question" => "Bagaimana cara mendaftarkan akun perusahaan?",
                "answer" => "Pilih jenis akun Perusahaan dan lengkapi informasi perusahaan pada formulir pendaftaran."
            ],
            [
                "question" => "Mengapa akun perusahaan perlu diverifikasi?",
                "answer" => "Verifikasi membantu memastikan identitas dan legalitas perusahaan sehingga informasi lowongan lebih dapat dipercaya."
            ],
            [
                "question" => "Bagaimana jika verifikasi perusahaan ditolak?",
                "answer" => "Baca catatan administrator, perbaiki dokumen atau informasi yang diminta, lalu ajukan verifikasi kembali."
            ],
            [
                "question" => "Bagaimana cara memasang lowongan?",
                "answer" => "Setelah akun terverifikasi, buka menu Kelola Lowongan dan pilih Tambah Lowongan. Lengkapi informasi pekerjaan sebelum mempublikasikannya."
            ]
        ]
    ],

    "pelaporan" => [
        "title" => "Pelaporan & Keamanan",
        "description" => "Panduan melaporkan perusahaan, lowongan, atau pengguna.",
        "short_description" => "Melaporkan perusahaan, lowongan, atau pengguna.",
        "faqs" => [
            [
                "question" => "Bagaimana cara melaporkan lowongan?",
                "answer" => "Buka detail lowongan, pilih Laporkan, lalu isi alasan dan keterangan yang relevan. Setelah dikirim, laporan akan diterima oleh administrator."
            ],
            [
                "question" => "Apa yang bisa saya laporkan?",
                "answer" => "Kamu dapat melaporkan informasi perusahaan, lowongan, atau perilaku pengguna yang mencurigakan dan tidak sesuai."
            ],
            [
                "question" => "Bagaimana laporan ditangani?",
                "answer" => "Tim administrator akan meninjau laporan dan menentukan tindak lanjut yang diperlukan."
            ],
            [
                "question" => "Apakah identitas pelapor ditampilkan?",
                "answer" => "Informasi pelapor digunakan untuk pemeriksaan dan penanganan laporan sesuai kebijakan privasi CareerMap."
            ]
        ]
    ]

];

$categoryKey = trim($_GET["kategori"] ?? "");
$keyword = trim($_GET["q"] ?? "");

$isCategoryPage = isset($helpCategories[$categoryKey]);

$selectedCategory = $isCategoryPage
    ? $helpCategories[$categoryKey]
    : null;

$searchResults = [];

if ($keyword !== "") {

    foreach ($helpCategories as $key => $category) {

        foreach ($category["faqs"] as $faq) {

            $rawSearchText = $faq["question"] . " " .
                $faq["answer"] . " " .
                $category["title"];

            if (function_exists("mb_stripos")) {
                $isMatch = mb_stripos(
                    $rawSearchText,
                    $keyword,
                    0,
                    "UTF-8"
                ) !== false;
            } else {
                $isMatch = stripos($rawSearchText, $keyword) !== false;
            }

            if ($isMatch) {

                $searchResults[] = [
                    "category_key" => $key,
                    "category_title" => $category["title"],
                    "question" => $faq["question"],
                    "answer" => $faq["answer"]
                ];

            }
        }
    }
}

$currentPage = "bantuan";

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Bantuan & Pusat Dukungan | CareerMap</title>

    <link rel="stylesheet" href="../assets/css/bantuan_mahasiswa.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

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
            <a href="index.php" class="menu-item active" aria-current="page">Bantuan &amp; Pusat Dukungan</a>
            <a href="../auth/logout.php" class="menu-item logout">Keluar</a>
        </div>
    </aside>

    

    <main class="main-content">

        <header class="page-header">

            <h1>Bantuan & Pusat Dukungan</h1>

            <p>
                Temukan panduan penggunaan CareerMap dan bantuan saat mengalami kendala.
            </p>

        </header>

        <section class="help-container">

            <div class="help-inner">

                <?php if ($isCategoryPage): ?>

                    

                    <div class="breadcrumb">

                        <a href="index.php">
                            Bantuan & Pusat Dukungan
                        </a>

                    </div>

                    <div class="category-heading">

                        <h2>
                            <?php echo e($selectedCategory["title"]); ?>
                        </h2>

                        <p>
                            <?php echo e($selectedCategory["description"]); ?>
                        </p>

                    </div>

                    <div class="faq-list">

                        <?php foreach ($selectedCategory["faqs"] as $index => $faq): ?>

                            <details class="faq-item">

                                <summary>

                                    <div class="faq-question">

                                        <?php echo e($faq["question"]); ?>

                                    </div>

                                    <span class="faq-arrow">›</span>

                                </summary>

                                <div class="faq-answer">
                                    <?php echo e($faq["answer"]); ?>
                                </div>

                            </details>

                        <?php endforeach; ?>

                    </div>

                    <div class="category-footer">

                        <div>

                            <h3>
                                <?php

                                $footerTitles = [
                                    "akun" => "Masih mengalami kendala saat masuk?",
                                    "profil" => "Dokumen gagal diunggah?",
                                    "lowongan" => "Mengalami kendala saat melamar?",
                                    "perusahaan" => "Butuh bantuan terkait verifikasi?",
                                    "pelaporan" => "Menemukan aktivitas mencurigakan?"
                                ];

                                echo e($footerTitles[$categoryKey]);

                                ?>
                            </h3>

                            <p>
                                Hubungi tim dukungan jika masalah belum terselesaikan.
                            </p>

                        </div>

                        <a href="hubungi.php" class="contact-button">
                            Hubungi Kami
                        </a>

                    </div>

                <?php elseif ($keyword !== ""): ?>

                    

                    <div class="search-result-heading">

                        <h2>Hasil Pencarian Bantuan</h2>

                        <p>
                            Hasil pencarian untuk:
                            <strong><?php echo e($keyword); ?></strong>
                        </p>

                    </div>

                    <form action="index.php" method="GET" class="help-search">

                        <input
                            type="text"
                            name="q"
                            value="<?php echo e($keyword); ?>"
                            placeholder="Cari topik bantuan..."
                        >

                        <button type="submit">
                            Cari
                        </button>

                    </form>

                    <?php if (!empty($searchResults)): ?>

                        <div class="faq-list search-results">

                            <?php foreach ($searchResults as $result): ?>

                                <details class="faq-item">

                                    <summary>

                                        <div class="faq-question">

                                            <?php echo e($result["question"]); ?>

                                            <span class="faq-short-answer">
                                                <?php echo e($result["category_title"]); ?>
                                            </span>

                                        </div>

                                        <span class="faq-arrow">›</span>

                                    </summary>

                                    <div class="faq-answer">

                                        <?php echo e($result["answer"]); ?>

                                        <p class="answer-category">

                                            Kategori:

                                            <a href="index.php?kategori=<?php echo e($result["category_key"]); ?>">
                                                <?php echo e($result["category_title"]); ?>
                                            </a>

                                        </p>

                                    </div>

                                </details>

                            <?php endforeach; ?>

                        </div>

                    <?php else: ?>

                        <div class="empty-search">

                            <h3>Topik bantuan tidak ditemukan</h3>

                            <p>
                                Coba gunakan kata kunci yang berbeda atau hubungi tim dukungan.
                            </p>

                            <a href="hubungi.php" class="contact-button">
                                Hubungi Kami
                            </a>

                        </div>

                    <?php endif; ?>

                    <div class="category-footer">

                        <div>

                            <h3>Belum menemukan jawaban?</h3>

                            <p>
                                Hubungi tim dukungan melalui formulir bantuan.
                            </p>

                        </div>

                        <a href="hubungi.php" class="contact-button">
                            Hubungi Kami
                        </a>

                    </div>

                <?php else: ?>

                    

                    <div class="help-home">

                        <div class="help-intro">

                            <h2>Ada yang bisa kami bantu?</h2>

                            <p>
                                Temukan jawaban atau hubungi tim dukungan CareerMap.
                            </p>

                        </div>

                        

                        <form action="index.php" method="GET" class="help-search">

                            <input
                                type="text"
                                name="q"
                                placeholder="Cari topik bantuan, misalnya akun, lamaran, atau lowongan..."
                                required
                            >

                            <button type="submit">
                                Cari
                            </button>

                        </form>

                        

                        <div class="topic-heading">
                            Topik Bantuan
                        </div>

                        <div class="topic-grid">

                            <?php foreach ($helpCategories as $key => $category): ?>

                                <a
                                    href="index.php?kategori=<?php echo e($key); ?>"
                                    class="topic-card"
                                >

                                    <div>

                                        <h3>
                                            <?php echo e($category["title"]); ?>
                                        </h3>

                                        <p>
                                            <?php echo e($category["short_description"]); ?>
                                        </p>

                                    </div>

                                    <span class="topic-arrow">›</span>

                                </a>

                            <?php endforeach; ?>

                        </div>

                        

                        <div class="help-footer">

                            <div>

                                <h3>Belum menemukan jawaban?</h3>

                                <p>
                                    Hubungi tim dukungan melalui formulir bantuan.
                                </p>

                            </div>

                            <a href="hubungi.php" class="contact-button">
                                Hubungi Kami
                            </a>

                        </div>

                    </div>

                <?php endif; ?>

            </div>

        </section>

    </main>

</div>

</body>
</html>

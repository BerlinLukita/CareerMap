<?php
require_once "../config/database.php";

$message = "";
$message_type = "";

$name = $_POST["name"] ?? "";
$email = $_POST["email"] ?? "";
$nim = $_POST["nim"] ?? "";
$prodi = $_POST["prodi"] ?? "";
$phone = $_POST["phone"] ?? "";
$website = $_POST["website"] ?? "";
$industry = $_POST["industry"] ?? "";
$address = $_POST["address"] ?? "";
$company_username = $_POST["company_username"] ?? "";
$role = $_POST["role"] ?? "student";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $nim = trim($_POST["nim"] ?? "");
    $prodi = trim($_POST["prodi"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $website = trim($_POST["website"] ?? "");
    $industry = trim($_POST["industry"] ?? "");
    $address = trim($_POST["address"] ?? "");
    $company_username = trim($_POST["company_username"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";
    $role = $_POST["role"] ?? "student";

    $username = ($role === "student") ? $nim : $company_username;

    $missing_student = ($role === "student" && (empty($nim) || empty($prodi)));
    $missing_company = ($role === "company" && (empty($phone) || empty($industry) || empty($address) || empty($company_username)));

    if (
        empty($name) ||
        empty($email) ||
        empty($password) ||
        empty($confirm_password) ||
        $missing_student ||
        $missing_company
    ) {
        $message = "Semua kolom wajib diisi!";
        $message_type = "error";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Format email tidak valid!";
        $message_type = "error";
    } elseif ($password !== $confirm_password) {
        $message = "Konfirmasi password tidak cocok!";
        $message_type = "error";
    } elseif (strlen($password) < 8) {
        $message = "Password minimal 8 karakter!";
        $message_type = "error";
    } elseif (!in_array($role, ["student", "company"])) {
        $message = "Jenis akun tidak valid!";
        $message_type = "error";
    } else {

        $check = $pdo->prepare(
            "SELECT id FROM users WHERE email = ? OR username = ?"
        );
        $check->execute([$email, $username]);

        if ($check->rowCount() > 0) {
            $message = ($role === "student")
                ? "Email atau NIM sudah terdaftar!"
                : "Email atau username perusahaan sudah digunakan!";
            $message_type = "error";
        } else {

            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            try {
                $pdo->beginTransaction();

                $sql = "INSERT INTO users
                        (name, email, username, password_hash, role)
                        VALUES (?, ?, ?, ?, ?)";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $name,
                    $email,
                    $username,
                    $password_hash,
                    $role
                ]);

                $user_id = $pdo->lastInsertId();

                if ($role == "student") {
                    $profile = $pdo->prepare(
                        "INSERT INTO student_profiles (user_id, nim, study_program)
                         VALUES (?, ?, ?)"
                    );
                    $profile->execute([$user_id, $nim, $prodi]);
                } else {
                    $profile = $pdo->prepare(
                        "INSERT INTO company_profiles
                         (user_id, company_name)
                         VALUES (?, ?)"
                    );
                    $profile->execute([$user_id, $name]);
                }

                $pdo->commit();

                $message = "Registrasi berhasil! Silakan login.";
                $message_type = "success";
                $name = $email = $nim = $prodi = $phone = $website = $industry = $address = $company_username = "";

            } catch (PDOException $e) {
                $pdo->rollBack();
                $message = "Registrasi gagal. Silakan coba lagi.";
                $message_type = "error";
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
    <title>Buat Akun - CareerMap</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="register.css">
</head>
<body>

    <header class="navbar">
        <a href="../index.php" class="brand">
            <img src="logouajy.jpeg" alt="Logo CareerMap" class="brand-logo" onerror="this.style.display='none'" onerror="this.style.display='none'">
            <span class="brand-name">CareerMap</span>
        </a>
        <div class="nav-action-placeholder"></div>
    </header>

    <main class="main-container">
        <section class="register-card">
            <div class="card-header">
                <h1 class="card-title">Buat akun CareerMap</h1>
                <p class="card-subtitle">Pilih jenis akun dan lengkapi data awalmu.</p>
            </div>

            <div class="role-tabs">
                <button type="button"
                        class="role-tab <?php echo $role === 'student' ? 'active' : 'inactive'; ?>"
                        data-role="student"
                        onclick="selectRole('student')">
                    MAHASISWA
                </button>
                <button type="button"
                        class="role-tab <?php echo $role === 'company' ? 'active' : 'inactive'; ?>"
                        data-role="company"
                        onclick="selectRole('company')">
                    PERUSAHAAN
                </button>
            </div>

            <p class="role-helper" id="roleHelper" style="<?php echo $role === 'company' ? '' : 'display:none;'; ?>">
                Lengkapi data perusahaan terlebih dahulu.
            </p>

            <?php if (!empty($message)): ?>
                <div class="alert <?php echo $message_type === 'success' ? 'alert-success' : 'alert-error'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="register-form" id="registerForm">
                <input type="hidden" name="role" id="roleInput" value="<?php echo htmlspecialchars($role); ?>">

                <div class="form-grid">
                    <!-- Baris 1: Nama / Nama Perusahaan & Email -->
                    <div class="form-group">
                        <label class="form-label" id="nameLabel">
                            <?php echo $role === 'company' ? 'Nama Perusahaan' : 'Nama Lengkap'; ?>
                        </label>
                        <input type="text"
                               name="name"
                               id="nameInput"
                               class="form-input"
                               placeholder="<?php echo $role === 'company' ? 'Contoh: PT Teknologi Nusantara' : 'Nama sesuai identitas'; ?>"
                               value="<?php echo htmlspecialchars($name); ?>"
                               required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" id="emailLabel">
                            <?php echo $role === 'company' ? 'Email Perusahaan' : 'Email UAJY / Email Aktif'; ?>
                        </label>
                        <input type="email"
                               name="email"
                               id="emailInput"
                               class="form-input"
                               placeholder="<?php echo $role === 'company' ? 'hr@perusahaan.co.id' : 'nama@email.com'; ?>"
                               value="<?php echo htmlspecialchars($email); ?>"
                               required>
                    </div>

                    <!-- Baris 2 (Mahasiswa): NIM & Program Studi -->
                    <div class="form-group student-only" id="nimGroup" style="<?php echo $role === 'company' ? 'display:none;' : ''; ?>">
                        <label class="form-label">NIM</label>
                        <input type="text"
                               name="nim"
                               id="nimInput"
                               class="form-input"
                               placeholder="Nomor Induk Mahasiswa"
                               value="<?php echo htmlspecialchars($nim); ?>"
                               <?php echo $role === 'student' ? 'required' : ''; ?>>
                    </div>

                    <div class="form-group student-only" id="prodiGroup" style="<?php echo $role === 'company' ? 'display:none;' : ''; ?>">
                        <label class="form-label">Program Studi</label>
                        <select name="prodi" id="prodiInput" class="form-select" <?php echo $role === 'student' ? 'required' : ''; ?>>
                            <option value="" disabled <?php echo empty($prodi) ? 'selected' : ''; ?>>Pilih program studi</option>
                            <option value="Informatika" <?php echo $prodi === 'Informatika' ? 'selected' : ''; ?>>Informatika</option>
                            <option value="Sistem Informasi" <?php echo $prodi === 'Sistem Informasi' ? 'selected' : ''; ?>>Sistem Informasi</option>
                            <option value="Teknik Industri" <?php echo $prodi === 'Teknik Industri' ? 'selected' : ''; ?>>Teknik Industri</option>
                            <option value="Teknik Sipil" <?php echo $prodi === 'Teknik Sipil' ? 'selected' : ''; ?>>Teknik Sipil</option>
                            <option value="Arsitektur" <?php echo $prodi === 'Arsitektur' ? 'selected' : ''; ?>>Arsitektur</option>
                            <option value="Manajemen" <?php echo $prodi === 'Manajemen' ? 'selected' : ''; ?>>Manajemen</option>
                            <option value="Akuntansi" <?php echo $prodi === 'Akuntansi' ? 'selected' : ''; ?>>Akuntansi</option>
                            <option value="Ekonomi Pembangunan" <?php echo $prodi === 'Ekonomi Pembangunan' ? 'selected' : ''; ?>>Ekonomi Pembangunan</option>
                            <option value="Ilmu Hukum" <?php echo $prodi === 'Ilmu Hukum' ? 'selected' : ''; ?>>Ilmu Hukum</option>
                            <option value="Ilmu Komunikasi" <?php echo $prodi === 'Ilmu Komunikasi' ? 'selected' : ''; ?>>Ilmu Komunikasi</option>
                            <option value="Sosiologi" <?php echo $prodi === 'Sosiologi' ? 'selected' : ''; ?>>Sosiologi</option>
                            <option value="Biologi" <?php echo $prodi === 'Biologi' ? 'selected' : ''; ?>>Biologi</option>
                        </select>
                    </div>

                    <!-- Baris 2 (Perusahaan): Nomor Telepon & Website Perusahaan -->
                    <div class="form-group company-only" id="phoneGroup" style="<?php echo $role === 'company' ? '' : 'display:none;'; ?>">
                        <label class="form-label">Nomor Telepon</label>
                        <input type="text"
                               name="phone"
                               id="phoneInput"
                               class="form-input"
                               placeholder="08xxxxxxxxxx"
                               value="<?php echo htmlspecialchars($phone); ?>"
                               <?php echo $role === 'company' ? 'required' : ''; ?>>
                    </div>

                    <div class="form-group company-only" id="websiteGroup" style="<?php echo $role === 'company' ? '' : 'display:none;'; ?>">
                        <label class="form-label">Website Perusahaan</label>
                        <input type="text"
                               name="website"
                               id="websiteInput"
                               class="form-input"
                               placeholder="https://www.perusahaan.co.id"
                               value="<?php echo htmlspecialchars($website); ?>">
                    </div>

                    <!-- Baris 3 (Perusahaan): Bidang Industri & Alamat Perusahaan -->
                    <div class="form-group company-only" id="industryGroup" style="<?php echo $role === 'company' ? '' : 'display:none;'; ?>">
                        <label class="form-label">Bidang Industri</label>
                        <select name="industry" id="industryInput" class="form-select" <?php echo $role === 'company' ? 'required' : ''; ?>>
                            <option value="" disabled <?php echo empty($industry) ? 'selected' : ''; ?>>Pilih bidang industri</option>
                            <option value="Teknologi Informasi" <?php echo $industry === 'Teknologi Informasi' ? 'selected' : ''; ?>>Teknologi Informasi</option>
                            <option value="Keuangan & Perbankan" <?php echo $industry === 'Keuangan & Perbankan' ? 'selected' : ''; ?>>Keuangan & Perbankan</option>
                            <option value="Manufaktur & Industri" <?php echo $industry === 'Manufaktur & Industri' ? 'selected' : ''; ?>>Manufaktur & Industri</option>
                            <option value="Pariwisata & Perhotelan" <?php echo $industry === 'Pariwisata & Perhotelan' ? 'selected' : ''; ?>>Pariwisata & Perhotelan</option>
                            <option value="Media & Kreatif" <?php echo $industry === 'Media & Kreatif' ? 'selected' : ''; ?>>Media & Kreatif</option>
                            <option value="Lainnya" <?php echo $industry === 'Lainnya' ? 'selected' : ''; ?>>Lainnya</option>
                        </select>
                    </div>

                    <div class="form-group company-only" id="addressGroup" style="<?php echo $role === 'company' ? '' : 'display:none;'; ?>">
                        <label class="form-label">Alamat Perusahaan</label>
                        <input type="text"
                               name="address"
                               id="addressInput"
                               class="form-input"
                               placeholder="Alamat lengkap perusahaan"
                               value="<?php echo htmlspecialchars($address); ?>"
                               <?php echo $role === 'company' ? 'required' : ''; ?>>
                    </div>

                    <!-- Baris 4 (Perusahaan): Username (kiri) & Kata Sandi (kanan) -->
                    <div class="form-group company-only" id="companyUsernameGroup" style="<?php echo $role === 'company' ? '' : 'display:none;'; ?>">
                        <label class="form-label">Username</label>
                        <input type="text"
                               name="company_username"
                               id="companyUsernameInput"
                               class="form-input"
                               placeholder="Buat username perusahaan"
                               value="<?php echo htmlspecialchars($company_username); ?>"
                               <?php echo $role === 'company' ? 'required' : ''; ?>>
                    </div>

                    <!-- Kata Sandi (tampil di sini saat Perusahaan, atau di baris sendiri saat Mahasiswa) -->
                    <div class="form-group" id="passwordGroup">
                        <label class="form-label">Kata Sandi</label>
                        <div class="input-password-wrap">
                            <input type="password"
                                   name="password"
                                   id="passwordInput"
                                   class="form-input has-toggle"
                                   placeholder="Minimal 8 karakter"
                                   required>
                            <button type="button" class="toggle-password" onclick="togglePassword('passwordInput', this)" aria-label="Tampilkan kata sandi">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                            </button>
                        </div>
                        <span class="field-error" id="passwordError"></span>
                    </div>

                    <!-- Baris 5 (Perusahaan): Konfirmasi Kata Sandi (kiri) & Box Ketentuan Kata Sandi (kanan) -->
                    <div class="form-group" id="confirmPasswordGroup">
                        <label class="form-label">Konfirmasi Kata Sandi</label>
                        <div class="input-password-wrap">
                            <input type="password"
                                   name="confirm_password"
                                   id="confirmPasswordInput"
                                   class="form-input has-toggle"
                                   placeholder="Ulangi kata sandi"
                                   required>
                            <button type="button" class="toggle-password" onclick="togglePassword('confirmPasswordInput', this)" aria-label="Tampilkan konfirmasi kata sandi">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                            </button>
                        </div>
                        <span class="field-error" id="confirmPasswordError"></span>
                    </div>

                    <div class="password-hint-box company-only" id="passwordHintBox" style="<?php echo $role === 'company' ? '' : 'display:none;'; ?>">
                        <div class="password-hint-title">
                            <span class="hint-icon">!</span>
                            <span>Ketentuan kata sandi</span>
                        </div>
                        <p class="password-hint-text">Gunakan minimal 8 karakter agar akun lebih aman.</p>
                    </div>
                </div>

                <div class="terms-group">
                    <input type="checkbox" id="terms" class="terms-checkbox" required>
                    <label for="terms" class="terms-label">Saya menyetujui Syarat & Ketentuan CareerMap</label>
                </div>

                <div class="submit-row">
                    <button type="submit" class="btn-submit" id="submitBtn">
                        <?php echo $role === 'company' ? 'Buat Akun Perusahaan' : 'Buat Akun'; ?>
                    </button>
                    <p class="login-inline">
                        Sudah punya akun? <a href="login.php" class="login-redirect">Masuk di sini</a>
                    </p>
                </div>

                <p class="company-note" id="companyNote" style="<?php echo $role === 'company' ? '' : 'display:none;'; ?>">
                    Setelah mendaftar, perusahaan dapat melengkapi profil dan mengirim dokumen untuk verifikasi admin.
                </p>
            </form>
        </section>
    </main>

    <script>
        function selectRole(selectedRole) {
            document.getElementById('roleInput').value = selectedRole;

            document.querySelectorAll('.role-tab').forEach(tab => {
                const isSelected = tab.dataset.role === selectedRole;
                tab.classList.toggle('active', isSelected);
                tab.classList.toggle('inactive', !isSelected);
            });

            const roleHelper = document.getElementById('roleHelper');
            const nameLabel = document.getElementById('nameLabel');
            const nameInput = document.getElementById('nameInput');
            const emailLabel = document.getElementById('emailLabel');
            const emailInput = document.getElementById('emailInput');
            const submitBtn = document.getElementById('submitBtn');
            const companyNote = document.getElementById('companyNote');

            const studentGroups = document.querySelectorAll('.student-only');
            const companyGroups = document.querySelectorAll('.company-only');

            const nimInput = document.getElementById('nimInput');
            const prodiInput = document.getElementById('prodiInput');
            const phoneInput = document.getElementById('phoneInput');
            const industryInput = document.getElementById('industryInput');
            const addressInput = document.getElementById('addressInput');
            const companyUsernameInput = document.getElementById('companyUsernameInput');

            if (selectedRole === 'company') {
                roleHelper.style.display = 'block';
                nameLabel.textContent = 'Nama Perusahaan';
                nameInput.placeholder = 'Contoh: PT Teknologi Nusantara';
                emailLabel.textContent = 'Email Perusahaan';
                emailInput.placeholder = 'hr@perusahaan.co.id';
                submitBtn.textContent = 'Buat Akun Perusahaan';
                companyNote.style.display = 'block';

                studentGroups.forEach(el => el.style.display = 'none');
                companyGroups.forEach(el => el.style.display = 'flex');

                nimInput.required = false;
                prodiInput.required = false;
                phoneInput.required = true;
                industryInput.required = true;
                addressInput.required = true;
                companyUsernameInput.required = true;
            } else {
                roleHelper.style.display = 'none';
                nameLabel.textContent = 'Nama Lengkap';
                nameInput.placeholder = 'Nama sesuai identitas';
                emailLabel.textContent = 'Email UAJY / Email Aktif';
                emailInput.placeholder = 'nama@email.com';
                submitBtn.textContent = 'Buat Akun';
                companyNote.style.display = 'none';

                studentGroups.forEach(el => el.style.display = 'flex');
                companyGroups.forEach(el => el.style.display = 'none');

                nimInput.required = true;
                prodiInput.required = true;
                phoneInput.required = false;
                industryInput.required = false;
                addressInput.required = false;
                companyUsernameInput.required = false;
            }
        }

        function togglePassword(inputId, btn) {
            const input = document.getElementById(inputId);
            input.type = input.type === 'password' ? 'text' : 'password';
            btn.classList.toggle('active', input.type === 'text');
        }

        // Validasi visual password di sisi klien sebelum submit
        const passwordInput = document.getElementById('passwordInput');
        const confirmPasswordInput = document.getElementById('confirmPasswordInput');
        const passwordError = document.getElementById('passwordError');
        const confirmPasswordError = document.getElementById('confirmPasswordError');

        function validatePasswords() {
            const pwd = passwordInput.value;
            const confirmPwd = confirmPasswordInput.value;

            if (pwd.length > 0 && pwd.length < 8) {
                passwordInput.classList.add('input-error');
                passwordError.textContent = 'Kata sandi minimal 8 karakter.';
            } else {
                passwordInput.classList.remove('input-error');
                passwordError.textContent = '';
            }

            if (confirmPwd.length > 0 && pwd !== confirmPwd) {
                confirmPasswordInput.classList.add('input-error');
                confirmPasswordError.textContent = 'Konfirmasi kata sandi tidak cocok.';
            } else {
                confirmPasswordInput.classList.remove('input-error');
                confirmPasswordError.textContent = '';
            }
        }

        passwordInput.addEventListener('input', validatePasswords);
        confirmPasswordInput.addEventListener('input', validatePasswords);
    </script>

</body>
</html>
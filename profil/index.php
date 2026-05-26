<?php
session_start();

require_once '../config/database.php';
require_once '../includes/ui.php';

if (!isset($_SESSION['tenant_id'], $_SESSION['user_id'], $_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

$tenant_id = (int) $_SESSION['tenant_id'];
$user_id = (int) $_SESSION['user_id'];

function column_exists($pdo, $table, $column) {
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $stmt->execute([$column]);
        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return false;
    }
}

function pick_user_name_column($pdo) {
    $candidates = ['nama', 'nama_lengkap', 'name', 'username'];
    foreach ($candidates as $col) {
        if (column_exists($pdo, 'users', $col)) {
            return $col;
        }
    }
    return null;
}

function redirect_profile($status) {
    header('Location: index.php?status=' . urlencode($status));
    exit;
}

$has_foto_profil = column_exists($pdo, 'users', 'foto_profil');
$name_column = pick_user_name_column($pdo);

if (!$has_foto_profil) {
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN foto_profil VARCHAR(255) NULL AFTER email");
        $has_foto_profil = true;
    } catch (Throwable $e) {
        $has_foto_profil = false;
    }
}

try {
    $stmt = $pdo->prepare("
        SELECT *
        FROM users
        WHERE id = ?
          AND tenant_id = ?
        LIMIT 1
    ");
    $stmt->execute([$user_id, $tenant_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $user = false;
}

if (!$user) {
    die('User tidak ditemukan.');
}

$nama_user = 'User';
if ($name_column && isset($user[$name_column]) && trim((string)$user[$name_column]) !== '') {
    $nama_user = $user[$name_column];
} elseif (!empty($user['email'])) {
    $nama_user = $user['email'];
}

$email_user = $user['email'] ?? '';
$role_user = $user['role'] ?? ($_SESSION['role'] ?? '-');
$foto_user = $has_foto_profil ? ($user['foto_profil'] ?? '') : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'update_profil') {
        $nama_baru = trim($_POST['nama'] ?? '');
        $email_baru = trim($_POST['email'] ?? '');

        if ($nama_baru === '') {
            redirect_profile('nama_kosong');
        }

        if ($email_baru === '' || !filter_var($email_baru, FILTER_VALIDATE_EMAIL)) {
            redirect_profile('email_invalid');
        }

        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM users
                WHERE email = ?
                  AND id <> ?
            ");
            $stmt->execute([$email_baru, $user_id]);
            $email_dipakai = (int) $stmt->fetchColumn();

            if ($email_dipakai > 0) {
                redirect_profile('email_dipakai');
            }

            $set = [];
            $params = [];

            if ($name_column) {
                $set[] = "`$name_column` = ?";
                $params[] = $nama_baru;
            }

            if (column_exists($pdo, 'users', 'email')) {
                $set[] = "email = ?";
                $params[] = $email_baru;
            }

            if (empty($set)) {
                redirect_profile('gagal');
            }

            $params[] = $user_id;
            $params[] = $tenant_id;

            $sql = "
                UPDATE users
                SET " . implode(', ', $set) . "
                WHERE id = ?
                  AND tenant_id = ?
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            if ($name_column) {
                $_SESSION['nama'] = $nama_baru;
                $_SESSION['nama_lengkap'] = $nama_baru;
                $_SESSION['name'] = $nama_baru;
            }

            $_SESSION['email'] = $email_baru;

            redirect_profile('profil_sukses');
        } catch (Throwable $e) {
            redirect_profile('gagal');
        }
    }

    if ($aksi === 'ganti_password') {
        $password_lama = $_POST['password_lama'] ?? '';
        $password_baru = $_POST['password_baru'] ?? '';
        $password_konfirmasi = $_POST['password_konfirmasi'] ?? '';

        if ($password_lama === '' || $password_baru === '' || $password_konfirmasi === '') {
            redirect_profile('password_kosong');
        }

        if (strlen($password_baru) < 6) {
            redirect_profile('password_pendek');
        }

        if ($password_baru !== $password_konfirmasi) {
            redirect_profile('password_tidak_sama');
        }

        $hash_lama = $user['password'] ?? '';

        $password_valid = false;

        if ($hash_lama && password_verify($password_lama, $hash_lama)) {
            $password_valid = true;
        } elseif ($hash_lama && md5($password_lama) === $hash_lama) {
            $password_valid = true;
        } elseif ($hash_lama && $password_lama === $hash_lama) {
            $password_valid = true;
        }

        if (!$password_valid) {
            redirect_profile('password_lama_salah');
        }

        try {
            $hash_baru = password_hash($password_baru, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("
                UPDATE users
                SET password = ?
                WHERE id = ?
                  AND tenant_id = ?
            ");
            $stmt->execute([$hash_baru, $user_id, $tenant_id]);

            redirect_profile('password_sukses');
        } catch (Throwable $e) {
            redirect_profile('gagal');
        }
    }

    if ($aksi === 'ganti_foto') {
        if (!$has_foto_profil) {
            redirect_profile('gagal');
        }

        if (!isset($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
            redirect_profile('foto_kosong');
        }

        $file = $_FILES['foto'];

        if ($file['size'] > 2 * 1024 * 1024) {
            redirect_profile('foto_besar');
        }

        $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];
        $allowed_mime = ['image/jpeg', 'image/png', 'image/webp'];

        $original_name = $file['name'] ?? '';
        $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : '';
        if ($finfo) {
            finfo_close($finfo);
        }

        if (!in_array($ext, $allowed_ext, true) || !in_array($mime, $allowed_mime, true)) {
            redirect_profile('foto_invalid');
        }

        $upload_dir = "../public/uploads/tenant_{$tenant_id}/profil/";

        if (!is_dir($upload_dir)) {
            @mkdir($upload_dir, 0775, true);
        }

        if (!is_dir($upload_dir) || !is_writable($upload_dir)) {
            redirect_profile('folder_upload_gagal');
        }

        $nama_file = 'profil_' . $user_id . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $target = $upload_dir . $nama_file;

        if (!move_uploaded_file($file['tmp_name'], $target)) {
            redirect_profile('upload_gagal');
        }

        try {
            $foto_lama = $user['foto_profil'] ?? '';

            $stmt = $pdo->prepare("
                UPDATE users
                SET foto_profil = ?
                WHERE id = ?
                  AND tenant_id = ?
            ");
            $stmt->execute([$nama_file, $user_id, $tenant_id]);

            if (!empty($foto_lama)) {
                $path_lama = $upload_dir . $foto_lama;
                if (is_file($path_lama)) {
                    @unlink($path_lama);
                }
            }

            $_SESSION['foto_profil'] = $nama_file;

            redirect_profile('foto_sukses');
        } catch (Throwable $e) {
            if (is_file($target)) {
                @unlink($target);
            }
            redirect_profile('gagal');
        }
    }

    redirect_profile('gagal');
}

$status = $_GET['status'] ?? '';
$alertTitle = '';
$alertText = '';
$alertIcon = '';

$alertMap = [
    'profil_sukses' => ['Profil Diperbarui', 'Data profil berhasil disimpan.', 'success'],
    'password_sukses' => ['Password Diganti', 'Password akun berhasil diperbarui.', 'success'],
    'foto_sukses' => ['Foto Profil Diganti', 'Foto profil berhasil diperbarui.', 'success'],

    'nama_kosong' => ['Nama Kosong', 'Nama tidak boleh kosong.', 'warning'],
    'email_invalid' => ['Email Tidak Valid', 'Masukkan alamat email yang benar.', 'warning'],
    'email_dipakai' => ['Email Sudah Dipakai', 'Email tersebut sudah digunakan oleh user lain.', 'warning'],

    'password_kosong' => ['Password Belum Lengkap', 'Isi password lama, password baru, dan konfirmasi password.', 'warning'],
    'password_pendek' => ['Password Terlalu Pendek', 'Password baru minimal 6 karakter.', 'warning'],
    'password_tidak_sama' => ['Konfirmasi Tidak Cocok', 'Password baru dan konfirmasi password tidak sama.', 'warning'],
    'password_lama_salah' => ['Password Lama Salah', 'Password lama yang Anda masukkan tidak sesuai.', 'error'],

    'foto_kosong' => ['Foto Belum Dipilih', 'Pilih foto profil terlebih dahulu.', 'warning'],
    'foto_besar' => ['Foto Terlalu Besar', 'Ukuran foto maksimal 2 MB.', 'warning'],
    'foto_invalid' => ['Format Foto Tidak Didukung', 'Gunakan file JPG, PNG, atau WEBP.', 'warning'],
    'folder_upload_gagal' => ['Folder Upload Bermasalah', 'Folder upload tidak bisa dibuat atau tidak bisa ditulis.', 'error'],
    'upload_gagal' => ['Upload Gagal', 'Foto gagal diunggah. Coba lagi.', 'error'],

    'gagal' => ['Gagal', 'Proses gagal. Silakan coba lagi.', 'error'],
];

if (isset($alertMap[$status])) {
    [$alertTitle, $alertText, $alertIcon] = $alertMap[$status];
}

$foto_url = '';
if (!empty($foto_user)) {
    $foto_url = "../public/uploads/tenant_{$tenant_id}/profil/" . rawurlencode($foto_user);
}

ui_head('Profil Saya');
ui_nav($pdo, 'Profil Saya');
?>

<style>
.profile-avatar {
    width: 112px;
    height: 112px;
    border-radius: 999px;
    overflow: hidden;
    border: 2px solid rgba(249, 115, 22, .6);
    background: #020617;
    display: flex;
    align-items: center;
    justify-content: center;
}

.profile-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.profile-avatar svg {
    width: 52px;
    height: 52px;
    color: #64748b;
}

.profile-section-title {
    font-size: 1rem;
    font-weight: 800;
    color: #f8fafc;
}

.profile-section-desc {
    color: #94a3b8;
    font-size: .875rem;
}

.swal2-container,
.kasirrapi-swal-container {
    position: fixed !important;
    inset: 0 !important;
    width: 100vw !important;
    height: 100vh !important;
    z-index: 999999 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    padding: 16px !important;
    overflow: hidden !important;
}

.swal2-popup,
.kasirrapi-swal-popup {
    margin: 0 !important;
    max-width: 92vw !important;
    border-radius: 18px !important;
}

body.swal2-shown,
body.swal2-height-auto {
    height: auto !important;
    overflow: hidden !important;
}
</style>

<div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
    <div>
        <h2 class="text-2xl font-bold text-emerald-400">Profil Saya</h2>
        <p class="text-slate-400">Kelola data akun, foto profil, dan keamanan password.</p>
    </div>

    <a href="../dashboard.php" class="btn btn-secondary">Kembali</a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-[360px_1fr] gap-5">

    <div class="app-card p-5">
        <div class="flex flex-col items-center text-center">
            <div class="profile-avatar mb-4">
                <?php if (!empty($foto_url)): ?>
                    <img src="<?= h($foto_url) ?>" alt="Foto Profil">
                <?php else: ?>
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 7.5a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 20.25a7.5 7.5 0 0 1 15 0"/>
                    </svg>
                <?php endif; ?>
            </div>

            <h3 class="text-xl font-extrabold text-white"><?= h($nama_user) ?></h3>
            <p class="text-sm text-emerald-300"><?= h($email_user ?: '-') ?></p>
            <p class="mt-2 inline-flex px-3 py-1 rounded-full bg-slate-900 border border-slate-700 text-xs text-slate-300">
                <?= h($role_user ?: '-') ?>
            </p>
        </div>

        <form method="POST" enctype="multipart/form-data" class="mt-6 space-y-3">
            <input type="hidden" name="aksi" value="ganti_foto">

            <div>
                <label class="label">Ganti Foto Profil</label>
                <input 
                    type="file" 
                    name="foto" 
                    accept="image/jpeg,image/png,image/webp" 
                    class="app-input"
                    required
                >
                <p class="text-xs text-slate-500 mt-1">Format JPG, PNG, atau WEBP. Maksimal 2 MB.</p>
            </div>

            <button type="submit" class="btn btn-primary w-full">
                Upload Foto
            </button>
        </form>
    </div>

    <div class="space-y-5">

        <div class="app-card p-5">
            <div class="mb-4">
                <div class="profile-section-title">Data Profil</div>
                <div class="profile-section-desc">Ubah nama dan email akun Anda.</div>
            </div>

            <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <input type="hidden" name="aksi" value="update_profil">

                <div>
                    <label class="label">Nama</label>
                    <input 
                        type="text" 
                        name="nama" 
                        value="<?= h($nama_user) ?>" 
                        class="app-input" 
                        required
                    >
                </div>

                <div>
                    <label class="label">Email</label>
                    <input 
                        type="email" 
                        name="email" 
                        value="<?= h($email_user) ?>" 
                        class="app-input" 
                        required
                    >
                </div>

                <div class="md:col-span-2 flex justify-end">
                    <button type="submit" class="btn btn-primary">
                        Simpan Profil
                    </button>
                </div>
            </form>
        </div>

        <div class="app-card p-5">
    <div class="mb-4">
        <div class="profile-section-title">Ganti Password</div>
        <div class="profile-section-desc">Gunakan password yang kuat agar akun tetap aman.</div>
    </div>

    <form method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <input type="hidden" name="aksi" value="ganti_password">

        <div>
            <label class="label">Password Lama</label>
            <div class="relative">
                <input 
                    type="password" 
                    name="password_lama" 
                    class="app-input pr-12 input-password" 
                    autocomplete="current-password"
                    required
                >
                <button 
                    type="button" 
                    class="btn-toggle-password absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-orange-300"
                    data-target="password_lama"
                    aria-label="Tampilkan password lama"
                >
                    <svg class="icon-eye w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                    </svg>
                    <svg class="icon-eye-off w-5 h-5 hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.58 10.58A2 2 0 0 0 13.42 13.42"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.88 5.39A9.77 9.77 0 0 1 12 5.25c6 0 9.75 6.75 9.75 6.75a16.7 16.7 0 0 1-3.1 3.89"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.53 6.53C3.76 8.36 2.25 12 2.25 12s3.75 6.75 9.75 6.75c1.55 0 2.95-.45 4.17-1.1"/>
                    </svg>
                </button>
            </div>
        </div>

        <div>
            <label class="label">Password Baru</label>
            <div class="relative">
                <input 
                    type="password" 
                    name="password_baru" 
                    class="app-input pr-12 input-password" 
                    autocomplete="new-password"
                    minlength="6"
                    required
                >
                <button 
                    type="button" 
                    class="btn-toggle-password absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-orange-300"
                    data-target="password_baru"
                    aria-label="Tampilkan password baru"
                >
                    <svg class="icon-eye w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                    </svg>
                    <svg class="icon-eye-off w-5 h-5 hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.58 10.58A2 2 0 0 0 13.42 13.42"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.88 5.39A9.77 9.77 0 0 1 12 5.25c6 0 9.75 6.75 9.75 6.75a16.7 16.7 0 0 1-3.1 3.89"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.53 6.53C3.76 8.36 2.25 12 2.25 12s3.75 6.75 9.75 6.75c1.55 0 2.95-.45 4.17-1.1"/>
                    </svg>
                </button>
            </div>
        </div>

        <div>
            <label class="label">Konfirmasi Password</label>
            <div class="relative">
                <input 
                    type="password" 
                    name="password_konfirmasi" 
                    class="app-input pr-12 input-password" 
                    autocomplete="new-password"
                    minlength="6"
                    required
                >
                <button 
                    type="button" 
                    class="btn-toggle-password absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-orange-300"
                    data-target="password_konfirmasi"
                    aria-label="Tampilkan konfirmasi password"
                >
                    <svg class="icon-eye w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                    </svg>
                    <svg class="icon-eye-off w-5 h-5 hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.58 10.58A2 2 0 0 0 13.42 13.42"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.88 5.39A9.77 9.77 0 0 1 12 5.25c6 0 9.75 6.75 9.75 6.75a16.7 16.7 0 0 1-3.1 3.89"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.53 6.53C3.76 8.36 2.25 12 2.25 12s3.75 6.75 9.75 6.75c1.55 0 2.95-.45 4.17-1.1"/>
                    </svg>
                </button>
            </div>
        </div>

        <div class="md:col-span-3 flex justify-end">
            <button type="submit" class="btn btn-danger">
                Ganti Password
            </button>
        </div>
    </form>
</div>

        <div class="app-card p-5 border border-amber-700/40 bg-amber-950/10">
            <div class="profile-section-title text-amber-300">Catatan Keamanan</div>
            <p class="text-sm text-slate-400 mt-2">
                Jangan bagikan password kepada siapa pun. Admin toko hanya boleh mengatur data operasional, bukan meminta password user.
            </p>
        </div>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const alertTitle = <?= json_encode($alertTitle) ?>;
    const alertText = <?= json_encode($alertText) ?>;
    const alertIcon = <?= json_encode($alertIcon) ?>;

    const swalDarkOptions = {
        confirmButtonColor: '#f97316',
        background: '#020617',
        color: '#e5e7eb',
        heightAuto: false,
        scrollbarPadding: false,
        backdrop: true,
        customClass: {
            container: 'kasirrapi-swal-container',
            popup: 'kasirrapi-swal-popup'
        }
    };

    if (alertTitle && typeof Swal !== 'undefined') {
        Swal.fire({
            ...swalDarkOptions,
            title: alertTitle,
            text: alertText,
            icon: alertIcon,
            confirmButtonText: 'OK'
        });

        if (window.history.replaceState) {
            const url = new URL(window.location.href);
            url.searchParams.delete('status');
            window.history.replaceState({}, document.title, url.pathname + url.search);
        }
    }

    document.querySelectorAll('.btn-toggle-password').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const targetName = this.dataset.target;
            const input = document.querySelector('input[name="' + targetName + '"]');

            if (!input) return;

            const iconEye = this.querySelector('.icon-eye');
            const iconEyeOff = this.querySelector('.icon-eye-off');

            if (input.type === 'password') {
                input.type = 'text';
                if (iconEye) iconEye.classList.add('hidden');
                if (iconEyeOff) iconEyeOff.classList.remove('hidden');
                this.setAttribute('aria-label', 'Sembunyikan password');
            } else {
                input.type = 'password';
                if (iconEye) iconEye.classList.remove('hidden');
                if (iconEyeOff) iconEyeOff.classList.add('hidden');
                this.setAttribute('aria-label', 'Tampilkan password');
            }
        });
    });
});
</script>

<?php ui_footer(); ?>

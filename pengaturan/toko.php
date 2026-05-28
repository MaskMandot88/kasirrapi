<?php
// pengaturan/toko.php
session_start();

require_once '../config/database.php';
require_once '../includes/ui.php';
require_once '../includes/receipt.php';

if (!isset($_SESSION['tenant_id'], $_SESSION['user_id'], $_SESSION['role'])) {
    header('Location: ../auth/login.php');
    exit;
}

if (!ui_is_role(['Owner', 'Admin'])) {
    die('Akses ditolak. Hanya Owner/Admin yang dapat mengatur toko.');
}

$tenant_id = (int) $_SESSION['tenant_id'];
$subscription = kasirrapi_tenant_subscription($pdo, $tenant_id);
$custom_struk_allowed = kasirrapi_feature_allowed($subscription, 'custom_struk');
receipt_ensure_settings_schema($pdo);

function clean_text($value, $max = 255) {
    $value = trim((string) $value);
    if (mb_strlen($value) > $max) {
        $value = mb_substr($value, 0, $max);
    }
    return $value;
}

function parse_maps_coordinate($url) {
    $url = trim((string) $url);

    if ($url === '') {
        return [null, null];
    }

    // Format: https://www.google.com/maps/@-7.123456,112.123456,17z
    if (preg_match('/@(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)/', $url, $m)) {
        return [(float) $m[1], (float) $m[2]];
    }

    // Format: https://www.google.com/maps?q=-7.123456,112.123456
    if (preg_match('/[?&]q=(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)/', $url, $m)) {
        return [(float) $m[1], (float) $m[2]];
    }

    // Format umum yang kadang ada di link maps: !3d-7.123456!4d112.123456
    if (preg_match('/!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)/', $url, $m)) {
        return [(float) $m[1], (float) $m[2]];
    }

    return [null, null];
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $nama_toko = clean_text($_POST['nama_toko'] ?? '', 150);
    $alamat_toko = trim((string) ($_POST['alamat_toko'] ?? ''));
    $maps_url = clean_text($_POST['maps_url'] ?? '', 500);
    $catatan_struk = clean_text($_POST['catatan_struk'] ?? '', 180);

    $latitude_toko = trim((string) ($_POST['latitude_toko'] ?? ''));
    $longitude_toko = trim((string) ($_POST['longitude_toko'] ?? ''));
    $radius_absensi_meter = (int) ($_POST['radius_absensi_meter'] ?? 100);

    if ($radius_absensi_meter < 20) {
        $radius_absensi_meter = 20;
    }

    if ($radius_absensi_meter > 1000) {
        $radius_absensi_meter = 1000;
    }

    // Kalau latitude/longitude kosong, coba ambil dari link Google Maps.
    if (($latitude_toko === '' || $longitude_toko === '') && $maps_url !== '') {
        [$latParsed, $lngParsed] = parse_maps_coordinate($maps_url);

        if ($latParsed !== null && $lngParsed !== null) {
            $latitude_toko = (string) $latParsed;
            $longitude_toko = (string) $lngParsed;
        }
    }

    $latValue = $latitude_toko !== '' ? (float) $latitude_toko : null;
    $lngValue = $longitude_toko !== '' ? (float) $longitude_toko : null;

    if ($nama_toko === '') {
        $_SESSION['flash']['error'] = 'Nama toko wajib diisi.';
        header('Location: toko.php');
        exit;
    }

    if ($latValue !== null && ($latValue < -90 || $latValue > 90)) {
        $_SESSION['flash']['error'] = 'Latitude tidak valid.';
        header('Location: toko.php');
        exit;
    }

    if ($lngValue !== null && ($lngValue < -180 || $lngValue > 180)) {
        $_SESSION['flash']['error'] = 'Longitude tidak valid.';
        header('Location: toko.php');
        exit;
    }

    try {
        $stmtCurrent = $pdo->prepare("SELECT logo_struk FROM tenants WHERE id = ? LIMIT 1");
        $stmtCurrent->execute([$tenant_id]);
        $currentLogo = (string)($stmtCurrent->fetchColumn() ?: '');

        $stmt = $pdo->prepare("
            UPDATE tenants
            SET nama_toko = ?,
                alamat_toko = ?,
                maps_url = ?,
                latitude_toko = ?,
                longitude_toko = ?,
                radius_absensi_meter = ?
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([
            $nama_toko,
            $alamat_toko !== '' ? $alamat_toko : null,
            $maps_url !== '' ? $maps_url : null,
            $latValue,
            $lngValue,
            $radius_absensi_meter,
            $tenant_id
        ]);

        if ($custom_struk_allowed) {
            $logoStruk = $currentLogo !== '' ? $currentLogo : null;

            if (!empty($_POST['hapus_logo_struk'])) {
                if ($currentLogo !== '') {
                    $oldPath = receipt_logo_path($tenant_id, $currentLogo);
                    if ($oldPath !== '' && is_file($oldPath)) {
                        @unlink($oldPath);
                    }
                }
                $logoStruk = null;
            }

            if (!empty($_FILES['logo_struk']['name']) && ($_FILES['logo_struk']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                if ($_FILES['logo_struk']['error'] !== UPLOAD_ERR_OK) {
                    throw new RuntimeException('Upload logo struk gagal.');
                }
                if ((int)$_FILES['logo_struk']['size'] > 2 * 1024 * 1024) {
                    throw new RuntimeException('Ukuran logo struk maksimal 2MB.');
                }

                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($_FILES['logo_struk']['tmp_name']);
                $extMap = [
                    'image/png' => 'png',
                    'image/jpeg' => 'jpg',
                    'image/webp' => 'webp',
                ];
                if (!isset($extMap[$mime])) {
                    throw new RuntimeException('Logo struk harus berupa PNG, JPG, atau WEBP.');
                }

                $dir = receipt_upload_dir($tenant_id);
                if (!is_dir($dir)) {
                    mkdir($dir, 0775, true);
                }

                $newLogo = 'logo_struk_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $extMap[$mime];
                $target = $dir . '/' . $newLogo;

                if (!move_uploaded_file($_FILES['logo_struk']['tmp_name'], $target)) {
                    throw new RuntimeException('Logo struk gagal disimpan.');
                }

                if ($currentLogo !== '') {
                    $oldPath = receipt_logo_path($tenant_id, $currentLogo);
                    if ($oldPath !== '' && is_file($oldPath)) {
                        @unlink($oldPath);
                    }
                }

                $logoStruk = $newLogo;
            }

            $stmtReceipt = $pdo->prepare("UPDATE tenants SET logo_struk = ?, catatan_struk = ? WHERE id = ? LIMIT 1");
            $stmtReceipt->execute([
                $logoStruk,
                $catatan_struk !== '' ? $catatan_struk : null,
                $tenant_id,
            ]);
        }

        $_SESSION['flash']['success'] = 'Pengaturan toko berhasil disimpan.';
        header('Location: toko.php');
        exit;

    } catch (Throwable $e) {
        $_SESSION['flash']['error'] = 'Gagal menyimpan pengaturan toko: ' . $e->getMessage();
        header('Location: toko.php');
        exit;
    }
}

$stmt = $pdo->prepare("
    SELECT nama_toko,
           alamat_toko,
           logo_struk,
           catatan_struk,
           maps_url,
           latitude_toko,
           longitude_toko,
           radius_absensi_meter
    FROM tenants
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$tenant_id]);
$toko = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$toko) {
    die('Data toko tidak ditemukan.');
}

ui_head('Pengaturan Toko');
ui_nav($pdo, 'Pengaturan Toko');
?>

<div class="space-y-6">

    <div class="app-card p-4 md:p-6">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <div class="inline-flex items-center px-3 py-1 rounded-full bg-orange-950/60 border border-orange-700/50 text-orange-300 text-xs font-bold mb-4">
                    Pengaturan
                </div>

                <h2 class="text-2xl md:text-4xl font-black text-white leading-tight">
                    Pengaturan <span class="brand-orange">Toko</span>
                </h2>

                <p class="text-slate-400 mt-2">
                    Atur identitas toko, alamat, lokasi Google Maps, dan radius absensi wajah.
                </p>
            </div>

            <a href="../dashboard/index.php" class="btn btn-secondary">
                Kembali ke Dashboard
            </a>
            <a href="diskon.php" class="btn btn-primary">
                Aturan Diskon
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-[1fr_360px] gap-4 items-start">
        <form method="POST" enctype="multipart/form-data" class="app-card p-4 md:p-5 space-y-4" data-loading-text="Menyimpan pengaturan toko...">

            <div>
                <h3 class="text-xl font-bold text-orange-400">Data Toko</h3>
                <p class="text-sm text-slate-400">
                    Lokasi toko akan dipakai untuk membatasi absensi wajah agar hanya bisa dilakukan di area toko.
                </p>
            </div>

            <label class="block">
                <span class="label">Nama Toko</span>
                <input type="text"
                       name="nama_toko"
                       class="app-input mt-1"
                       required
                       value="<?= h($toko['nama_toko'] ?? '') ?>"
                       placeholder="Contoh: Toko Paham">
            </label>

            <label class="block">
                <span class="label">Alamat Toko</span>
                <textarea name="alamat_toko"
                          rows="3"
                          class="app-textarea mt-1"
                          placeholder="Alamat lengkap toko"><?= h($toko['alamat_toko'] ?? '') ?></textarea>
            </label>

            <label class="block">
                <span class="label">Link Google Maps, opsional</span>
                <input type="text"
                       name="maps_url"
                       id="maps_url"
                       class="app-input mt-1"
                       value="<?= h($toko['maps_url'] ?? '') ?>"
                       placeholder="https://www.google.com/maps/@-7.xxxxxx,112.xxxxxx,17z">
                <span class="block text-xs text-slate-500 mt-1">
                    Bisa paste link Google Maps. Link pendek maps.app.goo.gl kadang tidak bisa dibaca otomatis, gunakan tombol lokasi sebagai cara utama.
                </span>
            </label>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <label class="block">
                    <span class="label">Latitude Toko</span>
                    <input type="text"
                           name="latitude_toko"
                           id="latitude_toko"
                           class="app-input mt-1"
                           value="<?= h($toko['latitude_toko'] ?? '') ?>"
                           placeholder="-7.12345678">
                </label>

                <label class="block">
                    <span class="label">Longitude Toko</span>
                    <input type="text"
                           name="longitude_toko"
                           id="longitude_toko"
                           class="app-input mt-1"
                           value="<?= h($toko['longitude_toko'] ?? '') ?>"
                           placeholder="112.12345678">
                </label>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-[1fr_auto] gap-3">
                <label class="block">
                    <span class="label">Radius Absensi</span>
                    <input type="number"
                           name="radius_absensi_meter"
                           id="radius_absensi_meter"
                           class="app-input mt-1"
                           min="20"
                           max="1000"
                           value="<?= h($toko['radius_absensi_meter'] ?? 100) ?>">
                    <span class="block text-xs text-slate-500 mt-1">
                        Saran toko biasa: 100 meter. Area pasar/ruko besar: 150-300 meter.
                    </span>
                </label>

                <div class="flex items-end">
                    <button type="button"
                            onclick="ambilLokasiSekarang()"
                            class="btn btn-secondary w-full md:w-auto">
                        Ambil Lokasi Saya
                    </button>
                </div>
            </div>

            <div id="lokasi_info" class="hidden rounded-2xl border border-slate-700 bg-slate-950 p-3 text-sm text-slate-300"></div>

            <div class="rounded-2xl border border-slate-800 bg-slate-950 p-4 space-y-3">
                <div>
                    <h3 class="text-lg font-bold text-orange-400">Branding Struk</h3>
                    <p class="text-sm text-slate-500">
                        Logo dan catatan struk tersedia untuk paket Plus dan Pro. Paket Anda: <?= h($subscription['plan'] ?? 'Gratis') ?>.
                    </p>
                </div>

                <?php if ($custom_struk_allowed): ?>
                    <?php if (!empty($toko['logo_struk'])): ?>
                        <div class="flex items-center gap-3 rounded-xl bg-slate-900 border border-slate-800 p-3">
                            <img src="<?= h(receipt_logo_url($tenant_id, $toko['logo_struk'])) ?>" alt="Logo struk" class="w-16 h-16 object-contain bg-white rounded-lg p-1">
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" name="hapus_logo_struk" value="1">
                                Hapus logo struk
                            </label>
                        </div>
                    <?php endif; ?>

                    <label class="block">
                        <span class="label">Logo Struk, opsional</span>
                        <input type="file" name="logo_struk" accept="image/png,image/jpeg,image/webp" class="app-input mt-1">
                        <span class="block text-xs text-slate-500 mt-1">PNG, JPG, atau WEBP. Maksimal 2MB.</span>
                    </label>

                    <label class="block">
                        <span class="label">Catatan Footer Struk</span>
                        <input type="text" name="catatan_struk" maxlength="180" class="app-input mt-1" value="<?= h($toko['catatan_struk'] ?? '') ?>" placeholder="Contoh: Terima kasih, barang yang sudah dibeli tidak dapat dikembalikan.">
                    </label>
                <?php else: ?>
                    <div class="rounded-xl bg-slate-900 border border-slate-800 p-3 text-sm text-slate-400">
                        Upgrade ke Plus atau Pro untuk memakai logo toko dan catatan footer khusus di struk.
                    </div>
                <?php endif; ?>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2">
                <button type="button"
                        onclick="cekLokasiKeToko()"
                        class="btn btn-secondary">
                    Cek Jarak Saya ke Toko
                </button>

                <button type="submit" class="btn btn-primary">
                    Simpan Pengaturan
                </button>
            </div>
        </form>

        <div class="app-card p-4 md:p-5 space-y-4">
            <h3 class="text-xl font-bold text-white">Panduan Lokasi</h3>

            <div class="space-y-3 text-sm text-slate-400">
                <p>
                    Cara paling mudah: buka halaman ini saat berada di toko, lalu klik
                    <strong class="text-slate-200">Ambil Lokasi Saya</strong>.
                </p>

                <p>
                    Setelah latitude dan longitude terisi, klik
                    <strong class="text-slate-200">Simpan Pengaturan</strong>.
                </p>

                <p>
                    Nantinya absensi wajah hanya boleh dilakukan jika karyawan berada dalam radius toko.
                </p>
            </div>

            <div class="rounded-2xl bg-slate-950 border border-slate-800 p-4">
                <div class="label">Status Lokasi Toko</div>

                <?php if (!empty($toko['latitude_toko']) && !empty($toko['longitude_toko'])): ?>
                    <div class="mt-2 text-emerald-300 font-bold">Sudah diatur</div>
                    <div class="text-xs text-slate-500 mt-1">
                        <?= h($toko['latitude_toko']) ?>, <?= h($toko['longitude_toko']) ?>
                    </div>
                    <div class="text-xs text-slate-500 mt-1">
                        Radius: <?= number_format((int)($toko['radius_absensi_meter'] ?? 100), 0, ',', '.') ?> meter
                    </div>
                <?php else: ?>
                    <div class="mt-2 text-red-300 font-bold">Belum diatur</div>
                    <div class="text-xs text-slate-500 mt-1">
                        Absensi wajah berbasis lokasi belum bisa divalidasi.
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($toko['latitude_toko']) && !empty($toko['longitude_toko'])): ?>
                <a href="https://www.google.com/maps?q=<?= h($toko['latitude_toko']) ?>,<?= h($toko['longitude_toko']) ?>"
                   target="_blank"
                   class="btn btn-secondary"
                   data-no-loading="1">
                    Buka Titik di Google Maps
                </a>
            <?php endif; ?>
        </div>
    </div>

</div>

<script>
function showLokasiInfo(message, type = 'info') {
    const el = document.getElementById('lokasi_info');
    el.classList.remove('hidden');
    el.textContent = message;

    el.className = 'rounded-2xl border p-3 text-sm';

    if (type === 'success') {
        el.classList.add('border-emerald-700', 'bg-emerald-950/40', 'text-emerald-200');
    } else if (type === 'error') {
        el.classList.add('border-red-700', 'bg-red-950/40', 'text-red-200');
    } else if (type === 'warning') {
        el.classList.add('border-amber-700', 'bg-amber-950/40', 'text-amber-200');
    } else {
        el.classList.add('border-slate-700', 'bg-slate-950', 'text-slate-300');
    }
}

function ambilLokasiSekarang() {
    if (!navigator.geolocation) {
        showLokasiInfo('Browser tidak mendukung akses lokasi.', 'error');
        return;
    }

    showLokasiInfo('Mengambil lokasi paling akurat. Tetap di tempat selama beberapa detik...', 'info');

    let bestPosition = null;
    let watchId = null;
    let selesai = false;

    function gunakanLokasiTerbaik() {
        if (selesai) return;
        selesai = true;

        if (watchId !== null) {
            navigator.geolocation.clearWatch(watchId);
        }

        if (!bestPosition) {
            showLokasiInfo('Gagal mendapatkan lokasi. Pastikan GPS aktif dan izin lokasi diberikan.', 'error');
            return;
        }

        const lat = bestPosition.coords.latitude;
        const lng = bestPosition.coords.longitude;
        const acc = Math.round(bestPosition.coords.accuracy || 0);

        document.getElementById('latitude_toko').value = lat.toFixed(8);
        document.getElementById('longitude_toko').value = lng.toFixed(8);

        if (acc <= 30) {
            showLokasiInfo(
                'Lokasi berhasil diambil dengan akurasi sangat baik, sekitar ' + acc + ' meter. Jangan lupa klik Simpan Pengaturan.',
                'success'
            );
        } else if (acc <= 100) {
            showLokasiInfo(
                'Lokasi berhasil diambil. Akurasi sekitar ' + acc + ' meter. Masih cukup aman untuk radius absensi 100-200 meter. Jangan lupa klik Simpan Pengaturan.',
                'warning'
            );
        } else {
            showLokasiInfo(
                'Lokasi berhasil diambil, tetapi akurasinya rendah, sekitar ' + acc + ' meter. Sebaiknya ulangi dari HP dengan GPS aktif, atau isi titik dari Google Maps secara manual.',
                'warning'
            );
        }
    }

    watchId = navigator.geolocation.watchPosition(
        function(pos) {
            const acc = pos.coords.accuracy || 999999;

            if (!bestPosition || acc < (bestPosition.coords.accuracy || 999999)) {
                bestPosition = pos;

                showLokasiInfo(
                    'Mencari lokasi terbaik... akurasi sementara sekitar ' + Math.round(acc) + ' meter.',
                    acc <= 100 ? 'success' : 'warning'
                );
            }

            // Kalau sudah sangat akurat, langsung pakai.
            if (acc <= 25) {
                gunakanLokasiTerbaik();
            }
        },
        function(err) {
            if (selesai) return;

            let msg = 'Gagal mengambil lokasi.';

            if (err.code === 1) {
                msg = 'Izin lokasi ditolak. Aktifkan izin lokasi browser lalu coba lagi.';
            } else if (err.code === 2) {
                msg = 'Lokasi tidak tersedia. Pastikan GPS/Location aktif.';
            } else if (err.code === 3) {
                msg = 'Pengambilan lokasi terlalu lama. Coba lagi.';
            }

            showLokasiInfo(msg, 'error');
        },
        {
            enableHighAccuracy: true,
            timeout: 20000,
            maximumAge: 0
        }
    );

    // Tunggu maksimal 12 detik, lalu pakai posisi terbaik yang didapat.
    setTimeout(gunakanLokasiTerbaik, 12000);
}

function hitungJarakMeter(lat1, lon1, lat2, lon2) {
    const R = 6371000;
    const toRad = function(v) { return v * Math.PI / 180; };

    const dLat = toRad(lat2 - lat1);
    const dLon = toRad(lon2 - lon1);

    const a =
        Math.sin(dLat / 2) * Math.sin(dLat / 2) +
        Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) *
        Math.sin(dLon / 2) * Math.sin(dLon / 2);

    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));

    return Math.round(R * c);
}

function cekLokasiKeToko() {
    const latToko = parseFloat(document.getElementById('latitude_toko').value || '0');
    const lngToko = parseFloat(document.getElementById('longitude_toko').value || '0');
    const radius = parseInt(document.getElementById('radius_absensi_meter').value || '100');

    if (!latToko || !lngToko) {
        showLokasiInfo('Latitude dan longitude toko belum diisi.', 'warning');
        return;
    }

    if (!navigator.geolocation) {
        showLokasiInfo('Browser tidak mendukung akses lokasi.', 'error');
        return;
    }

    showLokasiInfo('Menghitung jarak Anda ke toko...', 'info');

    navigator.geolocation.getCurrentPosition(
        function(pos) {
            const latUser = pos.coords.latitude;
            const lngUser = pos.coords.longitude;
            const acc = Math.round(pos.coords.accuracy || 0);
            const jarak = hitungJarakMeter(latUser, lngUser, latToko, lngToko);

            if (jarak <= radius) {
                showLokasiInfo(
                    'Lokasi valid. Jarak Anda sekitar ' + jarak + ' meter dari toko. Akurasi GPS sekitar ' + acc + ' meter.',
                    'success'
                );
            } else {
                showLokasiInfo(
                    'Di luar radius toko. Jarak Anda sekitar ' + jarak + ' meter, sedangkan radius absensi ' + radius + ' meter. Akurasi GPS sekitar ' + acc + ' meter.',
                    'warning'
                );
            }
        },
        function() {
            showLokasiInfo('Gagal mengambil lokasi Anda untuk cek jarak.', 'error');
        },
        {
            enableHighAccuracy: true,
            timeout: 15000,
            maximumAge: 0
        }
    );
}
</script>

<?php ui_footer(); ?>

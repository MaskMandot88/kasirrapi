<?php
// absensi/absen_wajah.php
require_once '_auth.php';

$stmt = $pdo->prepare("
    SELECT kw.threshold_similarity, kwe.embedding_json
    FROM karyawan_wajah kw
    JOIN karyawan_wajah_embedding kwe
        ON kwe.karyawan_wajah_id = kw.id
       AND kwe.aktif = 1
    WHERE kw.tenant_id = ?
      AND kw.user_id = ?
      AND kw.status = 'Aktif'
    ORDER BY kwe.id ASC
");
$stmt->execute([$tenant_id, $user_id_login]);
$rows = $stmt->fetchAll();

$threshold = $rows ? (float) $rows[0]['threshold_similarity'] : 0.52;
$descriptors = [];

foreach ($rows as $r) {
    $d = json_decode($r['embedding_json'], true);
    if (is_array($d)) {
        $descriptors[] = array_map('floatval', $d);
    }
}

$stmtUser = $pdo->prepare("
    SELECT id, nama, email, role
    FROM users
    WHERE id = ?
      AND tenant_id = ?
    LIMIT 1
");
$stmtUser->execute([$user_id_login, $tenant_id]);
$currentUser = $stmtUser->fetch();

$stmtTenant = $pdo->prepare("
    SELECT nama_toko,
           latitude_toko,
           longitude_toko,
           radius_absensi_meter
    FROM tenants
    WHERE id = ?
    LIMIT 1
");
$stmtTenant->execute([$tenant_id]);
$currentTenant = $stmtTenant->fetch();

$displayNama = $currentUser['nama'] ?? $nama_login;
$displayToko = $currentTenant['nama_toko'] ?? 'Toko';

$latitude_toko = $currentTenant['latitude_toko'] ?? null;
$longitude_toko = $currentTenant['longitude_toko'] ?? null;
$radius_absensi_meter = (int)($currentTenant['radius_absensi_meter'] ?? 100);

if ($radius_absensi_meter <= 0) {
    $radius_absensi_meter = 100;
}

$lokasi_toko_siap = !empty($latitude_toko) && !empty($longitude_toko);

$today = date('Y-m-d');

$stmtOpen = $pdo->prepare("
    SELECT *
    FROM absensi
    WHERE tenant_id = ?
      AND user_id = ?
      AND jam_masuk IS NOT NULL
      AND jam_pulang IS NULL
      AND jam_masuk >= DATE_SUB(NOW(), INTERVAL 36 HOUR)
    ORDER BY jam_masuk DESC
    LIMIT 1
");
$stmtOpen->execute([$tenant_id, $user_id_login]);
$openAbs = $stmtOpen->fetch();

$stmtToday = $pdo->prepare("
    SELECT *
    FROM absensi
    WHERE tenant_id = ?
      AND user_id = ?
      AND tanggal = ?
    LIMIT 1
");
$stmtToday->execute([$tenant_id, $user_id_login, $today]);
$todayAbs = $stmtToday->fetch();

$allowedAction = 'Masuk';
$actionLabel = 'Absen Masuk';
$actionInfo = 'Anda belum absen masuk. Setelah lokasi valid dan wajah cocok, tombol Masuk akan muncul.';
$alreadyDone = false;

if ($openAbs) {
    $allowedAction = 'Pulang';
    $actionLabel = 'Absen Pulang';
    $actionInfo = 'Anda sudah absen masuk pada ' . date('d/m/Y H:i', strtotime($openAbs['jam_masuk'])) . '. Setelah lokasi valid dan wajah cocok, tombol Pulang akan muncul.';
} elseif ($todayAbs && !empty($todayAbs['jam_masuk']) && !empty($todayAbs['jam_pulang'])) {
    $alreadyDone = true;
    $allowedAction = 'Selesai';
    $actionLabel = 'Absensi Selesai';
    $actionInfo = 'Absensi hari ini sudah lengkap. Masuk: ' . date('H:i', strtotime($todayAbs['jam_masuk'])) . ', Pulang: ' . date('H:i', strtotime($todayAbs['jam_pulang'])) . '.';
} elseif ($todayAbs && !empty($todayAbs['jam_masuk']) && empty($todayAbs['jam_pulang'])) {
    $allowedAction = 'Pulang';
    $actionLabel = 'Absen Pulang';
    $actionInfo = 'Anda sudah absen masuk pada ' . date('H:i', strtotime($todayAbs['jam_masuk'])) . '. Setelah lokasi valid dan wajah cocok, tombol Pulang akan muncul.';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Absen Wajah</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="../assets/face-api/face-api.min.js"></script>
    <style>
        .modal-box {
            max-height: calc(100vh - 24px);
            overflow-y: auto;
        }

        .scan-frame {
            position: absolute;
            inset: 11%;
            border: 3px solid rgba(249, 115, 22, .95);
            border-radius: 24px;
            box-shadow: 0 0 0 9999px rgba(0,0,0,.24);
            pointer-events: none;
        }

        .scan-frame::before {
            content: "";
            position: absolute;
            left: 12%;
            right: 12%;
            height: 2px;
            background: rgba(249, 115, 22, .95);
            box-shadow: 0 0 18px rgba(249, 115, 22, .95);
            animation: scanLine 1.7s infinite ease-in-out;
        }

        .scan-frame::before {
            top: 50%;
        }

        @keyframes scanLine {
            0%, 100% {
                transform: translateY(-35px);
                opacity: .35;
            }

            50% {
                transform: translateY(35px);
                opacity: 1;
            }
        }

        .pulse-dot {
            width: 10px;
            height: 10px;
            border-radius: 999px;
            background: #f97316;
            box-shadow: 0 0 0 rgba(249,115,22,.7);
            animation: pulseDot 1.3s infinite;
        }

        @keyframes pulseDot {
            0% {
                box-shadow: 0 0 0 0 rgba(249,115,22,.7);
            }

            70% {
                box-shadow: 0 0 0 12px rgba(249,115,22,0);
            }

            100% {
                box-shadow: 0 0 0 0 rgba(249,115,22,0);
            }
        }

        body.camera-open {
            overflow: hidden !important;
            height: 100dvh !important;
            touch-action: none;
        }

        @media (max-width: 640px) {
            .camera-wrap {
                aspect-ratio: 3 / 4;
            }
        }
    </style>
</head>
<body class="bg-slate-950 text-slate-200 min-h-screen">

<div class="max-w-5xl mx-auto p-4 md:p-6">
    <div class="flex justify-between items-center mb-5">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold text-orange-400">Absen Wajah</h1>
            <p class="text-slate-400"><?= h($displayNama) ?> - <?= h($displayToko) ?></p>
        </div>

        <a href="index.php" class="bg-slate-800 hover:bg-slate-700 px-4 py-2 rounded-lg">
            Kembali
        </a>
    </div>

    <?php if (count($descriptors) < 3): ?>
        <div class="p-5 bg-red-900/50 border border-red-500 rounded-2xl mb-6">
            Data wajah Anda belum cukup. Silakan daftar wajah minimal 5 referensi terlebih dahulu.
            <a class="underline text-red-200" href="daftar_wajah.php">Daftar wajah</a>.
        </div>
    <?php endif; ?>

    <?php if (!$lokasi_toko_siap): ?>
        <div class="p-5 bg-red-900/50 border border-red-500 rounded-2xl mb-6">
            Lokasi toko belum diatur. Owner/Admin harus mengatur lokasi toko terlebih dahulu di menu Pengaturan Toko.
        </div>
    <?php endif; ?>

    <?php if ($msg = flash('error')): ?>
        <div class="mb-4 p-3 bg-red-900/50 border border-red-500 rounded-xl"><?= h($msg) ?></div>
    <?php endif; ?>

    <?php if ($msg = flash('success')): ?>
        <div class="mb-4 p-3 bg-emerald-900/50 border border-emerald-500 rounded-xl"><?= h($msg) ?></div>
    <?php endif; ?>

    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 md:p-6">
        <h2 class="text-xl font-bold mb-2"><?= h($actionLabel) ?></h2>
        <p class="text-slate-400 mb-5"><?= h($actionInfo) ?></p>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="p-4 bg-slate-800 rounded-xl">
                <div class="text-slate-400 text-sm">Nama</div>
                <div class="text-lg font-bold truncate"><?= h($displayNama) ?></div>
            </div>

            <div class="p-4 bg-slate-800 rounded-xl">
                <div class="text-slate-400 text-sm">Toko</div>
                <div class="text-lg font-bold truncate"><?= h($displayToko) ?></div>
            </div>

            <div class="p-4 bg-slate-800 rounded-xl">
                <div class="text-slate-400 text-sm">Radius Absensi</div>
                <div class="text-lg font-bold truncate"><?= number_format($radius_absensi_meter, 0, ',', '.') ?> meter</div>
            </div>

            <div class="p-4 bg-slate-800 rounded-xl">
                <div class="text-slate-400 text-sm">Status Lokasi Toko</div>
                <?php if ($lokasi_toko_siap): ?>
                    <div class="text-lg font-bold text-emerald-300">Sudah diatur</div>
                <?php else: ?>
                    <div class="text-lg font-bold text-red-300">Belum diatur</div>
                <?php endif; ?>
            </div>
        </div>

        <div id="locationPanel" class="mt-5 rounded-2xl border border-slate-700 bg-slate-950 p-4 text-sm text-slate-300">
            Klik tombol mulai untuk mengecek lokasi. Kamera baru dibuka jika Anda berada dalam radius toko.
        </div>

        <?php if ($alreadyDone): ?>
            <div class="mt-6 p-4 rounded-xl bg-emerald-950/50 border border-emerald-600 text-emerald-100">
                Absensi hari ini sudah lengkap. Tidak perlu absen lagi.
            </div>
        <?php else: ?>
            <button type="button"
                    id="btnOpenModal"
                    class="mt-6 px-5 py-3 bg-orange-600 hover:bg-orange-500 rounded-xl font-bold disabled:opacity-40 disabled:cursor-not-allowed"
                    <?= count($descriptors) < 3 || !$lokasi_toko_siap ? 'disabled' : '' ?>>
                Cek Lokasi & Buka Kamera <?= h($actionLabel) ?>
            </button>
        <?php endif; ?>
    </div>
</div>

<div id="cameraModal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/80"></div>

    <div class="relative min-h-screen flex items-center justify-center p-3">
        <div class="modal-box w-full max-w-2xl bg-slate-900 border border-slate-700 rounded-3xl shadow-2xl overflow-hidden">
            <div class="flex items-center justify-between px-4 py-3 border-b border-slate-800 sticky top-0 bg-slate-900 z-10">
                <div>
                    <h2 class="text-lg md:text-xl font-bold text-orange-400">Pindai Wajah</h2>
                    <p class="text-xs md:text-sm text-slate-400">Arahkan wajah ke bingkai kamera.</p>
                </div>

                <button type="button" id="btnCloseModal" class="px-3 py-2 rounded-lg bg-slate-800 hover:bg-slate-700">
                    Tutup
                </button>
            </div>

            <div class="p-4">
                <div class="camera-wrap relative bg-black rounded-2xl overflow-hidden aspect-video">
                    <video id="video" autoplay muted playsinline class="w-full h-full object-cover"></video>
                    <canvas id="canvas" class="hidden"></canvas>
                    <div class="scan-frame"></div>

                    <div class="absolute left-3 top-3 flex items-center gap-2 bg-black/60 px-3 py-2 rounded-full max-w-[90%]">
                        <span class="pulse-dot shrink-0"></span>
                        <span id="scanStatus" class="text-xs md:text-sm font-semibold text-orange-200 truncate">Memuat model...</span>
                    </div>
                </div>

                <div id="resultPanel" class="mt-4 hidden rounded-2xl border border-emerald-500/60 bg-emerald-950/40 p-4">
                    <div class="text-center mb-4">
                        <div class="text-sm text-emerald-200">Wajah cocok</div>
                        <div class="text-2xl md:text-3xl font-extrabold text-white leading-tight mt-1"><?= h($displayNama) ?></div>
                        <div class="text-base md:text-lg text-slate-300 mt-1"><?= h($displayToko) ?></div>

                        <div class="mt-3 text-xs text-slate-300">
                            Jarak lokasi:
                            <span id="resultDistanceText" class="font-bold text-orange-300">-</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <?php if ($allowedAction === 'Masuk'): ?>
                            <button type="button"
                                    id="btnAction"
                                    class="py-4 rounded-xl bg-emerald-600 hover:bg-emerald-500 font-bold">
                                Masuk
                            </button>
                        <?php else: ?>
                            <button type="button"
                                    id="btnAction"
                                    class="py-4 rounded-xl bg-cyan-600 hover:bg-cyan-500 font-bold">
                                Pulang
                            </button>
                        <?php endif; ?>

                        <button type="button"
                                id="btnUlang"
                                class="py-4 rounded-xl bg-slate-700 hover:bg-slate-600 font-bold">
                            Ulangi
                        </button>
                    </div>
                </div>

                <div id="hintPanel" class="mt-4 rounded-2xl border border-slate-700 bg-slate-800 p-3 text-sm text-slate-300">
                    Sistem hanya akan menampilkan tombol <b><?= h($allowedAction) ?></b> sesuai status absensi Anda.
                </div>
            </div>
        </div>
    </div>
</div>

<form id="formAbsen" method="POST" action="proses_absen_wajah.php">
    <input type="hidden" name="jenis_absen" id="jenis_absen">
    <input type="hidden" name="distance" id="distance">
    <input type="hidden" name="threshold" value="<?= h($threshold) ?>">
    <input type="hidden" name="descriptor" id="descriptor">
    <input type="hidden" name="foto_capture" id="foto_capture">

    <input type="hidden" name="latitude_absen" id="latitude_absen">
    <input type="hidden" name="longitude_absen" id="longitude_absen">
    <input type="hidden" name="jarak_meter" id="jarak_meter">
    <input type="hidden" name="akurasi_meter" id="akurasi_meter">
</form>

<script>
const MODEL_URL = '../assets/face-api/models';
const enrolled = <?= json_encode($descriptors) ?>;
const threshold = <?= json_encode($threshold) ?>;
const enoughRefs = enrolled.length >= 3;
const allowedAction = <?= json_encode($allowedAction) ?>;
const alreadyDone = <?= $alreadyDone ? 'true' : 'false' ?>;

const tokoLat = <?= $lokasi_toko_siap ? json_encode((float)$latitude_toko) : 'null' ?>;
const tokoLng = <?= $lokasi_toko_siap ? json_encode((float)$longitude_toko) : 'null' ?>;
const radiusAbsensi = <?= json_encode((int)$radius_absensi_meter) ?>;

const cameraModal = document.getElementById('cameraModal');
const btnOpenModal = document.getElementById('btnOpenModal');
const btnCloseModal = document.getElementById('btnCloseModal');
const video = document.getElementById('video');
const canvas = document.getElementById('canvas');
const scanStatus = document.getElementById('scanStatus');
const resultPanel = document.getElementById('resultPanel');
const hintPanel = document.getElementById('hintPanel');
const btnAction = document.getElementById('btnAction');
const btnUlang = document.getElementById('btnUlang');
const locationPanel = document.getElementById('locationPanel');

let stream = null;
let modelsLoaded = false;
let cameraStarted = false;
let scanTimer = null;
let scanLocked = false;
let lastMatched = null;

let lokasiValid = false;
let lokasiTerakhir = null;

function setLocationPanel(text, type = 'info') {
    locationPanel.textContent = text;
    locationPanel.className = 'mt-5 rounded-2xl border p-4 text-sm';

    if (type === 'success') {
        locationPanel.classList.add('border-emerald-700', 'bg-emerald-950/40', 'text-emerald-200');
    } else if (type === 'error') {
        locationPanel.classList.add('border-red-700', 'bg-red-950/40', 'text-red-200');
    } else if (type === 'warning') {
        locationPanel.classList.add('border-amber-700', 'bg-amber-950/40', 'text-amber-200');
    } else {
        locationPanel.classList.add('border-slate-700', 'bg-slate-950', 'text-slate-300');
    }
}

function setScanStatus(text) {
    scanStatus.textContent = text;
}

function euclidean(a, b) {
    let sum = 0;

    for (let i = 0; i < a.length; i++) {
        const d = a[i] - b[i];
        sum += d * d;
    }

    return Math.sqrt(sum);
}

function hitungJarakMeter(lat1, lon1, lat2, lon2) {
    const R = 6371000;
    const toRad = function(v) {
        return v * Math.PI / 180;
    };

    const dLat = toRad(lat2 - lat1);
    const dLon = toRad(lon2 - lon1);

    const a =
        Math.sin(dLat / 2) * Math.sin(dLat / 2) +
        Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) *
        Math.sin(dLon / 2) * Math.sin(dLon / 2);

    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));

    return Math.round(R * c);
}

function snapshot() {
    canvas.width = video.videoWidth || 640;
    canvas.height = video.videoHeight || 480;
    canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
    return canvas.toDataURL('image/jpeg', 0.86);
}

async function loadModels() {
    if (modelsLoaded) return;

    if (typeof faceapi === 'undefined') {
        throw new Error('face-api belum termuat. Pastikan ../assets/face-api/face-api.min.js tersedia.');
    }

    setScanStatus('Memuat model...');

    await faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL);
    await faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL);
    await faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL);

    modelsLoaded = true;
    setScanStatus('Model siap');
}

async function startCamera() {
    if (cameraStarted) return;

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        throw new Error('Browser tidak mendukung kamera. Gunakan Chrome/Edge terbaru melalui HTTPS.');
    }

    stream = await navigator.mediaDevices.getUserMedia({
        video: {
            facingMode: 'user',
            width: { ideal: 800 },
            height: { ideal: 600 }
        },
        audio: false
    });

    video.srcObject = stream;
    await new Promise(resolve => {
        video.onloadedmetadata = resolve;
    });

    cameraStarted = true;
    setScanStatus('Mencari wajah...');
}

function stopCamera() {
    if (scanTimer) {
        clearInterval(scanTimer);
        scanTimer = null;
    }

    if (stream) {
        stream.getTracks().forEach(t => t.stop());
        stream = null;
    }

    video.srcObject = null;
    cameraStarted = false;
    scanLocked = false;

    document.body.classList.remove('camera-open');
}

function cekLokasiSebelumKamera() {
    if (alreadyDone) return;

    if (!enoughRefs) {
        setLocationPanel('Data wajah belum cukup. Silakan daftar wajah terlebih dahulu.', 'error');
        return;
    }

    if (!tokoLat || !tokoLng) {
        setLocationPanel('Lokasi toko belum diatur. Owner/Admin harus mengatur lokasi toko terlebih dahulu.', 'error');
        return;
    }

    if (!navigator.geolocation) {
        setLocationPanel('Browser tidak mendukung akses lokasi.', 'error');
        return;
    }

    lokasiValid = false;
    lokasiTerakhir = null;

    setLocationPanel('Mengambil lokasi Anda. Mohon izinkan akses lokasi browser...', 'info');

    navigator.geolocation.getCurrentPosition(
        function(pos) {
            const lat = pos.coords.latitude;
            const lng = pos.coords.longitude;
            const acc = Math.round(pos.coords.accuracy || 0);
            const jarak = hitungJarakMeter(lat, lng, tokoLat, tokoLng);

            lokasiTerakhir = {
                lat: lat,
                lng: lng,
                acc: acc,
                jarak: jarak
            };

            document.getElementById('latitude_absen').value = lat.toFixed(8);
            document.getElementById('longitude_absen').value = lng.toFixed(8);
            document.getElementById('jarak_meter').value = jarak;
            document.getElementById('akurasi_meter').value = acc;
            document.getElementById('resultDistanceText').textContent = jarak + ' meter';

            if (jarak > radiusAbsensi) {
                lokasiValid = false;

                setLocationPanel(
                    'Absensi ditolak. Anda berada sekitar ' + jarak + ' meter dari toko, sedangkan radius absensi ' + radiusAbsensi + ' meter. Akurasi GPS sekitar ' + acc + ' meter.',
                    'error'
                );

                return;
            }

            lokasiValid = true;

            let pesan = 'Lokasi valid. Jarak Anda sekitar ' + jarak + ' meter dari toko. Akurasi GPS sekitar ' + acc + ' meter. Membuka kamera wajah...';

            if (acc > radiusAbsensi) {
                pesan = 'Lokasi masih dalam radius, tetapi akurasi GPS rendah sekitar ' + acc + ' meter. Jika sering meleset, ulangi dengan GPS aktif. Membuka kamera wajah...';
            }

            setLocationPanel(pesan, acc > radiusAbsensi ? 'warning' : 'success');

            openModal();
        },
        function(err) {
            let msg = 'Gagal mengambil lokasi.';

            if (err.code === 1) {
                msg = 'Izin lokasi ditolak. Aktifkan izin lokasi browser lalu coba lagi.';
            } else if (err.code === 2) {
                msg = 'Lokasi tidak tersedia. Pastikan GPS/Location aktif.';
            } else if (err.code === 3) {
                msg = 'Pengambilan lokasi terlalu lama. Coba lagi.';
            }

            setLocationPanel(msg, 'error');
        },
        {
            enableHighAccuracy: true,
            timeout: 18000,
            maximumAge: 0
        }
    );
}

function openModal() {
    if (alreadyDone) return;

    if (!lokasiValid) {
        setLocationPanel('Lokasi belum valid. Kamera tidak dibuka.', 'error');
        return;
    }

    cameraModal.classList.remove('hidden');
    document.body.classList.add('camera-open');

    resultPanel.classList.add('hidden');
    hintPanel.classList.remove('hidden');

    lastMatched = null;
    scanLocked = false;

    bootAndScan().catch(err => {
        setScanStatus('Gagal: ' + err.message);
    });
}

function closeModal() {
    stopCamera();
    cameraModal.classList.add('hidden');
}

async function bootAndScan() {
    if (!enoughRefs) {
        setScanStatus('Data wajah belum cukup.');
        return;
    }

    await loadModels();
    await startCamera();

    setScanStatus('Mencari wajah...');
    startAutoScan();
}

function startAutoScan() {
    if (scanTimer) clearInterval(scanTimer);

    scanTimer = setInterval(async () => {
        if (scanLocked || !modelsLoaded || !cameraStarted) return;

        scanLocked = true;

        try {
            await detectAndMatchOnce();
        } catch (e) {
            setScanStatus('Gagal cek: ' + e.message);
        } finally {
            if (!lastMatched) scanLocked = false;
        }
    }, 900);
}

async function detectAndMatchOnce() {
    const options = new faceapi.TinyFaceDetectorOptions({
        inputSize: 320,
        scoreThreshold: 0.45
    });

    const det = await faceapi
        .detectAllFaces(video, options)
        .withFaceLandmarks()
        .withFaceDescriptors();

    if (det.length === 0) {
        setScanStatus('Mencari wajah...');
        return;
    }

    if (det.length > 1) {
        setScanStatus('Terdeteksi lebih dari satu wajah');
        return;
    }

    setScanStatus('Wajah ditemukan...');

    const live = Array.from(det[0].descriptor);
    let min = 999;

    for (const ref of enrolled) {
        const d = euclidean(live, ref);
        if (d < min) min = d;
    }

    if (min <= threshold) {
        lastMatched = {
            distance: min,
            descriptor: live,
            foto: snapshot()
        };

        document.getElementById('distance').value = min.toFixed(6);
        document.getElementById('descriptor').value = JSON.stringify(live);
        document.getElementById('foto_capture').value = lastMatched.foto;

        setScanStatus('Wajah cocok');
        hintPanel.classList.add('hidden');
        resultPanel.classList.remove('hidden');

        if (scanTimer) {
            clearInterval(scanTimer);
            scanTimer = null;
        }
    } else {
        setScanStatus('Wajah belum cocok');
    }
}

function repeatScan() {
    resultPanel.classList.add('hidden');
    hintPanel.classList.remove('hidden');

    lastMatched = null;
    scanLocked = false;

    document.getElementById('distance').value = '';
    document.getElementById('descriptor').value = '';
    document.getElementById('foto_capture').value = '';

    setScanStatus('Mengulang deteksi...');
    startAutoScan();
}

function submitAllowedAction() {
    if (!lokasiValid || !lokasiTerakhir) {
        alert('Lokasi belum valid. Silakan ulangi cek lokasi.');
        closeModal();
        return;
    }

    if (!lastMatched) {
        alert('Wajah belum terdeteksi/cocok. Silakan ulangi.');
        return;
    }

    if (allowedAction !== 'Masuk' && allowedAction !== 'Pulang') {
        alert('Absensi hari ini sudah selesai.');
        return;
    }

    document.getElementById('jenis_absen').value = allowedAction;
    document.getElementById('formAbsen').submit();
}

btnOpenModal?.addEventListener('click', cekLokasiSebelumKamera);
btnCloseModal?.addEventListener('click', closeModal);
btnAction?.addEventListener('click', submitAllowedAction);
btnUlang?.addEventListener('click', repeatScan);

window.addEventListener('load', () => {
    if (!enoughRefs || alreadyDone) return;

    // Mobile boleh otomatis mulai cek lokasi.
    // Kamera tetap tidak akan terbuka sebelum lokasi valid.
    const isMobile = window.matchMedia && window.matchMedia('(max-width: 768px)').matches;

    if (isMobile && tokoLat && tokoLng) {
        setTimeout(cekLokasiSebelumKamera, 700);
    }
});

window.addEventListener('beforeunload', stopCamera);
</script>
</body>
</html>

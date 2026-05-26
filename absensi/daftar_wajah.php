<?php
// absensi/daftar_wajah.php
require_once '_auth.php';

$can_manage = is_hr_role();
$target_user_id = $can_manage && isset($_GET['user_id']) ? (int) $_GET['user_id'] : $user_id_login;

if ($can_manage) {
    $stmt = $pdo->prepare("SELECT id, nama, role FROM users WHERE tenant_id = ? ORDER BY nama ASC");
    $stmt->execute([$tenant_id]);
    $users = $stmt->fetchAll();
} else {
    $users = [];
}

$stmt = $pdo->prepare("SELECT id, nama, role FROM users WHERE id = ? AND tenant_id = ? LIMIT 1");
$stmt->execute([$target_user_id, $tenant_id]);
$target_user = $stmt->fetch();

if (!$target_user) {
    die('Karyawan tidak ditemukan.');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Wajah</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="../assets/face-api/face-api.min.js"></script>

    <style>
        body.camera-open {
            overflow: hidden !important;
            height: 100dvh !important;
            touch-action: none;
        }

        #video,
        #modalVideo {
            background: #000;
            transform: scaleX(-1);
        }

        #modalVideo {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .scan-frame {
            position: absolute;
            inset: 12%;
            border: 4px solid rgba(249, 115, 22, .95);
            border-radius: 32px;
            box-shadow: 0 0 0 9999px rgba(0,0,0,.42);
            pointer-events: none;
        }

        .scan-frame::before {
            content: "";
            position: absolute;
            left: 14%;
            right: 14%;
            height: 3px;
            top: 50%;
            background: rgba(249, 115, 22, .95);
            box-shadow: 0 0 18px rgba(249, 115, 22, .95);
            animation: scanLine 1.7s infinite ease-in-out;
        }

        @keyframes scanLine {
            0%, 100% {
                transform: translateY(-50px);
                opacity: .35;
            }

            50% {
                transform: translateY(50px);
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

        .process-overlay {
            position: fixed;
            inset: 0;
            z-index: 10000;
            background: rgba(2, 6, 23, .86);
            backdrop-filter: blur(10px);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .process-overlay.show {
            display: flex;
        }

        .process-card {
            width: 100%;
            max-width: 320px;
            border: 1px solid rgba(249, 115, 22, .35);
            background: rgba(15, 23, 42, .96);
            border-radius: 24px;
            padding: 24px;
            text-align: center;
            box-shadow: 0 25px 80px rgba(0,0,0,.45);
        }

        .process-spinner {
            width: 56px;
            height: 56px;
            margin: 0 auto 16px;
            border-radius: 999px;
            border: 4px solid rgba(249, 115, 22, .18);
            border-top-color: #f97316;
            animation: spinProcess .85s linear infinite;
        }

        .process-dots {
            display: inline-flex;
            gap: 5px;
            margin-top: 12px;
        }

        .process-dots span {
            width: 7px;
            height: 7px;
            border-radius: 999px;
            background: #f97316;
            opacity: .35;
            animation: dotPulse 1s infinite ease-in-out;
        }

        .process-dots span:nth-child(2) {
            animation-delay: .15s;
        }

        .process-dots span:nth-child(3) {
            animation-delay: .3s;
        }

        @keyframes spinProcess {
            to {
                transform: rotate(360deg);
            }
        }

        @keyframes dotPulse {
            0%, 100% {
                transform: translateY(0);
                opacity: .35;
            }

            50% {
                transform: translateY(-6px);
                opacity: 1;
            }
        }
    </style>
</head>

<body class="bg-slate-950 text-slate-200 min-h-screen">

<div class="max-w-6xl mx-auto p-4 md:p-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold text-orange-400">Daftar Wajah</h1>
            <p class="text-slate-400">
                Ambil 5 referensi wajah untuk akurasi lebih baik.
            </p>
        </div>

        <a href="index.php" class="bg-slate-800 hover:bg-slate-700 px-4 py-2 rounded-lg text-center">
            Kembali ke Absensi
        </a>
    </div>

    <?php if ($msg = flash('error')): ?>
        <div class="mb-4 p-3 bg-red-900/50 border border-red-500 rounded-xl"><?= h($msg) ?></div>
    <?php endif; ?>

    <?php if ($msg = flash('success')): ?>
        <div class="mb-4 p-3 bg-emerald-900/50 border border-emerald-500 rounded-xl"><?= h($msg) ?></div>
    <?php endif; ?>

    <?php if ($can_manage): ?>
        <form method="GET" class="mb-5 bg-slate-900 border border-slate-800 rounded-2xl p-4 flex flex-col md:flex-row gap-3 md:items-end">
            <div class="flex-1">
                <label class="text-sm text-slate-400">Pilih karyawan</label>
                <select name="user_id" class="w-full mt-1 p-3 bg-slate-950 border border-slate-700 rounded-xl">
                    <?php foreach ($users as $u): ?>
                        <option value="<?= (int) $u['id'] ?>" <?= $target_user_id === (int) $u['id'] ? 'selected' : '' ?>>
                            <?= h($u['nama']) ?> - <?= h($u['role']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button class="px-5 py-3 bg-slate-700 hover:bg-slate-600 rounded-xl">
                Pilih
            </button>
        </form>
    <?php endif; ?>

    <div class="grid lg:grid-cols-[1fr_420px] gap-6">
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5">
            <h2 class="text-xl font-bold mb-2">
                Karyawan: <?= h($target_user['nama']) ?>
            </h2>

            <p class="text-slate-400 mb-4">
                Pastikan wajah terang, tidak blur, dan hanya satu wajah terlihat di kamera.
            </p>

            <div class="relative bg-black rounded-2xl overflow-hidden aspect-video border border-slate-700">
                <video id="video" autoplay muted playsinline class="w-full h-full object-cover"></video>
                <canvas id="canvas" class="hidden"></canvas>

                <div class="absolute left-3 top-3 flex items-center gap-2 bg-black/60 px-3 py-2 rounded-full max-w-[90%]">
                    <span class="pulse-dot shrink-0"></span>
                    <span id="status" class="text-xs md:text-sm font-semibold text-orange-200 truncate">
                        Memuat model wajah...
                    </span>
                </div>
            </div>

            <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
                <button type="button" id="btnStart" class="px-4 py-3 bg-orange-600 hover:bg-orange-500 rounded-xl font-bold">
                    Aktifkan Kamera
                </button>

                <button type="button" id="btnCapture" disabled class="px-4 py-3 bg-emerald-600 hover:bg-emerald-500 rounded-xl font-bold disabled:opacity-40">
                    Ambil Referensi
                </button>
            </div>

            <div class="mt-4 text-sm text-slate-500">
                Saat klik <strong class="text-slate-300">Aktifkan Kamera</strong>, kamera akan langsung tampil full screen agar lebih mudah mengambil referensi wajah.
            </div>
        </div>

        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5">
            <h2 class="text-xl font-bold mb-3">
                Referensi Terkumpul:
                <span id="count" class="text-orange-400">0</span>/5
            </h2>

            <div id="thumbs" class="grid grid-cols-2 gap-3 mb-4"></div>

            <form id="formEnroll" method="POST" action="proses_daftar_wajah.php">
                <input type="hidden" name="user_id" value="<?= (int) $target_user_id ?>">
                <input type="hidden" name="payload" id="payload">

                <button id="btnSave" disabled class="w-full py-3 bg-cyan-600 hover:bg-cyan-500 rounded-xl font-bold disabled:opacity-40">
                    Simpan Data Wajah
                </button>
            </form>

            <div class="mt-4 text-sm text-slate-400">
                Ambil pose berbeda: depan, sedikit kiri, sedikit kanan, sedikit atas, sedikit bawah.
            </div>
        </div>
    </div>
</div>

<div id="cameraModal" class="fixed inset-0 z-[9999] hidden bg-black overflow-hidden">
    <video id="modalVideo" autoplay muted playsinline></video>

    <div class="absolute top-0 left-0 right-0 z-20 bg-gradient-to-b from-black/95 to-transparent p-4">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 class="text-white font-bold text-lg">Rekam Referensi Wajah</h2>
                <p id="modalStatus" class="text-xs text-slate-300">
                    Posisikan wajah di dalam bingkai.
                </p>
            </div>

            <button type="button"
                    id="btnCloseFull"
                    class="w-11 h-11 rounded-full bg-white/15 hover:bg-white/25 text-white flex items-center justify-center"
                    title="Tutup kamera">
                <svg xmlns="http://www.w3.org/2000/svg"
                     width="26"
                     height="26"
                     viewBox="0 0 24 24"
                     fill="none"
                     stroke="currentColor"
                     stroke-width="2.5"
                     stroke-linecap="round"
                     stroke-linejoin="round"
                     aria-hidden="true">
                    <path d="M18 6 6 18"></path>
                    <path d="m6 6 12 12"></path>
                </svg>
            </button>
        </div>
    </div>

    <div class="absolute inset-0 z-10 pointer-events-none flex items-center justify-center">
        <div class="scan-frame w-[72vw] max-w-[340px] aspect-[3/4]"></div>
    </div>

    <div class="absolute bottom-0 left-0 right-0 z-20 bg-gradient-to-t from-black/95 to-transparent p-4">
        <div class="max-w-md mx-auto space-y-3">
            <div class="rounded-2xl bg-black/50 border border-white/10 p-3">
                <div class="flex items-center justify-between text-sm">
                    <span class="text-slate-300">Referensi</span>
                    <span class="font-bold text-orange-300"><span id="modalCount">0</span>/5</span>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <button type="button"
                        id="btnCaptureFull"
                        class="py-4 rounded-xl bg-emerald-600 hover:bg-emerald-500 font-bold disabled:opacity-40">
                    Ambil Referensi
                </button>

                <button type="button"
                        id="btnCloseFullBottom"
                        class="py-4 rounded-xl bg-slate-700 hover:bg-slate-600 font-bold">
                    Selesai
                </button>
            </div>
        </div>
    </div>
</div>

<div id="processOverlay" class="process-overlay">
    <div class="process-card">
        <div class="process-spinner"></div>

        <div id="processTitle" class="text-white font-extrabold text-lg">
            Memproses...
        </div>

        <div id="processText" class="text-slate-400 text-sm mt-1">
            Mohon tunggu sebentar
        </div>

        <div class="process-dots">
            <span></span>
            <span></span>
            <span></span>
        </div>
    </div>
</div>

<script>
const MODEL_URL = '../assets/face-api/models';

const video = document.getElementById('video');
const modalVideo = document.getElementById('modalVideo');
const canvas = document.getElementById('canvas');

const statusEl = document.getElementById('status');
const modalStatus = document.getElementById('modalStatus');

const btnStart = document.getElementById('btnStart');
const btnCloseFull = document.getElementById('btnCloseFull');
const btnCloseFullBottom = document.getElementById('btnCloseFullBottom');
const btnCapture = document.getElementById('btnCapture');
const btnCaptureFull = document.getElementById('btnCaptureFull');
const btnSave = document.getElementById('btnSave');

const payloadEl = document.getElementById('payload');
const thumbs = document.getElementById('thumbs');
const countEl = document.getElementById('count');
const modalCount = document.getElementById('modalCount');
const cameraModal = document.getElementById('cameraModal');

const processOverlay = document.getElementById('processOverlay');
const processTitle = document.getElementById('processTitle');
const processText = document.getElementById('processText');

let refs = [];
let modelsLoaded = false;
let cameraStarted = false;
let stream = null;
let activeVideo = modalVideo;

function showProcess(title = 'Memproses...', text = 'Mohon tunggu sebentar') {
    if (!processOverlay) return;

    processTitle.textContent = title;
    processText.textContent = text;
    processOverlay.classList.add('show');
}

function hideProcess() {
    if (!processOverlay) return;

    processOverlay.classList.remove('show');
}

function setStatus(text, tone = 'amber') {
    const cls = tone === 'ok'
        ? 'text-emerald-300'
        : tone === 'err'
            ? 'text-red-300'
            : 'text-orange-300';

    statusEl.className = 'text-xs md:text-sm font-semibold truncate ' + cls;
    statusEl.textContent = text;

    if (modalStatus) {
        modalStatus.textContent = text;
    }
}

function updateCaptureButton() {
    const disabled = !(modelsLoaded && cameraStarted) || refs.length >= 5;

    btnCapture.disabled = disabled;
    btnCaptureFull.disabled = disabled;
    btnSave.disabled = refs.length < 5;

    countEl.textContent = refs.length;
    modalCount.textContent = refs.length;
}

async function loadModels() {
    if (modelsLoaded) return;

    if (typeof faceapi === 'undefined') {
        throw new Error('Library face-api belum termuat. Pastikan file ../assets/face-api/face-api.min.js ada dan bisa diakses.');
    }

    showProcess('Memuat Model Wajah', 'Menyiapkan sistem pengenalan wajah...');
    setStatus('Memuat model wajah, tunggu sebentar...');

    try {
        await faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL);
        await faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL);
        await faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL);

        modelsLoaded = true;
        setStatus('Model siap. Klik Aktifkan Kamera.', 'ok');
    } finally {
        hideProcess();
    }

    updateCaptureButton();
}

async function startCamera() {
    btnStart.disabled = true;

    try {
        showProcess('Membuka Kamera', 'Mohon izinkan akses kamera jika diminta browser...');

        await loadModels();

        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            throw new Error('Browser tidak mendukung kamera. Gunakan Chrome/Edge terbaru melalui HTTPS.');
        }

        openFullCamera();

        if (!stream) {
            stream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: 'user',
                    width: { ideal: 800 },
                    height: { ideal: 600 }
                },
                audio: false
            });
        }

        video.srcObject = stream;
        modalVideo.srcObject = stream;

        await new Promise(resolve => {
            modalVideo.onloadedmetadata = resolve;
        });

        cameraStarted = true;
        activeVideo = modalVideo;

        setStatus('Kamera aktif. Ambil referensi sampai 5 foto.', 'ok');

    } catch (e) {
        closeFullCamera();
        setStatus('Kamera/model gagal: ' + e.message, 'err');
        btnStart.disabled = false;
    } finally {
        hideProcess();
    }

    updateCaptureButton();
}

function stopCamera() {
    if (stream) {
        stream.getTracks().forEach(track => track.stop());
        stream = null;
    }

    video.srcObject = null;
    modalVideo.srcObject = null;

    cameraStarted = false;
    btnStart.disabled = false;

    document.body.classList.remove('camera-open');

    updateCaptureButton();
}

function openFullCamera() {
    cameraModal.classList.remove('hidden');
    document.body.classList.add('camera-open');
    activeVideo = modalVideo;
}

function closeFullCamera() {
    cameraModal.classList.add('hidden');
    document.body.classList.remove('camera-open');
    activeVideo = modalVideo;

    if (cameraStarted) {
        setStatus('Kamera aktif di latar. Klik Aktifkan Kamera untuk membuka full screen lagi.', 'ok');
    } else {
        setStatus(modelsLoaded ? 'Model siap. Klik Aktifkan Kamera.' : 'Memuat model wajah...');
    }
}

function snapshot() {
    const srcVideo = activeVideo || modalVideo || video;

    canvas.width = srcVideo.videoWidth || 640;
    canvas.height = srcVideo.videoHeight || 480;

    const ctx = canvas.getContext('2d');
    ctx.drawImage(srcVideo, 0, 0, canvas.width, canvas.height);

    return canvas.toDataURL('image/jpeg', 0.86);
}

async function captureRef() {
    try {
        updateCaptureButton();

        if (btnCaptureFull.disabled && btnCapture.disabled) return;

        btnCapture.disabled = true;
        btnCaptureFull.disabled = true;

        showProcess('Mendeteksi Wajah', 'Pastikan hanya satu wajah terlihat jelas...');
        setStatus('Mendeteksi wajah...');

        const options = new faceapi.TinyFaceDetectorOptions({
            inputSize: 320,
            scoreThreshold: 0.45
        });

        const srcVideo = activeVideo || modalVideo || video;

        const det = await faceapi
            .detectAllFaces(srcVideo, options)
            .withFaceLandmarks()
            .withFaceDescriptors();

        if (det.length !== 1) {
            setStatus(
                det.length === 0
                    ? 'Wajah tidak terdeteksi. Dekatkan wajah dan tambah cahaya.'
                    : 'Terdeteksi lebih dari satu wajah. Pastikan hanya satu orang di kamera.',
                'err'
            );

            updateCaptureButton();
            return;
        }

        showProcess('Menyimpan Referensi', 'Mengambil data wajah sementara...');

        const img = snapshot();

        refs.push({
            pose: 'ref_' + (refs.length + 1),
            image: img,
            descriptor: Array.from(det[0].descriptor)
        });

        const im = document.createElement('img');
        im.src = img;
        im.className = 'rounded-xl border border-slate-700 aspect-video object-cover';
        thumbs.appendChild(im);

        payloadEl.value = JSON.stringify(refs);

        setStatus('Referensi ' + refs.length + ' tersimpan sementara.', 'ok');

        if (refs.length >= 5) {
            btnCapture.disabled = true;
            btnCaptureFull.disabled = true;
            btnSave.disabled = false;

            setStatus('5 referensi lengkap. Klik Simpan Data Wajah.', 'ok');
            closeFullCamera();
        } else {
            updateCaptureButton();
        }

    } catch (e) {
        setStatus('Gagal ambil referensi: ' + e.message, 'err');
        updateCaptureButton();
    } finally {
        hideProcess();
    }
}

btnStart.addEventListener('click', startCamera);
btnCloseFull.addEventListener('click', closeFullCamera);
btnCloseFullBottom.addEventListener('click', closeFullCamera);
btnCapture.addEventListener('click', startCamera);
btnCaptureFull.addEventListener('click', captureRef);

document.getElementById('formEnroll').addEventListener('submit', function () {
    showProcess('Menyimpan Data Wajah', 'Mohon tunggu, data wajah sedang disimpan...');
});

window.addEventListener('load', function () {
    loadModels().catch(function(e) {
        hideProcess();
        setStatus('Gagal memuat model wajah: ' + e.message, 'err');
    });
});

window.addEventListener('beforeunload', stopCamera);
</script>

</body>
</html>
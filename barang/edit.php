<?php
// barang/edit.php
session_start();

require_once '../config/database.php';
require_once '../includes/ui.php';

if (!isset($_SESSION['tenant_id'], $_SESSION['role']) || !in_array($_SESSION['role'], ['Owner', 'Admin', 'Gudang'], true)) {
    die("Akses ditolak.");
}

$tenant_id = (int) $_SESSION['tenant_id'];
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    die("ID barang tidak valid.");
}

$stmt = $pdo->prepare("
    SELECT *
    FROM barang
    WHERE id = ?
      AND tenant_id = ?
    LIMIT 1
");
$stmt->execute([$id, $tenant_id]);
$barang = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$barang) {
    die("Data barang tidak ditemukan.");
}

$is_eceran = (
    (int)($barang['isi_per_kemasan'] ?? 1) > 1 ||
    !empty($barang['barcode_eceran']) ||
    !empty($barang['satuan_ecer']) ||
    $barang['harga_ecer'] !== null
);

$stok_tampil = ui_format_stok_bertingkat(
    $barang['stok_gudang'] ?? 0,
    $barang['satuan'] ?? '',
    $barang['isi_per_kemasan'] ?? 1,
    $barang['satuan_ecer'] ?? ''
);
$stok_terkecil_tampil = ui_format_stok_terkecil(
    $barang['stok_gudang'] ?? 0,
    $barang['satuan'] ?? '',
    $barang['isi_per_kemasan'] ?? 1,
    $barang['satuan_ecer'] ?? ''
);
$stok_terkecil_awal = max(0, (int)($barang['stok_gudang'] ?? 0));
$isi_awal = max(1, (int)($barang['isi_per_kemasan'] ?? 1));
$stok_utama_awal = $is_eceran && $isi_awal > 1 ? intdiv($stok_terkecil_awal, $isi_awal) : $stok_terkecil_awal;
$stok_ecer_awal = $is_eceran && $isi_awal > 1 ? ($stok_terkecil_awal % $isi_awal) : 0;

ui_head('Edit Barang');
ui_nav($pdo, 'Barang & Stok');
?>

<script src="https://unpkg.com/html5-qrcode"></script>

<style>
input[type="text"],
input[type="number"],
select,
textarea {
    font-size: 16px;
}

.edit-section {
    background: rgba(2, 6, 23, .45);
    border: 1px solid rgba(148, 163, 184, .16);
    border-radius: 1rem;
    padding: 1rem;
}

.scan-field-wrap {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: .5rem;
    margin-top: .25rem;
}

.scanner-modal {
    position: fixed;
    inset: 0;
    z-index: 99999;
    background: rgba(0, 0, 0, .96);
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    backdrop-filter: blur(8px);
    padding: 1rem;
}

.scanner-modal.hidden {
    display: none;
}

.scanner-box {
    width: 100%;
    max-width: 380px;
    border-radius: 1rem;
    overflow: hidden;
    border: 2px solid rgba(249, 115, 22, .9);
    background: #020617;
}

.choice-card {
    display: flex;
    align-items: flex-start;
    gap: .75rem;
    border: 1px solid #1e293b;
    background: #020617;
    border-radius: .9rem;
    padding: .85rem;
    cursor: pointer;
}

.choice-card:has(input:checked) {
    border-color: rgba(249, 115, 22, .8);
    background: rgba(249, 115, 22, .08);
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
        <h2 class="text-2xl font-bold text-orange-400">Edit Barang</h2>
        <p class="text-slate-400">
            Perbarui data produk, barcode utama, barcode ecer, satuan, stok, harga, dan foto barang.
        </p>
    </div>

    <a href="index.php" class="btn btn-secondary">Kembali</a>
</div>

<form action="proses_edit.php" method="POST" enctype="multipart/form-data" class="space-y-5" data-loading-text="Menyimpan perubahan barang...">
    <input type="hidden" name="id" value="<?= (int)$barang['id'] ?>">
    <input type="hidden" name="foto_lama" value="<?= h($barang['foto_barang'] ?? '') ?>">

    <div class="app-card p-4 md:p-5">
        <div class="mb-5">
            <h3 class="text-xl font-bold text-orange-400">Identitas Barang</h3>
            <p class="text-sm text-slate-400">Barcode utama dan barcode ecer boleh sama atau berbeda.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <label class="block">
                <span class="label">Kode Barang Internal</span>
                <input
                    type="text"
                    name="kode_barang"
                    id="kode_barang"
                    class="app-input mt-1"
                    value="<?= h($barang['kode_barang'] ?? '') ?>"
                    placeholder="Contoh: BRG-001"
                >
            </label>

            <label class="block">
                <span class="label">Barcode / Kode Satuan Utama *</span>
                <div class="scan-field-wrap">
                    <input
                        type="text"
                        name="barcode"
                        id="barcode"
                        class="app-input"
                        value="<?= h($barang['barcode'] ?? '') ?>"
                        placeholder="Barcode karton/renceng/dus"
                        required
                    >
                    <button type="button" onclick="bukaKamera('utama')" class="btn btn-secondary !w-auto !px-3">
                        Scan
                    </button>
                </div>
            </label>

            <label class="md:col-span-2 block">
                <span class="label">Nama Produk *</span>
                <input
                    type="text"
                    name="nama_barang"
                    id="nama_barang"
                    class="app-input mt-1 text-lg font-bold"
                    value="<?= h($barang['nama_barang'] ?? '') ?>"
                    required
                    placeholder="Nama produk"
                >
            </label>

            <label class="block">
                <span class="label">Kategori</span>
                <input
                    type="text"
                    name="kategori"
                    class="app-input mt-1"
                    value="<?= h($barang['kategori'] ?? '') ?>"
                    placeholder="Contoh: Sembako"
                >
            </label>

            <label class="block">
                <span class="label">Satuan Utama *</span>
                <input
                    type="text"
                    name="satuan"
                    id="satuan"
                    class="app-input mt-1"
                    value="<?= h($barang['satuan'] ?? '') ?>"
                    required
                    placeholder="Contoh: Renceng, Dus, Karton"
                    oninput="updateLabelSatuan()"
                >
            </label>
        </div>
    </div>

    <div class="app-card p-4 md:p-5">
        <div class="mb-4">
            <h3 class="text-xl font-bold text-orange-400">Tipe Satuan</h3>
            <p class="text-sm text-slate-400">Pilih apakah barang hanya dihitung per satuan utama atau punya satuan kecil/ecer.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <label class="choice-card">
                <input
                    type="radio"
                    name="tipe_satuan"
                    value="utama"
                    <?= !$is_eceran ? 'checked' : '' ?>
                    onchange="setTipeSatuan('utama')"
                >
                <span>
                    <b>Satuan utama saja</b>
                    <span class="block text-sm text-slate-400 mt-1" id="helper_tipe_utama">Stok hanya dihitung per <?= h($barang['satuan'] ?: 'satuan utama') ?>.</span>
                </span>
            </label>

            <label class="choice-card">
                <input
                    type="radio"
                    name="tipe_satuan"
                    value="ecer"
                    <?= $is_eceran ? 'checked' : '' ?>
                    onchange="setTipeSatuan('ecer')"
                >
                <span>
                    <b>Punya satuan ecer</b>
                    <span class="block text-sm text-slate-400 mt-1" id="helper_tipe_ecer">Contoh: 1 <?= h($barang['satuan'] ?: 'box') ?> isi <?= max(1, (int)($barang['isi_per_kemasan'] ?? 1)) ?> <?= h($barang['satuan_ecer'] ?: 'pcs') ?>.</span>
                </span>
            </label>
        </div>

        <input
            type="checkbox"
            id="bisa_diecer"
            name="bisa_diecer"
            value="1"
            class="hidden"
            <?= $is_eceran ? 'checked' : '' ?>
        >

        <div id="blok_eceran" class="<?= $is_eceran ? 'grid' : 'hidden' ?> grid-cols-1 md:grid-cols-2 gap-4 pt-4 mt-4 border-t border-slate-800">
            <label class="block">
                <span class="label">Barcode Ecer</span>
                <div class="scan-field-wrap">
                    <input
                        type="text"
                        id="barcode_eceran"
                        name="barcode_eceran"
                        class="app-input"
                        value="<?= h($barang['barcode_eceran'] ?? '') ?>"
                        placeholder="Boleh kosong, boleh sama, boleh beda"
                    >
                    <button type="button" onclick="bukaKamera('ecer')" class="btn btn-secondary !w-auto !px-3">
                        Scan
                    </button>
                </div>
            </label>

            <label class="block">
                <span class="label" id="label_isi_per_satuan">Isi per <?= h($barang['satuan'] ?? 'Kemasan') ?></span>
                <input
                    type="number"
                    id="isi_per_kemasan"
                    name="isi_per_kemasan"
                    value="<?= max(1, (int)($barang['isi_per_kemasan'] ?? 1)) ?>"
                    min="1"
                    class="app-input mt-1"
                    <?= $is_eceran ? 'required' : '' ?>
                >
            </label>

            <label class="block">
                <span class="label">Satuan Ecer</span>
                <input
                    type="text"
                    id="satuan_ecer"
                    name="satuan_ecer"
                    value="<?= h($barang['satuan_ecer'] ?? '') ?>"
                    class="app-input mt-1"
                    placeholder="Contoh: Sachet, Pcs"
                    oninput="updateLabelSatuan()"
                    <?= $is_eceran ? 'required' : '' ?>
                >
            </label>

            <div class="md:col-span-2 text-xs text-slate-500">
                Barcode ecer boleh kosong, boleh sama dengan barcode utama, atau boleh berbeda. Jika sama, kasir akan menampilkan pilihan jual utama atau ecer.
            </div>
        </div>
    </div>

    <div class="app-card p-4 md:p-5">
        <div class="mb-5">
            <h3 class="text-xl font-bold text-orange-400">Stok & Harga</h3>
            <p class="text-sm text-slate-400">Stok gudang disimpan dalam satuan terkecil.</p>
            <div class="mt-3 rounded-2xl bg-slate-950 border border-slate-800 p-3">
                <div class="label">Tampilan stok saat ini</div>
                <div class="text-xl font-extrabold text-orange-300"><?= h($stok_tampil) ?></div>
                <?php if ((int)($barang['isi_per_kemasan'] ?? 1) > 1): ?>
                    <div class="text-xs text-slate-500 mt-1">
                        Total kecil tersimpan: <?= h($stok_terkecil_tampil) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <label class="block">
                <span class="label" id="label_stok_utama">Stok <?= h($barang['satuan'] ?: 'Utama') ?> *</span>
                <input
                    type="number"
                    id="stok_gudang_utama"
                    name="stok_gudang_utama"
                    class="app-input mt-1 text-lg font-bold text-orange-300"
                    value="<?= $stok_utama_awal ?>"
                    min="0"
                    required
                >
                <span class="text-xs text-slate-500 mt-1 block">
                    Isi jumlah satuan besar. Jika ada sisa ecer, isi kolom tambahan di samping.
                </span>
            </label>

            <label class="block <?= $is_eceran ? '' : 'hidden' ?>" id="blok_stok_ecer">
                <span class="label" id="label_stok_ecer">Sisa <?= h($barang['satuan_ecer'] ?: 'Ecer') ?></span>
                <input
                    type="number"
                    id="stok_gudang_ecer"
                    name="stok_gudang_ecer"
                    class="app-input mt-1 text-lg font-bold text-orange-300"
                    value="<?= $stok_ecer_awal ?>"
                    min="0"
                >
                <span class="text-xs text-slate-500 mt-1 block" id="helper_stok_total">
                    Total tersimpan: <?= h($stok_terkecil_tampil) ?>.
                </span>
            </label>

            <label class="block">
                <span class="label">Harga Modal / Beli *</span>
                <input
                    type="number"
                    name="harga_beli"
                    class="app-input mt-1"
                    value="<?= (int)($barang['harga_beli'] ?? 0) ?>"
                    min="0"
                    required
                >
            </label>

            <label class="block">
                <span class="label">Harga Jual Satuan Utama *</span>
                <input
                    type="number"
                    name="harga_jual"
                    class="app-input mt-1 text-lg font-bold text-orange-300"
                    value="<?= (int)($barang['harga_jual'] ?? 0) ?>"
                    min="0"
                    required
                >
            </label>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4 pt-4 border-t border-slate-800">
            <label class="block <?= $is_eceran ? '' : 'hidden' ?>" id="blok_harga_ecer">
                <span class="label" id="label_harga_ecer">Harga Jual Ecer</span>
                <input
                    type="number"
                    id="harga_ecer"
                    name="harga_ecer"
                    value="<?= $barang['harga_ecer'] !== null ? (int)$barang['harga_ecer'] : '' ?>"
                    class="app-input mt-1 text-orange-300 font-bold"
                    placeholder="0"
                    min="0"
                    <?= $is_eceran ? 'required' : '' ?>
                >
            </label>
        </div>
    </div>

    <div class="app-card p-4 md:p-5">
        <div class="mb-4">
            <h3 class="text-xl font-bold text-orange-400">Foto Barang</h3>
            <p class="text-sm text-slate-400">Kosongkan jika foto tidak ingin diganti.</p>
        </div>

        <input
            type="file"
            name="foto_barang"
            accept="image/*"
            class="mt-1 w-full text-sm text-slate-400 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-slate-800 file:text-orange-300 hover:file:bg-slate-700 cursor-pointer p-2 border border-slate-700 rounded-xl bg-slate-950"
        >

        <?php if (!empty($barang['foto_barang'])): ?>
            <div class="mt-4 flex items-center gap-3">
                <img
                    src="../public/uploads/tenant_<?= $tenant_id ?>/barang/<?= h($barang['foto_barang']) ?>"
                    class="w-20 h-20 object-cover rounded-xl border border-slate-700"
                    alt="Foto barang"
                >
                <div>
                    <div class="text-sm text-slate-300">Foto saat ini</div>
                    <div class="text-xs text-slate-500"><?= h($barang['foto_barang']) ?></div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <a href="index.php" class="btn btn-secondary text-center">Batal</a>
        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
    </div>
</form>

<div id="modal_kamera" class="scanner-modal hidden">
    <div class="w-full max-w-sm flex justify-between items-center p-2 mb-1">
        <h3 id="judul_scanner" class="text-white font-bold text-sm">Scanner Barcode</h3>
        <button type="button" onclick="tutupKamera()" class="btn btn-secondary !w-auto !px-3">
            Tutup
        </button>
    </div>

    <div class="scanner-box">
        <div id="reader" class="w-full h-full"></div>
    </div>

    <p id="helper_scanner" class="text-xs text-slate-400 text-center mt-3 max-w-sm">
        Arahkan kamera ke barcode sampai terbaca otomatis.
    </p>
</div>

<script>
let html5QrCode = null;
let scanTarget = 'utama';
let scanSedangProses = false;

function satuanUtama() {
    return (document.getElementById('satuan').value || 'Kemasan').trim();
}

function satuanEcer() {
    return (document.getElementById('satuan_ecer').value || 'Ecer').trim();
}

function updateLabelSatuan() {
    const utama = satuanUtama();
    const ecer = satuanEcer();

    const labelIsi = document.getElementById('label_isi_per_satuan');
    const labelHargaEcer = document.getElementById('label_harga_ecer');
    const labelStokUtama = document.getElementById('label_stok_utama');
    const labelStokEcer = document.getElementById('label_stok_ecer');
    const helperTipeUtama = document.getElementById('helper_tipe_utama');
    const helperTipeEcer = document.getElementById('helper_tipe_ecer');

    if (labelIsi) labelIsi.textContent = 'Isi per ' + utama;
    if (labelHargaEcer) labelHargaEcer.textContent = 'Harga Jual per ' + ecer;
    if (labelStokUtama) labelStokUtama.textContent = 'Stok ' + utama + ' *';
    if (labelStokEcer) labelStokEcer.textContent = 'Sisa ' + ecer;
    if (helperTipeUtama) helperTipeUtama.textContent = 'Stok hanya dihitung per ' + utama + '.';
    if (helperTipeEcer) helperTipeEcer.textContent = 'Contoh: 1 ' + utama + ' isi ' + (document.getElementById('isi_per_kemasan').value || '12') + ' ' + ecer + '.';
    updatePreviewStokEdit();
}

function setTipeSatuan(tipe) {
    const pakaiEcer = tipe === 'ecer';
    const check = document.getElementById('bisa_diecer');

    document.querySelectorAll('input[name="tipe_satuan"]').forEach(function (radio) {
        radio.checked = radio.value === tipe;
    });

    check.checked = pakaiEcer;
    toggleFormEceran();
    updateLabelSatuan();
}

function toggleFormEceran() {
    const check = document.getElementById('bisa_diecer').checked;
    const blok = document.getElementById('blok_eceran');
    const blokStokEcer = document.getElementById('blok_stok_ecer');
    const blokHargaEcer = document.getElementById('blok_harga_ecer');
    const isi = document.getElementById('isi_per_kemasan');
    const satuanEcer = document.getElementById('satuan_ecer');
    const hargaEcer = document.getElementById('harga_ecer');

    if (check) {
        blok.classList.remove('hidden');
        blok.classList.add('grid');

        isi.setAttribute('required', 'required');
        satuanEcer.setAttribute('required', 'required');
        hargaEcer.setAttribute('required', 'required');

        if (!isi.value || parseInt(isi.value || '0') < 1) isi.value = '1';
        if (!satuanEcer.value) satuanEcer.value = 'Pcs';
        if (blokStokEcer) blokStokEcer.classList.remove('hidden');
        if (blokHargaEcer) blokHargaEcer.classList.remove('hidden');
    } else {
        blok.classList.add('hidden');
        blok.classList.remove('grid');

        isi.removeAttribute('required');
        satuanEcer.removeAttribute('required');
        hargaEcer.removeAttribute('required');
        if (blokStokEcer) blokStokEcer.classList.add('hidden');
        if (blokHargaEcer) blokHargaEcer.classList.add('hidden');
        const stokEcer = document.getElementById('stok_gudang_ecer');
        if (stokEcer) stokEcer.value = '0';
    }

    updatePreviewStokEdit();
}

function formatNumberId(value) {
    const number = Number(value || 0);
    return number.toLocaleString('id-ID');
}

function formatStokBertingkat(stokTerkecil, satuanUtama, isiPerKemasan, satuanEcer) {
    const stok = Math.max(0, parseInt(stokTerkecil || 0, 10));
    const isi = Math.max(1, parseInt(isiPerKemasan || 1, 10));
    const utama = String(satuanUtama || 'Satuan').trim() || 'Satuan';
    const ecer = String(satuanEcer || utama).trim() || utama;

    if (isi <= 1 || utama.toLowerCase() === ecer.toLowerCase()) {
        return formatNumberId(stok) + ' ' + ecer;
    }

    const jumlahUtama = Math.floor(stok / isi);
    const sisaEcer = stok % isi;
    const parts = [];

    if (jumlahUtama > 0) parts.push(formatNumberId(jumlahUtama) + ' ' + utama);
    if (sisaEcer > 0 || stok === 0) parts.push(formatNumberId(sisaEcer) + ' ' + ecer);

    return parts.join(' ');
}

function updatePreviewStokEdit() {
    const utama = satuanUtama();
    const ecer = satuanEcer();
    const isi = Math.max(1, parseInt(document.getElementById('isi_per_kemasan').value || 1, 10));
    const jumlahUtama = Math.max(0, parseInt(document.getElementById('stok_gudang_utama').value || 0, 10));
    const jumlahEcer = document.getElementById('bisa_diecer').checked
        ? Math.max(0, parseInt(document.getElementById('stok_gudang_ecer').value || 0, 10))
        : 0;
    const totalKecil = (jumlahUtama * isi) + jumlahEcer;
    const helper = document.getElementById('helper_stok_total');

    if (helper) {
        helper.textContent = 'Total tersimpan: ' + formatNumberId(totalKecil) + ' ' + ecer + ' (' + formatStokBertingkat(totalKecil, utama, isi, ecer) + ').';
    }
}

function bukaKamera(target = 'utama') {
    scanTarget = target;
    scanSedangProses = false;

    const modal = document.getElementById('modal_kamera');
    const judul = document.getElementById('judul_scanner');
    const helper = document.getElementById('helper_scanner');

    if (scanTarget === 'ecer') {
        if (judul) judul.textContent = 'Scan Barcode Ecer';
        if (helper) helper.textContent = 'Hasil scan akan masuk ke field Barcode Ecer.';
    } else {
        if (judul) judul.textContent = 'Scan Barcode Satuan Utama';
        if (helper) helper.textContent = 'Hasil scan akan masuk ke field Barcode / Kode Satuan Utama.';
    }

    modal.classList.remove('hidden');

    if (!html5QrCode) {
        html5QrCode = new Html5Qrcode("reader");
    }

    html5QrCode.start(
        { facingMode: "environment" },
        { fps: 15, qrbox: { width: 250, height: 120 } },
        function (text) {
            if (scanSedangProses) return;
            scanSedangProses = true;

            prosesHasilScan(text);
        }
    ).catch(function () {
        tampilAlert('Kamera gagal dibuka. Pastikan izin kamera sudah diberikan.', 'error', 'Kamera Error');
        tutupKamera();
    });
}

function prosesHasilScan(text) {
    text = String(text || '').trim();

    if (text === '') {
        tutupKamera();
        return;
    }

    if (scanTarget === 'ecer') {
        setTipeSatuan('ecer');
        document.getElementById('barcode_eceran').value = text;
    } else {
        document.getElementById('barcode').value = text;
    }

    tutupKamera();

    setTimeout(function () {
        tampilAlert('Barcode berhasil discan.', 'success', 'Berhasil');
    }, 250);
}

function tutupKamera() {
    const modal = document.getElementById('modal_kamera');

    if (html5QrCode && html5QrCode.isScanning) {
        html5QrCode.stop().then(function () {
            modal.classList.add('hidden');
            scanSedangProses = false;
        }).catch(function () {
            modal.classList.add('hidden');
            scanSedangProses = false;
        });
    } else {
        modal.classList.add('hidden');
        scanSedangProses = false;
    }
}

function tampilAlert(text, icon = 'success', title = 'Info') {
    if (window.AppUI) {
        AppUI.alert(text, icon, title);
        return;
    }

    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: title,
            text: text,
            icon: icon,
            timer: 1300,
            showConfirmButton: false,
            confirmButtonColor: '#f97316',
            background: '#020617',
            color: '#e5e7eb',
            heightAuto: false,
            scrollbarPadding: false,
            customClass: {
                container: 'kasirrapi-swal-container',
                popup: 'kasirrapi-swal-popup'
            }
        });
    }
}

document.addEventListener('DOMContentLoaded', function () {
    setTipeSatuan(document.getElementById('bisa_diecer').checked ? 'ecer' : 'utama');

    ['stok_gudang_utama', 'stok_gudang_ecer', 'isi_per_kemasan'].forEach(function (id) {
        const el = document.getElementById(id);
        if (el) el.addEventListener('input', updatePreviewStokEdit);
    });
});
</script>

<?php ui_footer(); ?>

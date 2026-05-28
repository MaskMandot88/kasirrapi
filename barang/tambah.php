<?php
// barang/tambah.php
session_start();

require_once '../config/database.php';
require_once '../includes/ui.php';

if (!isset($_SESSION['tenant_id'], $_SESSION['user_id'], $_SESSION['role'])) {
    header('Location: ../auth/login.php');
    exit;
}

if (!in_array($_SESSION['role'], ['Owner', 'Admin', 'Gudang'], true)) {
    die("Akses ditolak.");
}

$tenant_id = (int) $_SESSION['tenant_id'];
$subscription = kasirrapi_tenant_subscription($pdo, $tenant_id);
$pembelian_allowed = kasirrapi_feature_allowed($subscription, 'pembelian');
$is_lanjut_nota = isset($_SESSION['current_pembelian_id']);
$current_nomor_nota = $_SESSION['current_nomor_nota'] ?? 'Nota aktif';

$stmt = $pdo->prepare("SELECT id, nama_supplier FROM suppliers WHERE tenant_id = ? ORDER BY nama_supplier ASC");
$stmt->execute([$tenant_id]);
$suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

ui_head('Penerimaan Barang');
ui_nav($pdo, 'Penerimaan Barang');
?>

<script src="https://unpkg.com/html5-qrcode"></script>

<style>
    input[type="text"], input[type="number"], input[type="date"], select, textarea { font-size: 16px; }

    .form-section {
        background: rgba(2, 6, 23, .45);
        border: 1px solid rgba(148, 163, 184, .16);
        border-radius: 1rem;
        padding: 1rem;
    }

    .form-section-orange { border-color: rgba(255, 106, 0, .35); }

    .search-dropdown {
        position: absolute;
        z-index: 60;
        width: 100%;
        background: #0f172a;
        border: 1px solid #334155;
        margin-top: .35rem;
        border-radius: .875rem;
        box-shadow: 0 20px 50px rgba(0,0,0,.35);
        max-height: 240px;
        overflow-y: auto;
    }

    .fade-in { animation: fadeIn .25s ease-out both; }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-8px); }
        to { opacity: 1; transform: translateY(0); }
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

    .scanner-modal.hidden { display: none; }

    .scanner-box {
        width: 100%;
        max-width: 380px;
        border-radius: 1rem;
        overflow: hidden;
        border: 2px solid rgba(249, 115, 22, .9);
        background: #020617;
    }

    .field-readonly {
        background: rgba(15, 23, 42, .75) !important;
        color: #94a3b8 !important;
        cursor: not-allowed !important;
    }

    .barang-existing-banner {
        background: rgba(245, 158, 11, .12);
        border: 1px solid rgba(245, 158, 11, .45);
        color: #fde68a;
        border-radius: 1rem;
        padding: .85rem 1rem;
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
        <h2 class="text-2xl font-bold text-orange-400">Penerimaan Barang</h2>
        <p class="text-slate-400">Cari barang dulu. Jika barang sudah ada, cukup isi jumlah dan harga terbaru.</p>
    </div>
    <a href="batal_nota.php" class="btn btn-secondary">Batal / Keluar</a>
</div>

<?php if ($is_lanjut_nota): ?>
    <div class="mb-5 app-card p-4 border-orange-500/40">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
            <div>
                <div class="text-sm text-slate-400">Tambah Banyak dalam 1 Nota</div>
                <div class="text-lg font-bold text-orange-300"><?= h($current_nomor_nota) ?></div>
            </div>
            <span class="inline-flex w-fit px-3 py-1 rounded-full text-xs font-bold bg-orange-600 text-white">Nota Aktif</span>
        </div>
    </div>
<?php endif; ?>

<form action="proses_tambah.php" method="POST" enctype="multipart/form-data" class="space-y-5" data-loading-text="Menyimpan data barang...">
    <input type="hidden" name="barang_id" id="barang_id">
    <input type="hidden" name="mode_input" id="mode_input" value="<?= $is_lanjut_nota ? 'nota' : 'cepat' ?>">

    <div class="form-section form-section-orange">
        <div class="mb-3">
            <h3 class="text-lg font-bold text-orange-400">Scan / Ketik Barcode atau Nama Barang</h3>
            <p class="text-sm text-slate-400">Pilih barang dari hasil pencarian jika sudah ada. Kalau belum ada, klik Lanjut untuk input barang baru.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-[1fr_auto_auto] gap-2">
            <div class="relative">
                <input
                    type="text"
                    id="search_input"
                    onkeyup="cariBarang(this.value)"
                    onkeydown="enterKodeBarang(event)"
                    placeholder="Scan barcode / ketik kode / cari nama barang..."
                    class="app-input"
                    autocomplete="off"
                >
                <ul id="search_results" class="search-dropdown hidden"></ul>
            </div>

            <button type="button" onclick="bukaKamera('cari')" class="btn btn-primary">Scan</button>
            <button type="button" onclick="tampilkanFormBarang()" class="btn btn-secondary">Lanjut Barang Baru</button>
        </div>
    </div>

    <div id="blok_isian_barang" class="hidden fade-in app-card p-4 md:p-5">
        <div class="mb-5">
            <h3 class="text-xl font-bold text-orange-400">Detail Barang</h3>
            <p class="text-sm text-slate-400">Jika barang sudah ada, data master dikunci. Anda cukup mengisi jumlah dan harga terbaru.</p>
        </div>

        <div id="info_barang_lama" class="barang-existing-banner hidden mb-5">
            <div class="font-bold">Barang sudah ada</div>
            <div class="text-sm mt-1">
                Data nama, barcode, kategori, dan satuan dikunci agar master barang tidak berubah tanpa sengaja.
                Isi <b>jumlah masuk</b> dan <b>harga terbaru</b> saja. Jika harga berubah, sistem otomatis mencatat riwayat harga.
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <label class="block">
                <span class="label">Barcode / Kode Satuan Utama</span>
                <div class="scan-field-wrap">
                    <input type="text" id="barcode" name="barcode" class="app-input master-field" placeholder="Barcode karton/renceng/dus, boleh kosong">
                    <button type="button" id="btn_scan_utama" onclick="bukaKamera('utama')" class="btn btn-secondary !w-auto !px-3">Scan</button>
                </div>
            </label>

            <label class="block">
                <span class="label">Kode Barang Internal, opsional</span>
                <input type="text" id="kode_barang" name="kode_barang" class="app-input mt-1 master-field" placeholder="Contoh: BRG-001">
            </label>

            <label class="md:col-span-2 block">
                <span class="label">Nama Produk *</span>
                <input type="text" id="input_nama_barang" name="nama_barang" class="app-input mt-1 text-lg font-bold master-field" required placeholder="Contoh: Indomie Goreng 1 Karton">
            </label>

            <label class="block">
                <span class="label">Kategori</span>
                <input type="text" name="kategori" class="app-input mt-1 master-field" placeholder="Contoh: Sembako">
            </label>

            <label class="block">
                <span class="label">Satuan Utama *</span>
                <input type="text" id="satuan" name="satuan" class="app-input mt-1 master-field" value="Renceng" required oninput="updateLabelSatuan()">
            </label>

            <div class="md:col-span-2 form-section">
                <div class="mb-3">
                    <h3 class="text-lg font-bold text-orange-400">Tipe Satuan</h3>
                    <p class="text-sm text-slate-400">Pilih dulu apakah barang hanya dihitung per satuan utama atau punya satuan kecil/ecer.</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <label class="choice-card">
                        <input type="radio" name="tipe_satuan" value="utama" checked onchange="setTipeSatuan('utama')">
                        <span>
                            <b>Satuan utama saja</b>
                            <span class="block text-sm text-slate-400 mt-1" id="helper_tipe_utama">Contoh: stok hanya dihitung per Renceng.</span>
                        </span>
                    </label>

                    <label class="choice-card">
                        <input type="radio" name="tipe_satuan" value="ecer" onchange="setTipeSatuan('ecer')">
                        <span>
                            <b>Punya satuan ecer</b>
                            <span class="block text-sm text-slate-400 mt-1" id="helper_tipe_ecer">Contoh: 1 Renceng isi 12 Sachet.</span>
                        </span>
                    </label>
                </div>

                <input type="checkbox" id="bisa_diecer" name="bisa_diecer" value="1" class="hidden">

                <div id="blok_eceran" class="hidden grid grid-cols-1 sm:grid-cols-4 gap-3 pt-4 mt-4 border-t border-slate-800">
                    <label class="block sm:col-span-2">
                        <span class="label">Barcode Ecer, opsional</span>
                        <div class="scan-field-wrap">
                            <input type="text" id="barcode_eceran" name="barcode_eceran" class="app-input master-field" placeholder="Boleh kosong, boleh sama, boleh beda">
                            <button type="button" id="btn_scan_ecer" onclick="bukaKamera('ecer')" class="btn btn-secondary !w-auto !px-3">Scan</button>
                        </div>
                    </label>

                    <label class="block">
                        <span class="label" id="label_isi_per_satuan">Isi per Renceng</span>
                        <input type="number" id="isi_per_kemasan" name="isi_per_kemasan" value="12" min="1" class="app-input mt-1 master-field">
                    </label>

                    <label class="block">
                        <span class="label">Satuan Ecer</span>
                        <input type="text" id="satuan_ecer" name="satuan_ecer" value="Sachet" class="app-input mt-1 master-field" oninput="updateLabelSatuan()">
                    </label>
                </div>
            </div>

            <label class="block">
                <span class="label" id="label_jumlah_masuk">Jumlah Renceng Masuk *</span>
                <input type="number" id="stok_gudang" name="stok_gudang" class="app-input mt-1 text-lg font-bold text-orange-300" placeholder="0" min="0" required>
                <span class="text-xs text-slate-500 mt-1 block" id="helper_stok">
                    Contoh: beli 3 Renceng, isi 12 per Renceng. Sistem menyimpan stok dalam satuan ecer.
                </span>
            </label>

            <label class="block hidden" id="blok_jumlah_ecer_masuk">
                <span class="label" id="label_jumlah_ecer_masuk">Tambahan Sachet Masuk</span>
                <input type="number" id="stok_gudang_ecer" name="stok_gudang_ecer" class="app-input mt-1 text-lg font-bold text-orange-300" placeholder="0" min="0" value="0">
                <span class="text-xs text-slate-500 mt-1 block" id="helper_stok_total">
                    Total tersimpan: 0 Sachet.
                </span>
            </label>

            <label class="block">
                <span class="label" id="label_harga_beli">Harga Modal per Renceng *</span>
                <input type="number" name="harga_beli" class="app-input mt-1" placeholder="0" min="0" required>
                <span class="text-xs text-slate-500 mt-1 block">Jika berbeda dari sebelumnya, otomatis masuk riwayat harga beli.</span>
            </label>

            <label class="block">
                <span class="label" id="label_harga_jual">Harga Jual per Renceng *</span>
                <input type="number" name="harga_jual" class="app-input mt-1 text-lg font-bold text-orange-300" placeholder="0" min="0" required>
                <span class="text-xs text-slate-500 mt-1 block">Jika berbeda dari sebelumnya, otomatis masuk riwayat harga jual.</span>
            </label>

            <label class="block hidden" id="blok_harga_ecer">
                <span class="label" id="label_harga_ecer">Harga Jual per Sachet</span>
                <input type="number" id="harga_ecer" name="harga_ecer" class="app-input mt-1 text-orange-300 font-bold" placeholder="0" min="0">
                <span class="text-xs text-slate-500 mt-1 block">Jika berbeda dari sebelumnya, otomatis masuk riwayat harga ecer.</span>
            </label>

            <label class="md:col-span-2 block" id="blok_foto_barang">
                <span class="label">Foto Fisik Barang, opsional</span>
                <input type="file" id="foto_barang" name="foto_barang" accept="image/*" class="mt-1 w-full text-sm text-slate-400 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-slate-800 file:text-orange-300 hover:file:bg-slate-700 cursor-pointer p-2 border border-slate-700 rounded-xl bg-slate-950">
            </label>
        </div>
    </div>

    <?php if (!$is_lanjut_nota && $pembelian_allowed): ?>
        <div class="form-section">
            <div class="mb-4">
                <h3 class="text-lg font-bold text-orange-400">Opsi Pembelian / Nota Supplier</h3>
                <p class="text-sm text-slate-400">Bagian ini opsional dan ditaruh di bawah agar input barang tetap cepat.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mb-4">
                <label class="flex items-start gap-2 cursor-pointer bg-slate-950 rounded-xl p-3 border border-slate-800">
                    <input type="radio" name="opsi_nota" value="cepat" checked onchange="toggleNotaSupplier()" class="mt-1">
                    <span><b>Tambah 1 Barang Saja</b><br><span class="text-sm text-slate-400">Input cepat tanpa data nota supplier.</span></span>
                </label>

                <label class="flex items-start gap-2 cursor-pointer bg-slate-950 rounded-xl p-3 border border-orange-700/50">
                    <input type="radio" name="opsi_nota" value="nota" onchange="toggleNotaSupplier()" class="mt-1">
                    <span><b>Tambah Banyak dalam 1 Nota</b><br><span class="text-sm text-slate-400">Untuk input beberapa barang dari satu nota/faktur supplier.</span></span>
                </label>
            </div>

            <div id="blok_nota_supplier" class="hidden space-y-4">
                <div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mb-3">
                        <label class="flex items-center gap-2 cursor-pointer bg-slate-950 rounded-xl p-3 border border-slate-800">
                            <input type="radio" name="opsi_supplier" value="lama" checked onchange="toggleSupplier()">
                            <span>Pilih Supplier Lama</span>
                        </label>

                        <label class="flex items-center gap-2 cursor-pointer bg-slate-950 rounded-xl p-3 border border-slate-800 text-orange-300">
                            <input type="radio" name="opsi_supplier" value="baru" onchange="toggleSupplier()">
                            <span>+ Supplier Baru</span>
                        </label>
                    </div>

                    <div id="div_supplier_lama">
                        <select name="supplier_id" class="app-select">
                            <option value="">-- Kosongkan jika tidak ada --</option>
                            <?php foreach ($suppliers as $sup): ?>
                                <option value="<?= (int)$sup['id'] ?>"><?= h($sup['nama_supplier']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div id="div_supplier_baru" class="hidden grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <input type="text" id="nama_sup_baru" name="nama_supplier_baru" placeholder="Nama toko grosir..." class="app-input">
                        <input type="text" name="wa_supplier_baru" placeholder="Nomor WA, opsional" class="app-input">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <input type="text" name="nomor_nota" class="app-input" placeholder="Nomor Nota / Faktur...">
                    <input type="date" name="tanggal" value="<?= date('Y-m-d') ?>" class="app-input">

                    <label class="sm:col-span-2 block">
                        <span class="label">Foto Lembar Nota, opsional</span>
                        <input type="file" name="foto_nota" accept="image/*" class="mt-1 w-full text-sm text-slate-400 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-slate-800 file:text-orange-300 hover:file:bg-slate-700 cursor-pointer p-2 border border-slate-700 rounded-xl bg-slate-950">
                    </label>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <button type="submit" name="aksi" value="tambah" class="btn btn-secondary">Simpan & Tambah Lagi</button>
        <button type="submit" name="aksi" value="selesai" class="btn btn-primary">Simpan & Selesai</button>
    </div>
</form>

<div id="modal_kamera" class="scanner-modal hidden">
    <div class="w-full max-w-sm flex justify-between items-center p-2 mb-1">
        <h3 id="judul_scanner" class="text-white font-bold text-sm">Scanner Barcode</h3>
        <button type="button" onclick="tutupKamera()" class="btn btn-secondary !w-auto !px-3">Tutup</button>
    </div>

    <div class="scanner-box">
        <div id="reader" class="w-full h-full"></div>
    </div>

    <p id="helper_scanner" class="text-xs text-slate-400 text-center mt-3 max-w-sm">
        Arahkan kamera ke barcode sampai terbaca otomatis.
    </p>
</div>

<script>
function satuanUtama() {
    return (document.getElementById('satuan').value || 'Satuan Utama').trim();
}

function satuanEcer() {
    return (document.getElementById('satuan_ecer').value || 'Ecer').trim();
}

function updateLabelSatuan() {
    const utama = satuanUtama();
    const ecer = satuanEcer();

    document.getElementById('label_jumlah_masuk').textContent = 'Jumlah ' + utama + ' Masuk *';
    document.getElementById('label_jumlah_ecer_masuk').textContent = 'Tambahan ' + ecer + ' Masuk';
    document.getElementById('label_harga_beli').textContent = 'Harga Modal per ' + utama + ' *';
    document.getElementById('label_harga_jual').textContent = 'Harga Jual per ' + utama + ' *';
    document.getElementById('label_isi_per_satuan').textContent = 'Isi per ' + utama;
    document.getElementById('label_harga_ecer').textContent = 'Harga Jual per ' + ecer;
    document.getElementById('helper_tipe_utama').textContent = 'Contoh: stok hanya dihitung per ' + utama + '.';
    document.getElementById('helper_tipe_ecer').textContent = 'Contoh: 1 ' + utama + ' isi 12 ' + ecer + '.';
    document.getElementById('helper_stok').textContent = 'Contoh: beli 10 ' + utama + ' dan 3 ' + ecer + '. Sistem menyimpan stok dalam ' + ecer + '.';
    updatePreviewStokMasuk();
}

function toggleNotaSupplier() {
    const checked = document.querySelector('input[name="opsi_nota"]:checked');
    const mode = checked ? checked.value : 'cepat';

    document.getElementById('mode_input').value = mode;

    const blok = document.getElementById('blok_nota_supplier');
    if (blok) {
        blok.classList.toggle('hidden', mode !== 'nota');
    }
}

function toggleSupplier() {
    const checked = document.querySelector('input[name="opsi_supplier"]:checked');
    if (!checked) return;

    const opsi = checked.value;

    document.getElementById('div_supplier_lama')?.classList.toggle('hidden', opsi === 'baru');
    document.getElementById('div_supplier_baru')?.classList.toggle('hidden', opsi === 'lama');
}

function setTipeSatuan(tipe) {
    const check = tipe === 'ecer';
    const bisaDiecer = document.getElementById('bisa_diecer');
    if (bisaDiecer) bisaDiecer.checked = check;

    document.querySelectorAll('input[name="tipe_satuan"]').forEach(function (radio) {
        radio.checked = radio.value === tipe;
    });

    toggleFormEceran();
    updateLabelSatuan();
}

function toggleFormEceran() {
    const check = document.getElementById('bisa_diecer').checked;
    document.getElementById('blok_eceran').classList.toggle('hidden', !check);
    document.getElementById('blok_jumlah_ecer_masuk').classList.toggle('hidden', !check);
    document.getElementById('blok_harga_ecer').classList.toggle('hidden', !check);

    if (!check) {
        document.getElementById('stok_gudang_ecer').value = '0';
    }

    updatePreviewStokMasuk();
}

function updatePreviewStokMasuk() {
    const utama = satuanUtama();
    const ecer = satuanEcer();
    const isi = Math.max(1, parseInt(document.getElementById('isi_per_kemasan').value || 1, 10));
    const jumlahUtama = Math.max(0, parseInt(document.getElementById('stok_gudang').value || 0, 10));
    const jumlahEcer = document.getElementById('bisa_diecer').checked
        ? Math.max(0, parseInt(document.getElementById('stok_gudang_ecer').value || 0, 10))
        : 0;
    const totalKecil = (jumlahUtama * isi) + jumlahEcer;

    const helper = document.getElementById('helper_stok_total');
    if (helper) {
        helper.textContent = 'Total tersimpan: ' + formatNumberId(totalKecil) + ' ' + ecer + ' (' + formatStokBertingkat(totalKecil, utama, isi, ecer) + ').';
    }
}

function normalizeCariResponse(data) {
    if (Array.isArray(data)) return data;
    if (data && data.status === 'pilihan' && Array.isArray(data.data)) return data.data;
    if (data && data.status === 'sukses' && data.data) return [data.data];
    return [];
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

function cariBarang(keyword) {
    const results = document.getElementById('search_results');

    if (keyword.length < 2) {
        results.classList.add('hidden');
        results.innerHTML = '';
        return;
    }

    results.classList.remove('hidden');
    results.innerHTML = '<li class="p-3 text-sm text-slate-400">Mencari barang...</li>';

    fetch('api_cari.php?keyword=' + encodeURIComponent(keyword))
        .then(res => res.json())
        .then(data => {
            const items = normalizeCariResponse(data);
            results.innerHTML = '';

            if (items.length === 0) {
                results.innerHTML = '<li class="p-3 text-sm text-slate-400">Barang tidak ditemukan. Klik Lanjut Barang Baru untuk input barang baru.</li>';
                return;
            }

            items.forEach(item => {
                const payload = JSON.stringify(item).replace(/"/g, '&quot;');
                const nama = item.nama_barang || '-';
                const barcode = item.barcode || item.kode_barang || '';
                const ecer = item.barcode_eceran ? ' | Ecer: ' + item.barcode_eceran : '';
                const stok = item.stok_gudang !== undefined
                    ? ' | Stok: ' + formatStokBertingkat(item.stok_gudang, item.satuan || '', item.isi_per_kemasan || 1, item.satuan_ecer || '')
                    : '';

                results.innerHTML += `
                    <li
                        class="p-3 hover:bg-slate-800 cursor-pointer text-sm text-white border-b border-slate-800 last:border-b-0"
                        onclick="isiForm(${payload})"
                    >
                        <div class="font-bold">${escapeHtml(nama)}</div>
                        <div class="text-xs text-slate-500">${escapeHtml(barcode + ecer + stok)}</div>
                        <div class="text-xs text-orange-300 mt-1">Pilih barang ini, lalu isi jumlah dan harga terbaru.</div>
                    </li>
                `;
            });
        })
        .catch(() => {
            results.innerHTML = '<li class="p-3 text-sm text-red-300">Gagal mencari barang.</li>';
        });
}

function setModeBarangLama(isLama) {
    const info = document.getElementById('info_barang_lama');
    const fotoBlok = document.getElementById('blok_foto_barang');
    const fotoInput = document.getElementById('foto_barang');
    const btnScanUtama = document.getElementById('btn_scan_utama');
    const btnScanEcer = document.getElementById('btn_scan_ecer');

    if (info) info.classList.toggle('hidden', !isLama);

    document.querySelectorAll('.master-field').forEach(function (el) {
        if (isLama) {
            el.setAttribute('readonly', 'readonly');
            el.classList.add('field-readonly');
        } else {
            el.removeAttribute('readonly');
            el.classList.remove('field-readonly');
        }
    });

    if (fotoBlok) fotoBlok.classList.toggle('hidden', isLama);
    if (fotoInput) fotoInput.disabled = isLama;
    if (btnScanUtama) btnScanUtama.disabled = isLama;
    if (btnScanEcer) btnScanEcer.disabled = isLama;
}

function resetFormBarangBaru(kode) {
    document.getElementById('barang_id').value = '';
    setModeBarangLama(false);

    document.getElementById('barcode').value = kode || '';
    document.getElementById('barcode_eceran').value = '';
    document.getElementById('kode_barang').value = '';
    document.getElementById('input_nama_barang').value = '';
    document.querySelector('input[name="kategori"]').value = '';
    document.getElementById('satuan').value = 'Renceng';
    document.querySelector('input[name="stok_gudang"]').value = '';
    document.getElementById('stok_gudang_ecer').value = '0';
    document.querySelector('input[name="harga_beli"]').value = '';
    document.querySelector('input[name="harga_jual"]').value = '';
    document.getElementById('bisa_diecer').checked = false;
    document.getElementById('isi_per_kemasan').value = '12';
    document.getElementById('satuan_ecer').value = 'Sachet';
    document.getElementById('harga_ecer').value = '';

    setTipeSatuan('utama');
    updateLabelSatuan();
    updatePreviewStokMasuk();
}

function tampilkanFormBarang() {
    const kode = document.getElementById('search_input').value.trim();

    resetFormBarangBaru(kode);

    document.getElementById('blok_isian_barang').classList.remove('hidden');

    const namaInput = document.getElementById('input_nama_barang');
    if (namaInput) namaInput.focus();
}

function enterKodeBarang(event) {
    if (event.key === 'Enter') {
        event.preventDefault();
        tampilkanFormBarang();
    }
}

function isiForm(item) {
    document.getElementById('barang_id').value = item.id || '';
    document.getElementById('kode_barang').value = item.kode_barang || '';
    document.getElementById('barcode').value = item.barcode || '';
    document.getElementById('barcode_eceran').value = item.barcode_eceran || '';
    document.getElementById('input_nama_barang').value = item.nama_barang || '';

    const kategori = document.querySelector('input[name="kategori"]');
    const satuan = document.querySelector('input[name="satuan"]');
    const hargaBeli = document.querySelector('input[name="harga_beli"]');
    const hargaJual = document.querySelector('input[name="harga_jual"]');
    const stokGudang = document.querySelector('input[name="stok_gudang"]');

    if (kategori) kategori.value = item.kategori || '';
    if (satuan) satuan.value = item.satuan || 'Pcs';
    if (hargaBeli) hargaBeli.value = item.harga_beli || '';
    if (hargaJual) hargaJual.value = item.harga_jual || '';
    if (stokGudang) stokGudang.value = '';

    const punyaEcer = parseInt(item.isi_per_kemasan || 1) > 1 || item.harga_ecer || item.barcode_eceran || item.satuan_ecer;

    if (punyaEcer) {
        setTipeSatuan('ecer');

        document.getElementById('isi_per_kemasan').value = item.isi_per_kemasan || 1;
        document.getElementById('satuan_ecer').value = item.satuan_ecer || 'Pcs';
        document.getElementById('harga_ecer').value = item.harga_ecer || '';
    } else {
        setTipeSatuan('utama');
        document.getElementById('harga_ecer').value = '';
    }

    updateLabelSatuan();
    setModeBarangLama(true);

    document.getElementById('search_results').classList.add('hidden');
    document.getElementById('blok_isian_barang').classList.remove('hidden');

    tampilAlertRingan('Barang sudah ada. Isi jumlah dan harga terbaru saja.', 'info', 'Barang Ditemukan');

    if (stokGudang) {
        setTimeout(function () {
            stokGudang.focus();
        }, 250);
    }
}

let html5QrCode = null;
let scanTarget = 'cari';
let scanSedangProses = false;

function bukaKamera(target = 'cari') {
    scanTarget = target;
    scanSedangProses = false;

    const modal = document.getElementById('modal_kamera');
    const judul = document.getElementById('judul_scanner');
    const helper = document.getElementById('helper_scanner');

    if (scanTarget === 'ecer') {
        if (judul) judul.textContent = 'Scan Barcode Ecer';
        if (helper) helper.textContent = 'Arahkan kamera ke barcode ecer. Hasil scan akan masuk ke field Barcode Ecer.';
    } else if (scanTarget === 'utama') {
        if (judul) judul.textContent = 'Scan Barcode Satuan Utama';
        if (helper) helper.textContent = 'Arahkan kamera ke barcode satuan utama. Hasil scan akan masuk ke field Barcode / Kode Satuan Utama.';
    } else {
        if (judul) judul.textContent = 'Scan Barcode Barang';
        if (helper) helper.textContent = 'Arahkan kamera ke barcode. Sistem akan mencari barang atau membuka form barang baru.';
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
        tampilAlertRingan('Kamera gagal dibuka. Pastikan izin kamera sudah diberikan.', 'error', 'Kamera Error');
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
        document.getElementById('bisa_diecer').checked = true;
        toggleFormEceran();

        document.getElementById('barcode_eceran').value = text;
        document.getElementById('blok_isian_barang').classList.remove('hidden');

        tutupKamera();

        setTimeout(function () {
            const inputEcer = document.getElementById('barcode_eceran');
            if (inputEcer) inputEcer.focus();

            tampilAlertRingan('Barcode ecer berhasil discan.', 'success', 'Berhasil');
        }, 250);

        return;
    }

    if (scanTarget === 'utama') {
        document.getElementById('barcode').value = text;
        document.getElementById('search_input').value = text;
        document.getElementById('blok_isian_barang').classList.remove('hidden');

        tutupKamera();

        setTimeout(function () {
            const inputBarcode = document.getElementById('barcode');
            if (inputBarcode) inputBarcode.focus();

            tampilAlertRingan('Barcode satuan utama berhasil discan.', 'success', 'Berhasil');
        }, 250);

        return;
    }

    document.getElementById('search_input').value = text;
    document.getElementById('barcode').value = text;

    tutupKamera();

    cariBarang(text);

    setTimeout(function () {
        const firstResult = document.querySelector('#search_results li');

        if (firstResult && !firstResult.textContent.includes('tidak ditemukan')) {
            firstResult.click();
        } else {
            tampilkanFormBarang();
        }
    }, 500);
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

function tampilAlertRingan(text, icon = 'success', title = 'Info') {
    if (window.AppUI) {
        AppUI.alert(text, icon, title);
        return;
    }

    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: title,
            text: text,
            icon: icon,
            timer: icon === 'info' ? 1800 : 1300,
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

function escapeHtml(str) {
    return String(str || '').replace(/[&<>"']/g, function (m) {
        return ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        })[m];
    });
}

document.addEventListener('DOMContentLoaded', function () {
    setTipeSatuan(document.getElementById('bisa_diecer').checked ? 'ecer' : 'utama');
    toggleNotaSupplier();
    setModeBarangLama(false);

    ['stok_gudang', 'stok_gudang_ecer', 'isi_per_kemasan'].forEach(function (id) {
        const el = document.getElementById(id);
        if (el) el.addEventListener('input', updatePreviewStokMasuk);
    });
});
</script>

<?php ui_footer(); ?>

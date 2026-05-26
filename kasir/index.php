<?php
// kasir/index.php
session_start();

require_once '../config/database.php';
require_once '../includes/ui.php';

if (!isset($_SESSION['tenant_id'], $_SESSION['user_id'], $_SESSION['role'])) {
    header('Location: ../auth/login.php');
    exit;
}

if (!ui_is_role(['Owner', 'Admin', 'Kasir'])) {
    die('Akses ditolak.');
}

$tenant_id = (int) $_SESSION['tenant_id'];

$stmt_pelanggan = $pdo->prepare("
    SELECT id, nama_pelanggan, no_wa
    FROM pelanggan
    WHERE tenant_id = ?
      AND status = 'Aktif'
    ORDER BY nama_pelanggan ASC
");
$stmt_pelanggan->execute([$tenant_id]);
$pelanggan_list = $stmt_pelanggan->fetchAll(PDO::FETCH_ASSOC);

ui_head('Kasir');
ui_nav($pdo, 'Kasir');
?>

<script src="https://unpkg.com/html5-qrcode"></script>

<style>
    input[type="text"],
    input[type="number"],
    select,
    textarea {
        font-size: 16px;
    }

    .kasir-sticky-total {
        position: sticky;
        bottom: 0;
        z-index: 10;
        background: rgba(15, 23, 42, .98);
        backdrop-filter: blur(10px);
    }

    @media (min-width: 1024px) {
        .kasir-sticky-total {
            position: static;
        }
    }

    body.kasir-camera-open {
        overflow: hidden !important;
        height: 100dvh !important;
        touch-action: none;
    }

    #modal_kamera {
        overscroll-behavior: none;
    }

    #reader {
        width: 100%;
        height: 100%;
        background: #000;
    }

    #reader video {
        width: 100% !important;
        height: 100% !important;
        object-fit: cover !important;
        border-radius: 0 !important;
    }

    #reader__dashboard_section,
    #reader__scan_region {
        border: none !important;
    }

    #reader__dashboard {
        display: none !important;
    }
</style>

<div class="mb-5">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div>
            <h2 class="text-2xl font-bold text-orange-400">Kasir Penjualan</h2>
            <p class="text-slate-400">
                Mobile otomatis membuka kamera. Desktop bisa klik tombol Scan atau ketik nama/kode barang manual.
            </p>
        </div>

        <a href="../piutang/index.php" class="btn btn-warning">
            Bayar Hutang / Piutang
        </a>
    </div>
</div>

<div class="grid lg:grid-cols-[1fr_380px] gap-4 items-start">
    <div class="space-y-4 min-w-0">
        <div class="app-card p-3 md:p-4 relative z-20">
            <div class="grid grid-cols-[1fr_auto] gap-2">
                <div class="relative min-w-0">
                    <input type="text"
                           id="input_barcode"
                           class="app-input font-mono"
                           placeholder="Ketik nama barang / barcode / kode manual..."
                           autocomplete="off">

                    <div id="dropdown_live"
                         class="absolute left-0 right-0 mt-2 bg-slate-900 border border-slate-700 rounded-2xl shadow-2xl overflow-hidden hidden max-h-72 overflow-y-auto z-50">
                        <ul id="list_live_search" class="divide-y divide-slate-800"></ul>
                    </div>
                </div>

                <button onclick="bukaKamera(false)"
                        class="btn btn-primary !w-auto !px-4"
                        type="button"
                        title="Scan barcode">
                    <svg xmlns="http://www.w3.org/2000/svg"
                         width="22"
                         height="22"
                         viewBox="0 0 24 24"
                         fill="none"
                         stroke="currentColor"
                         stroke-width="2"
                         stroke-linecap="round"
                         stroke-linejoin="round"
                         aria-hidden="true">
                        <path d="M3 7V5a2 2 0 0 1 2-2h2"/>
                        <path d="M17 3h2a2 2 0 0 1 2 2v2"/>
                        <path d="M21 17v2a2 2 0 0 1-2 2h-2"/>
                        <path d="M7 21H5a2 2 0 0 1-2-2v-2"/>
                        <path d="M7 8v8"/>
                        <path d="M10 8v8"/>
                        <path d="M14 8v8"/>
                        <path d="M17 8v8"/>
                    </svg>
                    <span class="hidden sm:inline">Scan</span>
                </button>
            </div>

            <div class="text-xs text-slate-500 mt-2">
                Scanner fisik juga bisa dipakai langsung di kolom ini.
            </div>

            <div id="pesan_notif" class="text-xs font-bold mt-2 hidden"></div>
        </div>

        <div class="app-card overflow-hidden">
            <div class="p-4 border-b border-slate-800 flex justify-between items-center">
                <h3 class="font-bold text-lg text-orange-400">Keranjang</h3>

                <button onclick="kosongkanKeranjang()"
                        type="button"
                        class="btn btn-danger !w-auto !py-2 !px-3 text-sm">
                    Batal
                </button>
            </div>

            <div id="body_keranjang" class="p-3 mobile-card-list min-h-[220px]">
                <div class="text-center py-8 text-slate-500 italic text-sm">
                    Keranjang kosong.
                </div>
            </div>
        </div>
    </div>

    <div class="kasir-sticky-total app-card overflow-hidden">
        <div class="p-4 md:p-5 bg-orange-950/30 border-b border-slate-800 text-center">
            <p class="text-slate-400 font-medium text-sm">Total Tagihan</p>
            <h2 id="teks_total" class="text-3xl md:text-4xl font-extrabold text-orange-400">Rp 0</h2>
        </div>

        <div class="p-4 md:p-5">
            <form id="form_transaksi"
                  action="proses_transaksi.php"
                  method="POST"
                  class="space-y-4"
                  data-loading-text="Memproses transaksi...">

                <input type="hidden" name="data_keranjang" id="data_keranjang_input">
                <input type="hidden" name="total_tagihan" id="total_tagihan_input" value="0">

                <label class="block">
                    <span class="label">Metode Pembayaran</span>
                    <select name="metode_bayar"
                            id="metode_bayar"
                            onchange="ubahMetodeBayar()"
                            class="app-select mt-1">
                        <option value="Tunai">Tunai</option>
                        <option value="QRIS">QRIS</option>
                        <option value="Transfer">Transfer</option>
                        <option value="Hutang">Hutang</option>
                    </select>

                    <span id="catatan_metode_bayar" class="block text-xs text-slate-500 mt-1">
                        Tunai: input nominal uang yang diterima.
                    </span>
                </label>

                <label class="block">
                    <span id="label_nominal_bayar" class="label">Bayar (Rp)</span>
                    <input type="number"
                           id="input_bayar"
                           name="nominal_bayar"
                           class="app-input mt-1 text-right text-xl font-bold"
                           placeholder="0"
                           min="0"
                           onkeyup="hitungKembalian()"
                           onchange="hitungKembalian()">
                </label>

                <div id="panel_piutang"
                     class="hidden bg-amber-950/40 border border-amber-700/50 rounded-2xl p-4 space-y-3">
                    <div>
                        <div class="font-bold text-amber-300">Data Pelanggan Piutang</div>
                        <div class="text-xs text-slate-400">Pilih pelanggan lama atau isi pelanggan baru.</div>
                    </div>

                    <select name="pelanggan_id" id="pelanggan_id" class="app-select">
                        <option value="">-- Pilih pelanggan lama / isi pelanggan baru --</option>
                        <?php foreach ($pelanggan_list as $p): ?>
                            <option value="<?= (int) $p['id'] ?>">
                                <?= h($p['nama_pelanggan'] . (!empty($p['no_wa']) ? ' - ' . $p['no_wa'] : '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <input type="text"
                           name="pelanggan_nama"
                           id="pelanggan_nama"
                           class="app-input"
                           placeholder="Nama pelanggan baru">

                    <input type="text"
                           name="pelanggan_wa"
                           id="pelanggan_wa"
                           class="app-input"
                           placeholder="No. WA pelanggan, opsional">

                    <label class="block">
                        <span class="label">Jatuh tempo, opsional</span>
                        <input type="date"
                               name="jatuh_tempo"
                               id="jatuh_tempo"
                               class="app-input mt-1">
                    </label>

                    <textarea name="catatan_piutang"
                              id="catatan_piutang"
                              rows="2"
                              class="app-textarea"
                              placeholder="Catatan piutang, opsional"></textarea>
                </div>

                <div id="teks_kembalian"
                     class="text-center text-lg font-bold text-white bg-slate-950 p-3 rounded-xl">
                    Kembali: Rp 0
                </div>

                <button type="button"
                        onclick="prosesBayar()"
                        class="btn btn-primary w-full text-lg">
                    Proses Bayar
                </button>
            </form>
        </div>
    </div>
</div>

<div id="modal_qty"
     class="fixed inset-0 z-50 bg-black/70 flex justify-center items-center hidden p-3 backdrop-blur-sm">
    <div class="bg-slate-900 border border-slate-700 rounded-3xl p-4 w-full max-w-sm shadow-2xl text-center transform transition-all scale-95"
         id="modal_qty_content">

        <h3 id="popup_nama_barang" class="text-lg font-bold text-white leading-tight">Barang</h3>
        <p id="popup_harga_barang" class="text-orange-400 font-bold text-base mb-3">Rp 0</p>

        <div id="wrapper_opsi_satuan"
             class="mb-3 text-left bg-slate-950 p-3 rounded-2xl border border-slate-700 hidden">
            <label class="block text-xs text-slate-400 mb-2">Pilih satuan jual:</label>

            <div class="grid gap-2 text-sm font-medium">
                <label class="flex items-center gap-2 cursor-pointer bg-slate-900 rounded-xl p-2">
                    <input type="radio"
                           name="radio_satuan"
                           id="satuan_besar_radio"
                           value="kemasan"
                           checked
                           onchange="updatePopupHarga()">
                    <span id="label_satuan_besar" class="text-white">Satuan Utama</span>
                </label>

                <label class="flex items-center gap-2 cursor-pointer text-amber-300 bg-slate-900 rounded-xl p-2">
                    <input type="radio"
                           name="radio_satuan"
                           id="satuan_kecil_radio"
                           value="eceran"
                           onchange="updatePopupHarga()">
                    <span id="label_satuan_kecil">Eceran</span>
                </label>
            </div>
        </div>

        <div class="flex justify-center items-center gap-3 mb-3">
            <button onclick="kurangQty()"
                    type="button"
                    class="bg-slate-700 text-white w-12 h-12 rounded-xl text-xl font-bold">-</button>

            <input type="number"
                   id="popup_input_qty"
                   class="w-20 h-12 text-center bg-slate-950 text-2xl font-bold rounded-xl border border-slate-600 focus:border-orange-500 outline-none"
                   value="1"
                   min="1">

            <button onclick="tambahQty()"
                    type="button"
                    class="bg-orange-600 text-white w-12 h-12 rounded-xl text-xl font-bold">+</button>
        </div>

        <p id="popup_stok_info" class="text-xs text-slate-500 mb-3">Sisa stok: 0</p>

        <div class="grid grid-cols-3 gap-2">
            <button onclick="tutupModalQty()"
                    type="button"
                    class="col-span-1 py-3 bg-slate-700 rounded-xl font-bold text-white">
                Batal
            </button>

            <button onclick="konfirmasiQty()"
                    type="button"
                    class="col-span-2 py-3 bg-orange-600 rounded-xl font-bold text-white">
                Masuk Keranjang
            </button>
        </div>
    </div>
</div>

<div id="modal_kamera"
     class="fixed inset-0 z-[9999] bg-black hidden overflow-hidden">

    <div class="absolute inset-0">
        <div id="reader" class="w-full h-full"></div>
    </div>

    <div class="absolute top-0 left-0 right-0 z-20 bg-gradient-to-b from-black/90 to-transparent p-4">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h3 class="text-white font-bold text-lg">Scan Barang</h3>
                <p class="text-xs text-slate-300">
                    Arahkan kamera ke barcode. Setelah barang masuk, kamera akan terbuka lagi di mobile.
                </p>
            </div>

            <button onclick="tutupKamera(true)"
                    type="button"
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
        <div class="w-[78vw] max-w-[420px] h-[28vh] max-h-[220px] border-4 border-orange-500 rounded-3xl shadow-[0_0_0_9999px_rgba(0,0,0,.45)]"></div>
    </div>

    <div class="absolute bottom-0 left-0 right-0 z-20 bg-gradient-to-t from-black/95 to-transparent p-4">
        <div class="grid grid-cols-2 gap-3 max-w-md mx-auto">
            <button type="button"
                    onclick="tutupKamera(true)"
                    class="btn btn-secondary">
                Input Manual
            </button>

            <button type="button"
                    onclick="restartKamera()"
                    class="btn btn-primary">
                Ulang Scan
            </button>
        </div>

        <p class="text-center text-xs text-slate-300 mt-3">
            Untuk menyelesaikan transaksi, tekan Input Manual lalu klik Proses Bayar.
        </p>
    </div>
</div>

<script>
let keranjang = [];
let barangSementara = null;
let hargaAktif = 0;
let timeoutLiveSearch = null;

let html5QrCode = null;
let kameraSedangAktif = false;
let autoCameraTried = false;
let modeScanBerulang = true;
let sedangProsesBayar = false;

const inputBarcode = document.getElementById('input_barcode');
const notif = document.getElementById('pesan_notif');
const dropdownLive = document.getElementById('dropdown_live');
const listLive = document.getElementById('list_live_search');

window.addEventListener('load', function () {
    if (!isMobileKasir()) {
        inputBarcode.focus();
    } else {
        inputBarcode.blur();
    }

    bukaKameraOtomatis();
});

function isMobileKasir() {
    const ua = navigator.userAgent || navigator.vendor || window.opera;

    const byUserAgent = /android|iphone|ipad|ipod|blackberry|iemobile|opera mini/i.test(ua);
    const byScreen = window.matchMedia && window.matchMedia('(max-width: 768px)').matches;
    const byTouch = ('ontouchstart' in window) || navigator.maxTouchPoints > 0;

    return byUserAgent || (byScreen && byTouch);
}

function appAlert(message, icon = 'info', title = 'Informasi') {
    if (window.AppUI) {
        AppUI.alert(message, icon, title);
    } else {
        alert(message);
    }
}

function appConfirm(message, callback, title = 'Konfirmasi') {
    if (window.AppUI) {
        AppUI.confirm(message, callback, title);
    } else if (confirm(message)) {
        callback();
    }
}

function normalizeResponse(res) {
    if (res && res.status === 'sukses') return [res.data];
    if (res && res.status === 'pilihan' && Array.isArray(res.data)) return res.data;
    if (Array.isArray(res)) return res;
    return [];
}

inputBarcode.addEventListener('input', function () {
    clearTimeout(timeoutLiveSearch);

    let keyword = this.value.trim();

    if (keyword.length < 2) {
        dropdownLive.classList.add('hidden');
        return;
    }

    timeoutLiveSearch = setTimeout(function () {
        dropdownLive.classList.remove('hidden');
        listLive.innerHTML = '<li class="p-3 text-center text-slate-400 text-xs">Mencari...</li>';

        fetch('api_cari.php?keyword=' + encodeURIComponent(keyword))
            .then(res => res.json())
            .then(res => {
                listLive.innerHTML = '';

                let dataArray = normalizeResponse(res);

                if (dataArray.length > 0) {
                    dataArray.forEach(item => {
                        let jsonItem = JSON.stringify(item).replace(/"/g, '&quot;');
                        let nama = item.nama_barang || '-';
                        let stokUtama = item.stok_maksimal_utama !== undefined ? item.stok_maksimal_utama : item.stok_maksimal;
                        let stokEcer = item.stok_maksimal_ecer !== undefined ? item.stok_maksimal_ecer : item.stok_gudang;
                        let harga = item.harga || item.harga_jual || 0;
                        let satuanInfo = item.satuan || '';
                        let satuanEcer = item.satuan_ecer || satuanInfo || 'Pcs';
                        let stokTampil = formatStokBertingkat(item.stok_gudang || stokEcer, satuanInfo, item.isi_per_kemasan || 1, satuanEcer);
                        let modeInfo = item.mode_jual === 'eceran' ? 'Ecer' : (item.mode_jual === 'pilihan' ? 'Pilihan' : 'Utama');

                        listLive.innerHTML += `
                            <li class="p-3 hover:bg-slate-800 cursor-pointer flex justify-between items-center gap-3"
                                onclick="pilihDariLiveSearch(${jsonItem})">
                                <div class="min-w-0">
                                    <div class="font-bold text-white text-sm truncate">${escapeHtml(nama)}</div>
                                    <div class="text-xs text-slate-400">
                                        ${modeInfo} ${escapeHtml(satuanInfo)} &middot; Stok: ${escapeHtml(stokTampil)}
                                    </div>
                                </div>
                                <div class="text-orange-400 font-bold text-sm whitespace-nowrap">
                                    Rp ${formatRupiah(parseFloat(harga))}
                                </div>
                            </li>
                        `;
                    });
                } else {
                    listLive.innerHTML = '<li class="p-3 text-center text-slate-400 text-xs italic">Tidak ditemukan</li>';
                }
            })
            .catch(function () {
                listLive.innerHTML = '<li class="p-3 text-center text-red-400 text-xs">Koneksi terputus</li>';
            });
    }, 300);
});

document.addEventListener('click', function (event) {
    if (!inputBarcode.contains(event.target) && !dropdownLive.contains(event.target)) {
        dropdownLive.classList.add('hidden');
    }
});

inputBarcode.addEventListener('keypress', function (e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        clearTimeout(timeoutLiveSearch);
        dropdownLive.classList.add('hidden');

        let kode = e.target.value.trim();

        if (kode !== '') {
            prosesKodeManual(kode);
        }
    }
});

function prosesKodeManual(kode) {
    fetch('api_cari.php?keyword=' + encodeURIComponent(kode))
        .then(res => res.json())
        .then(res => {
            inputBarcode.value = '';

            let dataArray = normalizeResponse(res);

            if (dataArray.length > 0) {
                bukaModalQty(normalizeItem(dataArray[0]));
            } else {
                appAlert('Barang tidak ditemukan. Silakan cek barcode/kode atau cari nama barang.', 'warning', 'Barang Tidak Ditemukan');
                inputBarcode.value = kode;
                inputBarcode.focus();
                inputBarcode.select();
            }
        })
        .catch(function () {
            appAlert('Gagal mencari barang. Periksa koneksi lalu coba lagi.', 'error', 'Koneksi Error');

            if (!isMobileKasir()) {
                inputBarcode.focus();
            }
        });
}

function pilihDariLiveSearch(item) {
    dropdownLive.classList.add('hidden');
    inputBarcode.value = '';
    bukaModalQty(normalizeItem(item));
}

function normalizeItem(item) {
    let modeJual = item.mode_jual || 'utama';

    const isiPerKemasan = Math.max(1, parseInt(item.isi_per_kemasan || 1));
    const stokGudang = parseInt(item.stok_gudang || 0);

    const stokMaksimalUtama = item.stok_maksimal_utama !== undefined
        ? parseInt(item.stok_maksimal_utama || 0)
        : (isiPerKemasan > 1 ? Math.floor(stokGudang / isiPerKemasan) : stokGudang);

    const stokMaksimalEcer = item.stok_maksimal_ecer !== undefined
        ? parseInt(item.stok_maksimal_ecer || 0)
        : stokGudang;

    return {
        id: item.id,
        nama_barang: item.nama_barang,

        harga: parseFloat(item.harga || item.harga_jual || 0),
        harga_jual: parseFloat(item.harga_jual || item.harga || 0),
        harga_ecer: item.harga_ecer !== null && item.harga_ecer !== undefined && item.harga_ecer !== ''
            ? parseFloat(item.harga_ecer)
            : null,

        // stok_gudang selalu stok satuan terkecil, contoh: sachet/pcs
        stok_gudang: stokGudang,

        // stok maksimal untuk jual satuan utama, contoh: renceng/dus/karton
        stok_maksimal_utama: stokMaksimalUtama,

        // stok maksimal untuk jual ecer, contoh: sachet/pcs
        stok_maksimal_ecer: stokMaksimalEcer,

        satuan: item.satuan || 'Pcs',
        satuan_ecer: item.satuan_ecer || 'Pcs',
        isi_per_kemasan: isiPerKemasan,
        mode_jual: modeJual
    };
}

function bukaModalQty(item) {
    barangSementara = item;

    document.getElementById('popup_nama_barang').innerText = item.nama_barang;
    document.getElementById('popup_input_qty').value = 1;

    const wrapperSatuan = document.getElementById('wrapper_opsi_satuan');
    const bisaPilihSatuan = item.isi_per_kemasan > 1 && item.harga_ecer !== null;

    if (bisaPilihSatuan) {
        wrapperSatuan.classList.remove('hidden');

        document.getElementById('label_satuan_besar').innerText =
            'Jual per ' + (item.satuan || 'Satuan Utama') +
            ' - Rp ' + formatRupiah(item.harga_jual) +
            ' | Stok besar: ' + item.stok_maksimal_utama + ' ' + (item.satuan || '');

        document.getElementById('label_satuan_kecil').innerText =
            'Jual per ' + (item.satuan_ecer || 'Ecer') +
            ' - Rp ' + formatRupiah(item.harga_ecer) +
            ' | Stok: ' + item.stok_maksimal_ecer + ' ' + (item.satuan_ecer || '');

        if (item.mode_jual === 'eceran') {
            document.getElementById('satuan_kecil_radio').checked = true;
        } else {
            document.getElementById('satuan_besar_radio').checked = true;
        }
    } else {
        wrapperSatuan.classList.add('hidden');
        document.getElementById('satuan_besar_radio').checked = true;
    }

    updatePopupHarga();

    document.getElementById('modal_qty').classList.remove('hidden');

    setTimeout(function () {
        document.getElementById('modal_qty_content').classList.remove('scale-95');
        document.getElementById('modal_qty_content').classList.add('scale-100');
        document.getElementById('popup_input_qty').focus();
        document.getElementById('popup_input_qty').select();
    }, 10);
}

function updatePopupHarga() {
    const wrapper = document.getElementById('wrapper_opsi_satuan');
    const stokInfo = document.getElementById('popup_stok_info');

    const pilihEcer =
        !wrapper.classList.contains('hidden') &&
        document.getElementById('satuan_kecil_radio').checked;

    if (pilihEcer) {
        hargaAktif = barangSementara.harga_ecer;

        stokInfo.innerText =
            'Sisa stok: ' +
            barangSementara.stok_maksimal_ecer +
            ' ' +
            (barangSementara.satuan_ecer || 'Pcs');

    } else {
        hargaAktif = barangSementara.harga_jual || barangSementara.harga;

        stokInfo.innerText =
            'Sisa stok: ' +
            formatStokBertingkat(
                barangSementara.stok_gudang,
                barangSementara.satuan || 'Pcs',
                barangSementara.isi_per_kemasan || 1,
                barangSementara.satuan_ecer || barangSementara.satuan || 'Pcs'
            );
    }

    document.getElementById('popup_harga_barang').innerText =
        'Rp ' + formatRupiah(hargaAktif);
}

function tutupModalQty() {
    document.getElementById('modal_qty_content').classList.remove('scale-100');
    document.getElementById('modal_qty_content').classList.add('scale-95');

    setTimeout(function () {
        document.getElementById('modal_qty').classList.add('hidden');
    }, 150);

    if (!isMobileKasir()) {
        inputBarcode.focus();
    }
}

function tambahQty() {
    document.getElementById('popup_input_qty').value++;
}

function kurangQty() {
    let v = parseInt(document.getElementById('popup_input_qty').value) || 1;
    if (v > 1) document.getElementById('popup_input_qty').value = v - 1;
}

document.getElementById('popup_input_qty').addEventListener('keypress', function (e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        konfirmasiQty();
    }
});

function konfirmasiQty() {
    let qtyInput = parseInt(document.getElementById('popup_input_qty').value) || 1;

    if (qtyInput <= 0) {
        appAlert('Qty harus lebih dari 0.', 'warning', 'Qty Tidak Valid');
        return;
    }

    const wrapper = document.getElementById('wrapper_opsi_satuan');

    let tipeSatuan = 'eceran';
    let labelTampil = barangSementara.satuan_ecer || barangSementara.satuan || 'Pcs';
    let stokMaksimalTampil = barangSementara.stok_maksimal_ecer;
    let qtyPotongStok = qtyInput;

    const pilihKemasan =
        !wrapper.classList.contains('hidden') &&
        document.getElementById('satuan_besar_radio').checked;

    if (pilihKemasan) {
        tipeSatuan = 'kemasan';
        labelTampil = barangSementara.satuan || 'Satuan Utama';
        stokMaksimalTampil = barangSementara.stok_maksimal_utama;

        // stok di database disimpan dalam satuan terkecil
        qtyPotongStok = qtyInput * barangSementara.isi_per_kemasan;
    }

    if (qtyInput > stokMaksimalTampil) {
        appAlert(
            'Stok tidak mencukupi. Sisa stok: ' + stokMaksimalTampil + ' ' + labelTampil,
            'warning',
            'Stok Tidak Cukup'
        );
        return;
    }

    keranjang.push({
        id: barangSementara.id,
        nama_barang: barangSementara.nama_barang,
        harga: hargaAktif,
        qty: qtyInput,

        // tampil di keranjang
        satuan_tampil: labelTampil,
        tipe_satuan: tipeSatuan,
        isi_per_kemasan: barangSementara.isi_per_kemasan,

        // penting untuk proses transaksi supaya stok dipotong dalam satuan terkecil
        qty_potong_stok: qtyPotongStok
    });

    renderKeranjang();
    tutupModalQty();

    setTimeout(function () {
        if (modeScanBerulang && !sedangProsesBayar && isMobileKasir()) {
            bukaKamera(false);
        } else if (!isMobileKasir()) {
            inputBarcode.focus();
        }
    }, 350);
}

function renderKeranjang() {
    let total = 0;
    let html = '';

    keranjang.forEach(function (item, i) {
        let sub = item.harga * item.qty;
        total += sub;

        let infoPotong = '';
        if (item.tipe_satuan === 'kemasan' && item.isi_per_kemasan > 1) {
            infoPotong = `<div class="text-[11px] text-slate-500 mt-1">Potong stok: ${item.qty_potong_stok} satuan kecil</div>`;
        }

        html += `
            <div class="bg-slate-950 border border-slate-800 rounded-2xl p-3">
                <div class="flex justify-between gap-3">
                    <div class="min-w-0">
                        <div class="font-bold text-white truncate">${escapeHtml(item.nama_barang)}</div>
                        <div class="text-xs text-orange-400">Rp ${formatRupiah(item.harga)} / ${escapeHtml(item.satuan_tampil)}</div>
                        ${infoPotong}
                    </div>

                    <button onclick="hapus(${i})"
                            type="button"
                            class="text-red-400 bg-red-950/50 rounded-xl px-3"
                            title="Hapus item">
                        <svg xmlns="http://www.w3.org/2000/svg"
                             width="18"
                             height="18"
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

                <div class="grid grid-cols-3 gap-2 mt-3">
                    <div class="bg-slate-900 rounded-xl p-2">
                        <div class="label">Qty</div>
                        <div class="value">${item.qty}</div>
                    </div>

                    <div class="bg-slate-900 rounded-xl p-2">
                        <div class="label">Satuan</div>
                        <div class="value">${escapeHtml(item.satuan_tampil)}</div>
                    </div>

                    <div class="bg-slate-900 rounded-xl p-2 text-right">
                        <div class="label">Subtotal</div>
                        <div class="value">Rp ${formatRupiah(sub)}</div>
                    </div>
                </div>
            </div>
        `;
    });

    document.getElementById('body_keranjang').innerHTML =
        html || '<div class="text-center py-8 text-slate-500 italic text-sm">Keranjang kosong.</div>';

    document.getElementById('teks_total').innerText = 'Rp ' + formatRupiah(total);
    document.getElementById('total_tagihan_input').value = total;
    document.getElementById('data_keranjang_input').value = JSON.stringify(keranjang);

    ubahMetodeBayar();
}

function hapus(i) {
    keranjang.splice(i, 1);
    renderKeranjang();

    if (!isMobileKasir()) {
        inputBarcode.focus();
    }
}

function kosongkanKeranjang() {
    if (keranjang.length === 0) {
        if (!isMobileKasir()) {
            inputBarcode.focus();
        }
        return;
    }

    appConfirm('Kosongkan keranjang?', function () {
        modeScanBerulang = false;
        tutupKamera(false);

        keranjang = [];
        renderKeranjang();

        setTimeout(function () {
            modeScanBerulang = true;

            if (!isMobileKasir()) {
                inputBarcode.focus();
            }
        }, 300);
    });
}

function getTotalTagihan() {
    return parseFloat(document.getElementById('total_tagihan_input').value) || 0;
}

function ubahMetodeBayar() {
    const metode = document.getElementById('metode_bayar').value;
    const inputBayar = document.getElementById('input_bayar');
    const labelBayar = document.getElementById('label_nominal_bayar');
    const catatan = document.getElementById('catatan_metode_bayar');
    const total = getTotalTagihan();
    const panelPiutang = document.getElementById('panel_piutang');

    panelPiutang.classList.add('hidden');
    inputBayar.readOnly = false;
    inputBayar.classList.remove('opacity-70', 'cursor-not-allowed');
    labelBayar.innerText = 'Bayar (Rp)';

    if (metode === 'Tunai') {
        catatan.innerText = 'Tunai: input nominal uang yang diterima.';
        if (!inputBayar.value || parseFloat(inputBayar.value) === 0) inputBayar.value = '';
    } else if (metode === 'QRIS' || metode === 'Transfer') {
        catatan.innerText = metode + ': nominal otomatis sama dengan total tagihan.';
        inputBayar.value = total;
        inputBayar.readOnly = true;
        inputBayar.classList.add('opacity-70', 'cursor-not-allowed');
    } else if (metode === 'Hutang') {
        catatan.innerText = 'Hutang: pilih pelanggan lama atau isi pelanggan baru. Sisa tagihan otomatis masuk data piutang.';
        panelPiutang.classList.remove('hidden');
        labelBayar.innerText = 'Dibayar Sekarang (Rp)';
        inputBayar.value = 0;
    }

    hitungKembalian();
}

function hitungKembalian() {
    const metode = document.getElementById('metode_bayar').value;
    const total = getTotalTagihan();
    const bayar = parseFloat(document.getElementById('input_bayar').value) || 0;
    const el = document.getElementById('teks_kembalian');

    if (metode === 'Hutang') {
        const sisa = Math.max(total - bayar, 0);
        el.innerText = 'Hutang: Rp ' + formatRupiah(sisa);
        el.className = 'text-center text-lg font-bold text-amber-300 bg-slate-950 p-3 rounded-xl';
        return;
    }

    let kembali = bayar - total;

    if (kembali < 0) {
        el.innerText = 'Kurang: Rp ' + formatRupiah(Math.abs(kembali));
        el.className = 'text-center text-lg font-bold text-red-300 bg-slate-950 p-3 rounded-xl';
    } else {
        el.innerText = 'Kembali: Rp ' + formatRupiah(kembali);
        el.className = 'text-center text-lg font-bold text-emerald-300 bg-slate-950 p-3 rounded-xl';
    }
}

function prosesBayar() {
    sedangProsesBayar = true;
    modeScanBerulang = false;
    tutupKamera(false);

    if (keranjang.length === 0) {
        sedangProsesBayar = false;
        modeScanBerulang = true;

        appAlert('Keranjang masih kosong.', 'warning', 'Belum Ada Barang');

        if (!isMobileKasir()) {
            inputBarcode.focus();
        }

        return;
    }

    const metode = document.getElementById('metode_bayar').value;
    const total = getTotalTagihan();
    const bayar = parseFloat(document.getElementById('input_bayar').value) || 0;

    if (metode !== 'Hutang' && bayar < total) {
        sedangProsesBayar = false;
        modeScanBerulang = true;

        appAlert('Nominal bayar masih kurang dari total tagihan.', 'warning', 'Pembayaran Kurang');
        document.getElementById('input_bayar').focus();
        return;
    }

    if (metode === 'Hutang') {
        const pelangganId = document.getElementById('pelanggan_id').value;
        const pelangganNama = document.getElementById('pelanggan_nama').value.trim();

        if (!pelangganId && pelangganNama.length < 2) {
            sedangProsesBayar = false;
            modeScanBerulang = true;

            appAlert('Untuk transaksi hutang, pilih pelanggan lama atau isi nama pelanggan baru.', 'warning', 'Data Pelanggan Kurang');
            document.getElementById('pelanggan_nama').focus();
            return;
        }

        appConfirm('Transaksi akan disimpan sebagai HUTANG dan dicatat ke data piutang. Lanjutkan?', function () {
            document.getElementById('form_transaksi').submit();
        });

        return;
    }

    document.getElementById('form_transaksi').submit();
}

function formatRupiah(angka) {
    angka = Math.round(Number(angka) || 0);
    return angka.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
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

function prosesKodeScan(text) {
    text = (text || '').trim();

    if (!text) {
        if (!isMobileKasir()) {
            inputBarcode.focus();
        }
        return;
    }

    inputBarcode.value = text;

    fetch('api_cari.php?keyword=' + encodeURIComponent(text))
        .then(res => res.json())
        .then(res => {
            inputBarcode.value = '';

            let dataArray = normalizeResponse(res);

            if (dataArray.length > 0) {
                bukaModalQty(normalizeItem(dataArray[0]));
            } else {
                appAlert(
                    'Barang tidak ditemukan. Silakan ketik nama barang atau kode manual.',
                    'warning',
                    'Barang Tidak Ditemukan'
                );

                inputBarcode.value = text;

                if (!isMobileKasir()) {
                    inputBarcode.focus();
                    inputBarcode.select();
                }
            }
        })
        .catch(function () {
            appAlert('Gagal mencari barang. Periksa koneksi lalu coba lagi.', 'error', 'Koneksi Error');

            if (!isMobileKasir()) {
                inputBarcode.focus();
            }
        });
}

function bukaKamera(auto = false) {
    const modal = document.getElementById('modal_kamera');

    if (sedangProsesBayar) {
        return;
    }

    inputBarcode.blur();

    modal.classList.remove('hidden');
    document.body.classList.add('kasir-camera-open');

    if (!html5QrCode) {
        html5QrCode = new Html5Qrcode('reader');
    }

    if (kameraSedangAktif) {
        return;
    }

    html5QrCode.start(
        { facingMode: 'environment' },
        {
            fps: 18,
            qrbox: function(viewfinderWidth, viewfinderHeight) {
                const width = Math.floor(viewfinderWidth * 0.78);
                const height = Math.floor(Math.min(viewfinderHeight * 0.28, 220));

                return {
                    width: Math.max(width, 240),
                    height: Math.max(height, 120)
                };
            },
            aspectRatio: 1.7777778
        },
        function (text) {
            if (!text) return;

            tutupKamera(false);
            prosesKodeScan(text);
        }
    ).then(function () {
        kameraSedangAktif = true;
    }).catch(function () {
        kameraSedangAktif = false;
        modal.classList.add('hidden');
        document.body.classList.remove('kasir-camera-open');

        if (!isMobileKasir()) {
            inputBarcode.focus();
        }

        if (!auto) {
            appAlert(
                'Kamera gagal dibuka. Pastikan izin kamera aktif, lalu coba lagi. Anda tetap bisa mengetik manual.',
                'error',
                'Kamera Error'
            );
        }
    });
}

function tutupKamera(focusManual = true) {
    const modal = document.getElementById('modal_kamera');

    function selesaiTutup() {
        kameraSedangAktif = false;
        modal.classList.add('hidden');
        document.body.classList.remove('kasir-camera-open');

        if (focusManual && !isMobileKasir()) {
            inputBarcode.focus();
        }
    }

    if (html5QrCode && kameraSedangAktif) {
        html5QrCode.stop()
            .then(selesaiTutup)
            .catch(selesaiTutup);
    } else {
        selesaiTutup();
    }
}

function restartKamera() {
    tutupKamera(false);

    setTimeout(function () {
        bukaKamera(false);
    }, 450);
}

function bukaKameraOtomatis() {
    if (autoCameraTried) return;

    autoCameraTried = true;

    if (!isMobileKasir()) {
        inputBarcode.focus();
        return;
    }

    inputBarcode.blur();

    setTimeout(function () {
        if (modeScanBerulang && !sedangProsesBayar) {
            bukaKamera(true);
        }
    }, 600);
}

ubahMetodeBayar();
</script>

<?php ui_footer(); ?>

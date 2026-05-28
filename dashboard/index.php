<?php
// dashboard/index.php
session_start();

require_once '../config/database.php';
require_once '../includes/ui.php';

if (!isset($_SESSION['tenant_id'], $_SESSION['user_id'], $_SESSION['role'])) {
    header('Location: ../auth/login.php');
    exit;
}

$tenant_id = (int) $_SESSION['tenant_id'];

function safe_count($pdo, $sql, $params = []) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function safe_sum($pdo, $sql, $params = []) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (float) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function safe_fetch_all($pdo, $sql, $params = []) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
}

function safe_exists_column($pdo, $table, $column) {
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $stmt->execute([$column]);
        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return false;
    }
}

$has_status_barang = safe_exists_column($pdo, 'barang', 'status_barang');

$where_barang_aktif = $has_status_barang
    ? "tenant_id = ? AND (status_barang IS NULL OR status_barang = 'Aktif')"
    : "tenant_id = ?";

$jumlah_barang = safe_count(
    $pdo,
    "SELECT COUNT(*) FROM barang WHERE $where_barang_aktif",
    [$tenant_id]
);

$stok_habis = safe_count(
    $pdo,
    "SELECT COUNT(*)
     FROM barang
     WHERE $where_barang_aktif
       AND stok_gudang <= 0",
    [$tenant_id]
);

$stok_menipis = safe_count(
    $pdo,
    "SELECT COUNT(*)
     FROM barang
     WHERE $where_barang_aktif
       AND stok_gudang > 0
       AND stok_gudang <= CASE
            WHEN COALESCE(isi_per_kemasan, 1) > 1 THEN COALESCE(isi_per_kemasan, 1)
            ELSE 5
       END",
    [$tenant_id]
);

$barang_habis_list = safe_fetch_all(
    $pdo,
    "SELECT nama_barang,
            kategori,
            satuan,
            satuan_ecer,
            stok_gudang,
            isi_per_kemasan
     FROM barang
     WHERE $where_barang_aktif
       AND stok_gudang <= 0
     ORDER BY nama_barang ASC
     LIMIT 100",
    [$tenant_id]
);

$barang_menipis_list = safe_fetch_all(
    $pdo,
    "SELECT nama_barang,
            kategori,
            satuan,
            satuan_ecer,
            stok_gudang,
            isi_per_kemasan
     FROM barang
     WHERE $where_barang_aktif
       AND stok_gudang > 0
       AND stok_gudang <= CASE
            WHEN COALESCE(isi_per_kemasan, 1) > 1 THEN COALESCE(isi_per_kemasan, 1)
            ELSE 5
       END
     ORDER BY stok_gudang ASC, nama_barang ASC
     LIMIT 100",
    [$tenant_id]
);

foreach ($barang_habis_list as &$barangStokRow) {
    $barangStokRow['stok_tampil'] = ui_format_stok_bertingkat(
        $barangStokRow['stok_gudang'] ?? 0,
        $barangStokRow['satuan'] ?? '',
        $barangStokRow['isi_per_kemasan'] ?? 1,
        $barangStokRow['satuan_ecer'] ?? ''
    );
    $barangStokRow['stok_terkecil_tampil'] = ui_format_stok_terkecil(
        $barangStokRow['stok_gudang'] ?? 0,
        $barangStokRow['satuan'] ?? '',
        $barangStokRow['isi_per_kemasan'] ?? 1,
        $barangStokRow['satuan_ecer'] ?? ''
    );
}
unset($barangStokRow);

foreach ($barang_menipis_list as &$barangStokRow) {
    $barangStokRow['stok_tampil'] = ui_format_stok_bertingkat(
        $barangStokRow['stok_gudang'] ?? 0,
        $barangStokRow['satuan'] ?? '',
        $barangStokRow['isi_per_kemasan'] ?? 1,
        $barangStokRow['satuan_ecer'] ?? ''
    );
    $barangStokRow['stok_terkecil_tampil'] = ui_format_stok_terkecil(
        $barangStokRow['stok_gudang'] ?? 0,
        $barangStokRow['satuan'] ?? '',
        $barangStokRow['isi_per_kemasan'] ?? 1,
        $barangStokRow['satuan_ecer'] ?? ''
    );
}
unset($barangStokRow);

$jumlah_transaksi_hari_ini = safe_count(
    $pdo,
    "SELECT COUNT(*)
     FROM transaksi
     WHERE tenant_id = ?
       AND DATE(tanggal) = CURDATE()",
    [$tenant_id]
);

$omzet_hari_ini = safe_sum(
    $pdo,
    "SELECT COALESCE(SUM(total), 0)
     FROM transaksi
     WHERE tenant_id = ?
       AND DATE(tanggal) = CURDATE()",
    [$tenant_id]
);

$nama_toko = ui_get_tenant_name($pdo);

function icon_svg($name) {
    $icons = [
        'kasir' => '
            <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-orange-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="4" width="18" height="16" rx="2"></rect>
                <path d="M7 8h10"></path>
                <path d="M7 12h10"></path>
                <path d="M7 16h3"></path>
                <path d="M14 16h3"></path>
            </svg>
        ',
        'barang' => '
            <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-orange-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"></path>
                <path d="m3.3 7 8.7 5 8.7-5"></path>
                <path d="M12 22V12"></path>
            </svg>
        ',
        'absensi' => '
            <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-orange-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"></path>
                <circle cx="12" cy="13" r="3"></circle>
            </svg>
        ',
        'wajah' => '
            <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-orange-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="4" width="18" height="16" rx="2"></rect>
                <circle cx="9" cy="10" r="2"></circle>
                <path d="M6.5 16c.8-2 4.2-2 5 0"></path>
                <path d="M14 8h4"></path>
                <path d="M14 12h4"></path>
                <path d="M14 16h3"></path>
            </svg>
        ',
        'gaji' => '
            <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-orange-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="2" y="6" width="20" height="12" rx="2"></rect>
                <circle cx="12" cy="12" r="3"></circle>
                <path d="M6 12h.01"></path>
                <path d="M18 12h.01"></path>
            </svg>
        ',
        'laporan' => '
            <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-orange-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 3v18h18"></path>
                <rect x="7" y="12" width="3" height="5"></rect>
                <rect x="12" y="8" width="3" height="9"></rect>
                <rect x="17" y="5" width="3" height="12"></rect>
            </svg>
        ',
        'piutang' => '
            <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-orange-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="2" y="5" width="20" height="14" rx="2"></rect>
                <path d="M2 10h20"></path>
                <path d="M7 15h3"></path>
                <path d="M14 15h3"></path>
            </svg>
        ',
        'karyawan' => '
            <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-orange-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
            </svg>
        ',
        'diskon' => '
            <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-orange-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 12V7a2 2 0 0 0-2-2h-5L5 13l6 6 9-7Z"></path>
                <path d="M16 8h.01"></path>
                <path d="m9 15 6-6"></path>
                <path d="M9.5 10.5h.01M14.5 14.5h.01"></path>
            </svg>
        ',
    ];

    return $icons[$name] ?? '';
}

function menu_card($href, $icon, $title, $desc) {
    echo '
    <a href="'.h($href).'" class="mobile-card hover:border-orange-500 transition">
        <div class="mb-2">'.icon_svg($icon).'</div>
        <div class="text-xl font-bold text-white">'.h($title).'</div>
        <p class="text-slate-400 text-sm mt-1">'.h($desc).'</p>
    </a>';
}

ui_head('Dashboard', true);
ui_nav($pdo, 'Dashboard');
?>

<div class="space-y-6">

    <div class="app-card p-4 md:p-6 overflow-hidden">
        <div class="grid lg:grid-cols-[1fr_320px] gap-5 items-center">
            <div>
                <div class="inline-flex items-center px-3 py-1 rounded-full bg-orange-950/60 border border-orange-700/50 text-orange-300 text-xs font-bold mb-4">
                    Dashboard Toko
                </div>

                <h2 class="text-2xl md:text-4xl font-black text-white leading-tight">
                    Selamat Datang
                </h2>

                <p class="text-sm text-slate-500 mt-2">
                    <span class="text-slate-300 font-semibold"><?= h($nama_toko) ?></span>
                </p>
            </div>

            <div class="hidden lg:flex justify-center">
                <img src="<?= h(asset_url('app/logo-full.png')) ?>?v=<?= h(APP_VERSION) ?>"
                     alt="<?= h(APP_NAME) ?>"
                     class="w-[260px] h-auto object-contain drop-shadow-xl">
            </div>
        </div>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">
        <div class="stat-card">
            <div class="label">Jumlah Barang</div>
            <div class="text-2xl font-extrabold text-white">
                <?= number_format($jumlah_barang, 0, ',', '.') ?>
            </div>
        </div>

        <button type="button"
                onclick="showStokPopup('habis')"
                class="stat-card text-left hover:border-red-500 transition cursor-pointer">
            <div class="label">Stok Habis</div>
            <div class="text-2xl font-extrabold <?= $stok_habis > 0 ? 'text-red-300' : 'text-white' ?>">
                <?= number_format($stok_habis, 0, ',', '.') ?>
            </div>
            <div class="text-xs text-slate-500 mt-1">Klik untuk detail</div>
        </button>

        <button type="button"
                onclick="showStokPopup('menipis')"
                class="stat-card text-left hover:border-amber-500 transition cursor-pointer">
            <div class="label">Stok Menipis</div>
            <div class="text-2xl font-extrabold <?= $stok_menipis > 0 ? 'text-amber-300' : 'text-white' ?>">
                <?= number_format($stok_menipis, 0, ',', '.') ?>
            </div>
            <div class="text-xs text-slate-500 mt-1">Klik untuk detail</div>
        </button>

        <div class="stat-card">
            <div class="label">Transaksi Hari Ini</div>
            <div class="text-2xl font-extrabold text-white">
                <?= number_format($jumlah_transaksi_hari_ini, 0, ',', '.') ?>
            </div>
        </div>

        <div class="stat-card">
            <div class="label">Omzet Hari Ini</div>
            <div class="text-xl md:text-2xl font-extrabold text-orange-300">
                <?= rupiah($omzet_hari_ini) ?>
            </div>
        </div>
    </div>

    <div>
        <div class="mb-3">
            <h3 class="text-xl font-bold text-white">Menu Cepat</h3>
            <p class="text-sm text-slate-400">Akses fitur sesuai hak akses akun Anda.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">

            <?php
            if (ui_is_role(['Owner','Admin','Kasir'])) {
                menu_card('../kasir/index.php', 'kasir', 'Kasir', 'Transaksi penjualan dan pembayaran.');
            }

            if (ui_is_role(['Owner','Admin','Gudang'])) {
                menu_card('../barang/index.php', 'barang', 'Barang', 'Kelola barang, stok, barcode, dan pembelian.');
            }

            if (ui_is_role(['Owner','Admin','Kasir'])) {
                menu_card('../piutang/index.php', 'piutang', 'Piutang', 'Kelola hutang pelanggan dan pembayaran piutang.');
            }

            if (ui_is_role(['Owner','Admin','Gudang','Kasir','HRD'])) {
                menu_card('../absensi/index.php', 'absensi', 'Absensi', 'Absen wajah, izin, cuti, sakit, dan tukar shift.');
            }

            if (ui_is_role(['Owner','Admin','Gudang','Kasir','HRD'])) {
                menu_card('../absensi/daftar_wajah.php', 'wajah', 'Rekam Wajah', 'Ambil atau perbarui referensi wajah untuk absensi.');
            }

            if (ui_is_role(['Owner','Admin','HRD'])) {
                menu_card('../gaji/index.php', 'gaji', 'Gaji', 'Payroll karyawan dan slip gaji.');
            }

            if (ui_is_role(['Owner','Admin'])) {
                menu_card('../laporan/index.php', 'laporan', 'Laporan', 'Laporan ringkas dan detail toko.');
            }

            if (ui_is_role(['Owner','Admin'])) {
                menu_card('../pengaturan/diskon.php', 'diskon', 'Diskon', 'Atur promo otomatis berdasarkan belanja, produk, qty, atau metode bayar.');
            }

            if (ui_is_role(['Owner'])) {
                menu_card('../karyawan/index.php', 'karyawan', 'Karyawan', 'Tambah staf, role akses, dan akun karyawan.');
            }
            ?>

        </div>
    </div>

</div>

<script>
const barangHabisList = <?= json_encode($barang_habis_list, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const barangMenipisList = <?= json_encode($barang_menipis_list, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
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
        return `${formatNumberId(stok)} ${ecer}`;
    }

    const jumlahUtama = Math.floor(stok / isi);
    const sisaEcer = stok % isi;
    const parts = [];

    if (jumlahUtama > 0) {
        parts.push(`${formatNumberId(jumlahUtama)} ${utama}`);
    }

    if (sisaEcer > 0 || stok === 0) {
        parts.push(`${formatNumberId(sisaEcer)} ${ecer}`);
    }

    return parts.join(' ');
}

function renderBarangStokList(items, type) {
    if (!items || items.length === 0) {
        return `
            <div class="text-center py-6 text-slate-400">
                Tidak ada barang ${type === 'habis' ? 'yang habis' : 'yang menipis'}.
            </div>
        `;
    }

    let html = '<div class="space-y-2 text-left max-h-[60vh] overflow-y-auto pr-1">';

    items.forEach(function(item) {
        const nama = escapeHtml(item.nama_barang || '-');
        const kategori = escapeHtml(item.kategori || 'Tanpa kategori');
        const isi = Number(item.isi_per_kemasan || 1);
        const batas = isi > 1 ? isi : 5;
        const stokTampil = escapeHtml(item.stok_tampil || formatStokBertingkat(item.stok_gudang || 0, item.satuan || '', isi, item.satuan_ecer || ''));
        const stokTerkecilTampil = escapeHtml(item.stok_terkecil_tampil || `${formatNumberId(item.stok_gudang || 0)} ${item.satuan_ecer || item.satuan || 'Pcs'}`);
        const batasTampil = escapeHtml(formatStokBertingkat(batas, item.satuan || '', isi, item.satuan_ecer || ''));
        const showTotalKecil = isi > 1;

        html += `
            <div class="rounded-2xl border border-slate-700 bg-slate-950 p-3">
                <div class="font-bold text-white">${nama}</div>
                <div class="text-xs text-slate-400 mt-1">${kategori}</div>
                <div class="grid grid-cols-2 gap-2 mt-3 text-xs">
                    <div class="rounded-xl bg-slate-900 p-2">
                        <div class="text-slate-500">Sisa Stok</div>
                        <div class="${type === 'habis' ? 'text-red-300' : 'text-amber-300'} font-extrabold text-base">${stokTampil}</div>
                        ${showTotalKecil ? `<div class="text-[11px] text-slate-500 mt-1">Total kecil: ${stokTerkecilTampil}</div>` : ''}
                    </div>
                    <div class="rounded-xl bg-slate-900 p-2">
                        <div class="text-slate-500">Batas Menipis</div>
                        <div class="text-slate-200 font-bold text-base">${batasTampil}</div>
                    </div>
                </div>
            </div>
        `;
    });

    html += '</div>';

    if (items.length >= 100) {
        html += '<div class="text-xs text-slate-500 text-center mt-3">Menampilkan maksimal 100 barang. Buka menu Barang untuk daftar lengkap.</div>';
    }

    return html;
}

function showStokPopup(type) {
    const isHabis = type === 'habis';
    const items = isHabis ? barangHabisList : barangMenipisList;

    const title = isHabis ? 'Daftar Barang Stok Habis' : 'Daftar Barang Stok Menipis';
    const icon = isHabis ? 'error' : 'warning';

    const html = renderBarangStokList(items, type);

    if (window.Swal) {
        Swal.fire({
            icon: icon,
            title: title,
            html: html,
            width: 720,
            confirmButtonText: 'Tutup',
            confirmButtonColor: isHabis ? '#dc2626' : '#f59e0b',
            background: '#0f172a',
            color: '#e2e8f0'
        });
    } else {
        alert(title + '\\n\\n' + (items && items.length ? items.map(function(item) {
            return '- ' + (item.nama_barang || '-') + ' | Stok: ' + (item.stok_gudang || 0);
        }).join('\\n') : 'Tidak ada data.'));
    }
}
</script>

<?php ui_footer(); ?>

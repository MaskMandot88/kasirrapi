<?php
session_start();

require_once '../config/database.php';
require_once '../includes/ui.php';

if (!isset($_SESSION['tenant_id'], $_SESSION['user_id'], $_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

if (!ui_is_role(['Owner', 'Admin', 'Gudang'])) {
    die('Akses ditolak: Anda tidak memiliki otoritas manajemen barang.');
}

$tenant_id = (int) $_SESSION['tenant_id'];
$q = trim($_GET['q'] ?? '');

// Hanya tampilkan barang aktif.
// Barang yang diarsipkan memiliki is_aktif = 0.
$where = ['tenant_id = ?', 'is_aktif = 1'];
$params = [$tenant_id];

if ($q !== '') {
    $where[] = "(nama_barang LIKE ? OR barcode LIKE ? OR kode_barang LIKE ? OR kategori LIKE ?)";
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like);
}

$whereSql = implode(' AND ', $where);

$stmt = $pdo->prepare("
    SELECT *
    FROM barang
    WHERE $whereSql
    ORDER BY id DESC
    LIMIT 300
");
$stmt->execute($params);
$data_barang = $stmt->fetchAll(PDO::FETCH_ASSOC);

function safe_count_barang($pdo, $sql, $params) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

$total_barang = safe_count_barang($pdo, "
    SELECT COUNT(*)
    FROM barang
    WHERE tenant_id = ?
      AND is_aktif = 1
", [$tenant_id]);

$stok_habis = safe_count_barang($pdo, "
    SELECT COUNT(*)
    FROM barang
    WHERE tenant_id = ?
      AND is_aktif = 1
      AND stok_gudang <= 0
", [$tenant_id]);

$stok_menipis = safe_count_barang($pdo, "
    SELECT COUNT(*)
    FROM barang
    WHERE tenant_id = ?
      AND is_aktif = 1
      AND stok_gudang > 0
      AND (
            (isi_per_kemasan > 1 AND stok_gudang <= isi_per_kemasan)
            OR
            ((isi_per_kemasan IS NULL OR isi_per_kemasan <= 1) AND stok_gudang <= 5)
          )
", [$tenant_id]);

$total_supplier = safe_count_barang($pdo, "
    SELECT COUNT(*)
    FROM suppliers
    WHERE tenant_id = ?
", [$tenant_id]);

$status = $_GET['status'] ?? '';
$alertTitle = '';
$alertText = '';
$alertIcon = '';

if ($status === 'sukses') {
    $alertTitle = 'Berhasil';
    $alertText = 'Katalog gudang telah diperbarui.';
    $alertIcon = 'success';
} elseif ($status === 'arsip') {
    $alertTitle = 'Barang Diarsipkan';
    $alertText = 'Barang berhasil diarsipkan. Riwayat transaksi dan laporan tetap aman.';
    $alertIcon = 'success';
} elseif ($status === 'gagal') {
    $alertTitle = 'Gagal';
    $alertText = 'Proses gagal. Silakan coba lagi.';
    $alertIcon = 'error';
} elseif ($status === 'terpakai') {
    $alertTitle = 'Barang Tidak Dihapus';
    $alertText = 'Barang sudah memiliki riwayat transaksi. Gunakan arsip agar laporan tetap aman.';
    $alertIcon = 'warning';
}

ui_head('Barang & Stok');
ui_nav($pdo, 'Barang & Stok');
?>

<style>
/* Paksa SweetAlert selalu berada di tengah viewport layar yang sedang terlihat */
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
        <h2 class="text-2xl font-bold text-emerald-400">Katalog Barang & Gudang</h2>
        <p class="text-slate-400">Kelola master produk, harga, satuan, foto, dan stok fisik.</p>
    </div>
    <a href="tambah.php" class="btn btn-primary">+ Tambah Produk</a>
</div>

<div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-5">
    <div class="stat-card">
        <div class="label">Total Barang</div>
        <div class="text-2xl font-extrabold"><?= $total_barang ?></div>
    </div>

    <div class="stat-card">
        <div class="label">Stok Habis</div>
        <div class="text-2xl font-extrabold <?= $stok_habis > 0 ? 'text-red-300' : '' ?>">
            <?= $stok_habis ?>
        </div>
    </div>

    <div class="stat-card">
        <div class="label">Stok Menipis</div>
        <div class="text-2xl font-extrabold <?= $stok_menipis > 0 ? 'text-amber-300' : '' ?>">
            <?= $stok_menipis ?>
        </div>
    </div>

    <div class="stat-card">
        <div class="label">Supplier</div>
        <div class="text-2xl font-extrabold"><?= $total_supplier ?></div>
    </div>

    <div class="stat-card">
        <div class="label">Ditampilkan</div>
        <div class="text-2xl font-extrabold"><?= count($data_barang) ?></div>
    </div>
</div>

<form method="GET" class="app-card p-3 mb-5 grid grid-cols-1 md:grid-cols-[1fr_auto] gap-2">
    <input
        type="text"
        name="q"
        value="<?= h($q) ?>"
        class="app-input"
        placeholder="Cari nama, barcode, SKU, atau kategori..."
    >
    <button class="btn btn-secondary">Cari</button>
</form>

<div class="mobile-card-list">
    <?php foreach ($data_barang as $row): ?>
        <?php
            $stok_gudang = (int)($row['stok_gudang'] ?? 0);
            $isi_per_kemasan = (int)($row['isi_per_kemasan'] ?? 0);

            $stok_habis_item = $stok_gudang <= 0;
            $stok_menipis_item = false;

            if (!$stok_habis_item) {
                if ($isi_per_kemasan > 1) {
                    $stok_menipis_item = $stok_gudang <= $isi_per_kemasan;
                } else {
                    $stok_menipis_item = $stok_gudang <= 5;
                }
            }

            $stok_tampil = ui_format_stok_bertingkat($stok_gudang, $row['satuan'] ?? '', $isi_per_kemasan, $row['satuan_ecer'] ?? '');
            $stok_terkecil_tampil = ui_format_stok_terkecil($stok_gudang, $row['satuan'] ?? '', $isi_per_kemasan, $row['satuan_ecer'] ?? '');
            $kode_tampil = $row['barcode'] ?: $row['kode_barang'];
        ?>

        <div class="mobile-card item-barang">
            <div class="flex gap-3">
                <div class="shrink-0">
                    <?php if (!empty($row['foto_barang'])): ?>
                        <img
                            src="../public/uploads/tenant_<?= $tenant_id ?>/barang/<?= h($row['foto_barang']) ?>"
                            class="w-16 h-16 object-cover rounded-xl border border-slate-700"
                            alt="<?= h($row['nama_barang']) ?>"
                        >
                    <?php else: ?>
                        <div class="w-16 h-16 bg-slate-800 rounded-xl flex items-center justify-center text-slate-500">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/>
                                <path d="M3.3 7 12 12l8.7-5"/>
                                <path d="M12 22V12"/>
                            </svg>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="min-w-0 flex-1">
                    <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-2">
                        <div class="min-w-0">
                            <h3 class="text-lg font-bold text-white truncate"><?= h($row['nama_barang']) ?></h3>
                            <p class="text-xs text-emerald-300 font-mono"><?= h($kode_tampil ?: '-') ?></p>
                            <p class="text-xs text-slate-500 mt-1"><?= h($row['kategori'] ?: '-') ?></p>
                        </div>

                        <div class="flex gap-2">
                            <a href="edit.php?id=<?= (int)$row['id'] ?>" class="btn btn-secondary !w-auto !px-3">
                                Edit
                            </a>

                            <button
                                type="button"
                                class="btn btn-danger !w-auto !px-3 btn-arsipkan"
                                data-url="hapus.php?id=<?= (int)$row['id'] ?>"
                                data-nama="<?= h($row['nama_barang']) ?>"
                            >
                                Arsipkan
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 md:grid-cols-4 gap-2 mt-3">
                        <div class="bg-slate-950 rounded-xl p-3">
                            <div class="label">Harga Beli</div>
                            <div class="value"><?= rupiah($row['harga_beli']) ?></div>
                        </div>

                        <div class="bg-slate-950 rounded-xl p-3">
                            <div class="label">Harga Jual</div>
                            <div class="value text-emerald-300"><?= rupiah($row['harga_jual']) ?></div>
                        </div>

                        <div class="bg-slate-950 rounded-xl p-3">
                            <div class="label">Harga Ecer</div>
                            <div class="value">
                                <?= $row['harga_ecer'] !== null ? rupiah($row['harga_ecer']) : '-' ?>
                            </div>
                        </div>

                        <div class="bg-slate-950 rounded-xl p-3">
                            <div class="label">Stok</div>
                            <div class="value <?= $stok_habis_item ? 'text-red-300' : ($stok_menipis_item ? 'text-amber-300' : '') ?>">
                                <?= h($stok_tampil) ?>
                            </div>
                            <?php if ($isi_per_kemasan > 1): ?>
                                <div class="text-xs text-slate-500 mt-1">
                                    Total kecil: <?= h($stok_terkecil_tampil) ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($stok_habis_item): ?>
                                <div class="text-xs text-red-300 mt-1">Stok habis</div>
                            <?php elseif ($stok_menipis_item): ?>
                                <div class="text-xs text-amber-300 mt-1">Stok menipis</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($isi_per_kemasan > 1): ?>
                        <div class="mt-3 text-xs text-amber-300 bg-amber-950/30 border border-amber-700/40 rounded-xl p-2">
                            Konversi stok: 1 <?= h($row['satuan'] ?: 'kemasan') ?> = <?= $isi_per_kemasan ?> <?= h($row['satuan_ecer'] ?: 'satuan') ?>.
                            Stok disimpan dalam satuan terkecil.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <?php if (empty($data_barang)): ?>
        <div class="mobile-card text-center text-slate-500">
            Belum ada data barang aktif.
        </div>
    <?php endif; ?>
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
        allowOutsideClick: false,
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

    document.querySelectorAll('.btn-arsipkan').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const url = this.dataset.url;
            const nama = this.dataset.nama || 'barang ini';

            if (typeof Swal === 'undefined') {
                if (confirm('Arsipkan ' + nama + '? Barang tidak akan tampil di katalog aktif, tetapi riwayat transaksi tetap aman.')) {
                    window.location.href = url;
                }
                return;
            }

            Swal.fire({
                ...swalDarkOptions,
                title: 'Arsipkan Barang?',
                html: 'Barang <b>' + escapeHtml(nama) + '</b> tidak akan tampil di katalog aktif, tetapi riwayat transaksi tetap aman.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Ya, Arsipkan',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#475569',
                reverseButtons: true,
                didOpen: function () {
                    const container = document.querySelector('.swal2-container');

                    if (container) {
                        container.style.position = 'fixed';
                        container.style.inset = '0';
                        container.style.width = '100vw';
                        container.style.height = '100vh';
                        container.style.display = 'flex';
                        container.style.alignItems = 'center';
                        container.style.justifyContent = 'center';
                        container.style.zIndex = '999999';
                        container.style.padding = '16px';
                        container.style.overflow = 'hidden';
                    }

                    const popup = document.querySelector('.swal2-popup');

                    if (popup) {
                        popup.style.margin = '0';
                        popup.style.maxWidth = '92vw';
                        popup.style.borderRadius = '18px';
                    }
                }
            }).then(function (result) {
                if (result.isConfirmed) {
                    window.location.href = url;
                }
            });
        });
    });

    function escapeHtml(str) {
        return String(str).replace(/[&<>"']/g, function (m) {
            return ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            })[m];
        });
    }
});
</script>

<?php ui_footer(); ?>

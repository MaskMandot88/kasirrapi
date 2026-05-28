<?php
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

require_once '../config/database.php';
require_once '../includes/ui.php';
require_once '../includes/discounts.php';

if (!isset($_SESSION['tenant_id'], $_SESSION['user_id'], $_SESSION['role'])) {
    header('Location: ../auth/login.php');
    exit;
}

if (!ui_is_role(['Owner','Admin'])) {
    die('Akses ditolak.');
}

$tenant_id = (int)$_SESSION['tenant_id'];
discounts_ensure_schema($pdo);

function diskon_all($pdo, $sql, $params = []) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
}

function redirect_diskon($message, $type = 'success') {
    $_SESSION['flash'][$type] = $message;
    header('Location: diskon.php');
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $aksi = $_POST['aksi'] ?? 'simpan';
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    if ($aksi === 'hapus' && $id > 0) {
        $stmt = $pdo->prepare("DELETE FROM diskon_rules WHERE id=? AND tenant_id=?");
        $stmt->execute([$id, $tenant_id]);
        redirect_diskon('Aturan diskon dihapus.');
    }

    if ($aksi === 'toggle' && $id > 0) {
        $stmt = $pdo->prepare("UPDATE diskon_rules SET aktif = IF(aktif=1,0,1) WHERE id=? AND tenant_id=?");
        $stmt->execute([$id, $tenant_id]);
        redirect_diskon('Status aturan diskon diperbarui.');
    }

    $nama = trim((string)($_POST['nama_diskon'] ?? ''));
    $kondisi = $_POST['kondisi'] ?? 'minimal_belanja';
    $tipe = $_POST['tipe_diskon'] ?? 'persen';
    $barangId = isset($_POST['barang_id']) && $_POST['barang_id'] !== '' ? (int)$_POST['barang_id'] : null;
    $metodeBayar = $_POST['metode_bayar'] ?? null;
    $minSubtotal = max(0, (float)($_POST['min_subtotal'] ?? 0));
    $minQty = max(0, (int)($_POST['min_qty'] ?? 0));
    $nilai = max(0, (float)($_POST['nilai_diskon'] ?? 0));
    $maxDiskon = max(0, (float)($_POST['max_diskon'] ?? 0));
    $mulai = trim((string)($_POST['mulai'] ?? ''));
    $selesai = trim((string)($_POST['selesai'] ?? ''));
    $prioritas = (int)($_POST['prioritas'] ?? 100);
    $aktif = isset($_POST['aktif']) ? 1 : 0;

    if ($nama === '' || !in_array($kondisi, ['minimal_belanja','produk_tertentu','qty_produk','metode_bayar'], true) || !in_array($tipe, ['persen','nominal'], true)) {
        redirect_diskon('Data aturan diskon belum lengkap.', 'error');
    }
    if ($nilai <= 0) {
        redirect_diskon('Nilai diskon harus lebih dari 0.', 'error');
    }
    if ($tipe === 'persen' && $nilai > 100) {
        redirect_diskon('Diskon persen maksimal 100%.', 'error');
    }
    if (in_array($kondisi, ['produk_tertentu','qty_produk'], true) && !$barangId) {
        redirect_diskon('Pilih produk untuk aturan diskon produk.', 'error');
    }
    if ($kondisi === 'qty_produk' && $minQty <= 0) {
        redirect_diskon('Minimal qty produk wajib diisi.', 'error');
    }
    if ($kondisi === 'metode_bayar' && !in_array($metodeBayar, ['Tunai','QRIS','Transfer','Hutang'], true)) {
        redirect_diskon('Metode bayar tidak valid.', 'error');
    }

    $mulai = $mulai !== '' ? $mulai : null;
    $selesai = $selesai !== '' ? $selesai : null;
    if ($selesai !== null && $mulai !== null && $selesai < $mulai) {
        redirect_diskon('Tanggal selesai tidak boleh sebelum tanggal mulai.', 'error');
    }

    if ($id > 0) {
        $stmt = $pdo->prepare("
            UPDATE diskon_rules
            SET nama_diskon=?, kondisi=?, barang_id=?, metode_bayar=?, min_subtotal=?, min_qty=?, tipe_diskon=?, nilai_diskon=?, max_diskon=?, mulai=?, selesai=?, aktif=?, prioritas=?
            WHERE id=? AND tenant_id=?
        ");
        $stmt->execute([$nama,$kondisi,$barangId,$metodeBayar,$minSubtotal,$minQty,$tipe,$nilai,$maxDiskon,$mulai,$selesai,$aktif,$prioritas,$id,$tenant_id]);
        redirect_diskon('Aturan diskon diperbarui.');
    }

    $stmt = $pdo->prepare("
        INSERT INTO diskon_rules
            (tenant_id, nama_diskon, kondisi, barang_id, metode_bayar, min_subtotal, min_qty, tipe_diskon, nilai_diskon, max_diskon, mulai, selesai, aktif, prioritas)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$tenant_id,$nama,$kondisi,$barangId,$metodeBayar,$minSubtotal,$minQty,$tipe,$nilai,$maxDiskon,$mulai,$selesai,$aktif,$prioritas]);
    redirect_diskon('Aturan diskon ditambahkan.');
}

$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$edit = null;
if ($editId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM diskon_rules WHERE id=? AND tenant_id=?");
    $stmt->execute([$editId, $tenant_id]);
    $edit = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$stmtBarang = $pdo->prepare("SELECT id, nama_barang, kode_barang FROM barang WHERE tenant_id=? AND COALESCE(is_aktif,1)=1 ORDER BY nama_barang ASC LIMIT 500");
$stmtBarang->execute([$tenant_id]);
$barangList = $stmtBarang->fetchAll(PDO::FETCH_ASSOC);

$rules = diskon_all($pdo, "
    SELECT dr.*, b.nama_barang
    FROM diskon_rules dr
    LEFT JOIN barang b ON b.id = dr.barang_id AND b.tenant_id = dr.tenant_id
    WHERE dr.tenant_id=?
    ORDER BY dr.aktif DESC, dr.prioritas ASC, dr.id DESC
", [$tenant_id]);

ui_head('Aturan Diskon');
ui_nav($pdo, 'Diskon');
?>

<div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
    <div>
        <h2 class="text-2xl font-bold text-orange-400">Aturan Diskon</h2>
        <p class="text-slate-400">Diskon otomatis diterapkan di kasir berdasarkan kondisi yang aktif.</p>
    </div>
    <a href="toko.php" class="btn btn-secondary">Pengaturan Toko</a>
</div>

<div class="grid lg:grid-cols-[420px_1fr] gap-4 items-start">
    <form method="POST" class="app-card p-4 space-y-3">
        <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">

        <div>
            <h3 class="font-bold text-lg text-white"><?= $edit ? 'Edit Aturan' : 'Tambah Aturan' ?></h3>
            <p class="text-sm text-slate-500">Contoh: diskon 5% minimal belanja Rp100.000 atau potongan produk tertentu.</p>
        </div>

        <label class="block">
            <span class="label">Nama Diskon</span>
            <input name="nama_diskon" class="app-input mt-1" value="<?= h($edit['nama_diskon'] ?? '') ?>" placeholder="Contoh: Belanja 100rb diskon 5%" required>
        </label>

        <label class="block">
            <span class="label">Kondisi</span>
            <select name="kondisi" id="kondisi_diskon" class="app-select mt-1" onchange="toggleDiskonFields()">
                <?php
                $conditions = [
                    'minimal_belanja' => 'Minimal belanja',
                    'produk_tertentu' => 'Produk tertentu',
                    'qty_produk' => 'Minimal qty produk',
                    'metode_bayar' => 'Metode bayar',
                ];
                foreach ($conditions as $key => $label):
                ?>
                    <option value="<?= h($key) ?>" <?= (($edit['kondisi'] ?? '') === $key) ? 'selected' : '' ?>><?= h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="block field-min-subtotal">
            <span class="label">Minimal Belanja (Rp)</span>
            <input type="number" name="min_subtotal" class="app-input mt-1" min="0" value="<?= h($edit['min_subtotal'] ?? 0) ?>">
        </label>

        <label class="block field-product">
            <span class="label">Produk</span>
            <select name="barang_id" class="app-select mt-1">
                <option value="">-- Pilih produk --</option>
                <?php foreach ($barangList as $barang): ?>
                    <option value="<?= (int)$barang['id'] ?>" <?= (int)($edit['barang_id'] ?? 0) === (int)$barang['id'] ? 'selected' : '' ?>>
                        <?= h($barang['nama_barang'] . (!empty($barang['kode_barang']) ? ' - ' . $barang['kode_barang'] : '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="block field-min-qty">
            <span class="label">Minimal Qty Produk</span>
            <input type="number" name="min_qty" class="app-input mt-1" min="0" value="<?= h($edit['min_qty'] ?? 0) ?>">
        </label>

        <label class="block field-payment">
            <span class="label">Metode Bayar</span>
            <select name="metode_bayar" class="app-select mt-1">
                <?php foreach (['Tunai','QRIS','Transfer','Hutang'] as $method): ?>
                    <option value="<?= h($method) ?>" <?= (($edit['metode_bayar'] ?? '') === $method) ? 'selected' : '' ?>><?= h($method) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <div class="grid grid-cols-2 gap-2">
            <label class="block">
                <span class="label">Tipe Diskon</span>
                <select name="tipe_diskon" class="app-select mt-1">
                    <option value="persen" <?= (($edit['tipe_diskon'] ?? '') === 'persen') ? 'selected' : '' ?>>Persen</option>
                    <option value="nominal" <?= (($edit['tipe_diskon'] ?? '') === 'nominal') ? 'selected' : '' ?>>Nominal</option>
                </select>
            </label>
            <label class="block">
                <span class="label">Nilai</span>
                <input type="number" name="nilai_diskon" class="app-input mt-1" min="0" step="0.01" value="<?= h($edit['nilai_diskon'] ?? 0) ?>" required>
            </label>
        </div>

        <label class="block">
            <span class="label">Maksimal Diskon (Rp, opsional)</span>
            <input type="number" name="max_diskon" class="app-input mt-1" min="0" value="<?= h($edit['max_diskon'] ?? 0) ?>">
        </label>

        <div class="grid grid-cols-2 gap-2">
            <label class="block">
                <span class="label">Mulai</span>
                <input type="date" name="mulai" class="app-input mt-1" value="<?= h($edit['mulai'] ?? '') ?>">
            </label>
            <label class="block">
                <span class="label">Selesai</span>
                <input type="date" name="selesai" class="app-input mt-1" value="<?= h($edit['selesai'] ?? '') ?>">
            </label>
        </div>

        <div class="grid grid-cols-[1fr_auto] gap-2 items-end">
            <label class="block">
                <span class="label">Prioritas</span>
                <input type="number" name="prioritas" class="app-input mt-1" value="<?= h($edit['prioritas'] ?? 100) ?>">
            </label>
            <label class="flex items-center gap-2 bg-slate-950 border border-slate-800 rounded-xl px-3 min-h-[44px]">
                <input type="checkbox" name="aktif" value="1" <?= !isset($edit['aktif']) || (int)$edit['aktif'] === 1 ? 'checked' : '' ?>>
                <span class="font-bold">Aktif</span>
            </label>
        </div>

        <button class="btn btn-primary w-full"><?= $edit ? 'Simpan Perubahan' : 'Tambah Diskon' ?></button>
        <?php if ($edit): ?>
            <a href="diskon.php" class="btn btn-secondary w-full" data-no-loading="1">Batal Edit</a>
        <?php endif; ?>
    </form>

    <div class="app-card overflow-hidden">
        <div class="p-4 border-b border-slate-800">
            <h3 class="font-bold text-lg text-white">Daftar Aturan</h3>
            <p class="text-sm text-slate-500">Aturan aktif akan dihitung otomatis berurutan dari prioritas terkecil.</p>
        </div>
        <div class="overflow-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-950 text-slate-400">
                    <tr>
                        <th class="p-3 text-left">Aturan</th>
                        <th class="p-3 text-left">Kondisi</th>
                        <th class="p-3 text-right">Diskon</th>
                        <th class="p-3 text-center">Status</th>
                        <th class="p-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                <?php foreach ($rules as $rule): ?>
                    <tr>
                        <td class="p-3">
                            <div class="font-bold text-white"><?= h($rule['nama_diskon']) ?></div>
                            <div class="text-xs text-slate-500">Prioritas <?= (int)$rule['prioritas'] ?> &middot; <?= h(($rule['mulai'] ?: 'kapan saja') . ' s/d ' . ($rule['selesai'] ?: 'seterusnya')) ?></div>
                        </td>
                        <td class="p-3">
                            <div class="font-semibold"><?= h($conditions[$rule['kondisi']] ?? $rule['kondisi']) ?></div>
                            <div class="text-xs text-slate-500">
                                <?php if (in_array($rule['kondisi'], ['produk_tertentu','qty_produk'], true)): ?>
                                    <?= h($rule['nama_barang'] ?: 'Produk dihapus') ?><?= $rule['kondisi'] === 'qty_produk' ? ' min ' . (int)$rule['min_qty'] . ' qty' : '' ?>
                                <?php elseif ($rule['kondisi'] === 'metode_bayar'): ?>
                                    <?= h($rule['metode_bayar']) ?><?= (float)$rule['min_subtotal'] > 0 ? ' min Rp ' . number_format($rule['min_subtotal'],0,',','.') : '' ?>
                                <?php else: ?>
                                    Min Rp <?= number_format($rule['min_subtotal'],0,',','.') ?>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="p-3 text-right font-bold">
                            <?= $rule['tipe_diskon'] === 'persen' ? h(number_format($rule['nilai_diskon'],2,',','.')) . '%' : rupiah($rule['nilai_diskon']) ?>
                            <?php if ((float)$rule['max_diskon'] > 0): ?>
                                <div class="text-xs text-slate-500">Max <?= rupiah($rule['max_diskon']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="p-3 text-center">
                            <span class="px-2 py-1 rounded-full text-xs font-bold <?= (int)$rule['aktif'] === 1 ? 'bg-emerald-900 text-emerald-200' : 'bg-slate-800 text-slate-400' ?>">
                                <?= (int)$rule['aktif'] === 1 ? 'Aktif' : 'Nonaktif' ?>
                            </span>
                        </td>
                        <td class="p-3">
                            <div class="flex justify-end gap-2">
                                <a href="?edit=<?= (int)$rule['id'] ?>" class="btn btn-secondary !w-auto !py-2 !px-3 text-xs">Edit</a>
                                <form method="POST">
                                    <input type="hidden" name="id" value="<?= (int)$rule['id'] ?>">
                                    <input type="hidden" name="aksi" value="toggle">
                                    <button class="btn btn-secondary !w-auto !py-2 !px-3 text-xs"><?= (int)$rule['aktif'] === 1 ? 'Matikan' : 'Aktifkan' ?></button>
                                </form>
                                <form method="POST">
                                    <input type="hidden" name="id" value="<?= (int)$rule['id'] ?>">
                                    <input type="hidden" name="aksi" value="hapus">
                                    <button class="btn btn-danger !w-auto !py-2 !px-3 text-xs" data-confirm="Hapus aturan diskon ini?">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rules): ?>
                    <tr><td colspan="5" class="p-6 text-center text-slate-500">Belum ada aturan diskon.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function toggleDiskonFields() {
    const kondisi = document.getElementById('kondisi_diskon').value;
    document.querySelectorAll('.field-product,.field-min-qty,.field-payment,.field-min-subtotal').forEach(el => el.classList.add('hidden'));

    if (kondisi === 'minimal_belanja') {
        document.querySelectorAll('.field-min-subtotal').forEach(el => el.classList.remove('hidden'));
    } else if (kondisi === 'produk_tertentu') {
        document.querySelectorAll('.field-product').forEach(el => el.classList.remove('hidden'));
    } else if (kondisi === 'qty_produk') {
        document.querySelectorAll('.field-product,.field-min-qty').forEach(el => el.classList.remove('hidden'));
    } else if (kondisi === 'metode_bayar') {
        document.querySelectorAll('.field-payment,.field-min-subtotal').forEach(el => el.classList.remove('hidden'));
    }
}
toggleDiskonFields();
</script>

<?php ui_footer(); ?>

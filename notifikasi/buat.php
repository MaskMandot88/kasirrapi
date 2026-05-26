<?php
session_start();
require_once '../config/database.php';
require_once '../includes/ui.php';

if (!isset($_SESSION['tenant_id'], $_SESSION['user_id'], $_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}
if (!ui_is_role(['Owner'])) {
    die('Akses ditolak. Hanya Owner yang bisa membuat pengumuman.');
}

$tenant_id = (int)$_SESSION['tenant_id'];

$stmt = $pdo->prepare("SELECT id, nama, role FROM users WHERE tenant_id = ? ORDER BY nama ASC");
$stmt->execute([$tenant_id]);
$users = $stmt->fetchAll();

ui_head('Buat Pengumuman');
ui_nav($pdo, 'Buat Pengumuman');
?>

<div class="max-w-3xl">
    <div class="mb-5">
        <h2 class="text-2xl font-bold text-emerald-400">Kirim Pemberitahuan</h2>
        <p class="text-slate-400">Owner bisa mengirim pengumuman ke semua user, role tertentu, atau satu user.</p>
    </div>

    <?php if (!empty($_SESSION['flash']['error'])): ?>
        <div class="mb-4 p-3 bg-red-900/50 border border-red-500 rounded-xl"><?= h($_SESSION['flash']['error']); unset($_SESSION['flash']['error']); ?></div>
    <?php endif; ?>

    <form method="POST" action="proses_buat.php" class="app-card p-4 md:p-5 space-y-4">
        <label class="block">
            <span class="label">Judul</span>
            <input name="judul" required maxlength="150" class="app-input mt-1" placeholder="Contoh: Rapat evaluasi toko">
        </label>

        <label class="block">
            <span class="label">Pesan</span>
            <textarea name="pesan" required rows="6" class="app-textarea mt-1" placeholder="Tulis isi pengumuman..."></textarea>
        </label>

        <div class="grid md:grid-cols-2 gap-4">
            <label class="block">
                <span class="label">Target Role</span>
                <select name="target_role" id="targetRole" class="app-select mt-1">
                    <option value="Semua">Semua User</option>
                    <option value="Owner">Owner</option>
                    <option value="Admin">Admin</option>
                    <option value="Gudang">Gudang</option>
                    <option value="Kasir">Kasir</option>
                    <option value="HRD">HRD</option>
                    <option value="User Tertentu">User Tertentu</option>
                </select>
            </label>

            <label class="block" id="userTargetBox" style="display:none">
                <span class="label">Pilih User</span>
                <select name="target_user_id" class="app-select mt-1">
                    <option value="">Pilih user</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= (int)$u['id'] ?>"><?= h($u['nama']) ?> - <?= h($u['role']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>

        <div class="grid md:grid-cols-2 gap-4">
            <label class="block">
                <span class="label">Tipe</span>
                <select name="tipe" class="app-select mt-1">
                    <option value="Pengumuman">Pengumuman</option>
                    <option value="Info">Info</option>
                    <option value="Sistem">Sistem</option>
                    <option value="Absensi">Absensi</option>
                    <option value="Gaji">Gaji</option>
                    <option value="Stok">Stok</option>
                    <option value="Piutang">Piutang</option>
                </select>
            </label>

            <label class="block">
                <span class="label">Prioritas</span>
                <select name="prioritas" class="app-select mt-1">
                    <option value="Normal">Normal</option>
                    <option value="Penting">Penting</option>
                    <option value="Darurat">Darurat</option>
                </select>
            </label>
        </div>

        <label class="block">
            <span class="label">Link opsional</span>
            <input name="link" class="app-input mt-1" placeholder="../absensi/approval.php">
        </label>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
            <a href="index.php" class="btn btn-secondary">Batal</a>
            <button class="btn btn-primary">Kirim Pemberitahuan</button>
        </div>
    </form>
</div>

<script>
document.getElementById('targetRole').addEventListener('change', function(){
    document.getElementById('userTargetBox').style.display = this.value === 'User Tertentu' ? 'block' : 'none';
});
</script>

<?php ui_footer(); ?>

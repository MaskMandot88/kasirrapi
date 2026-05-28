<?php
// superadmin/index.php
require_once __DIR__ . '/_auth.php';
superadmin_require_login();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/tripay.php';
require_once __DIR__ . '/../includes/support.php';
require_once __DIR__ . '/../includes/notifications.php';

tripay_ensure_subscription_columns($pdo);
support_tickets_table($pdo);
app_notifications_ensure_tables($pdo);

function superadmin_h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

if (isset($_GET['ticket_status'], $_GET['ticket_id'])) {
    $status = $_GET['ticket_status'];
    $ticketId = (int)$_GET['ticket_id'];
    if (in_array($status, ['Baru', 'Diproses', 'Selesai'], true) && $ticketId > 0) {
        $stmt = $pdo->prepare("UPDATE support_tickets SET status = ? WHERE id = ?");
        $stmt->execute([$status, $ticketId]);
        header("Location: index.php?pesan=ticket");
        exit;
    }
}

// Menangani Hapus Tenant
if (isset($_GET['hapus'])) {
    $id = $_GET['hapus'];
    // Karena pakai ON DELETE CASCADE, menghapus tenant otomatis menghapus usernya juga
    $stmt = $pdo->prepare("DELETE FROM tenants WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: index.php?pesan=dihapus");
    exit;
}

// Menangani Tambah Tenant + User Baru
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($requestMethod === 'POST' && isset($_POST['kirim_pengumuman_owner'])) {
    $target = $_POST['target_owner'] ?? 'semua';
    $tenantId = (int)($_POST['tenant_id'] ?? 0);
    $ownerUserId = (int)($_POST['owner_user_id'] ?? 0);
    $judul = trim($_POST['judul'] ?? '');
    $pesan = trim($_POST['pesan'] ?? '');
    $prioritas = $_POST['prioritas'] ?? 'Penting';

    if ($judul === '' || $pesan === '') {
        $errorAnnouncement = 'Judul dan pesan pengumuman wajib diisi.';
    } elseif (!in_array($prioritas, ['Normal', 'Penting', 'Darurat'], true)) {
        $errorAnnouncement = 'Prioritas tidak valid.';
    } else {
        try {
            if ($target === 'owner_tertentu') {
                $stmt = $pdo->prepare("
                    SELECT id, tenant_id
                    FROM users
                    WHERE id = ?
                      AND role = 'Owner'
                      AND (? = 0 OR tenant_id = ?)
                    LIMIT 1
                ");
                $stmt->execute([$ownerUserId, $tenantId, $tenantId]);
                $owner = $stmt->fetch();

                if (!$owner) {
                    $errorAnnouncement = 'Owner tujuan tidak valid.';
                } else {
                    app_notification_create($pdo, (int)$owner['tenant_id'], null, (int)$owner['id'], 'Owner', 'Pengumuman', $judul, $pesan, '../notifikasi/index.php', $prioritas);
                    header("Location: index.php?pesan=pengumuman");
                    exit;
                }
            } elseif ($target === 'tenant_tertentu') {
                $stmt = $pdo->prepare("SELECT id FROM tenants WHERE id = ? LIMIT 1");
                $stmt->execute([$tenantId]);
                if (!$stmt->fetch()) {
                    $errorAnnouncement = 'Tenant tujuan tidak valid.';
                } else {
                    app_notification_create($pdo, $tenantId, null, null, 'Owner', 'Pengumuman', $judul, $pesan, '../notifikasi/index.php', $prioritas);
                    header("Location: index.php?pesan=pengumuman");
                    exit;
                }
            } else {
                $stmt = $pdo->query("SELECT id FROM tenants ORDER BY id ASC");
                $count = 0;
                foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
                    app_notification_create($pdo, (int)$id, null, null, 'Owner', 'Pengumuman', $judul, $pesan, '../notifikasi/index.php', $prioritas);
                    $count++;
                }
                header("Location: index.php?pesan=pengumuman&jumlah=" . $count);
                exit;
            }
        } catch (Throwable $e) {
            $errorAnnouncement = 'Gagal mengirim pengumuman: ' . $e->getMessage();
        }
    }
}

if ($requestMethod === 'POST' && isset($_POST['kirim_reminder_masa_aktif'])) {
    try {
        $sentReminder = app_notify_all_owner_expiry_warnings($pdo);
        header("Location: index.php?pesan=reminder&jumlah=" . $sentReminder);
        exit;
    } catch (Throwable $e) {
        $errorAnnouncement = 'Gagal membuat reminder masa aktif: ' . $e->getMessage();
    }
}

if ($requestMethod === 'POST' && isset($_POST['tambah'])) {
    $nama_toko = $_POST['nama_toko'];
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $nama_toko)));
    $nama_pemilik = $_POST['nama_pemilik'];
    $email = $_POST['email'];
    $paket = $_POST['paket_langganan'];
    $plan = kasirrapi_plan($paket);

    // Mengamankan password dengan Hash (BCRYPT) sebelum masuk database
    $password_polos = $_POST['password'];
    $password_hashed = password_hash($password_polos, PASSWORD_BCRYPT);

    try {
        // Mulai database transaction agar jika salah satu gagal, semua dibatalkan
        $pdo->beginTransaction();

        // 1. Masukkan data ke tabel tenants
        $stmt_tenant = $pdo->prepare("
            INSERT INTO tenants (
                nama_toko, slug, nama_pemilik, email, paket_langganan, plan,
                max_users, max_products, max_transactions_per_month,
                addon_hrd_enabled, max_employees, max_outlets
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 1)
        ");
        $stmt_tenant->execute([
            $nama_toko,
            $slug,
            $nama_pemilik,
            $email,
            $paket,
            $paket,
            (int)$plan['max_users'],
            (int)$plan['max_products'],
            (int)$plan['max_transactions_per_month'],
        ]);

        // Ambil ID tenant yang baru saja dibuat
        $tenant_id = $pdo->lastInsertId();

        // 2. Masukkan data ke tabel users sebagai 'Owner'
        $stmt_user = $pdo->prepare("INSERT INTO users (tenant_id, nama, email, password, role) VALUES (?, ?, ?, ?, 'Owner')");
        $stmt_user->execute([$tenant_id, $nama_pemilik, $email, $password_hashed]);

        // Jika semua lancar, simpan permanen ke database
        $pdo->commit();

        header("Location: index.php?pesan=sukses");
        exit;
    } catch (PDOException $e) {
        // Jika ada yang error (misal email kembar), batalkan semua transaksi
        $pdo->rollBack();
        $error = "Gagal mendaftarkan: " . $e->getMessage();
    }
}

// Ambil semua data tenant untuk ditampilkan di tabel bawah
$stmt = $pdo->query("SELECT * FROM tenants ORDER BY FIELD(status, 'Aktif', 'Suspend'), id DESC");
$tenants = $stmt->fetchAll();

$stmt = $pdo->query("
    SELECT u.id, u.tenant_id, u.nama, u.email, t.nama_toko
    FROM users u
    JOIN tenants t ON t.id = u.tenant_id
    WHERE u.role = 'Owner'
    ORDER BY t.nama_toko ASC, u.nama ASC
");
$owners = $stmt->fetchAll();

$ticketBaru = (int)$pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status = 'Baru'")->fetchColumn();
$stmt = $pdo->query("
    SELECT *
    FROM support_tickets
    ORDER BY FIELD(status, 'Baru', 'Diproses', 'Selesai'), created_at DESC
    LIMIT 50
");
$supportTickets = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Super Admin - Kelola Tenant</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f4f7f6; }
        .card { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        th { background-color: #007bff; color: white; }
        input, select, button { padding: 10px; margin: 8px 0; width: 100%; box-sizing: border-box; display: block;}
        textarea { padding: 10px; margin: 8px 0; width: 100%; min-height: 120px; box-sizing: border-box; display: block; resize: vertical; }
        .btn { background: #28a745; color: white; border: none; cursor: pointer; font-weight: bold; }
        .btn-danger { background: #dc3545; color: white; padding: 5px 10px; text-decoration: none; border-radius: 4px;}
        .btn-small { display: inline-block; width: auto; margin: 3px; padding: 6px 9px; border-radius: 4px; color: #fff; text-decoration: none; font-size: 12px; }
        .btn-process { background: #f59e0b; }
        .btn-done { background: #16a34a; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 999px; color: #fff; font-weight: bold; font-size: 12px; }
        .badge-baru { background: #dc3545; }
        .badge-diproses { background: #f59e0b; }
        .badge-selesai { background: #16a34a; }
        .badge-aktif { background: #16a34a; }
        .badge-suspend { background: #64748b; }
        .alert-ticket { background: #fff7ed; border: 1px solid #fdba74; color: #9a3412; padding: 10px 12px; border-radius: 6px; }
        .alert-success { background: #ecfdf5; border: 1px solid #86efac; color: #166534; padding: 10px 12px; border-radius: 6px; }
        .message-preview { max-width: 360px; white-space: normal; line-height: 1.4; }
        .grid-2 { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; align-items: start; }
        @media (max-width: 800px) { .grid-2 { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

    <h1>Super Admin Dashboard</h1>
    <p><a href="logout.php">Logout Super Admin</a></p>
    <?php if(isset($_GET['pesan']) && $_GET['pesan'] == 'pengumuman'): ?>
        <p class="alert-success">Pengumuman owner berhasil dikirim ke <?= (int)($_GET['jumlah'] ?? 1) ?> target.</p>
    <?php endif; ?>
    <?php if(isset($_GET['pesan']) && $_GET['pesan'] == 'reminder'): ?>
        <p class="alert-success">Reminder masa aktif dibuat untuk <?= (int)($_GET['jumlah'] ?? 0) ?> tenant yang memenuhi syarat.</p>
    <?php endif; ?>

    <div class="card" style="max-width: 100%;">
        <h3>Tiket Live Chat Mentok</h3>
        <?php if ($ticketBaru > 0): ?>
            <p class="alert-ticket">Ada <?= $ticketBaru ?> tiket baru dari CS AI yang perlu dicek tim teknis.</p>
        <?php else: ?>
            <p>Tidak ada tiket baru dari live chat.</p>
        <?php endif; ?>

        <table>
            <tr>
                <th>Kode</th>
                <th>Status</th>
                <th>Email / Toko</th>
                <th>CS</th>
                <th>Pesan</th>
                <th>Waktu</th>
                <th>Aksi</th>
            </tr>
            <?php if (!$supportTickets): ?>
                <tr><td colspan="7">Belum ada tiket live chat.</td></tr>
            <?php endif; ?>
            <?php foreach ($supportTickets as $ticket): ?>
            <?php
                $badgeClass = 'badge-' . strtolower((string)$ticket['status']);
                $messageText = (string)$ticket['message'];
                $messageLength = function_exists('mb_strlen') ? mb_strlen($messageText) : strlen($messageText);
                $messagePreview = $messageLength > 180
                    ? (function_exists('mb_substr') ? mb_substr($messageText, 0, 180) : substr($messageText, 0, 180)) . '...'
                    : $messageText;
            ?>
            <tr>
                <td><strong><?= superadmin_h($ticket['ticket_code']) ?></strong></td>
                <td><span class="badge <?= superadmin_h($badgeClass) ?>"><?= superadmin_h($ticket['status']) ?></span></td>
                <td>
                    <?= superadmin_h($ticket['email']) ?><br>
                    <small><?= superadmin_h($ticket['nama_user'] ?: '-') ?> / <?= superadmin_h($ticket['nama_toko'] ?: '-') ?></small>
                </td>
                <td><?= superadmin_h($ticket['cs_name'] ?: '-') ?></td>
                <td class="message-preview"><?= superadmin_h($messagePreview) ?></td>
                <td><?= superadmin_h($ticket['created_at']) ?></td>
                <td>
                    <?php if ($ticket['status'] !== 'Diproses'): ?>
                        <a class="btn-small btn-process" href="index.php?ticket_id=<?= (int)$ticket['id'] ?>&ticket_status=Diproses">Proses</a>
                    <?php endif; ?>
                    <?php if ($ticket['status'] !== 'Selesai'): ?>
                        <a class="btn-small btn-done" href="index.php?ticket_id=<?= (int)$ticket['id'] ?>&ticket_status=Selesai">Selesai</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <div class="grid-2">
    <div class="card">
        <h3>Daftarkan Toko & Owner Baru</h3>
        <?php if(isset($error)) echo "<p style='color:red;'>" . superadmin_h($error) . "</p>"; ?>
        <?php if(isset($_GET['pesan']) && $_GET['pesan'] == 'sukses') echo "<p style='color:green;'>Toko dan Akun Owner berhasil dibuat!</p>"; ?>

        <form method="POST">
            <label>Nama Toko:</label>
            <input type="text" name="nama_toko" placeholder="Contoh: Toko Jaya" required>

            <label>Nama Pemilik (Owner):</label>
            <input type="text" name="nama_pemilik" placeholder="Nama Lengkap" required>

            <label>Email Login:</label>
            <input type="email" name="email" placeholder="Contoh: owner@jayastore.com" required>

            <label>Password Login Owner:</label>
            <input type="password" name="password" placeholder="Minimal 6 karakter" required>

            <label>Paket SaaS:</label>
            <select name="paket_langganan">
                <?php foreach (kasirrapi_plans() as $code => $plan): ?>
                    <option value="<?= superadmin_h($code) ?>"><?= superadmin_h($plan['name']) ?></option>
                <?php endforeach; ?>
            </select>

            <button type="submit" name="tambah" class="btn">Daftarkan Toko & Owner</button>
        </form>
    </div>

    <div class="card">
        <h3>Pengumuman Khusus Owner</h3>
        <?php if(isset($errorAnnouncement)) echo "<p style='color:red;'>" . superadmin_h($errorAnnouncement) . "</p>"; ?>

        <form method="POST">
            <label>Target:</label>
            <select name="target_owner" id="targetOwner">
                <option value="semua">Semua Owner Semua Tenant</option>
                <option value="tenant_tertentu">Owner di Tenant Tertentu</option>
                <option value="owner_tertentu">Owner Tertentu</option>
            </select>

            <label id="tenantTargetLabel">Tenant:</label>
            <select name="tenant_id" id="tenantTarget">
                <option value="0">Pilih tenant</option>
                <?php foreach ($tenants as $t): ?>
                    <option value="<?= (int)$t['id'] ?>"><?= superadmin_h($t['nama_toko']) ?> - <?= superadmin_h($t['email']) ?></option>
                <?php endforeach; ?>
            </select>

            <label id="ownerTargetLabel">Owner:</label>
            <select name="owner_user_id" id="ownerTarget">
                <option value="0">Pilih owner</option>
                <?php foreach ($owners as $owner): ?>
                    <option value="<?= (int)$owner['id'] ?>" data-tenant-id="<?= (int)$owner['tenant_id'] ?>">
                        <?= superadmin_h($owner['nama_toko']) ?> - <?= superadmin_h($owner['nama']) ?> (<?= superadmin_h($owner['email']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>

            <label>Judul:</label>
            <input type="text" name="judul" maxlength="150" placeholder="Contoh: Info perpanjangan layanan">

            <label>Pesan:</label>
            <textarea name="pesan" placeholder="Tulis pengumuman untuk owner..."></textarea>

            <label>Prioritas:</label>
            <select name="prioritas">
                <option value="Penting">Penting</option>
                <option value="Normal">Normal</option>
                <option value="Darurat">Darurat</option>
            </select>

            <button type="submit" name="kirim_pengumuman_owner" class="btn">Kirim Pengumuman Owner</button>
        </form>

        <form method="POST">
            <button type="submit" name="kirim_reminder_masa_aktif" class="btn">Kirim Reminder H-3 Masa Aktif</button>
        </form>
    </div>
    </div>

    <div class="card" style="max-width: 100%;">
        <h3>Daftar Semua Tenant</h3>
        <table>
            <tr>
                <th>ID Tenant</th>
                <th>Nama Toko</th>
                <th>URL Slug</th>
                <th>Pemilik & Email</th>
                <th>Paket</th>
                <th>Status</th>
                <th>Masa Aktif</th>
                <th>Aksi</th>
            </tr>
            <?php foreach ($tenants as $t): ?>
            <?php
                $tenantStatus = $t['status'] ?? '-';
                $statusClass = strtolower((string)$tenantStatus) === 'aktif' ? 'badge-aktif' : 'badge-suspend';
                $expiredAt = !empty($t['plan_expired_at']) ? date('d/m/Y H:i', strtotime($t['plan_expired_at'])) : '-';
            ?>
            <tr>
                <td><?= $t['id'] ?></td>
                <td><?= superadmin_h($t['nama_toko']) ?></td>
                <td><strong>/<?= superadmin_h($t['slug']) ?></strong></td>
                <td><?= superadmin_h($t['nama_pemilik']) ?><br><small><?= superadmin_h($t['email']) ?></small></td>
                <td><?= superadmin_h($t['paket_langganan']) ?><br><small><?= superadmin_h($t['plan'] ?? '-') ?></small></td>
                <td><span class="badge <?= superadmin_h($statusClass) ?>"><?= superadmin_h($tenantStatus) ?></span></td>
                <td><?= superadmin_h($expiredAt) ?></td>
                <td>
                    <a href="index.php?hapus=<?= $t['id'] ?>" class="btn-danger" onclick="return confirm('Hapus tenant ini? Semua data user dan barang toko ini akan ikut terhapus permanen!')">Hapus</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>

<script>
(function(){
    const targetOwner = document.getElementById('targetOwner');
    const tenantTarget = document.getElementById('tenantTarget');
    const ownerTarget = document.getElementById('ownerTarget');
    const tenantLabel = document.getElementById('tenantTargetLabel');
    const ownerLabel = document.getElementById('ownerTargetLabel');
    const ownerOptions = Array.from(ownerTarget.options);

    function syncTargets(){
        const mode = targetOwner.value;
        const showTenant = mode === 'tenant_tertentu' || mode === 'owner_tertentu';
        const showOwner = mode === 'owner_tertentu';
        tenantTarget.style.display = showTenant ? 'block' : 'none';
        tenantLabel.style.display = showTenant ? 'block' : 'none';
        ownerTarget.style.display = showOwner ? 'block' : 'none';
        ownerLabel.style.display = showOwner ? 'block' : 'none';
        filterOwners();
    }

    function filterOwners(){
        const selectedTenant = tenantTarget.value;
        ownerOptions.forEach(function(option){
            const tenantId = option.getAttribute('data-tenant-id');
            option.hidden = selectedTenant !== '0' && tenantId && tenantId !== selectedTenant;
        });
        if (ownerTarget.selectedOptions[0] && ownerTarget.selectedOptions[0].hidden) {
            ownerTarget.value = '0';
        }
    }

    targetOwner.addEventListener('change', syncTargets);
    tenantTarget.addEventListener('change', filterOwners);
    syncTargets();
})();
</script>

</body>
</html>

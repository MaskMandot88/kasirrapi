<?php
// superadmin/index.php
require_once '../config/database.php';
require_once '../includes/tripay.php';
require_once '../includes/support.php';

tripay_ensure_subscription_columns($pdo);
support_tickets_table($pdo);

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
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah'])) {
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
$stmt = $pdo->query("SELECT * FROM tenants ORDER BY id DESC");
$tenants = $stmt->fetchAll();

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
        .btn { background: #28a745; color: white; border: none; cursor: pointer; font-weight: bold; }
        .btn-danger { background: #dc3545; color: white; padding: 5px 10px; text-decoration: none; border-radius: 4px;}
        .btn-small { display: inline-block; width: auto; margin: 3px; padding: 6px 9px; border-radius: 4px; color: #fff; text-decoration: none; font-size: 12px; }
        .btn-process { background: #f59e0b; }
        .btn-done { background: #16a34a; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 999px; color: #fff; font-weight: bold; font-size: 12px; }
        .badge-baru { background: #dc3545; }
        .badge-diproses { background: #f59e0b; }
        .badge-selesai { background: #16a34a; }
        .alert-ticket { background: #fff7ed; border: 1px solid #fdba74; color: #9a3412; padding: 10px 12px; border-radius: 6px; }
        .message-preview { max-width: 360px; white-space: normal; line-height: 1.4; }
    </style>
</head>
<body>

    <h1>Super Admin Dashboard</h1>

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

    <div class="card" style="max-width: 100%;">
        <h3>Daftar Toko Aktif</h3>
        <table>
            <tr>
                <th>ID Tenant</th>
                <th>Nama Toko</th>
                <th>URL Slug</th>
                <th>Pemilik & Email</th>
                <th>Paket</th>
                <th>Aksi</th>
            </tr>
            <?php foreach ($tenants as $t): ?>
            <tr>
                <td><?= $t['id'] ?></td>
                <td><?= superadmin_h($t['nama_toko']) ?></td>
                <td><strong>/<?= superadmin_h($t['slug']) ?></strong></td>
                <td><?= superadmin_h($t['nama_pemilik']) ?><br><small><?= superadmin_h($t['email']) ?></small></td>
                <td><?= superadmin_h($t['paket_langganan']) ?></td>
                <td>
                    <a href="index.php?hapus=<?= $t['id'] ?>" class="btn-danger" onclick="return confirm('Hapus tenant ini? Semua data user dan barang toko ini akan ikut terhapus permanen!')">Hapus</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>

</body>
</html>

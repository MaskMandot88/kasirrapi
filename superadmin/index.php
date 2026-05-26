<?php
// superadmin/index.php
require_once '../config/database.php';

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
    
    // Mengamankan password dengan Hash (BCRYPT) sebelum masuk database
    $password_polos = $_POST['password'];
    $password_hashed = password_hash($password_polos, PASSWORD_BCRYPT);

    try {
        // Mulai database transaction agar jika salah satu gagal, semua dibatalkan
        $pdo->beginTransaction();

        // 1. Masukkan data ke tabel tenants
        $stmt_tenant = $pdo->prepare("INSERT INTO tenants (nama_toko, slug, nama_pemilik, email, paket_langganan) VALUES (?, ?, ?, ?, ?)");
        $stmt_tenant->execute([$nama_toko, $slug, $nama_pemilik, $email, $paket]);
        
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
    </style>
</head>
<body>

    <h1>Super Admin Dashboard</h1>
    
    <div class="card">
        <h3>Daftarkan Toko & Owner Baru</h3>
        <?php if(isset($error)) echo "<p style='color:red;'>$error</p>"; ?>
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
                <option value="Basic">Basic</option>
                <option value="Pro">Pro</option>
                <option value="VIP">VIP</option>
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
                <td><?= htmlspecialchars($t['nama_toko']) ?></td>
                <td><strong>/<?= htmlspecialchars($t['slug']) ?></strong></td>
                <td><?= htmlspecialchars($t['nama_pemilik']) ?><br><small><?= htmlspecialchars($t['email']) ?></small></td>
                <td><?= $t['paket_langganan'] ?></td>
                <td>
                    <a href="index.php?hapus=<?= $t['id'] ?>" class="btn-danger" onclick="return confirm('Hapus tenant ini? Semua data user dan barang toko ini akan ikut terhapus permanen!')">Hapus</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>

</body>
</html>
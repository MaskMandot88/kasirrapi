<?php
$host     = 'localhost';
$dbname   = 'NAMA_DATABASE_CPANEL';
$username = 'USERNAME_DATABASE_CPANEL';
$password = 'ISI_PASSWORD_DATABASE';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die('Koneksi database gagal: ' . $e->getMessage());
}

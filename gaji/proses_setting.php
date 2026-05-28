<?php
require_once '_auth.php';

$user_id = (int)($_POST['user_id'] ?? 0);
if ($user_id <= 0) redirect_with('setting.php', 'error', 'User tidak valid.');

$stmt = $pdo->prepare("SELECT id FROM users WHERE tenant_id = ? AND id = ? LIMIT 1");
$stmt->execute([$tenant_id, $user_id]);
if (!$stmt->fetch()) redirect_with('setting.php', 'error', 'User tidak ditemukan.');

$fields = [
    'gaji_pokok',
    'uang_makan_per_hari',
    'uang_transport_per_hari',
    'tarif_lembur_per_jam',
    'potongan_terlambat_per_menit',
    'potongan_pulang_cepat_per_menit',
    'potongan_alpha_per_hari'
];

$data = [];
foreach ($fields as $f) {
    $data[$f] = max(0, (float)($_POST[$f] ?? 0));
}
$jam = max(1, (int)($_POST['jam_kerja_normal_menit'] ?? 480));
$aktif = isset($_POST['aktif']) ? 1 : 0;

$sql = "INSERT INTO karyawan_gaji
        (tenant_id, user_id, gaji_pokok, uang_makan_per_hari, uang_transport_per_hari, tarif_lembur_per_jam,
         potongan_terlambat_per_menit, potongan_pulang_cepat_per_menit, potongan_alpha_per_hari,
         jam_kerja_normal_menit, aktif)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
         gaji_pokok = VALUES(gaji_pokok),
         uang_makan_per_hari = VALUES(uang_makan_per_hari),
         uang_transport_per_hari = VALUES(uang_transport_per_hari),
         tarif_lembur_per_jam = VALUES(tarif_lembur_per_jam),
         potongan_terlambat_per_menit = VALUES(potongan_terlambat_per_menit),
         potongan_pulang_cepat_per_menit = VALUES(potongan_pulang_cepat_per_menit),
         potongan_alpha_per_hari = VALUES(potongan_alpha_per_hari),
         jam_kerja_normal_menit = VALUES(jam_kerja_normal_menit),
         aktif = VALUES(aktif)";
$stmt = $pdo->prepare($sql);
$stmt->execute([
    $tenant_id, $user_id,
    $data['gaji_pokok'], $data['uang_makan_per_hari'], $data['uang_transport_per_hari'],
    $data['tarif_lembur_per_jam'], $data['potongan_terlambat_per_menit'],
    $data['potongan_pulang_cepat_per_menit'], $data['potongan_alpha_per_hari'],
    $jam, $aktif
]);

redirect_with('setting.php', 'success', 'Setting gaji berhasil disimpan.');

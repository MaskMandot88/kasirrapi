<?php
require_once '_auth.php';

$nama = trim($_POST['nama_periode'] ?? '');
$mulai = $_POST['periode_mulai'] ?? '';
$selesai = $_POST['periode_selesai'] ?? '';
$catatan = trim($_POST['catatan'] ?? '');
$replace = isset($_POST['replace_existing']);

if ($nama === '' || !$mulai || !$selesai || strtotime($mulai) === false || strtotime($selesai) === false) {
    redirect_with('generate.php', 'error', 'Data periode tidak valid.');
}
if ($selesai < $mulai) {
    redirect_with('generate.php', 'error', 'Periode selesai tidak boleh lebih awal dari mulai.');
}

function count_date_range_days($start, $end) {
    $a = new DateTime($start);
    $b = new DateTime($end);
    return (int)$a->diff($b)->days + 1;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT * FROM payroll_periode
                           WHERE tenant_id = ? AND periode_mulai = ? AND periode_selesai = ?
                           LIMIT 1 FOR UPDATE");
    $stmt->execute([$tenant_id, $mulai, $selesai]);
    $existing = $stmt->fetch();

    if ($existing) {
        if (!$replace) {
            throw new RuntimeException('Periode ini sudah ada. Centang generate ulang jika ingin mengganti.');
        }
        if ($existing['status'] !== 'Draft') {
            throw new RuntimeException('Periode ini sudah dikunci/dibayar sehingga tidak bisa digenerate ulang.');
        }

        $payroll_id = (int)$existing['id'];
        $pdo->prepare("DELETE FROM payroll_detail WHERE tenant_id = ? AND payroll_id = ?")->execute([$tenant_id, $payroll_id]);
        $pdo->prepare("UPDATE payroll_periode SET nama_periode=?, catatan=?, total_gaji=0, dibuat_by=?, updated_at=NOW() WHERE id=? AND tenant_id=?")
            ->execute([$nama, $catatan, $user_id_login, $payroll_id, $tenant_id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO payroll_periode
                               (tenant_id, nama_periode, periode_mulai, periode_selesai, status, dibuat_by, catatan)
                               VALUES (?, ?, ?, ?, 'Draft', ?, ?)");
        $stmt->execute([$tenant_id, $nama, $mulai, $selesai, $user_id_login, $catatan]);
        $payroll_id = (int)$pdo->lastInsertId();
    }

    $stmt = $pdo->prepare("SELECT u.id, u.nama, u.role, kg.*
                           FROM users u
                           JOIN karyawan_gaji kg ON kg.user_id = u.id AND kg.tenant_id = u.tenant_id
                           WHERE u.tenant_id = ? AND kg.aktif = 1
                           ORDER BY u.nama ASC");
    $stmt->execute([$tenant_id]);
    $karyawan = $stmt->fetchAll();

    if (!$karyawan) {
        throw new RuntimeException('Belum ada setting gaji karyawan aktif.');
    }

    $totalPayroll = 0;

    foreach ($karyawan as $k) {
        $uid = (int)$k['user_id'];

        // Jumlah jadwal kerja.
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM jadwal_shift
                               WHERE tenant_id = ? AND user_id = ? AND tanggal BETWEEN ? AND ?
                                 AND status_jadwal IN ('Dijadwalkan','Tukar Shift')");
        $stmt->execute([$tenant_id, $uid, $mulai, $selesai]);
        $hari_jadwal = (int)$stmt->fetchColumn();

        // Rekap absensi.
        $stmt = $pdo->prepare("SELECT
                                  COUNT(CASE WHEN jam_masuk IS NOT NULL THEN 1 END) AS hari_hadir,
                                  SUM(CASE WHEN status_kehadiran = 'Izin' THEN 1 ELSE 0 END) AS hari_izin,
                                  SUM(CASE WHEN status_kehadiran = 'Cuti' THEN 1 ELSE 0 END) AS hari_cuti,
                                  SUM(CASE WHEN status_kehadiran = 'Sakit' THEN 1 ELSE 0 END) AS hari_sakit,
                                  COALESCE(SUM(menit_terlambat),0) AS telat,
                                  COALESCE(SUM(menit_pulang_cepat),0) AS pulang_cepat,
                                  COALESCE(SUM(durasi_kerja_menit),0) AS menit_kerja
                               FROM absensi
                               WHERE tenant_id = ? AND user_id = ? AND tanggal BETWEEN ? AND ?");
        $stmt->execute([$tenant_id, $uid, $mulai, $selesai]);
        $a = $stmt->fetch();

        $hari_hadir = (int)($a['hari_hadir'] ?? 0);
        $hari_izin = (int)($a['hari_izin'] ?? 0);
        $hari_cuti = (int)($a['hari_cuti'] ?? 0);
        $hari_sakit = (int)($a['hari_sakit'] ?? 0);
        $telat = (int)($a['telat'] ?? 0);
        $pulang_cepat = (int)($a['pulang_cepat'] ?? 0);
        $menit_kerja = (int)($a['menit_kerja'] ?? 0);

        // Alpha = jadwal kerja yang tidak tertutup hadir/izin/cuti/sakit.
        $hari_tertutup = $hari_hadir + $hari_izin + $hari_cuti + $hari_sakit;
        $hari_alpha = max(0, $hari_jadwal - $hari_tertutup);

        $normal_per_hari = max(1, (int)$k['jam_kerja_normal_menit']);
        $menit_normal_total = $hari_hadir * $normal_per_hari;
        $jam_lembur = max(0, ($menit_kerja - $menit_normal_total) / 60);
        $jam_lembur = round($jam_lembur, 2);

        $gaji_pokok = (float)$k['gaji_pokok'];
        $uang_makan = $hari_hadir * (float)$k['uang_makan_per_hari'];
        $uang_transport = $hari_hadir * (float)$k['uang_transport_per_hari'];
        $uang_lembur = $jam_lembur * (float)$k['tarif_lembur_per_jam'];

        $pot_telat = $telat * (float)$k['potongan_terlambat_per_menit'];
        $pot_pulang = $pulang_cepat * (float)$k['potongan_pulang_cepat_per_menit'];
        $pot_alpha = $hari_alpha * (float)$k['potongan_alpha_per_hari'];

        $total = $gaji_pokok + $uang_makan + $uang_transport + $uang_lembur - $pot_telat - $pot_pulang - $pot_alpha;
        $total = max(0, $total);

        $stmt = $pdo->prepare("INSERT INTO payroll_detail
                               (tenant_id, payroll_id, user_id, hari_jadwal, hari_hadir, hari_izin, hari_cuti, hari_sakit, hari_alpha,
                                total_menit_terlambat, total_menit_pulang_cepat, total_menit_kerja, total_jam_lembur,
                                gaji_pokok, uang_makan, uang_transport, uang_lembur,
                                potongan_terlambat, potongan_pulang_cepat, potongan_alpha, total_gaji)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $tenant_id, $payroll_id, $uid, $hari_jadwal, $hari_hadir, $hari_izin, $hari_cuti, $hari_sakit, $hari_alpha,
            $telat, $pulang_cepat, $menit_kerja, $jam_lembur,
            $gaji_pokok, $uang_makan, $uang_transport, $uang_lembur,
            $pot_telat, $pot_pulang, $pot_alpha, $total
        ]);

        $totalPayroll += $total;
    }

    $stmt = $pdo->prepare("UPDATE payroll_periode SET total_gaji = ? WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$totalPayroll, $payroll_id, $tenant_id]);

    $pdo->commit();
    redirect_with('detail.php?id=' . $payroll_id, 'success', 'Generate gaji berhasil.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    redirect_with('generate.php', 'error', $e->getMessage());
}

<?php
require_once '_auth.php';
if (!is_hr_role()) die('Akses ditolak.');

$tipe = $_POST['tipe'] ?? '';
$id = (int)($_POST['id'] ?? 0);
$aksi = $_POST['aksi'] ?? '';
$catatan = trim($_POST['catatan'] ?? '');

if (!in_array($tipe, ['pengajuan_absensi','shift_tukar'], true) || $id <= 0 || !in_array($aksi, ['setujui','tolak'], true)) {
    redirect_with('approval.php', 'error', 'Data approval tidak valid.');
}

function each_date_range($start, $end) {
    $dates = [];
    $cur = strtotime($start);
    $last = strtotime($end);
    while ($cur <= $last) {
        $dates[] = date('Y-m-d', $cur);
        $cur = strtotime('+1 day', $cur);
    }
    return $dates;
}

function get_jadwal_id_for_date($pdo, $tenant_id, $user_id, $tanggal) {
    $stmt = $pdo->prepare("SELECT id FROM jadwal_shift WHERE tenant_id = ? AND user_id = ? AND tanggal = ? LIMIT 1");
    $stmt->execute([$tenant_id, $user_id, $tanggal]);
    $row = $stmt->fetch();
    return $row ? (int)$row['id'] : null;
}

try {
    $pdo->beginTransaction();

    if ($tipe === 'pengajuan_absensi') {
        $stmt = $pdo->prepare("SELECT * FROM pengajuan_absensi WHERE tenant_id = ? AND id = ? LIMIT 1 FOR UPDATE");
        $stmt->execute([$tenant_id, $id]);
        $p = $stmt->fetch();

        if (!$p) throw new RuntimeException('Pengajuan tidak ditemukan.');
        if ($p['status'] !== 'Menunggu') throw new RuntimeException('Pengajuan ini sudah diproses.');

        if ($aksi === 'tolak') {
            $stmt = $pdo->prepare("UPDATE pengajuan_absensi
                                   SET status = 'Ditolak', approved_by = ?, approved_at = NOW(), catatan_approval = ?
                                   WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$user_id_login, $catatan, $id, $tenant_id]);

            $pdo->commit();
            redirect_with('approval.php', 'success', 'Pengajuan berhasil ditolak.');
        }

        $jenis = $p['jenis'];
        $statusKehadiranMap = [
            'Izin' => 'Izin',
            'Cuti' => 'Cuti',
            'Sakit' => 'Sakit'
        ];

        if (isset($statusKehadiranMap[$jenis])) {
            foreach (each_date_range($p['tanggal_mulai'], $p['tanggal_selesai']) as $tgl) {
                $jadwal_id = get_jadwal_id_for_date($pdo, $tenant_id, $p['user_id'], $tgl);

                $stmt = $pdo->prepare("SELECT id FROM absensi WHERE tenant_id = ? AND user_id = ? AND tanggal = ? LIMIT 1 FOR UPDATE");
                $stmt->execute([$tenant_id, $p['user_id'], $tgl]);
                $abs = $stmt->fetch();

                if ($abs) {
                    $stmt = $pdo->prepare("UPDATE absensi
                                           SET jadwal_shift_id = COALESCE(jadwal_shift_id, ?),
                                               status_kehadiran = ?,
                                               catatan = ?,
                                               updated_by = ?
                                           WHERE id = ? AND tenant_id = ?");
                    $stmt->execute([$jadwal_id, $statusKehadiranMap[$jenis], $jenis . ': ' . $p['alasan'], $user_id_login, $abs['id'], $tenant_id]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO absensi
                                           (tenant_id, user_id, jadwal_shift_id, tanggal, status_kehadiran, catatan, created_by)
                                           VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$tenant_id, $p['user_id'], $jadwal_id, $tgl, $statusKehadiranMap[$jenis], $jenis . ': ' . $p['alasan'], $user_id_login]);
                }

                $stmt = $pdo->prepare("UPDATE jadwal_shift
                                       SET status_jadwal = ?, catatan = CONCAT(COALESCE(catatan,''), ?)
                                       WHERE tenant_id = ? AND user_id = ? AND tanggal = ?");
                $stmt->execute([$statusKehadiranMap[$jenis], "\nDisetujui: " . $jenis, $tenant_id, $p['user_id'], $tgl]);
            }
        } else {
            $tgl = $p['tanggal_mulai'];
            $jam = $p['jam_koreksi'];
            if (!$jam) throw new RuntimeException('Jam koreksi/manual kosong.');

            $dt = $tgl . ' ' . $jam . ':00';
            $jadwal_id = get_jadwal_id_for_date($pdo, $tenant_id, $p['user_id'], $tgl);

            $stmt = $pdo->prepare("SELECT id, jam_masuk, jam_pulang FROM absensi WHERE tenant_id = ? AND user_id = ? AND tanggal = ? LIMIT 1 FOR UPDATE");
            $stmt->execute([$tenant_id, $p['user_id'], $tgl]);
            $abs = $stmt->fetch();

            if (!$abs) {
                $stmt = $pdo->prepare("INSERT INTO absensi
                                       (tenant_id, user_id, jadwal_shift_id, tanggal, status_kehadiran, catatan, created_by)
                                       VALUES (?, ?, ?, ?, 'Belum Pulang', ?, ?)");
                $stmt->execute([$tenant_id, $p['user_id'], $jadwal_id, $tgl, 'Dari approval ' . $jenis . ': ' . $p['alasan'], $user_id_login]);

                $absId = (int)$pdo->lastInsertId();
                $abs = ['id' => $absId, 'jam_masuk' => null, 'jam_pulang' => null];
            }

            if (in_array($jenis, ['Manual Masuk','Koreksi Masuk'], true)) {
                $stmt = $pdo->prepare("UPDATE absensi
                                       SET jadwal_shift_id = COALESCE(jadwal_shift_id, ?),
                                           jam_masuk = ?,
                                           metode_masuk = 'Koreksi',
                                           status_kehadiran = 'Belum Pulang',
                                           catatan = CONCAT(COALESCE(catatan,''), ?),
                                           updated_by = ?
                                       WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$jadwal_id, $dt, "\nDisetujui " . $jenis . ': ' . $p['alasan'], $user_id_login, $abs['id'], $tenant_id]);
            } else {
                if (empty($abs['jam_masuk'])) {
                    throw new RuntimeException('Tidak bisa menyetujui pulang karena karyawan belum memiliki jam masuk pada tanggal tersebut.');
                }

                $durasi = max(0, (int)floor((strtotime($dt) - strtotime($abs['jam_masuk'])) / 60));
                $status = 'Hadir';

                $stmt = $pdo->prepare("UPDATE absensi
                                       SET jadwal_shift_id = COALESCE(jadwal_shift_id, ?),
                                           jam_pulang = ?,
                                           metode_pulang = 'Koreksi',
                                           status_kehadiran = ?,
                                           durasi_kerja_menit = ?,
                                           catatan = CONCAT(COALESCE(catatan,''), ?),
                                           updated_by = ?
                                       WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$jadwal_id, $dt, $status, $durasi, "\nDisetujui " . $jenis . ': ' . $p['alasan'], $user_id_login, $abs['id'], $tenant_id]);
            }
        }

        $stmt = $pdo->prepare("UPDATE pengajuan_absensi
                               SET status = 'Disetujui', approved_by = ?, approved_at = NOW(), catatan_approval = ?
                               WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$user_id_login, $catatan, $id, $tenant_id]);

        $pdo->commit();
        redirect_with('approval.php', 'success', 'Pengajuan berhasil disetujui.');
    }

    if ($tipe === 'shift_tukar') {
        $stmt = $pdo->prepare("SELECT * FROM shift_tukar WHERE tenant_id = ? AND id = ? LIMIT 1 FOR UPDATE");
        $stmt->execute([$tenant_id, $id]);
        $t = $stmt->fetch();

        if (!$t) throw new RuntimeException('Pengajuan tukar shift tidak ditemukan.');
        if ($t['status'] !== 'Menunggu') throw new RuntimeException('Pengajuan ini sudah diproses.');

        if ($aksi === 'tolak') {
            $stmt = $pdo->prepare("UPDATE shift_tukar
                                   SET status = 'Ditolak', approved_by = ?, approved_at = NOW(), catatan_approval = ?
                                   WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$user_id_login, $catatan, $id, $tenant_id]);

            $pdo->commit();
            redirect_with('approval.php', 'success', 'Pengajuan tukar shift berhasil ditolak.');
        }

        // Tandai jadwal asal sebagai Tukar Shift jika ada.
        if (!empty($t['jadwal_asal_id'])) {
            $stmt = $pdo->prepare("UPDATE jadwal_shift
                                   SET status_jadwal = 'Tukar Shift',
                                       catatan = CONCAT(COALESCE(catatan,''), ?)
                                   WHERE tenant_id = ? AND id = ?");
            $stmt->execute(["\nDitukar sementara ke user ID " . $t['user_pengganti_id'], $tenant_id, $t['jadwal_asal_id']]);
        }

        // Buat/update jadwal pengganti pada tanggal tersebut.
        $stmt = $pdo->prepare("SELECT id FROM jadwal_shift WHERE tenant_id = ? AND user_id = ? AND tanggal = ? LIMIT 1 FOR UPDATE");
        $stmt->execute([$tenant_id, $t['user_pengganti_id'], $t['tanggal']]);
        $existing = $stmt->fetch();

        if ($existing) {
            $stmt = $pdo->prepare("UPDATE jadwal_shift
                                   SET shift_id = ?,
                                       status_jadwal = 'Dijadwalkan',
                                       catatan = CONCAT(COALESCE(catatan,''), ?),
                                       created_by = ?
                                   WHERE tenant_id = ? AND id = ?");
            $stmt->execute([$t['shift_id'], "\nPengganti tukar shift dari user ID " . $t['user_asal_id'], $user_id_login, $tenant_id, $existing['id']]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO jadwal_shift
                                   (tenant_id, user_id, tanggal, shift_id, status_jadwal, catatan, created_by)
                                   VALUES (?, ?, ?, ?, 'Dijadwalkan', ?, ?)");
            $stmt->execute([$tenant_id, $t['user_pengganti_id'], $t['tanggal'], $t['shift_id'], 'Pengganti tukar shift dari user ID ' . $t['user_asal_id'], $user_id_login]);
        }

        $stmt = $pdo->prepare("UPDATE shift_tukar
                               SET status = 'Disetujui', approved_by = ?, approved_at = NOW(), catatan_approval = ?
                               WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$user_id_login, $catatan, $id, $tenant_id]);

        $pdo->commit();
        redirect_with('approval.php', 'success', 'Pengajuan tukar shift berhasil disetujui.');
    }

    throw new RuntimeException('Tipe approval tidak valid.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    redirect_with('approval.php', 'error', $e->getMessage());
}

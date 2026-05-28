<?php
// absensi/proses_absen_wajah.php
require_once '_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: absen_wajah.php');
    exit;
}

$jenis_absen = $_POST['jenis_absen'] ?? '';
$distance = isset($_POST['distance']) ? (float) $_POST['distance'] : null;
$threshold = isset($_POST['threshold']) ? (float) $_POST['threshold'] : 0.52;
$descriptor = $_POST['descriptor'] ?? '';
$foto_capture = $_POST['foto_capture'] ?? '';

$latitude_absen = isset($_POST['latitude_absen']) && $_POST['latitude_absen'] !== '' ? (float) $_POST['latitude_absen'] : null;
$longitude_absen = isset($_POST['longitude_absen']) && $_POST['longitude_absen'] !== '' ? (float) $_POST['longitude_absen'] : null;
$jarak_meter_client = isset($_POST['jarak_meter']) && $_POST['jarak_meter'] !== '' ? (int) $_POST['jarak_meter'] : null;
$akurasi_meter = isset($_POST['akurasi_meter']) && $_POST['akurasi_meter'] !== '' ? (int) $_POST['akurasi_meter'] : null;

if (!in_array($jenis_absen, ['Masuk', 'Pulang'], true)) {
    flash_set('error', 'Jenis absensi tidak valid.');
    header('Location: absen_wajah.php');
    exit;
}

if ($distance === null || $distance <= 0) {
    flash_set('error', 'Data kecocokan wajah tidak valid.');
    header('Location: absen_wajah.php');
    exit;
}

if ($distance > $threshold) {
    flash_set('error', 'Wajah tidak cocok. Absensi ditolak.');
    header('Location: absen_wajah.php');
    exit;
}

if ($latitude_absen === null || $longitude_absen === null) {
    flash_set('error', 'Lokasi absensi tidak terbaca. Aktifkan GPS/lokasi lalu coba lagi.');
    header('Location: absen_wajah.php');
    exit;
}

if ($latitude_absen < -90 || $latitude_absen > 90 || $longitude_absen < -180 || $longitude_absen > 180) {
    flash_set('error', 'Koordinat lokasi absensi tidak valid.');
    header('Location: absen_wajah.php');
    exit;
}

function col_exists_absen_wajah($pdo, $table, $column) {
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $stmt->execute([$column]);
        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return false;
    }
}

function hitung_jarak_meter_absen_wajah($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371000;

    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);

    $a = sin($dLat / 2) * sin($dLat / 2)
       + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
       * sin($dLon / 2) * sin($dLon / 2);

    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

    return (int) round($earthRadius * $c);
}

function simpan_foto_absen_wajah($foto_capture, $tenant_id, $user_id) {
    if (empty($foto_capture)) {
        return null;
    }

    if (!preg_match('/^data:image\/jpeg;base64,/', $foto_capture)) {
        return null;
    }

    $base64 = preg_replace('/^data:image\/jpeg;base64,/', '', $foto_capture);
    $binary = base64_decode($base64, true);

    if ($binary === false) {
        return null;
    }

    if (strlen($binary) > 2 * 1024 * 1024) {
        return null;
    }

    $folder = __DIR__ . "/../public/uploads/tenant_{$tenant_id}/absensi";

    if (!is_dir($folder)) {
        @mkdir($folder, 0755, true);
    }

    if (!is_dir($folder) || !is_writable($folder)) {
        return null;
    }

    $filename = 'absen_wajah_' . $user_id . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.jpg';
    $path = $folder . '/' . $filename;

    if (file_put_contents($path, $binary) === false) {
        return null;
    }

    return $filename;
}

try {
    $stmtTenant = $pdo->prepare("
        SELECT latitude_toko,
               longitude_toko,
               radius_absensi_meter
        FROM tenants
        WHERE id = ?
        LIMIT 1
    ");
    $stmtTenant->execute([$tenant_id]);
    $tenant = $stmtTenant->fetch(PDO::FETCH_ASSOC);

    if (!$tenant || empty($tenant['latitude_toko']) || empty($tenant['longitude_toko'])) {
        flash_set('error', 'Lokasi toko belum diatur. Hubungi Owner/Admin.');
        header('Location: absen_wajah.php');
        exit;
    }

    $latitude_toko = (float) $tenant['latitude_toko'];
    $longitude_toko = (float) $tenant['longitude_toko'];
    $radius_absensi_meter = (int) ($tenant['radius_absensi_meter'] ?? 100);

    if ($radius_absensi_meter <= 0) {
        $radius_absensi_meter = 100;
    }

    $jarak_meter_server = hitung_jarak_meter_absen_wajah(
        $latitude_absen,
        $longitude_absen,
        $latitude_toko,
        $longitude_toko
    );

    if ($jarak_meter_server > $radius_absensi_meter) {
        flash_set(
            'error',
            'Absensi ditolak. Anda berada di luar radius toko. Jarak sekitar ' . number_format($jarak_meter_server, 0, ',', '.') . ' meter, radius toko ' . number_format($radius_absensi_meter, 0, ',', '.') . ' meter.'
        );
        header('Location: absen_wajah.php');
        exit;
    }

    $has_tanggal = col_exists_absen_wajah($pdo, 'absensi', 'tanggal');
    $has_jam_masuk = col_exists_absen_wajah($pdo, 'absensi', 'jam_masuk');
    $has_jam_pulang = col_exists_absen_wajah($pdo, 'absensi', 'jam_pulang');
    $has_status = col_exists_absen_wajah($pdo, 'absensi', 'status');

    if (!$has_tanggal || !$has_jam_masuk || !$has_jam_pulang) {
        flash_set('error', 'Struktur tabel absensi belum sesuai.');
        header('Location: absen_wajah.php');
        exit;
    }

    $has_foto = col_exists_absen_wajah($pdo, 'absensi', 'foto');
    $has_foto_masuk = col_exists_absen_wajah($pdo, 'absensi', 'foto_masuk');
    $has_foto_pulang = col_exists_absen_wajah($pdo, 'absensi', 'foto_pulang');

    $has_latitude_absen = col_exists_absen_wajah($pdo, 'absensi', 'latitude_absen');
    $has_longitude_absen = col_exists_absen_wajah($pdo, 'absensi', 'longitude_absen');
    $has_jarak_meter = col_exists_absen_wajah($pdo, 'absensi', 'jarak_meter');
    $has_lokasi_valid = col_exists_absen_wajah($pdo, 'absensi', 'lokasi_valid');
    $has_akurasi_meter = col_exists_absen_wajah($pdo, 'absensi', 'akurasi_meter');

    $has_face_distance = col_exists_absen_wajah($pdo, 'absensi', 'face_distance');
    $has_threshold = col_exists_absen_wajah($pdo, 'absensi', 'threshold');
    $has_descriptor = col_exists_absen_wajah($pdo, 'absensi', 'descriptor');
    $has_metode = col_exists_absen_wajah($pdo, 'absensi', 'metode');
    $has_keterangan = col_exists_absen_wajah($pdo, 'absensi', 'keterangan');

    $foto_filename = simpan_foto_absen_wajah($foto_capture, $tenant_id, $user_id_login);

    $pdo->beginTransaction();

    $stmtOpen = $pdo->prepare("
        SELECT *
        FROM absensi
        WHERE tenant_id = ?
          AND user_id = ?
          AND jam_masuk IS NOT NULL
          AND jam_pulang IS NULL
          AND jam_masuk >= DATE_SUB(NOW(), INTERVAL 36 HOUR)
        ORDER BY jam_masuk DESC
        LIMIT 1
    ");
    $stmtOpen->execute([$tenant_id, $user_id_login]);
    $openAbs = $stmtOpen->fetch(PDO::FETCH_ASSOC);

    $today = date('Y-m-d');

    $stmtToday = $pdo->prepare("
        SELECT *
        FROM absensi
        WHERE tenant_id = ?
          AND user_id = ?
          AND tanggal = ?
        LIMIT 1
    ");
    $stmtToday->execute([$tenant_id, $user_id_login, $today]);
    $todayAbs = $stmtToday->fetch(PDO::FETCH_ASSOC);

    if ($jenis_absen === 'Masuk') {
        if ($openAbs || ($todayAbs && !empty($todayAbs['jam_masuk']) && empty($todayAbs['jam_pulang']))) {
            $pdo->rollBack();
            flash_set('error', 'Anda sudah absen masuk. Silakan lakukan absen pulang.');
            header('Location: absen_wajah.php');
            exit;
        }

        if ($todayAbs && !empty($todayAbs['jam_masuk']) && !empty($todayAbs['jam_pulang'])) {
            $pdo->rollBack();
            flash_set('error', 'Absensi hari ini sudah lengkap.');
            header('Location: absen_wajah.php');
            exit;
        }

        $cols = ['tenant_id', 'user_id', 'tanggal', 'jam_masuk'];
        $vals = ['?', '?', 'CURDATE()', 'NOW()'];
        $params = [$tenant_id, $user_id_login];

        if ($has_status) {
            $cols[] = 'status';
            $vals[] = '?';
            $params[] = 'Hadir';
        }

        if ($has_foto_masuk) {
            $cols[] = 'foto_masuk';
            $vals[] = '?';
            $params[] = $foto_filename;
        } elseif ($has_foto) {
            $cols[] = 'foto';
            $vals[] = '?';
            $params[] = $foto_filename;
        }

        if ($has_latitude_absen) {
            $cols[] = 'latitude_absen';
            $vals[] = '?';
            $params[] = $latitude_absen;
        }

        if ($has_longitude_absen) {
            $cols[] = 'longitude_absen';
            $vals[] = '?';
            $params[] = $longitude_absen;
        }

        if ($has_jarak_meter) {
            $cols[] = 'jarak_meter';
            $vals[] = '?';
            $params[] = $jarak_meter_server;
        }

        if ($has_lokasi_valid) {
            $cols[] = 'lokasi_valid';
            $vals[] = '1';
        }

        if ($has_akurasi_meter) {
            $cols[] = 'akurasi_meter';
            $vals[] = '?';
            $params[] = $akurasi_meter;
        }

        if ($has_face_distance) {
            $cols[] = 'face_distance';
            $vals[] = '?';
            $params[] = $distance;
        }

        if ($has_threshold) {
            $cols[] = 'threshold';
            $vals[] = '?';
            $params[] = $threshold;
        }

        if ($has_descriptor) {
            $cols[] = 'descriptor';
            $vals[] = '?';
            $params[] = $descriptor;
        }

        if ($has_metode) {
            $cols[] = 'metode';
            $vals[] = '?';
            $params[] = 'Wajah';
        }

        if ($has_keterangan) {
            $cols[] = 'keterangan';
            $vals[] = '?';
            $params[] = 'Absen masuk via wajah. Lokasi valid: ' . $jarak_meter_server . ' meter dari toko.';
        }

        $sql = "INSERT INTO absensi (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ")";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $pdo->commit();

        flash_set('success', 'Absen masuk berhasil. Jarak dari toko sekitar ' . number_format($jarak_meter_server, 0, ',', '.') . ' meter.');
        header('Location: index.php');
        exit;
    }

    if ($jenis_absen === 'Pulang') {
        $targetAbs = $openAbs ?: $todayAbs;

        if (!$targetAbs || empty($targetAbs['jam_masuk'])) {
            $pdo->rollBack();
            flash_set('error', 'Belum ada absen masuk yang bisa dipulangkan.');
            header('Location: absen_wajah.php');
            exit;
        }

        if (!empty($targetAbs['jam_pulang'])) {
            $pdo->rollBack();
            flash_set('error', 'Anda sudah absen pulang.');
            header('Location: absen_wajah.php');
            exit;
        }

        $sets = ['jam_pulang = NOW()'];
        $params = [];

        if ($has_foto_pulang) {
            $sets[] = 'foto_pulang = ?';
            $params[] = $foto_filename;
        } elseif ($has_foto) {
            $sets[] = 'foto = ?';
            $params[] = $foto_filename;
        }

        if ($has_latitude_absen) {
            $sets[] = 'latitude_absen = ?';
            $params[] = $latitude_absen;
        }

        if ($has_longitude_absen) {
            $sets[] = 'longitude_absen = ?';
            $params[] = $longitude_absen;
        }

        if ($has_jarak_meter) {
            $sets[] = 'jarak_meter = ?';
            $params[] = $jarak_meter_server;
        }

        if ($has_lokasi_valid) {
            $sets[] = 'lokasi_valid = 1';
        }

        if ($has_akurasi_meter) {
            $sets[] = 'akurasi_meter = ?';
            $params[] = $akurasi_meter;
        }

        if ($has_face_distance) {
            $sets[] = 'face_distance = ?';
            $params[] = $distance;
        }

        if ($has_threshold) {
            $sets[] = 'threshold = ?';
            $params[] = $threshold;
        }

        if ($has_descriptor) {
            $sets[] = 'descriptor = ?';
            $params[] = $descriptor;
        }

        if ($has_metode) {
            $sets[] = 'metode = ?';
            $params[] = 'Wajah';
        }

        if ($has_keterangan) {
            $sets[] = 'keterangan = ?';
            $params[] = 'Absen pulang via wajah. Lokasi valid: ' . $jarak_meter_server . ' meter dari toko.';
        }

        $params[] = (int) $targetAbs['id'];
        $params[] = $tenant_id;
        $params[] = $user_id_login;

        $sql = "
            UPDATE absensi
            SET " . implode(', ', $sets) . "
            WHERE id = ?
              AND tenant_id = ?
              AND user_id = ?
            LIMIT 1
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $pdo->commit();

        flash_set('success', 'Absen pulang berhasil. Jarak dari toko sekitar ' . number_format($jarak_meter_server, 0, ',', '.') . ' meter.');
        header('Location: index.php');
        exit;
    }

    $pdo->rollBack();

    flash_set('error', 'Jenis absensi tidak valid.');
    header('Location: absen_wajah.php');
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Gagal proses absen wajah: ' . $e->getMessage());

    flash_set('error', 'Gagal menyimpan absensi wajah.');
    header('Location: absen_wajah.php');
    exit;
}

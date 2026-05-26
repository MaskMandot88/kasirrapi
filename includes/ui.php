<?php
// includes/ui.php
// Komponen UI global mobile-first untuk KasirRapi.

if (file_exists(__DIR__ . '/../config/app.php')) {
    require_once __DIR__ . '/../config/app.php';
}

if (!defined('APP_NAME')) define('APP_NAME', 'KasirRapi');
if (!defined('APP_TAGLINE')) define('APP_TAGLINE', 'Transaksi rapi, usaha lebih pasti.');
if (!defined('APP_SEO_TITLE')) define('APP_SEO_TITLE', APP_NAME . ' - Aplikasi Kasir Online untuk Toko, Grosir, dan UMKM');
if (!defined('APP_SEO_DESCRIPTION')) define('APP_SEO_DESCRIPTION', 'Kelola kasir, stok, piutang, absensi, gaji, dan laporan dalam satu aplikasi.');
if (!defined('APP_BASE_URL')) define('APP_BASE_URL', '');
if (!defined('APP_VERSION')) define('APP_VERSION', '1.0.9');

if (!function_exists('app_url')) {
    function app_url($path = '') {
        $base = rtrim(APP_BASE_URL, '/');
        $path = '/' . ltrim((string)$path, '/');
        return $base . $path;
    }
}

if (!function_exists('asset_url')) {
    function asset_url($path = '') {
        return app_url('assets/' . ltrim((string)$path, '/'));
    }
}

if (!function_exists('h')) {
    function h($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('rupiah')) {
    function rupiah($angka) {
        return 'Rp ' . number_format((float)$angka, 0, ',', '.');
    }
}

if (!function_exists('ui_format_stok_bertingkat')) {
    function ui_format_stok_bertingkat($stokTerkecil, $satuanUtama = '', $isiPerKemasan = 1, $satuanEcer = '') {
        $stok = max(0, (int)$stokTerkecil);
        $isi = max(1, (int)$isiPerKemasan);
        $utama = trim((string)$satuanUtama);
        $ecer = trim((string)$satuanEcer);

        if ($utama === '') $utama = 'Satuan';
        if ($ecer === '') $ecer = $utama;

        if ($isi <= 1 || strcasecmp($utama, $ecer) === 0) {
            return number_format($stok, 0, ',', '.') . ' ' . $ecer;
        }

        $jumlahUtama = intdiv($stok, $isi);
        $sisaEcer = $stok % $isi;
        $parts = [];

        if ($jumlahUtama > 0) {
            $parts[] = number_format($jumlahUtama, 0, ',', '.') . ' ' . $utama;
        }

        if ($sisaEcer > 0 || $stok === 0) {
            $parts[] = number_format($sisaEcer, 0, ',', '.') . ' ' . $ecer;
        }

        return implode(' ', $parts);
    }
}

if (!function_exists('ui_format_stok_terkecil')) {
    function ui_format_stok_terkecil($stokTerkecil, $satuanUtama = '', $isiPerKemasan = 1, $satuanEcer = '') {
        $stok = max(0, (int)$stokTerkecil);
        $isi = max(1, (int)$isiPerKemasan);
        $utama = trim((string)$satuanUtama);
        $ecer = trim((string)$satuanEcer);

        if ($ecer === '') $ecer = $utama !== '' ? $utama : 'Satuan';

        return number_format($stok, 0, ',', '.') . ' ' . $ecer;
    }
}

if (!function_exists('ui_current_user')) {
    function ui_current_user() {
        return [
            'tenant_id' => (int)($_SESSION['tenant_id'] ?? 0),
            'user_id'   => (int)($_SESSION['user_id'] ?? 0),
            'role'      => $_SESSION['role'] ?? '',
            'nama'      => $_SESSION['nama'] ?? ($_SESSION['nama_lengkap'] ?? ($_SESSION['nama_user'] ?? ($_SESSION['name'] ?? 'User'))),
            'email'     => $_SESSION['email'] ?? '',
            'foto'      => $_SESSION['foto_profil'] ?? ''
        ];
    }
}

if (!function_exists('ui_is_role')) {
    function ui_is_role($roles) {
        $u = ui_current_user();
        return in_array($u['role'], (array)$roles, true);
    }
}

if (!function_exists('ui_flash_get')) {
    function ui_flash_get($key) {
        if (!isset($_SESSION['flash'][$key])) return '';
        $v = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $v;
    }
}

if (!function_exists('ui_get_tenant_name')) {
    function ui_get_tenant_name($pdo) {
        $tenant_id = (int)($_SESSION['tenant_id'] ?? 0);

        if ($tenant_id <= 0) {
            return APP_NAME;
        }

        try {
            $s = $pdo->prepare("SELECT nama_toko FROM tenants WHERE id = ? LIMIT 1");
            $s->execute([$tenant_id]);
            $n = $s->fetchColumn();
            return $n ?: APP_NAME;
        } catch (Throwable $e) {
            return APP_NAME;
        }
    }
}

if (!function_exists('notif_count_unread')) {
    function notif_count_unread($pdo) {
        $tenant_id = (int)($_SESSION['tenant_id'] ?? 0);
        $user_id = (int)($_SESSION['user_id'] ?? 0);
        $role = $_SESSION['role'] ?? '';

        if ($tenant_id <= 0 || $user_id <= 0) {
            return 0;
        }

        try {
            $s = $pdo->prepare("
                SELECT COUNT(*)
                FROM notifikasi n
                LEFT JOIN notifikasi_read nr
                    ON nr.notifikasi_id = n.id
                   AND nr.user_id = ?
                   AND nr.tenant_id = n.tenant_id
                WHERE n.tenant_id = ?
                  AND nr.id IS NULL
                  AND (n.target_user_id = ? OR n.target_user_id IS NULL)
                  AND (n.target_role = 'Semua' OR n.target_role = ? OR n.target_role IS NULL)
            ");
            $s->execute([$user_id, $tenant_id, $user_id, $role]);
            return (int)$s->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }
}

if (!function_exists('ui_icon')) {
    function ui_icon($name, $class = 'w-5 h-5') {
        $icons = [
            'dashboard' => '<svg xmlns="http://www.w3.org/2000/svg" class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10.5 12 3l9 7.5"/><path stroke-linecap="round" stroke-linejoin="round" d="M5 10v10h14V10"/><path stroke-linecap="round" stroke-linejoin="round" d="M9 20v-6h6v6"/></svg>',
            'kasir' => '<svg xmlns="http://www.w3.org/2000/svg" class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 3h12v18H6z"/><path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6"/><path stroke-linecap="round" stroke-linejoin="round" d="M9 11h.01M12 11h.01M15 11h.01M9 15h.01M12 15h.01M15 15h.01"/></svg>',
            'barang' => '<svg xmlns="http://www.w3.org/2000/svg" class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M3.3 7 12 12l8.7-5"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 22V12"/></svg>',
            'piutang' => '<svg xmlns="http://www.w3.org/2000/svg" class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="14" rx="2"/><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18"/><path stroke-linecap="round" stroke-linejoin="round" d="M7 15h3"/></svg>',
            'laporan' => '<svg xmlns="http://www.w3.org/2000/svg" class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 19V5"/><path stroke-linecap="round" stroke-linejoin="round" d="M4 19h16"/><path stroke-linecap="round" stroke-linejoin="round" d="M8 16v-5"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 16V8"/><path stroke-linecap="round" stroke-linejoin="round" d="M16 16v-3"/></svg>',
            'absensi' => '<svg xmlns="http://www.w3.org/2000/svg" class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 8V6a2 2 0 0 1 2-2h2"/><path stroke-linecap="round" stroke-linejoin="round" d="M16 4h2a2 2 0 0 1 2 2v2"/><path stroke-linecap="round" stroke-linejoin="round" d="M20 16v2a2 2 0 0 1-2 2h-2"/><path stroke-linecap="round" stroke-linejoin="round" d="M8 20H6a2 2 0 0 1-2-2v-2"/><circle cx="12" cy="10" r="3"/><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 17a5 5 0 0 1 9 0"/></svg>',
            'gaji' => '<svg xmlns="http://www.w3.org/2000/svg" class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="6" width="18" height="12" rx="2"/><circle cx="12" cy="12" r="3"/><path stroke-linecap="round" stroke-linejoin="round" d="M6 9h.01M18 15h.01"/></svg>',
            'notifikasi' => '<svg xmlns="http://www.w3.org/2000/svg" class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path stroke-linecap="round" stroke-linejoin="round" d="M10 21h4"/></svg>',
            'karyawan' => '<svg xmlns="http://www.w3.org/2000/svg" class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path stroke-linecap="round" stroke-linejoin="round" d="M22 21v-2a4 4 0 0 0-3-3.87"/><path stroke-linecap="round" stroke-linejoin="round" d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
            'pengaturan' => '<svg xmlns="http://www.w3.org/2000/svg" class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15.5A3.5 3.5 0 1 0 12 8a3.5 3.5 0 0 0 0 7.5Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.4 15a1.7 1.7 0 0 0 .34 1.87l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06A1.7 1.7 0 0 0 15 19.4a1.7 1.7 0 0 0-1 .6V20a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1-.6 1.7 1.7 0 0 0-1.87.34l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-.6-1H4a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 .6-1 1.7 1.7 0 0 0-.34-1.87l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-.6V4a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 .6 1.7 1.7 0 0 0 1.87-.34l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.7 1.7 0 0 0 19.4 9c.2.36.4.7.6 1H20a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-.5 1Z"/></svg>',
            'profil' => '<svg xmlns="http://www.w3.org/2000/svg" class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 7.5a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 20.25a7.5 7.5 0 0 1 15 0"/></svg>',
            'logout' => '<svg xmlns="http://www.w3.org/2000/svg" class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M10 17l5-5-5-5"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12H3"/><path stroke-linecap="round" stroke-linejoin="round" d="M21 3v18"/></svg>',
        ];

        return $icons[$name] ?? $icons['dashboard'];
    }
}

if (!function_exists('ui_menu_items')) {
    function ui_menu_items() {
        $role = $_SESSION['role'] ?? '';

        $items = [
            ['Dashboard', app_url('dashboard/index.php'), 'dashboard', ['Owner','Admin','Gudang','Kasir','HRD']],
            ['Kasir', app_url('kasir/index.php'), 'kasir', ['Owner','Admin','Kasir']],
            ['Barang', app_url('barang/index.php'), 'barang', ['Owner','Admin','Gudang']],
            ['Piutang', app_url('piutang/index.php'), 'piutang', ['Owner','Admin','Kasir']],
            ['Laporan', app_url('laporan/index.php'), 'laporan', ['Owner','Admin']],
            ['Absensi', app_url('absensi/index.php'), 'absensi', ['Owner','Admin','Gudang','Kasir','HRD']],
            ['Gaji', app_url('gaji/index.php'), 'gaji', ['Owner','Admin','HRD']],
            ['Notifikasi', app_url('notifikasi/index.php'), 'notifikasi', ['Owner','Admin','Gudang','Kasir','HRD']],
            ['Karyawan', app_url('karyawan/index.php'), 'karyawan', ['Owner']],
            ['Pengaturan', app_url('pengaturan/toko.php'), 'pengaturan', ['Owner','Admin']],

            // Menu baru Profil Saya
            ['Profil Saya', app_url('profil/index.php'), 'profil', ['Owner','Admin','Gudang','Kasir','HRD']],

            ['Logout', app_url('auth/logout.php'), 'logout', ['Owner','Admin','Gudang','Kasir','HRD']],
        ];

        $out = [];

        foreach ($items as $i) {
            if (in_array($role, $i[3], true)) {
                $out[] = [
                    'label' => $i[0],
                    'url'   => $i[1],
                    'icon'  => $i[2],
                ];
            }
        }

        return $out;
    }
}

if (!function_exists('ui_head')) {
    function ui_head($title = null) {
        $titleText = $title ? $title . ' - ' . APP_NAME : APP_SEO_TITLE;

        echo '<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>'.h($titleText).'</title>
<meta name="description" content="'.h(APP_SEO_DESCRIPTION).'">
<meta name="theme-color" content="#FF6A00">
<link rel="icon" href="'.h(asset_url('app/favicon.png')).'?v='.h(APP_VERSION).'" type="image/png">
<link rel="apple-touch-icon" href="'.h(asset_url('app/apple-touch-icon.png')).'?v='.h(APP_VERSION).'">
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="'.h(asset_url('app/app-ui.css')).'?v='.h(APP_VERSION).'">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>';
    }
}

if (!function_exists('ui_loader_html')) {
    function ui_loader_html() {
        echo '<div id="globalLoader">
            <div class="loader-card">
                <img src="'.h(asset_url('app/logo-full.png')).'?v='.h(APP_VERSION).'"
                     alt="'.h(APP_NAME).'"
                     class="loader-logo-full">
                <div id="globalLoaderText" class="font-extrabold text-white">'.h(APP_NAME).' memuat...</div>
                <div class="text-sm text-slate-400 mt-1">Mohon tunggu sebentar</div>
                <div class="loader-ring"></div>
            </div>
        </div>';
    }
}

if (!function_exists('ui_flash_html')) {
    function ui_flash_html() {
        echo '<div id="appFlashData"
            data-success="'.h(ui_flash_get('success')).'"
            data-error="'.h(ui_flash_get('error')).'"
            data-warning="'.h(ui_flash_get('warning')).'"
            data-info="'.h(ui_flash_get('info')).'"></div>';
    }
}

if (!function_exists('ui_user_avatar_html')) {
    function ui_user_avatar_html($u) {
        $tenant_id = (int)($u['tenant_id'] ?? 0);
        $foto = trim((string)($u['foto'] ?? ''));

        if ($tenant_id > 0 && $foto !== '') {
            $src = app_url('public/uploads/tenant_'.$tenant_id.'/profil/' . rawurlencode($foto));
            return '<img src="'.h($src).'" alt="Profil" class="w-8 h-8 rounded-full object-cover border border-orange-500/60">';
        }

        return '<span class="w-8 h-8 rounded-full bg-slate-800 border border-slate-700 flex items-center justify-center text-orange-300">'.ui_icon('profil', 'w-4 h-4').'</span>';
    }
}

if (!function_exists('ui_nav')) {
    function ui_nav($pdo, $title = 'Dashboard') {
        $u = ui_current_user();
        $tenantName = ui_get_tenant_name($pdo);
        $unread = notif_count_unread($pdo);
        $items = ui_menu_items();
        $activeTitle = (string)$title;

        echo '<body>';
        ui_loader_html();
        ui_flash_html();

        echo '<div class="app-shell md:flex">
    <aside id="sidebar" class="app-sidebar fixed md:sticky md:top-0 inset-y-0 left-0 z-40 w-[82%] max-w-[230px] bg-slate-950 border-r border-slate-800 transform -translate-x-full md:translate-x-0 transition-transform duration-200 no-print">
        <div class="p-4 border-b border-slate-800">
            <div class="flex items-center">
                <img src="'.h(asset_url('app/logo-full.png')).'?v='.h(APP_VERSION).'"
                     alt="'.h(APP_NAME).'"
                     class="h-14 w-auto max-w-[190px] object-contain">
            </div>
        </div>

        <nav class="p-3 space-y-1">';

        foreach ($items as $it) {
            $isActive = ($activeTitle === $it['label'])
                || ($activeTitle === 'Barang & Stok' && $it['label'] === 'Barang')
                || ($activeTitle === 'Katalog Barang & Gudang' && $it['label'] === 'Barang');

            $class = $isActive
                ? 'flex items-center gap-3 px-3 py-3 rounded-xl bg-orange-600/20 border border-orange-500/40 text-orange-200'
                : 'flex items-center gap-3 px-3 py-3 rounded-xl hover:bg-slate-800 text-slate-200';

            echo '<a href="'.h($it['url']).'" class="'.$class.'">
                    <span class="shrink-0">'.ui_icon($it['icon'], 'w-5 h-5').'</span>
                    <span class="font-semibold">'.h($it['label']).'</span>
                  </a>';
        }

        echo '</nav>
    </aside>

    <div id="sidebarBackdrop" class="hidden fixed inset-0 bg-black/60 z-30 md:hidden no-print"></div>

    <main class="flex-1 min-w-0">
        <header class="sticky top-0 z-20 bg-slate-950/95 backdrop-blur border-b border-slate-800 no-print">
            <div class="flex items-center justify-between gap-3 p-3 md:p-4">
                <div class="flex items-center gap-3 min-w-0">
                    <button id="btnSidebar" type="button" class="md:hidden btn btn-secondary !w-auto !px-3" data-no-loading="1" aria-label="Buka menu">&#9776;</button>

                    <div class="min-w-0">
                        <h1 class="app-title text-xl md:text-2xl font-extrabold text-white truncate">'.h($title).'</h1>
                        <p class="text-xs md:text-sm text-slate-400 truncate">'.h($tenantName).' &middot; '.h(APP_NAME).'</p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <a href="'.h(app_url('notifikasi/index.php')).'" class="relative btn btn-secondary !w-auto !px-3" title="Notifikasi">
                        '.ui_icon('notifikasi', 'w-5 h-5');

        if ($unread > 0) {
            echo '<span class="absolute -top-1 -right-1 min-w-[20px] h-5 px-1 rounded-full bg-red-600 text-white text-xs flex items-center justify-center">'.($unread > 99 ? '99+' : $unread).'</span>';
        }

        echo '</a>

                    <a href="'.h(app_url('profil/index.php')).'" class="btn btn-secondary !w-auto !px-2 md:!px-3 inline-flex items-center gap-2" title="Profil Saya">
                        '.ui_user_avatar_html($u).'
                        <span class="hidden lg:inline max-w-[120px] truncate">'.h($u['nama'] ?: 'Profil Saya').'</span>
                    </a>
                </div>
            </div>
        </header>

        <section class="app-page p-4 md:p-6">';
    }
}

if (!function_exists('ui_footer')) {
    function ui_footer() {
        echo '</section>
    </main>
</div>

<script src="'.h(asset_url('app/app-ui.js')).'?v='.h(APP_VERSION).'"></script>

<script>
(function(){
    const btn = document.getElementById("btnSidebar");
    const sidebar = document.getElementById("sidebar");
    const backdrop = document.getElementById("sidebarBackdrop");

    function openSidebar(){
        if (!sidebar || !backdrop) return;
        sidebar.classList.remove("-translate-x-full");
        backdrop.classList.remove("hidden");
    }

    function closeSidebar(){
        if (!sidebar || !backdrop) return;
        sidebar.classList.add("-translate-x-full");
        backdrop.classList.add("hidden");
    }

    if (btn) btn.addEventListener("click", openSidebar);
    if (backdrop) backdrop.addEventListener("click", closeSidebar);
})();
</script>

</body>
</html>';
    }
}


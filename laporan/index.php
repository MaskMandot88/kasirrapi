<?php
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

require_once '../config/database.php';
require_once '../includes/ui.php';

if (!isset($_SESSION['tenant_id'], $_SESSION['user_id'], $_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

if (!ui_is_role(['Owner','Admin'])) {
    die('Akses ditolak.');
}

$tenant_id = (int)$_SESSION['tenant_id'];
$mulai = $_GET['mulai'] ?? date('Y-m-01');
$selesai = $_GET['selesai'] ?? date('Y-m-d');
$mode = $_GET['mode'] ?? 'ringkas';
$export = $_GET['export'] ?? '';

if (strtotime($mulai) === false) $mulai = date('Y-m-01');
if (strtotime($selesai) === false) $selesai = date('Y-m-d');
if ($selesai < $mulai) $selesai = $mulai;
if (!in_array($mode, ['ringkas','detail'], true)) $mode = 'ringkas';

$subscription = kasirrapi_tenant_subscription($pdo, $tenant_id);
$canExport = kasirrapi_feature_allowed($subscription, 'export');
$canDetail = kasirrapi_feature_allowed($subscription, 'laporan_detail');

if ($export === 'xls' && !$canExport) {
    http_response_code(403);
    die('Export laporan tersedia mulai paket Plus.');
}

if ($mode === 'detail' && !$canDetail) {
    if ($export === 'xls') {
        http_response_code(403);
        die('Mode detail laporan tersedia untuk paket Pro.');
    }
    $_SESSION['flash']['warning'] = 'Mode detail laporan tersedia untuk paket Pro. Saat ini ditampilkan mode ringkas.';
    $mode = 'ringkas';
}

$dtMulai = $mulai . ' 00:00:00';
$dtSelesai = $selesai . ' 23:59:59';

function qval($pdo, $sql, $params, $default = 0) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $v = $stmt->fetchColumn();
        return $v === false || $v === null ? $default : $v;
    } catch (Throwable $e) {
        return $default;
    }
}

function qall($pdo, $sql, $params = []) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
}

function tglid_lap($v) {
    return $v ? date('d/m/Y', strtotime($v)) : '-';
}

function tgljam_lap($v) {
    return $v ? date('d/m/Y H:i', strtotime($v)) : '-';
}

function pct_lap($value) {
    return number_format((float)$value, 2, ',', '.') . '%';
}

function number_lap($value) {
    return number_format((float)$value, 0, ',', '.');
}

function ratio_lap($num, $den) {
    $den = (float)$den;
    return $den == 0.0 ? 0.0 : ((float)$num / $den) * 100;
}

function report_metric($label, $value, $tone = '', $note = '') {
    $toneClass = $tone === 'good' ? 'text-emerald-300' : ($tone === 'bad' ? 'text-red-300' : ($tone === 'warn' ? 'text-amber-300' : 'text-white'));
    echo '<div class="stat-card">
        <div class="label">'.h($label).'</div>
        <div class="text-xl md:text-2xl font-extrabold '.$toneClass.'">'.h($value).'</div>';
    if ($note !== '') {
        echo '<div class="text-xs text-slate-500 mt-1">'.h($note).'</div>';
    }
    echo '</div>';
}

function render_report_table($title, $columns, $rows, $options = []) {
    $description = $options['description'] ?? '';
    $totalColumns = count($columns);

    echo '<h2>'.h($title).'</h2>';
    if ($description !== '') {
        echo '<p>'.h($description).'</p>';
    }
    echo '<table>';
    echo '<thead><tr>';
    foreach ($columns as $column) {
        $align = $column['align'] ?? 'left';
        echo '<th style="text-align:'.$align.'">'.h($column['label']).'</th>';
    }
    echo '</tr></thead><tbody>';

    if (!$rows) {
        echo '<tr><td colspan="'.$totalColumns.'">Tidak ada data.</td></tr>';
    }

    foreach ($rows as $row) {
        echo '<tr>';
        foreach ($columns as $column) {
            $key = $column['key'];
            $align = $column['align'] ?? 'left';
            $type = $column['type'] ?? 'text';
            $value = $row[$key] ?? '';

            if ($type === 'money') {
                $display = rupiah($value);
            } elseif ($type === 'number') {
                $display = number_lap($value);
            } elseif ($type === 'percent') {
                $display = pct_lap($value);
            } elseif ($type === 'date') {
                $display = tglid_lap($value);
            } elseif ($type === 'datetime') {
                $display = tgljam_lap($value);
            } else {
                $display = (string)$value;
            }

            echo '<td style="text-align:'.$align.'">'.h($display).'</td>';
        }
        echo '</tr>';
    }

    echo '</tbody></table>';
}

function render_report_document($report, $mode) {
    echo '<div class="management-report">';
    echo '<div class="report-cover">
        <h1>'.h($report['title']).'</h1>
        <div>Periode: '.h($report['period']).'</div>
        <div>Toko: '.h($report['tenant_name']).'</div>
        <div>Mode: '.h($mode === 'detail' ? 'Detail Profesional' : 'Ringkas Manajemen').'</div>
        <div>Dicetak: '.h(date('d/m/Y H:i')).'</div>
    </div>';

    render_report_table('1. Ringkasan Eksekutif', [
        ['key' => 'indikator', 'label' => 'Indikator'],
        ['key' => 'nilai', 'label' => 'Nilai', 'align' => 'right'],
        ['key' => 'catatan', 'label' => 'Catatan'],
    ], $report['executive_summary']);

    render_report_table('2. KPI Keuangan dan Operasional', [
        ['key' => 'indikator', 'label' => 'KPI'],
        ['key' => 'nilai', 'label' => 'Nilai', 'align' => 'right'],
        ['key' => 'interpretasi', 'label' => 'Interpretasi'],
    ], $report['kpi_rows']);

    render_report_table('3. Rekap Metode Pembayaran', [
        ['key' => 'metode_bayar', 'label' => 'Metode'],
        ['key' => 'jumlah', 'label' => 'Transaksi', 'type' => 'number', 'align' => 'right'],
        ['key' => 'total', 'label' => 'Nilai', 'type' => 'money', 'align' => 'right'],
        ['key' => 'porsi', 'label' => 'Porsi Omzet', 'type' => 'percent', 'align' => 'right'],
    ], $report['payment_rows']);

    render_report_table('4. Rekap Penjualan Harian', [
        ['key' => 'tanggal', 'label' => 'Tanggal', 'type' => 'date'],
        ['key' => 'jumlah', 'label' => 'Transaksi', 'type' => 'number', 'align' => 'right'],
        ['key' => 'gross_sales', 'label' => 'Penjualan Kotor', 'type' => 'money', 'align' => 'right'],
        ['key' => 'diskon', 'label' => 'Diskon', 'type' => 'money', 'align' => 'right'],
        ['key' => 'total', 'label' => 'Omzet Bersih', 'type' => 'money', 'align' => 'right'],
        ['key' => 'laba_kotor', 'label' => 'Laba Kotor', 'type' => 'money', 'align' => 'right'],
    ], $report['daily_rows']);

    render_report_table('5. Produk Terlaris', [
        ['key' => 'nama_barang', 'label' => 'Barang'],
        ['key' => 'qty', 'label' => 'Qty Jual', 'type' => 'number', 'align' => 'right'],
        ['key' => 'qty_stok', 'label' => 'Qty Stok Keluar', 'type' => 'number', 'align' => 'right'],
        ['key' => 'total', 'label' => 'Omzet', 'type' => 'money', 'align' => 'right'],
        ['key' => 'laba_kotor', 'label' => 'Laba Kotor', 'type' => 'money', 'align' => 'right'],
    ], $report['top_products']);

    if ($mode === 'detail') {
        render_report_table('6. Buku Penjualan Detail', [
            ['key' => 'nomor_invoice', 'label' => 'Invoice'],
            ['key' => 'tanggal', 'label' => 'Tanggal', 'type' => 'datetime'],
            ['key' => 'kasir', 'label' => 'Kasir'],
            ['key' => 'pelanggan', 'label' => 'Pelanggan'],
            ['key' => 'metode_bayar', 'label' => 'Metode'],
            ['key' => 'subtotal', 'label' => 'Subtotal', 'type' => 'money', 'align' => 'right'],
            ['key' => 'diskon', 'label' => 'Diskon', 'type' => 'money', 'align' => 'right'],
            ['key' => 'total', 'label' => 'Total', 'type' => 'money', 'align' => 'right'],
            ['key' => 'hpp', 'label' => 'HPP', 'type' => 'money', 'align' => 'right'],
            ['key' => 'laba_kotor', 'label' => 'Laba Kotor', 'type' => 'money', 'align' => 'right'],
        ], $report['transactions']);

        render_report_table('7. Detail Produk Terjual', [
            ['key' => 'nama_barang', 'label' => 'Barang'],
            ['key' => 'kategori', 'label' => 'Kategori'],
            ['key' => 'qty', 'label' => 'Qty Jual', 'type' => 'number', 'align' => 'right'],
            ['key' => 'qty_stok', 'label' => 'Qty Stok Keluar', 'type' => 'number', 'align' => 'right'],
            ['key' => 'total', 'label' => 'Omzet', 'type' => 'money', 'align' => 'right'],
            ['key' => 'hpp', 'label' => 'HPP', 'type' => 'money', 'align' => 'right'],
            ['key' => 'laba_kotor', 'label' => 'Laba Kotor', 'type' => 'money', 'align' => 'right'],
            ['key' => 'margin', 'label' => 'Margin', 'type' => 'percent', 'align' => 'right'],
        ], $report['product_detail']);

        render_report_table('8. Pembelian dan Pengadaan', [
            ['key' => 'tanggal', 'label' => 'Tanggal', 'type' => 'date'],
            ['key' => 'nomor_nota', 'label' => 'Nota'],
            ['key' => 'nama_supplier', 'label' => 'Supplier'],
            ['key' => 'total_pembelian', 'label' => 'Total Pembelian', 'type' => 'money', 'align' => 'right'],
        ], $report['purchase_rows']);

        render_report_table('9. Pembayaran Piutang', [
            ['key' => 'tanggal_bayar', 'label' => 'Tanggal Bayar', 'type' => 'datetime'],
            ['key' => 'nomor_invoice', 'label' => 'Invoice'],
            ['key' => 'nama_pelanggan', 'label' => 'Pelanggan'],
            ['key' => 'metode_bayar', 'label' => 'Metode'],
            ['key' => 'kasir', 'label' => 'Kasir'],
            ['key' => 'nominal_bayar', 'label' => 'Nominal', 'type' => 'money', 'align' => 'right'],
        ], $report['receivable_payments']);

        render_report_table('10. Piutang Aktif dan Risiko Tagihan', [
            ['key' => 'nomor_invoice', 'label' => 'Invoice'],
            ['key' => 'tanggal', 'label' => 'Tanggal', 'type' => 'datetime'],
            ['key' => 'nama_pelanggan', 'label' => 'Pelanggan'],
            ['key' => 'jatuh_tempo', 'label' => 'Jatuh Tempo', 'type' => 'date'],
            ['key' => 'jumlah_piutang', 'label' => 'Nilai Piutang', 'type' => 'money', 'align' => 'right'],
            ['key' => 'total_dibayar', 'label' => 'Dibayar', 'type' => 'money', 'align' => 'right'],
            ['key' => 'sisa_piutang', 'label' => 'Sisa', 'type' => 'money', 'align' => 'right'],
            ['key' => 'umur_hari', 'label' => 'Umur Hari', 'type' => 'number', 'align' => 'right'],
        ], $report['open_receivables']);

        render_report_table('11. Perhatian Stok', [
            ['key' => 'kode_barang', 'label' => 'Kode'],
            ['key' => 'nama_barang', 'label' => 'Barang'],
            ['key' => 'kategori', 'label' => 'Kategori'],
            ['key' => 'stok_gudang', 'label' => 'Stok Kecil', 'type' => 'number', 'align' => 'right'],
            ['key' => 'stok_tampil', 'label' => 'Stok Tampil'],
            ['key' => 'nilai_aset', 'label' => 'Nilai Aset', 'type' => 'money', 'align' => 'right'],
        ], $report['low_stock_rows']);
    }

    echo '</div>';
}

$tenantName = ui_get_tenant_name($pdo);
$periodText = tglid_lap($mulai) . ' s/d ' . tglid_lap($selesai);

$salesAgg = qall($pdo, "
    SELECT
        COUNT(*) jumlah_transaksi,
        COALESCE(SUM(subtotal),0) penjualan_kotor,
        COALESCE(SUM(diskon),0) diskon,
        COALESCE(SUM(total),0) omzet,
        COALESCE(SUM(CASE WHEN metode_bayar='Hutang' THEN total ELSE 0 END),0) hutang_baru,
        COALESCE(SUM(CASE WHEN metode_bayar='Tunai' THEN total ELSE 0 END),0) tunai,
        COALESCE(SUM(CASE WHEN metode_bayar='QRIS' THEN total ELSE 0 END),0) qris,
        COALESCE(SUM(CASE WHEN metode_bayar='Transfer' THEN total ELSE 0 END),0) transfer
    FROM transaksi
    WHERE tenant_id=? AND tanggal BETWEEN ? AND ?
", [$tenant_id, $dtMulai, $dtSelesai]);

$sales = $salesAgg[0] ?? [];
$trx_count = (int)($sales['jumlah_transaksi'] ?? 0);
$penjualan_kotor = (float)($sales['penjualan_kotor'] ?? 0);
$diskon_total = (float)($sales['diskon'] ?? 0);
$omzet = (float)($sales['omzet'] ?? 0);
$hutang_baru = (float)($sales['hutang_baru'] ?? 0);
$tunai = (float)($sales['tunai'] ?? 0);
$qris = (float)($sales['qris'] ?? 0);
$transfer = (float)($sales['transfer'] ?? 0);

$hpp = (float)qval($pdo, "
    SELECT COALESCE(SUM(td.qty_konversi_stok * td.harga_modal_satuan),0)
    FROM transaksi_detail td
    JOIN transaksi t ON t.id = td.transaksi_id
    WHERE t.tenant_id=? AND t.tanggal BETWEEN ? AND ?
", [$tenant_id, $dtMulai, $dtSelesai]);

$laba_kotor = $omzet - $hpp;
$margin_kotor = ratio_lap($laba_kotor, $omzet);
$avg_trx = $trx_count > 0 ? $omzet / $trx_count : 0;

$pembayaran_piutang = (float)qval($pdo, "SELECT COALESCE(SUM(nominal_bayar),0) FROM piutang_pembayaran WHERE tenant_id=? AND tanggal_bayar BETWEEN ? AND ?", [$tenant_id,$dtMulai,$dtSelesai]);
$pembelian = (float)qval($pdo, "SELECT COALESCE(SUM(total_pembelian),0) FROM pembelian WHERE tenant_id=? AND tanggal BETWEEN ? AND ?", [$tenant_id,$mulai,$selesai]);
$sisa_piutang = (float)qval($pdo, "SELECT COALESCE(SUM(sisa_piutang),0) FROM piutang WHERE tenant_id=? AND status='Belum Lunas'", [$tenant_id]);
$piutang_aktif_count = (int)qval($pdo, "SELECT COUNT(*) FROM piutang WHERE tenant_id=? AND status='Belum Lunas'", [$tenant_id]);
$piutang_jatuh_tempo = (float)qval($pdo, "SELECT COALESCE(SUM(sisa_piutang),0) FROM piutang WHERE tenant_id=? AND status='Belum Lunas' AND jatuh_tempo IS NOT NULL AND jatuh_tempo < CURDATE()", [$tenant_id]);
$produk_aktif = (int)qval($pdo, "SELECT COUNT(*) FROM barang WHERE tenant_id=? AND is_aktif=1", [$tenant_id]);
$stok_menipis = (int)qval($pdo, "SELECT COUNT(*) FROM barang WHERE tenant_id=? AND is_aktif=1 AND stok_gudang > 0 AND stok_gudang <= 5", [$tenant_id]);
$stok_habis = (int)qval($pdo, "SELECT COUNT(*) FROM barang WHERE tenant_id=? AND is_aktif=1 AND stok_gudang <= 0", [$tenant_id]);
$nilai_aset = (float)qval($pdo, "SELECT COALESCE(SUM(stok_gudang * harga_beli),0) FROM barang WHERE tenant_id=? AND is_aktif=1", [$tenant_id]);

$kas_penjualan = $tunai + $qris + $transfer;
$kas_masuk = $kas_penjualan + $pembayaran_piutang;
$arus_kas_operasional = $kas_masuk - $pembelian;

$rekap_metode = qall($pdo, "
    SELECT metode_bayar, COUNT(*) jumlah, COALESCE(SUM(total),0) total
    FROM transaksi
    WHERE tenant_id=? AND tanggal BETWEEN ? AND ?
    GROUP BY metode_bayar
    ORDER BY total DESC
", [$tenant_id,$dtMulai,$dtSelesai]);

$payment_rows = array_map(function ($row) use ($omzet) {
    $row['porsi'] = ratio_lap($row['total'] ?? 0, $omzet);
    return $row;
}, $rekap_metode);

$daily_rows = qall($pdo, "
    SELECT
        DATE(t.tanggal) tanggal,
        COUNT(*) jumlah,
        COALESCE(SUM(t.subtotal),0) gross_sales,
        COALESCE(SUM(t.diskon),0) diskon,
        COALESCE(SUM(t.total),0) total,
        COALESCE(SUM(cost.hpp),0) hpp,
        COALESCE(SUM(t.total),0) - COALESCE(SUM(cost.hpp),0) laba_kotor
    FROM transaksi t
    LEFT JOIN (
        SELECT transaksi_id, SUM(qty_konversi_stok * harga_modal_satuan) hpp
        FROM transaksi_detail
        GROUP BY transaksi_id
    ) cost ON cost.transaksi_id = t.id
    WHERE t.tenant_id=? AND t.tanggal BETWEEN ? AND ?
    GROUP BY DATE(t.tanggal)
    ORDER BY tanggal ASC
", [$tenant_id,$dtMulai,$dtSelesai]);

$product_detail = qall($pdo, "
    SELECT
        b.nama_barang,
        COALESCE(b.kategori,'-') kategori,
        COALESCE(SUM(td.qty),0) qty,
        COALESCE(SUM(td.qty_konversi_stok),0) qty_stok,
        COALESCE(SUM(td.subtotal),0) total,
        COALESCE(SUM(td.qty_konversi_stok * td.harga_modal_satuan),0) hpp,
        COALESCE(SUM(td.subtotal),0) - COALESCE(SUM(td.qty_konversi_stok * td.harga_modal_satuan),0) laba_kotor
    FROM transaksi_detail td
    JOIN transaksi t ON t.id = td.transaksi_id
    JOIN barang b ON b.id = td.barang_id
    WHERE t.tenant_id=? AND t.tanggal BETWEEN ? AND ?
    GROUP BY b.id, b.nama_barang, b.kategori
    ORDER BY total DESC, qty_stok DESC
    LIMIT 100
", [$tenant_id,$dtMulai,$dtSelesai]);

foreach ($product_detail as &$row) {
    $row['margin'] = ratio_lap($row['laba_kotor'] ?? 0, $row['total'] ?? 0);
}
unset($row);

$top_products = array_slice($product_detail, 0, 20);

$transactions = qall($pdo, "
    SELECT
        t.nomor_invoice,
        t.tanggal,
        COALESCE(u.nama,'-') kasir,
        COALESCE(p.nama_pelanggan,'Umum') pelanggan,
        t.metode_bayar,
        t.subtotal,
        t.diskon,
        t.total,
        COALESCE(cost.hpp,0) hpp,
        t.total - COALESCE(cost.hpp,0) laba_kotor
    FROM transaksi t
    LEFT JOIN users u ON u.id = t.kasir_id
    LEFT JOIN pelanggan p ON p.id = t.pelanggan_id
    LEFT JOIN (
        SELECT transaksi_id, SUM(qty_konversi_stok * harga_modal_satuan) hpp
        FROM transaksi_detail
        GROUP BY transaksi_id
    ) cost ON cost.transaksi_id = t.id
    WHERE t.tenant_id=? AND t.tanggal BETWEEN ? AND ?
    ORDER BY t.tanggal DESC
    LIMIT 500
", [$tenant_id,$dtMulai,$dtSelesai]);

$purchase_rows = qall($pdo, "
    SELECT p.tanggal, COALESCE(p.nomor_nota,'-') nomor_nota, COALESCE(s.nama_supplier,'-') nama_supplier, p.total_pembelian
    FROM pembelian p
    LEFT JOIN suppliers s ON s.id = p.supplier_id
    WHERE p.tenant_id=? AND p.tanggal BETWEEN ? AND ?
    ORDER BY p.tanggal DESC, p.id DESC
    LIMIT 300
", [$tenant_id,$mulai,$selesai]);

$receivable_payments = qall($pdo, "
    SELECT pp.tanggal_bayar, pi.nomor_invoice, COALESCE(pl.nama_pelanggan,'-') nama_pelanggan, pp.metode_bayar, COALESCE(u.nama,'-') kasir, pp.nominal_bayar
    FROM piutang_pembayaran pp
    JOIN piutang pi ON pi.id = pp.piutang_id
    LEFT JOIN pelanggan pl ON pl.id = pi.pelanggan_id
    LEFT JOIN users u ON u.id = pp.kasir_id
    WHERE pp.tenant_id=? AND pp.tanggal_bayar BETWEEN ? AND ?
    ORDER BY pp.tanggal_bayar DESC
    LIMIT 300
", [$tenant_id,$dtMulai,$dtSelesai]);

$open_receivables = qall($pdo, "
    SELECT
        pi.nomor_invoice,
        pi.tanggal,
        COALESCE(pl.nama_pelanggan,'-') nama_pelanggan,
        pi.jatuh_tempo,
        pi.jumlah_piutang,
        pi.total_dibayar,
        pi.sisa_piutang,
        GREATEST(DATEDIFF(CURDATE(), DATE(pi.tanggal)), 0) umur_hari
    FROM piutang pi
    LEFT JOIN pelanggan pl ON pl.id = pi.pelanggan_id
    WHERE pi.tenant_id=? AND pi.status='Belum Lunas'
    ORDER BY pi.jatuh_tempo IS NULL ASC, pi.jatuh_tempo ASC, pi.sisa_piutang DESC
    LIMIT 300
", [$tenant_id]);

$low_stock_rows = qall($pdo, "
    SELECT kode_barang, nama_barang, COALESCE(kategori,'-') kategori, stok_gudang, harga_beli, satuan, isi_per_kemasan, satuan_ecer, (stok_gudang * harga_beli) nilai_aset
    FROM barang
    WHERE tenant_id=? AND is_aktif=1 AND stok_gudang <= 5
    ORDER BY stok_gudang ASC, nama_barang ASC
    LIMIT 200
", [$tenant_id]);

foreach ($low_stock_rows as &$row) {
    $row['stok_tampil'] = ui_format_stok_bertingkat($row['stok_gudang'] ?? 0, $row['satuan'] ?? '', $row['isi_per_kemasan'] ?? 1, $row['satuan_ecer'] ?? '');
}
unset($row);

$cashHealth = $arus_kas_operasional >= 0 ? 'Positif' : 'Negatif';
$marginStatus = $margin_kotor >= 20 ? 'Baik' : ($margin_kotor >= 10 ? 'Perlu dipantau' : 'Rendah');
$receivableStatus = $piutang_jatuh_tempo > 0 ? 'Ada piutang jatuh tempo' : 'Terkendali';
$stockStatus = ($stok_habis + $stok_menipis) > 0 ? 'Perlu restock' : 'Aman';

$executive_summary = [
    ['indikator' => 'Kinerja penjualan', 'nilai' => rupiah($omzet), 'catatan' => $trx_count . ' transaksi dengan rata-rata ' . rupiah($avg_trx) . ' per transaksi.'],
    ['indikator' => 'Profitabilitas kotor', 'nilai' => rupiah($laba_kotor) . ' (' . pct_lap($margin_kotor) . ')', 'catatan' => 'Status margin: ' . $marginStatus . '.'],
    ['indikator' => 'Arus kas operasional', 'nilai' => rupiah($arus_kas_operasional), 'catatan' => 'Kas masuk dikurangi pembelian barang. Status: ' . $cashHealth . '.'],
    ['indikator' => 'Piutang aktif', 'nilai' => rupiah($sisa_piutang), 'catatan' => $piutang_aktif_count . ' invoice aktif. ' . $receivableStatus . '.'],
    ['indikator' => 'Persediaan', 'nilai' => rupiah($nilai_aset), 'catatan' => $produk_aktif . ' produk aktif. ' . $stok_menipis . ' menipis, ' . $stok_habis . ' habis.'],
];

$kpi_rows = [
    ['indikator' => 'Penjualan kotor', 'nilai' => rupiah($penjualan_kotor), 'interpretasi' => 'Nilai sebelum diskon.'],
    ['indikator' => 'Diskon penjualan', 'nilai' => rupiah($diskon_total), 'interpretasi' => pct_lap(ratio_lap($diskon_total, $penjualan_kotor)) . ' dari penjualan kotor.'],
    ['indikator' => 'Omzet bersih', 'nilai' => rupiah($omzet), 'interpretasi' => 'Total penjualan setelah diskon.'],
    ['indikator' => 'HPP', 'nilai' => rupiah($hpp), 'interpretasi' => 'Perkiraan modal barang terjual dari detail transaksi.'],
    ['indikator' => 'Laba kotor', 'nilai' => rupiah($laba_kotor), 'interpretasi' => 'Margin kotor ' . pct_lap($margin_kotor) . '.'],
    ['indikator' => 'Kas penjualan diterima', 'nilai' => rupiah($kas_penjualan), 'interpretasi' => 'Tunai + QRIS + Transfer, tidak termasuk transaksi Hutang.'],
    ['indikator' => 'Pembayaran piutang', 'nilai' => rupiah($pembayaran_piutang), 'interpretasi' => 'Kas masuk dari pelunasan/cicilan piutang.'],
    ['indikator' => 'Pembelian barang', 'nilai' => rupiah($pembelian), 'interpretasi' => 'Pengeluaran pembelian barang pada periode laporan.'],
    ['indikator' => 'Arus kas operasional', 'nilai' => rupiah($arus_kas_operasional), 'interpretasi' => $cashHealth . '.'],
    ['indikator' => 'Nilai aset persediaan', 'nilai' => rupiah($nilai_aset), 'interpretasi' => 'Estimasi stok akhir berdasarkan harga beli saat ini.'],
];

$report = [
    'title' => 'Laporan Manajemen Toko',
    'tenant_name' => $tenantName,
    'period' => $periodText,
    'executive_summary' => $executive_summary,
    'kpi_rows' => $kpi_rows,
    'payment_rows' => $payment_rows,
    'daily_rows' => $daily_rows,
    'top_products' => $top_products,
    'transactions' => $transactions,
    'product_detail' => $product_detail,
    'purchase_rows' => $purchase_rows,
    'receivable_payments' => $receivable_payments,
    'open_receivables' => $open_receivables,
    'low_stock_rows' => $low_stock_rows,
];

if ($export === 'xls') {
    $filename = 'laporan_' . $mode . '_' . $mulai . '_' . $selesai . '.xls';
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    header('Cache-Control: max-age=0');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
        body{font-family:Arial,sans-serif;color:#111}
        h1{font-size:22px;margin:0 0 6px}
        h2{font-size:16px;margin:22px 0 8px;background:#f97316;color:#fff;padding:7px}
        p{margin:0 0 8px;color:#555}
        table{border-collapse:collapse;width:100%;margin-bottom:12px}
        th,td{border:1px solid #999;padding:6px;font-size:12px;vertical-align:top}
        th{background:#111827;color:#fff;font-weight:bold}
        .report-cover{margin-bottom:14px}
    </style></head><body>';
    render_report_document($report, $mode);
    echo '</body></html>';
    exit;
}

ui_head('Laporan');
ui_nav($pdo, 'Laporan');
?>

<style>
    .report-grid{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:1rem}
    .report-panel{background:rgba(15,23,42,.96);border:1px solid #1e293b;border-radius:1rem;padding:1rem}
    .report-panel h3{font-size:1rem;font-weight:900;color:#fff;margin:0 0 .75rem}
    .report-table-screen{width:100%;border-collapse:separate;border-spacing:0;font-size:.875rem}
    .report-table-screen th{color:#94a3b8;font-size:.75rem;text-transform:uppercase;letter-spacing:.04em;text-align:left;padding:.65rem;border-bottom:1px solid #1e293b}
    .report-table-screen td{padding:.7rem .65rem;border-bottom:1px solid rgba(30,41,59,.72);vertical-align:top}
    .report-table-screen tr:last-child td{border-bottom:0}
    .report-badge{display:inline-flex;align-items:center;border-radius:999px;padding:.28rem .55rem;font-size:.72rem;font-weight:900;background:#1e293b;color:#cbd5e1}
    .report-badge.good{background:rgba(16,185,129,.14);color:#86efac}
    .report-badge.warn{background:rgba(245,158,11,.14);color:#fcd34d}
    .report-badge.bad{background:rgba(239,68,68,.14);color:#fca5a5}
    .management-report{display:grid;gap:14px}
    .management-report .report-cover{border-bottom:2px solid #111827;padding-bottom:10px;margin-bottom:6px}
    .management-report h1{font-size:22px;margin:0 0 6px;color:#111827}
    .management-report h2{font-size:15px;margin:18px 0 7px;color:#111827}
    .management-report p{margin:0 0 8px;color:#475569}
    .management-report table{width:100%;border-collapse:collapse;margin-bottom:12px;page-break-inside:auto}
    .management-report th,.management-report td{border:1px solid #cbd5e1;padding:6px;font-size:11px;vertical-align:top;color:#111827}
    .management-report th{background:#111827;color:#fff}
    .print-report{display:none}
    @media(max-width:768px){
        .report-grid{grid-template-columns:1fr}
        .report-panel{padding:.9rem}
        .report-table-screen{font-size:.8rem}
        .report-table-wrap{overflow:auto}
    }
    @media print{
        @page{size:A4 landscape;margin:10mm}
        .screen-report,.no-print{display:none!important}
        .print-report{display:block!important}
        .app-page{padding:0!important}
        body{background:#fff!important;color:#111!important}
        .management-report{display:block}
        .management-report h1{font-size:20px}
        .management-report h2{page-break-after:avoid}
        .management-report table{page-break-inside:auto}
        .management-report tr{page-break-inside:avoid;page-break-after:auto}
    }
</style>

<div class="screen-report">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
        <div>
            <h2 class="text-2xl font-bold text-emerald-400">Laporan Manajemen Toko</h2>
            <p class="text-slate-400">
                <?= h($periodText) ?>. Mode ringkas untuk keputusan cepat, mode detail untuk audit operasional dan keuangan.
            </p>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-2 no-print">
            <?php if ($canDetail || $mode === 'detail'): ?>
                <a href="?mulai=<?= h($mulai) ?>&selesai=<?= h($selesai) ?>&mode=<?= $mode === 'ringkas' ? 'detail' : 'ringkas' ?>" class="btn btn-secondary">
                    <?= $mode === 'ringkas' ? 'Mode Detail' : 'Mode Ringkas' ?>
                </a>
            <?php else: ?>
                <span class="btn btn-secondary opacity-60 cursor-not-allowed" title="Mode detail tersedia untuk paket Pro">Detail Pro</span>
            <?php endif; ?>
            <?php if ($canExport): ?>
                <a href="?mulai=<?= h($mulai) ?>&selesai=<?= h($selesai) ?>&mode=<?= h($mode) ?>&export=xls" class="btn btn-primary" data-no-loading="1" download>
                    Export Excel
                </a>
            <?php else: ?>
                <span class="btn btn-primary opacity-60 cursor-not-allowed" title="Export tersedia mulai paket Plus">Export Plus</span>
            <?php endif; ?>
            <button type="button" onclick="window.print()" class="btn btn-secondary">PDF / Print</button>
            <a href="<?= h(app_url('laporan/stok.php')) ?>" class="btn btn-secondary">Laporan Stok</a>
        </div>
    </div>

    <?php if (!empty($_SESSION['flash']['warning'])): ?>
        <div class="mb-4 p-3 bg-amber-900/40 border border-amber-500/60 text-amber-100 rounded-xl no-print">
            <?= h($_SESSION['flash']['warning']); unset($_SESSION['flash']['warning']); ?>
        </div>
    <?php endif; ?>

    <form method="GET" class="app-card p-3 mb-5 grid grid-cols-1 md:grid-cols-[1fr_1fr_auto_auto] gap-2 no-print">
        <input type="hidden" name="mode" value="<?= h($mode) ?>">
        <label><span class="label">Mulai</span><input type="date" name="mulai" value="<?= h($mulai) ?>" class="app-input mt-1"></label>
        <label><span class="label">Selesai</span><input type="date" name="selesai" value="<?= h($selesai) ?>" class="app-input mt-1"></label>
        <button class="btn btn-secondary self-end">Terapkan</button>
        <a href="?mode=<?= h($mode) ?>" class="btn btn-secondary self-end" data-no-loading="1">Bulan Ini</a>
    </form>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
        <?php
        report_metric('Omzet Bersih', rupiah($omzet), 'good', $trx_count . ' transaksi');
        report_metric('Laba Kotor', rupiah($laba_kotor), $laba_kotor >= 0 ? 'good' : 'bad', 'Margin ' . pct_lap($margin_kotor));
        report_metric('Kas Masuk', rupiah($kas_masuk), 'good', 'Penjualan + piutang');
        report_metric('Arus Kas Operasional', rupiah($arus_kas_operasional), $arus_kas_operasional >= 0 ? 'good' : 'bad', 'Kas masuk - pembelian');
        report_metric('Penjualan Kotor', rupiah($penjualan_kotor), '', 'Sebelum diskon');
        report_metric('Diskon', rupiah($diskon_total), $diskon_total > 0 ? 'warn' : '', pct_lap(ratio_lap($diskon_total, $penjualan_kotor)) . ' dari kotor');
        report_metric('Piutang Aktif', rupiah($sisa_piutang), $sisa_piutang > 0 ? 'warn' : 'good', $piutang_aktif_count . ' invoice');
        report_metric('Nilai Persediaan', rupiah($nilai_aset), '', $produk_aktif . ' produk aktif');
        report_metric('Tunai', rupiah($tunai), '', 'Penjualan diterima');
        report_metric('QRIS', rupiah($qris), '', 'Penjualan diterima');
        report_metric('Transfer', rupiah($transfer), '', 'Penjualan diterima');
        report_metric('Hutang Baru', rupiah($hutang_baru), $hutang_baru > 0 ? 'warn' : '', 'Penjualan kredit');
        ?>
    </div>

    <div class="report-grid mb-6">
        <section class="report-panel col-span-12 lg:col-span-5">
            <div class="flex items-center justify-between gap-3 mb-3">
                <h3>Ringkasan Eksekutif</h3>
                <span class="report-badge <?= $arus_kas_operasional >= 0 ? 'good' : 'bad' ?>"><?= h($cashHealth) ?></span>
            </div>
            <div class="grid gap-3">
                <?php foreach ($executive_summary as $row): ?>
                    <div class="bg-slate-950 rounded-xl p-3 border border-slate-800">
                        <div class="text-sm text-slate-400"><?= h($row['indikator']) ?></div>
                        <div class="font-extrabold text-white mt-1"><?= h($row['nilai']) ?></div>
                        <div class="text-sm text-slate-500 mt-1"><?= h($row['catatan']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="report-panel col-span-12 lg:col-span-7">
            <h3>KPI Manajemen</h3>
            <div class="report-table-wrap">
                <table class="report-table-screen">
                    <thead><tr><th>KPI</th><th class="text-right">Nilai</th><th>Interpretasi</th></tr></thead>
                    <tbody>
                    <?php foreach ($kpi_rows as $row): ?>
                        <tr>
                            <td class="font-bold text-white"><?= h($row['indikator']) ?></td>
                            <td class="text-right font-bold"><?= h($row['nilai']) ?></td>
                            <td class="text-slate-400"><?= h($row['interpretasi']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div class="report-grid mb-6">
        <section class="report-panel col-span-12 lg:col-span-4">
            <h3>Metode Pembayaran</h3>
            <div class="mobile-card-list">
                <?php foreach ($payment_rows as $row): ?>
                    <div class="bg-slate-950 rounded-xl p-3 flex justify-between gap-3 border border-slate-800">
                        <div>
                            <b><?= h($row['metode_bayar'] ?: '-') ?></b>
                            <div class="text-sm text-slate-500"><?= number_lap($row['jumlah']) ?> transaksi &middot; <?= pct_lap($row['porsi']) ?></div>
                        </div>
                        <div class="font-bold text-right"><?= rupiah($row['total']) ?></div>
                    </div>
                <?php endforeach; ?>
                <?php if (!$payment_rows): ?><div class="text-slate-500">Tidak ada data.</div><?php endif; ?>
            </div>
        </section>

        <section class="report-panel col-span-12 lg:col-span-8">
            <h3>Rekap Harian</h3>
            <div class="report-table-wrap">
                <table class="report-table-screen">
                    <thead>
                        <tr>
                            <th>Tanggal</th><th class="text-right">Trx</th><th class="text-right">Omzet</th><th class="text-right">Laba Kotor</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($daily_rows as $row): ?>
                        <tr>
                            <td class="font-bold text-white"><?= h(tglid_lap($row['tanggal'])) ?></td>
                            <td class="text-right"><?= number_lap($row['jumlah']) ?></td>
                            <td class="text-right font-bold"><?= rupiah($row['total']) ?></td>
                            <td class="text-right <?= $row['laba_kotor'] >= 0 ? 'text-emerald-300' : 'text-red-300' ?>"><?= rupiah($row['laba_kotor']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$daily_rows): ?><tr><td colspan="4" class="text-slate-500">Tidak ada data.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <section class="report-panel mb-6">
        <h3>Produk Terlaris</h3>
        <div class="report-table-wrap">
            <table class="report-table-screen">
                <thead>
                    <tr>
                        <th>Barang</th><th class="text-right">Qty Jual</th><th class="text-right">Qty Stok Keluar</th><th class="text-right">Omzet</th><th class="text-right">Laba Kotor</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($top_products as $row): ?>
                    <tr>
                        <td class="font-bold text-white"><?= h($row['nama_barang']) ?></td>
                        <td class="text-right"><?= number_lap($row['qty']) ?></td>
                        <td class="text-right"><?= number_lap($row['qty_stok']) ?></td>
                        <td class="text-right font-bold"><?= rupiah($row['total']) ?></td>
                        <td class="text-right <?= $row['laba_kotor'] >= 0 ? 'text-emerald-300' : 'text-red-300' ?>"><?= rupiah($row['laba_kotor']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$top_products): ?><tr><td colspan="5" class="text-slate-500">Tidak ada data.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <?php if ($mode === 'detail'): ?>
        <section class="report-panel mb-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2 mb-3">
                <h3>Buku Penjualan Detail</h3>
                <span class="text-xs text-slate-500">Maksimal 500 transaksi terbaru pada periode ini.</span>
            </div>
            <div class="report-table-wrap">
                <table class="report-table-screen">
                    <thead>
                        <tr>
                            <th>Invoice</th><th>Tanggal</th><th>Kasir</th><th>Pelanggan</th><th>Metode</th><th class="text-right">Total</th><th class="text-right">HPP</th><th class="text-right">Laba</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($transactions as $row): ?>
                        <tr>
                            <td class="font-bold text-white"><?= h($row['nomor_invoice']) ?></td>
                            <td><?= h(tgljam_lap($row['tanggal'])) ?></td>
                            <td><?= h($row['kasir']) ?></td>
                            <td><?= h($row['pelanggan']) ?></td>
                            <td><?= h($row['metode_bayar']) ?></td>
                            <td class="text-right font-bold"><?= rupiah($row['total']) ?></td>
                            <td class="text-right"><?= rupiah($row['hpp']) ?></td>
                            <td class="text-right <?= $row['laba_kotor'] >= 0 ? 'text-emerald-300' : 'text-red-300' ?>"><?= rupiah($row['laba_kotor']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$transactions): ?><tr><td colspan="8" class="text-slate-500">Tidak ada data.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <div class="report-grid mb-6">
            <section class="report-panel col-span-12 lg:col-span-6">
                <h3>Pembelian Barang</h3>
                <div class="report-table-wrap">
                    <table class="report-table-screen">
                        <thead><tr><th>Tanggal</th><th>Nota</th><th>Supplier</th><th class="text-right">Total</th></tr></thead>
                        <tbody>
                        <?php foreach ($purchase_rows as $row): ?>
                            <tr><td><?= h(tglid_lap($row['tanggal'])) ?></td><td><?= h($row['nomor_nota']) ?></td><td><?= h($row['nama_supplier']) ?></td><td class="text-right font-bold"><?= rupiah($row['total_pembelian']) ?></td></tr>
                        <?php endforeach; ?>
                        <?php if (!$purchase_rows): ?><tr><td colspan="4" class="text-slate-500">Tidak ada data.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="report-panel col-span-12 lg:col-span-6">
                <h3>Pembayaran Piutang</h3>
                <div class="report-table-wrap">
                    <table class="report-table-screen">
                        <thead><tr><th>Tanggal</th><th>Invoice</th><th>Pelanggan</th><th class="text-right">Nominal</th></tr></thead>
                        <tbody>
                        <?php foreach ($receivable_payments as $row): ?>
                            <tr><td><?= h(tgljam_lap($row['tanggal_bayar'])) ?></td><td><?= h($row['nomor_invoice']) ?></td><td><?= h($row['nama_pelanggan']) ?></td><td class="text-right font-bold"><?= rupiah($row['nominal_bayar']) ?></td></tr>
                        <?php endforeach; ?>
                        <?php if (!$receivable_payments): ?><tr><td colspan="4" class="text-slate-500">Tidak ada data.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <div class="report-grid mb-6">
            <section class="report-panel col-span-12 lg:col-span-6">
                <h3>Piutang Aktif</h3>
                <div class="report-table-wrap">
                    <table class="report-table-screen">
                        <thead><tr><th>Invoice</th><th>Pelanggan</th><th>Jatuh Tempo</th><th class="text-right">Sisa</th></tr></thead>
                        <tbody>
                        <?php foreach (array_slice($open_receivables, 0, 20) as $row): ?>
                            <tr><td class="font-bold text-white"><?= h($row['nomor_invoice']) ?></td><td><?= h($row['nama_pelanggan']) ?></td><td><?= h(tglid_lap($row['jatuh_tempo'])) ?></td><td class="text-right font-bold"><?= rupiah($row['sisa_piutang']) ?></td></tr>
                        <?php endforeach; ?>
                        <?php if (!$open_receivables): ?><tr><td colspan="4" class="text-slate-500">Tidak ada data.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="report-panel col-span-12 lg:col-span-6">
                <h3>Perhatian Stok</h3>
                <div class="report-table-wrap">
                    <table class="report-table-screen">
                        <thead><tr><th>Barang</th><th>Stok</th><th class="text-right">Nilai Aset</th></tr></thead>
                        <tbody>
                        <?php foreach (array_slice($low_stock_rows, 0, 20) as $row): ?>
                            <tr><td class="font-bold text-white"><?= h($row['nama_barang']) ?></td><td><?= h($row['stok_tampil']) ?></td><td class="text-right font-bold"><?= rupiah($row['nilai_aset']) ?></td></tr>
                        <?php endforeach; ?>
                        <?php if (!$low_stock_rows): ?><tr><td colspan="3" class="text-slate-500">Tidak ada data.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    <?php endif; ?>
</div>

<div class="print-report">
    <?php render_report_document($report, $mode); ?>
</div>

<?php ui_footer(); ?>

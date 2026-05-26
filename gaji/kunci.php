<?php
require_once '_auth.php';

$id = (int)($_POST['id'] ?? 0);

$stmt = $pdo->prepare("SELECT status FROM payroll_periode WHERE tenant_id = ? AND id = ? LIMIT 1");
$stmt->execute([$tenant_id, $id]);
$p = $stmt->fetch();

if (!$p) redirect_with('index.php', 'error', 'Periode tidak ditemukan.');
if ($p['status'] !== 'Draft') redirect_with('detail.php?id=' . $id, 'error', 'Periode sudah tidak berstatus Draft.');

$stmt = $pdo->prepare("UPDATE payroll_periode SET status = 'Dikunci' WHERE tenant_id = ? AND id = ?");
$stmt->execute([$tenant_id, $id]);

redirect_with('detail.php?id=' . $id, 'success', 'Periode berhasil dikunci.');

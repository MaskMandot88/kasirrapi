<?php
// barang/batal_nota.php
session_start();
unset($_SESSION['current_pembelian_id']);
unset($_SESSION['current_nomor_nota']);
header("Location: index.php");
exit;
?>

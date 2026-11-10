<?php
/**
 * BütçeTakip — Fatura / Harcama Durumu Güncelleme (durum.php)
 *
 * Tablodaki veya yaklaşan ödemelerdeki durum etiketine tıklandığında açılan
 * mini menüden (Ödendi / Ödenecek / Ertelendi) gelen POST isteğini işler.
 */

require_once 'auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

if (!csrf_dogrula($_POST['csrf'] ?? '')) {
    header('Location: index.php?hata=oturum');
    exit;
}

$kayit_id   = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$yeni_durum = isset($_POST['durum']) ? trim($_POST['durum']) : '';
$gecerli    = ['Ödendi', 'Ödenecek', 'Ertelendi'];

if ($kayit_id > 0 && in_array($yeni_durum, $gecerli, true)) {
    kayit_durum_guncelle($kullanici_id, $kayit_id, $yeni_durum);
}

header('Location: index.php?durum_degisti=' . urlencode($yeni_durum));
exit;

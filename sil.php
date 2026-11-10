<?php
/**
 * BütçeTakip — Kayıt Silme (sil.php)
 *
 * Yalnızca POST isteğini kabul eder (GET ile adres çubuğundan silme yapılamaz).
 * CSRF anahtarı doğrulanır, ardından ilgili ID kullanıcının veritabanı kayıtlarından silinir.
 */

require_once 'auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id'])) {
    header('Location: index.php');
    exit;
}

if (!csrf_dogrula($_POST['csrf'] ?? '')) {
    header('Location: index.php?hata=oturum');
    exit;
}

kayit_sil($kullanici_id, (int)$_POST['id']);

header('Location: index.php?silindi=1');
exit;

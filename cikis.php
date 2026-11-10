<?php
/**
 * BütçeTakip — Çıkış (cikis.php)
 * Oturumdaki tüm bilgileri siler ve giriş ekranına döner.
 */

session_start();

$_SESSION = [];          // Oturum değişkenlerini temizle
session_destroy();       // Sunucudaki oturum dosyasını sil

header('Location: giris.php?cikis=1');
exit;

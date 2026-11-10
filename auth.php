<?php
/**
 * BütçeTakip — Giriş Koruması (auth.php)
 *
 * Korunması gereken her sayfanın EN ÜSTÜNDE require edilir:
 *     require_once 'auth.php';
 *
 * Görevleri:
 *   1. Oturumu başlatır, fonksiyonları ve veritabanı bağlantısını yükler.
 *   2. Oturumda kullanıcı ID'si yoksa giris.php'ye yönlendirir.
 *   3. Kullanıcının güncel bilgilerini HER İSTEKTE veritabanından okur
 *      (profil düzenlenince değişiklik anında yansısın diye).
 *   4. Sayfaların kullanacağı $aktif_kullanici, $kullanici_id, $kullanici_adi değişkenlerini hazırlar.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/functions.php';

if (empty($_SESSION['kullanici_id'])) {
    header('Location: giris.php?gerekli=1');
    exit;
}

$aktif_kullanici = kullanici_getir((int)$_SESSION['kullanici_id']);

// Kullanıcı veritabanından silinmişse oturumu kapat
if ($aktif_kullanici === null) {
    $_SESSION = [];
    session_destroy();
    header('Location: giris.php?gerekli=1');
    exit;
}

$kullanici_id  = (int)$aktif_kullanici['id'];
$kullanici_adi = $aktif_kullanici['kullanici_adi'];

// Görünen ad ve selamlamada kullanılacak ilk isim
$aktif_kullanici['gorunen_ad'] = $aktif_kullanici['ad_soyad'];
$aktif_kullanici['ilk_ad']     = explode(' ', trim($aktif_kullanici['ad_soyad']))[0];

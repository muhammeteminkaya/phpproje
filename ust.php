<?php
/**
 * Ortak sayfa üst kısmı: <head>, yan menü ve ana alanın açılışı.
 *
 * Kullanmadan önce sayfada şu değişkenler tanımlanmalı:
 *   $sayfa_basligi  → tarayıcı sekmesinde görünecek başlık
 *   $aktif_sayfa    → 'panel' | 'ekle' | 'rapor' | 'profil' (menüde vurgulanır)
 *
 * Tekrarı önlemek için tüm iç sayfalar bu dosyayı include eder.
 */

$menu = [
    'panel'  => ['adres' => 'index.php',  'no' => '01', 'ad' => 'Panel'],
    'ekle'   => ['adres' => 'ekle.php',   'no' => '02', 'ad' => 'Yeni kayıt'],
    'rapor'  => ['adres' => 'rapor.php',  'no' => '03', 'ad' => 'Rapor'],
    'profil' => ['adres' => 'profil.php', 'no' => '04', 'ad' => 'Profil & Maaş'],
];

$bas_harf = mb_strtoupper(mb_substr($aktif_kullanici['gorunen_ad'], 0, 1, 'UTF-8'), 'UTF-8');
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= guvenli($sayfa_basligi) ?> — BütçeTakip</title>
    <script>
        if (localStorage.getItem('tema') === 'gece') document.documentElement.classList.add('gece');
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,600;0,9..144,800;1,9..144,400&family=IBM+Plex+Mono:wght@400;500;600&family=Instrument+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css?v=<?= filemtime(__DIR__ . '/../assets/style.css') ?>">
</head>
<body>

<div class="duzen">

    <!-- YAN MENÜ -->
    <aside class="yan-menu">
        <a href="index.php" class="marka-ust">
            <span class="logo-muhur">B</span>
            <span class="marka-adi">BütçeTakip</span>
        </a>

        <nav class="menu-listesi">
            <span class="menu-baslik">Defter</span>
            <?php foreach ($menu as $anahtar => $oge): ?>
                <a href="<?= $oge['adres'] ?>" class="menu-oge <?= $aktif_sayfa === $anahtar ? 'aktif' : '' ?>">
                    <span class="menu-no"><?= $oge['no'] ?></span>
                    <?= guvenli($oge['ad']) ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="menu-alt">
            <!-- Yan menüde hızlı maaş özeti -->
            <a href="profil.php" class="yan-maas-kutu" title="Maaş ve limiti düzenle">
                <span class="ust-etiket">Aylık Maaş</span>
                <strong><?= para_formati($aktif_kullanici['aylik_maas']) ?></strong>
                <small>Limit: <?= para_formati($aktif_kullanici['aylik_limit']) ?> · Düzenle →</small>
            </a>

            <button type="button" class="tema-dugme" onclick="temaDegistir()" title="Gece / gündüz">
                <span class="tema-ikon-gunduz">☾ Gece modu</span>
                <span class="tema-ikon-gece">☀ Gündüz modu</span>
            </button>

            <div class="kullanici-karti">
                <a href="profil.php" class="avatar" title="Profili düzenle"><?= guvenli($bas_harf) ?></a>
                <a href="profil.php" class="kullanici-bilgi" style="text-decoration:none">
                    <strong><?= guvenli($aktif_kullanici['gorunen_ad']) ?></strong>
                    <small>@<?= guvenli($kullanici_adi) ?></small>
                </a>
                <a href="cikis.php" class="cikis-baglanti" title="Çıkış yap">Çıkış</a>
            </div>
        </div>
    </aside>

    <!-- ANA İÇERİK -->
    <main class="ana-alan">

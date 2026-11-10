<?php
/**
 * BütçeTakip — Giriş ve Kayıt Ekranı (giris.php)
 *
 * Aynı sayfada iki sekme vardır:
 *   - Giriş yap  (giris.php)           → islem=giris
 *   - Kayıt ol   (giris.php?mod=kayit) → islem=kayit
 *
 * Giriş akışı:
 *   1. Alanlar boş mu kontrol edilir.
 *   2. kullanici_dogrula() veritabanındaki hash ile password_verify() yapar.
 *   3. Başarılıysa oturum kimliği yenilenir ve kullanıcı ID'si $_SESSION'a yazılır.
 *
 * Kayıt akışı:
 *   1. Tüm alanlar PHP tarafında doğrulanır (boşluk, uzunluk, biçim, şifre eşleşmesi, benzersiz kullanıcı adı).
 *   2. Kullanıcı veritabanına eklenir (şifre password_hash ile).
 *   3. Otomatik giriş yapılır ve panele yönlendirilir.
 */

session_start();
require_once 'functions.php';

// Zaten giriş yapılmışsa bu sayfayı gösterme
if (!empty($_SESSION['kullanici_id'])) {
    header('Location: index.php');
    exit;
}

$mod     = (isset($_GET['mod']) && $_GET['mod'] === 'kayit') ? 'kayit' : 'giris';
$hata    = '';          // Giriş hatası
$hatalar = [];          // Kayıt hataları (alan adına göre)
$deger   = ['kullanici_adi' => '', 'ad_soyad' => '', 'eposta' => '', 'aylik_maas' => '', 'aylik_limit' => ''];

/**
 * Oturumu açıp panele gönderen küçük yardımcı (giriş ve kayıt ortak kullanır).
 */
function oturum_ac_ve_yonlendir($kullanici_id, $mesaj) {
    session_regenerate_id(true); // Oturum sabitleme (session fixation) saldırısına karşı
    $_SESSION['kullanici_id'] = $kullanici_id;
    header('Location: index.php?' . $mesaj . '=1');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $islem = $_POST['islem'] ?? 'giris';

    /* ------------------------- GİRİŞ ------------------------- */
    if ($islem === 'giris') {
        $mod = 'giris';
        $deger['kullanici_adi'] = trim($_POST['kullanici_adi'] ?? '');
        $sifre = $_POST['sifre'] ?? '';

        if ($deger['kullanici_adi'] === '' || $sifre === '') {
            $hata = 'Kullanıcı adı ve şifre boş bırakılamaz.';
        } else {
            $id = kullanici_dogrula($deger['kullanici_adi'], $sifre);
            if ($id !== null) {
                oturum_ac_ve_yonlendir($id, 'hosgeldin');
            }
            $hata = 'Kullanıcı adı veya şifre hatalı.';
        }
    }

    /* ------------------------- KAYIT ------------------------- */
    if ($islem === 'kayit') {
        $mod = 'kayit';
        foreach ($deger as $alan => $v) {
            $deger[$alan] = trim($_POST[$alan] ?? '');
        }
        $deger['kullanici_adi'] = mb_strtolower($deger['kullanici_adi'], 'UTF-8');
        $sifre        = $_POST['sifre'] ?? '';
        $sifre_tekrar = $_POST['sifre_tekrar'] ?? '';
        $maas         = tutar_coz($deger['aylik_maas']);   // "42.000,50" → "42000.50"
        $limit        = tutar_coz($deger['aylik_limit']);

        if ($deger['ad_soyad'] === '') {
            $hatalar['ad_soyad'] = 'Ad soyad boş bırakılamaz.';
        } elseif (mb_strlen($deger['ad_soyad'], 'UTF-8') < 2 || mb_strlen($deger['ad_soyad'], 'UTF-8') > 60) {
            $hatalar['ad_soyad'] = 'Ad soyad 2–60 karakter olmalı.';
        }

        if ($deger['kullanici_adi'] === '') {
            $hatalar['kullanici_adi'] = 'Kullanıcı adı boş bırakılamaz.';
        } elseif (!preg_match('/^[a-z0-9_]{3,30}$/', $deger['kullanici_adi'])) {
            $hatalar['kullanici_adi'] = '3–30 karakter; yalnızca küçük harf (a-z), rakam ve _ kullanın.';
        } elseif (kullanici_adi_var_mi($deger['kullanici_adi'])) {
            $hatalar['kullanici_adi'] = 'Bu kullanıcı adı alınmış, başka bir tane deneyin.';
        }

        if ($deger['eposta'] !== '' && !filter_var($deger['eposta'], FILTER_VALIDATE_EMAIL)) {
            $hatalar['eposta'] = 'Geçerli bir e-posta adresi girin (veya boş bırakın).';
        }

        if ($deger['aylik_maas'] === '') {
            $hatalar['aylik_maas'] = 'Aylık gelirinizi (maaş) girin.';
        } elseif (!is_numeric($maas) || (float)$maas < 0) {
            $hatalar['aylik_maas'] = 'Maaş sıfır veya pozitif bir sayı olmalı.';
        }

        if ($deger['aylik_limit'] !== '' && (!is_numeric($limit) || (float)$limit < 0)) {
            $hatalar['aylik_limit'] = 'Limit pozitif bir sayı olmalı (veya boş bırakın).';
        }

        if (mb_strlen($sifre, 'UTF-8') < 4) {
            $hatalar['sifre'] = 'Şifre en az 4 karakter olmalı.';
        } elseif ($sifre !== $sifre_tekrar) {
            $hatalar['sifre_tekrar'] = 'Şifreler birbiriyle uyuşmuyor.';
        }

        if (empty($hatalar)) {
            // Limit boş bırakıldıysa maaşın %80'i varsayılan limit olsun
            $limit_degeri = $deger['aylik_limit'] === '' ? round((float)$maas * 0.8, 2) : (float)$limit;

            $yeni_id = kullanici_olustur($deger['kullanici_adi'], $deger['ad_soyad'], $deger['eposta'],
                                         $sifre, (float)$maas, $limit_degeri);
            oturum_ac_ve_yonlendir($yeni_id, 'yeni_hesap');
        }
    }
}

/** Kayıt formunda alanın altına hata mesajı basar. */
function kayit_hatasi($hatalar, $alan) {
    return isset($hatalar[$alan]) ? '<span class="alan-hata">' . guvenli($hatalar[$alan]) . '</span>' : '';
}

// Sol paneldeki finans sözleri: her açılışta rastgele biri
$sozler = [
    ['Küçük masraflara dikkat edin; küçük bir delik koca bir gemiyi batırır.', 'Benjamin Franklin'],
    ['Bütçe, paraya nereye gideceğini söylemektir; nereye gittiğini merak etmek değil.', 'Dave Ramsey'],
    ['Zengin olmak kazandığınla değil, elinde tuttuğunla ilgilidir.', 'Halk sözü'],
    ['Ölçemediğin şeyi yönetemezsin.', 'Peter Drucker'],
    ['Damlaya damlaya göl olur.', 'Atasözü'],
];
$soz = $sozler[array_rand($sozler)];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $mod === 'kayit' ? 'Kayıt ol' : 'Giriş' ?> — BütçeTakip</title>
    <script>
        if (localStorage.getItem('tema') === 'gece') document.documentElement.classList.add('gece');
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,600;0,9..144,800;1,9..144,400&family=IBM+Plex+Mono:wght@400;500;600&family=Instrument+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css?v=<?= filemtime(__DIR__ . '/assets/style.css') ?>">
</head>
<body class="giris-sayfasi">

<div class="giris-cerceve">

    <!-- SOL: Marka ve dekoratif defter sayfası -->
    <section class="giris-marka" aria-hidden="true">
        <div class="marka-ust">
            <span class="logo-muhur">B</span>
            <span class="marka-adi">BütçeTakip</span>
        </div>

        <h1 class="giris-manset">
            Her kuruşun<br>
            bir <em>hikâyesi</em> var.
        </h1>

        <div class="mini-defter">
            <div class="mini-defter-baslik">
                <span>Tarih</span><span>Açıklama</span><span>Tutar</span>
            </div>
            <div class="mini-satir"><span>01.11</span><span>Kira</span><span>14.500,00</span></div>
            <div class="mini-satir"><span>04.11</span><span>Market</span><span>1.850,50</span></div>
            <div class="mini-satir"><span>06.11</span><span>Elektrik</span><span>940,00</span></div>
            <div class="mini-satir"><span>09.11</span><span>Kart ekstresi</span><span>4.650,00</span></div>
            <div class="mini-satir toplam"><span></span><span>Toplam</span><span>21.940,50</span></div>
            <span class="mini-muhur">DEFTER<br>KAPANDI</span>
        </div>

        <blockquote class="giris-soz">
            “<?= guvenli($soz[0]) ?>”
            <cite>— <?= guvenli($soz[1]) ?></cite>
        </blockquote>
    </section>

    <!-- SAĞ: Giriş / Kayıt formu -->
    <section class="giris-form-alani">
        <button type="button" class="tema-dugme tema-dugme-kose" onclick="temaDegistir()" title="Gece / gündüz">
            <span class="tema-ikon-gunduz">☾</span><span class="tema-ikon-gece">☀</span>
        </button>

        <div class="giris-kutu <?= ($hata || $hatalar) ? 'salla' : '' ?> <?= $mod === 'kayit' ? 'genis' : '' ?>">

            <!-- Sekmeler -->
            <nav class="sekmeler">
                <a href="giris.php" class="sekme <?= $mod === 'giris' ? 'aktif' : '' ?>">Giriş yap</a>
                <a href="giris.php?mod=kayit" class="sekme <?= $mod === 'kayit' ? 'aktif' : '' ?>">Kayıt ol</a>
            </nav>

            <?php if ($mod === 'giris'): ?>
                <!-- ===================== GİRİŞ FORMU ===================== -->
                <h2 class="giris-baslik">Tekrar hoş geldin.</h2>

                <?php if (isset($_GET['cikis'])): ?>
                    <div class="bildirim bildirim-bilgi">Güvenli şekilde çıkış yaptın.</div>
                <?php elseif (isset($_GET['gerekli'])): ?>
                    <div class="bildirim bildirim-bilgi">Bu sayfayı görmek için önce giriş yapmalısın.</div>
                <?php endif; ?>

                <?php if ($hata): ?>
                    <div class="bildirim bildirim-hata"><?= guvenli($hata) ?></div>
                <?php endif; ?>

                <form method="POST" action="giris.php" novalidate>
                    <input type="hidden" name="islem" value="giris">

                    <label class="alan">
                        <span class="alan-etiket">Kullanıcı adı</span>
                        <input type="text" name="kullanici_adi" class="girdi" autocomplete="username"
                               value="<?= guvenli($deger['kullanici_adi']) ?>" autofocus>
                    </label>

                    <label class="alan">
                        <span class="alan-etiket">Şifre</span>
                        <span class="sifre-sarmal">
                            <input type="password" name="sifre" class="girdi" autocomplete="current-password">
                            <button type="button" class="sifre-goster">Göster</button>
                        </span>
                    </label>

                    <button type="submit" class="dugme dugme-ana dugme-genis">Giriş yap →</button>
                </form>

                <p class="kucuk-not">Hesabın yok mu? <a href="giris.php?mod=kayit" class="ince-baglanti">Hemen kayıt ol</a></p>

                <div class="yapiskan-not">
                    <strong>Demo hesaplar</strong>
                    <span><code>emin</code> / <code>1234</code></span>
                    <span><code>misafir</code> / <code>misafir</code></span>
                </div>

            <?php else: ?>
                <!-- ===================== KAYIT FORMU ===================== -->
                <h2 class="giris-baslik">Yeni bir defter aç.</h2>

                <?php if ($hatalar): ?>
                    <div class="bildirim bildirim-hata"><?= count($hatalar) ?> alanda düzeltme gerekiyor.</div>
                <?php endif; ?>

                <form method="POST" action="giris.php?mod=kayit" novalidate class="kayit-formu">
                    <input type="hidden" name="islem" value="kayit">

                    <label class="alan <?= isset($hatalar['ad_soyad']) ? 'hatali' : '' ?>">
                        <span class="alan-etiket">Ad soyad <i>*</i></span>
                        <input type="text" name="ad_soyad" class="girdi" maxlength="60" autocomplete="name"
                               value="<?= guvenli($deger['ad_soyad']) ?>" autofocus>
                        <?= kayit_hatasi($hatalar, 'ad_soyad') ?>
                    </label>

                    <div class="alan-ikili">
                        <label class="alan <?= isset($hatalar['kullanici_adi']) ? 'hatali' : '' ?>">
                            <span class="alan-etiket">Kullanıcı adı <i>*</i></span>
                            <input type="text" name="kullanici_adi" class="girdi" maxlength="30" autocomplete="username"
                                   placeholder="ornek_ad" value="<?= guvenli($deger['kullanici_adi']) ?>">
                            <?= kayit_hatasi($hatalar, 'kullanici_adi') ?>
                        </label>

                        <label class="alan <?= isset($hatalar['eposta']) ? 'hatali' : '' ?>">
                            <span class="alan-etiket">E-posta <small>(isteğe bağlı)</small></span>
                            <input type="email" name="eposta" class="girdi" maxlength="120" autocomplete="email"
                                   value="<?= guvenli($deger['eposta']) ?>">
                            <?= kayit_hatasi($hatalar, 'eposta') ?>
                        </label>
                    </div>

                    <div class="alan-ikili">
                        <label class="alan <?= isset($hatalar['aylik_maas']) ? 'hatali' : '' ?>">
                            <span class="alan-etiket">Aylık maaş <i>*</i></span>
                            <span class="tutar-sarmal">
                                <input type="text" name="aylik_maas" class="girdi girdi-tutar" inputmode="decimal"
                                       placeholder="0" value="<?= guvenli($deger['aylik_maas']) ?>">
                                <span class="tutar-birim">₺</span>
                            </span>
                            <?= kayit_hatasi($hatalar, 'aylik_maas') ?>
                        </label>

                        <label class="alan <?= isset($hatalar['aylik_limit']) ? 'hatali' : '' ?>">
                            <span class="alan-etiket">Harcama limiti <small>(boşsa maaşın %80'i)</small></span>
                            <span class="tutar-sarmal">
                                <input type="text" name="aylik_limit" class="girdi girdi-tutar" inputmode="decimal"
                                       placeholder="0" value="<?= guvenli($deger['aylik_limit']) ?>">
                                <span class="tutar-birim">₺</span>
                            </span>
                            <?= kayit_hatasi($hatalar, 'aylik_limit') ?>
                        </label>
                    </div>

                    <div class="alan-ikili">
                        <label class="alan <?= isset($hatalar['sifre']) ? 'hatali' : '' ?>">
                            <span class="alan-etiket">Şifre <i>*</i></span>
                            <span class="sifre-sarmal">
                                <input type="password" name="sifre" class="girdi" autocomplete="new-password">
                                <button type="button" class="sifre-goster">Göster</button>
                            </span>
                            <?= kayit_hatasi($hatalar, 'sifre') ?>
                        </label>

                        <label class="alan <?= isset($hatalar['sifre_tekrar']) ? 'hatali' : '' ?>">
                            <span class="alan-etiket">Şifre tekrar <i>*</i></span>
                            <input type="password" name="sifre_tekrar" class="girdi" autocomplete="new-password">
                            <?= kayit_hatasi($hatalar, 'sifre_tekrar') ?>
                        </label>
                    </div>

                    <button type="submit" class="dugme dugme-ana dugme-genis">Hesabı oluştur →</button>
                </form>

                <p class="kucuk-not">Zaten hesabın var mı? <a href="giris.php" class="ince-baglanti">Giriş yap</a></p>
            <?php endif; ?>
        </div>
    </section>

</div>

<script>
function temaDegistir() {
    var gece = document.documentElement.classList.toggle('gece');
    localStorage.setItem('tema', gece ? 'gece' : 'gunduz');
}
// "Göster / Gizle" düğmeleri: yanındaki şifre alanının tipini değiştirir
document.querySelectorAll('.sifre-goster').forEach(function (dugme) {
    dugme.addEventListener('click', function () {
        var alan = dugme.previousElementSibling;
        var gizli = alan.type === 'password';
        alan.type = gizli ? 'text' : 'password';
        dugme.textContent = gizli ? 'Gizle' : 'Göster';
    });
});
</script>
</body>
</html>

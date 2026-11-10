<?php
/**
 * BütçeTakip — Profil, Maaş ve Bütçe Limiti Düzenleme (profil.php)
 *
 * Kullanıcı bu sayfada:
 *   1. Ad soyad, kullanıcı adı ve e-posta bilgilerini güncelleyebilir,
 *   2. Aylık maaşını (gelirini) ve harcama limitini belirleyebilir,
 *   3. İsterse mevcut şifresini doğrulayarak şifresini değiştirebilir,
 *   4. Sağdaki "Maaş & Bütçe Özeti" fişinde maaşından geriye ne kadar kaldığını görür.
 */

require_once 'auth.php';

$hatalar  = [];
$basarili = false;

$ad_soyad      = $aktif_kullanici['ad_soyad'];
$yeni_kadi     = $aktif_kullanici['kullanici_adi'];
$eposta        = (string)$aktif_kullanici['eposta'];
$aylik_maas    = number_format((float)$aktif_kullanici['aylik_maas'], 2, ',', '.');
$aylik_limit   = number_format((float)$aktif_kullanici['aylik_limit'], 2, ',', '.');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_dogrula($_POST['csrf'] ?? '')) {
        $hatalar['genel'] = 'Oturum doğrulanamadı. Sayfayı yenileyip tekrar deneyin.';
    }

    $ad_soyad      = trim($_POST['ad_soyad'] ?? '');
    $yeni_kadi     = mb_strtolower(trim($_POST['kullanici_adi'] ?? ''), 'UTF-8');
    $eposta        = trim($_POST['eposta'] ?? '');
    $aylik_maas    = trim($_POST['aylik_maas'] ?? '');
    $aylik_limit   = trim($_POST['aylik_limit'] ?? '');

    $mevcut_sifre  = $_POST['mevcut_sifre'] ?? '';
    $yeni_sifre    = $_POST['yeni_sifre'] ?? '';
    $sifre_tekrar  = $_POST['sifre_tekrar'] ?? '';

    $maas_sayi  = tutar_coz($aylik_maas);
    $limit_sayi = tutar_coz($aylik_limit);

    if ($ad_soyad === '' || mb_strlen($ad_soyad, 'UTF-8') < 2 || mb_strlen($ad_soyad, 'UTF-8') > 60) {
        $hatalar['ad_soyad'] = 'Ad soyad 2–60 karakter olmalı.';
    }

    if (!preg_match('/^[a-z0-9_]{3,30}$/', $yeni_kadi)) {
        $hatalar['kullanici_adi'] = '3–30 karakter; yalnızca küçük harf (a-z), rakam ve _ kullanın.';
    } elseif (kullanici_adi_var_mi($yeni_kadi, $kullanici_id)) {
        $hatalar['kullanici_adi'] = 'Bu kullanıcı adı başka bir hesapta kullanılıyor.';
    }

    if ($eposta !== '' && !filter_var($eposta, FILTER_VALIDATE_EMAIL)) {
        $hatalar['eposta'] = 'Geçerli bir e-posta adresi girin (veya boş bırakın).';
    }

    if ($aylik_maas === '' || !is_numeric($maas_sayi) || (float)$maas_sayi < 0) {
        $hatalar['aylik_maas'] = 'Geçerli bir aylık maaş / gelir tutarı girin.';
    }

    if ($aylik_limit === '' || !is_numeric($limit_sayi) || (float)$limit_sayi < 0) {
        $hatalar['aylik_limit'] = 'Geçerli bir aylık harcama limiti girin.';
    }

    // Şifre alanlarından herhangi biri doldurulduysa şifre değişimi doğrulanır
    $sifre_degisecek = ($mevcut_sifre !== '' || $yeni_sifre !== '' || $sifre_tekrar !== '');
    if ($sifre_degisecek) {
        if ($mevcut_sifre === '') {
            $hatalar['mevcut_sifre'] = 'Şifre değiştirmek için mevcut şifrenizi girin.';
        }
        if (mb_strlen($yeni_sifre, 'UTF-8') < 4) {
            $hatalar['yeni_sifre'] = 'Yeni şifre en az 4 karakter olmalı.';
        } elseif ($yeni_sifre !== $sifre_tekrar) {
            $hatalar['sifre_tekrar'] = 'Yeni şifreler birbiriyle uyuşmuyor.';
        }
    }

    if (empty($hatalar) && $sifre_degisecek) {
        if (!kullanici_sifre_degistir($kullanici_id, $mevcut_sifre, $yeni_sifre)) {
            $hatalar['mevcut_sifre'] = 'Mevcut şifreniz hatalı.';
        }
    }

    if (empty($hatalar)) {
        kullanici_profil_guncelle(
            $kullanici_id,
            $yeni_kadi,
            $ad_soyad,
            $eposta,
            round((float)$maas_sayi, 2),
            round((float)$limit_sayi, 2)
        );

        header('Location: profil.php?kaydedildi=1');
        exit;
    }
}

function profil_hatasi($hatalar, $alan) {
    return isset($hatalar[$alan]) ? '<span class="alan-hata">' . guvenli($hatalar[$alan]) . '</span>' : '';
}

$kayitlar   = kayitlari_getir($kullanici_id);
$ozet       = istatistik_hesapla($kayitlar);
$maas_tablo = maas_ozeti($aktif_kullanici['aylik_maas'], $ozet['odenen_tutar'], $ozet['odenecek_tutar']);

$sayfa_basligi = 'Profil & Maaş';
$aktif_sayfa   = 'profil';
require 'parcalar/ust.php';
?>

<header class="sayfa-basi">
    <div>
        <p class="ust-etiket">Hesap · Gelir ve Limit Ayarları</p>
        <h1 class="sayfa-manset">Profil &amp; <em>maaş bilgilerin</em>.</h1>
    </div>
    <a href="index.php" class="dugme dugme-sade">← Panele dön</a>
</header>

<?php if (isset($_GET['kaydedildi'])): ?>
    <div class="bildirim bildirim-basari">Profil, maaş ve bütçe limiti bilgilerin veritabanına kaydedildi.</div>
<?php endif; ?>

<?php if (!empty($hatalar)): ?>
    <div class="bildirim bildirim-hata">
        <?= isset($hatalar['genel']) ? guvenli($hatalar['genel']) : count($hatalar) . ' alanda düzeltme gerekiyor.' ?>
    </div>
<?php endif; ?>

<div class="ekle-izgara">

    <!-- SOL: Profil ve Maaş Formu -->
    <form method="POST" action="profil.php" class="kagit kart form-kart" novalidate id="profilFormu">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">

        <h2 class="kart-baslik">Kişisel bilgiler</h2>

        <div class="alan-ikili">
            <label class="alan <?= isset($hatalar['ad_soyad']) ? 'hatali' : '' ?>">
                <span class="alan-etiket">Ad soyad <i>*</i></span>
                <input type="text" name="ad_soyad" class="girdi" maxlength="60" value="<?= guvenli($ad_soyad) ?>">
                <?= profil_hatasi($hatalar, 'ad_soyad') ?>
            </label>

            <label class="alan <?= isset($hatalar['kullanici_adi']) ? 'hatali' : '' ?>">
                <span class="alan-etiket">Kullanıcı adı <i>*</i></span>
                <input type="text" name="kullanici_adi" class="girdi" maxlength="30" value="<?= guvenli($yeni_kadi) ?>">
                <?= profil_hatasi($hatalar, 'kullanici_adi') ?>
            </label>
        </div>

        <label class="alan <?= isset($hatalar['eposta']) ? 'hatali' : '' ?>">
            <span class="alan-etiket">E-posta <small>(isteğe bağlı)</small></span>
            <input type="email" name="eposta" class="girdi" maxlength="120"
                   placeholder="ornek@eposta.com" value="<?= guvenli($eposta) ?>">
            <?= profil_hatasi($hatalar, 'eposta') ?>
        </label>

        <hr class="bolum-cizgisi">

        <h2 class="kart-baslik">Aylık gelir (maaş) ve bütçe limiti</h2>

        <div class="alan-ikili">
            <label class="alan <?= isset($hatalar['aylik_maas']) ? 'hatali' : '' ?>">
                <span class="alan-etiket">Aylık net maaş / gelir <i>*</i></span>
                <span class="tutar-sarmal">
                    <input type="text" name="aylik_maas" id="maasGirdi" class="girdi girdi-tutar" inputmode="decimal"
                           value="<?= guvenli($aylik_maas) ?>">
                    <span class="tutar-birim">₺</span>
                </span>
                <?= profil_hatasi($hatalar, 'aylik_maas') ?>
            </label>

            <label class="alan <?= isset($hatalar['aylik_limit']) ? 'hatali' : '' ?>">
                <span class="alan-etiket">Aylık harcama limiti <i>*</i></span>
                <span class="tutar-sarmal">
                    <input type="text" name="aylik_limit" id="limitGirdi" class="girdi girdi-tutar" inputmode="decimal"
                           value="<?= guvenli($aylik_limit) ?>">
                    <span class="tutar-birim">₺</span>
                </span>
                <?= profil_hatasi($hatalar, 'aylik_limit') ?>
            </label>
        </div>

        <div class="cipler">
            <span class="kucuk-not" style="align-self:center;margin-right:4px">Limiti maaşa göre ayarla:</span>
            <button type="button" class="cip" data-oran="0.50">%50 Tutumlu</button>
            <button type="button" class="cip" data-oran="0.70">%70 Dengeli</button>
            <button type="button" class="cip" data-oran="0.80">%80 Standart</button>
            <button type="button" class="cip" data-oran="0.90">%90 Esnek</button>
        </div>

        <hr class="bolum-cizgisi">

        <h2 class="kart-baslik">Şifre değiştir <small class="kucuk-not">(değiştirmek istemiyorsan boş bırak)</small></h2>

        <label class="alan <?= isset($hatalar['mevcut_sifre']) ? 'hatali' : '' ?>">
            <span class="alan-etiket">Mevcut şifre</span>
            <input type="password" name="mevcut_sifre" class="girdi" autocomplete="current-password">
            <?= profil_hatasi($hatalar, 'mevcut_sifre') ?>
        </label>

        <div class="alan-ikili">
            <label class="alan <?= isset($hatalar['yeni_sifre']) ? 'hatali' : '' ?>">
                <span class="alan-etiket">Yeni şifre</span>
                <input type="password" name="yeni_sifre" class="girdi" autocomplete="new-password">
                <?= profil_hatasi($hatalar, 'yeni_sifre') ?>
            </label>

            <label class="alan <?= isset($hatalar['sifre_tekrar']) ? 'hatali' : '' ?>">
                <span class="alan-etiket">Yeni şifre tekrar</span>
                <input type="password" name="sifre_tekrar" class="girdi" autocomplete="new-password">
                <?= profil_hatasi($hatalar, 'sifre_tekrar') ?>
            </label>
        </div>

        <div class="form-alt">
            <a href="index.php" class="dugme dugme-sade">Vazgeç</a>
            <button type="submit" class="dugme dugme-ana">Değişiklikleri kaydet →</button>
        </div>
    </form>

    <!-- SAĞ: Maaş Bordrosu & Ay Sonu Özeti -->
    <aside class="fis-alani">
        <p class="ust-etiket">Maaş &amp; Bütçe Dengesi</p>
        <div class="fis">
            <div class="fis-ust">
                <strong>GELİR — GİDER BORDROSU</strong>
                <span>@<?= guvenli($kullanici_adi) ?></span>
            </div>
            <div class="fis-cizgi"></div>
            <div class="fis-satir"><span>Aylık Maaş</span><strong><?= para_formati($maas_tablo['maas']) ?></strong></div>
            <div class="fis-satir"><span>Harcama Limiti</span><span><?= para_formati($aktif_kullanici['aylik_limit']) ?></span></div>
            <div class="fis-cizgi"></div>
            <div class="fis-satir"><span>− Ödenen giderler</span><span><?= para_formati($ozet['odenen_tutar']) ?></span></div>
            <div class="fis-satir"><span>− Bekleyen faturalar</span><span><?= para_formati($ozet['odenecek_tutar']) ?></span></div>
            <div class="fis-cizgi"></div>
            <div class="fis-toplam">
                <span>AY SONU KALAN</span>
                <span class="<?= $maas_tablo['kalan'] < 0 ? 'metin-kirmizi' : '' ?>">
                    <?= para_formati($maas_tablo['kalan']) ?>
                </span>
            </div>
            <div class="fis-cizgi"></div>
            <div class="fis-satir">
                <span>Tasarruf oranı</span>
                <span>%<?= $maas_tablo['tasarruf_orani'] ?></span>
            </div>
            <div class="fis-satir">
                <span>Kayıtlı harcama</span>
                <span><?= $ozet['toplam_kayit'] ?> adet</span>
            </div>
            <div class="fis-satir">
                <span>Hesap açılışı</span>
                <span><?= tarih_formati($aktif_kullanici['olusturma']) ?></span>
            </div>
            <div class="fis-barkod" aria-hidden="true"></div>
        </div>
    </aside>

</div>

<script>
// Maaşa göre yüzde çipleri (örn: %80 Standart tıklanınca limiti maaş × 0.80 yapar)
document.querySelectorAll('[data-oran]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var ham = document.getElementById('maasGirdi').value.replace(/\s/g, '').replace(/\./g, '').replace(',', '.');
        var maas = parseFloat(ham);
        if (!isNaN(maas) && maas > 0) {
            var limit = Math.round(maas * parseFloat(btn.dataset.oran));
            document.getElementById('limitGirdi').value = limit.toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
    });
});
</script>

<?php require 'parcalar/alt.php'; ?>

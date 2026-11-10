<?php
/**
 * BütçeTakip — Ana Panel (index.php)
 *
 * Bu sayfada:
 *   - Giriş kontrolü (auth.php) ve MySQL'den kayıtların okunması
 *   - Dönem / Ay Seçici (?ay=YYYY-MM veya tumu) ve geçen ayla karşılaştırma
 *   - Maaş & ay sonu kalan gelir kartı + özet sayaçlar + aylık limit çubuğu
 *   - Kumbara / Birikim Hedefi kartı (ilerleme çubuğu + hızlı para ekleme + hedef düzenleme)
 *   - Kategori halka grafiği (PHP ile hesaplanan SVG) + Yaklaşan ödemeler
 *   - Kayıt defteri: GET filtre + arama, Sabit giderleri aktarma, Excel (CSV) indirme,
 *     düzenleme (duzenle.php), dekont görüntüleme, küçültme/kompakt görünüm, tıklanabilir durum menüsü
 */

require_once 'auth.php';

/* ---------- POST İŞLEMLERİ ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['islem'])) {
    if (!csrf_dogrula($_POST['csrf'] ?? '')) {
        header('Location: index.php?hata=oturum');
        exit;
    }

    $islem = $_POST['islem'];

    // 1) Örnek verilere sıfırlama
    if ($islem === 'sifirla') {
        ornek_verileri_yukle($kullanici_id, $kullanici_adi);
        header('Location: index.php?sifirlandi=1');
        exit;
    }

    // 2) Tekrarlayan (sabit) giderleri seçili aya aktarma
    if ($islem === 'tekrarlayan_aktar') {
        $hedef_ay = trim($_POST['hedef_ay'] ?? date('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $hedef_ay)) {
            $hedef_ay = date('Y-m');
        }
        $eklenen = tekrarlayan_giderleri_aktar($kullanici_id, $hedef_ay);
        header('Location: index.php?aktarildi=' . (int)$eklenen . '&ay=' . urlencode($hedef_ay));
        exit;
    }

    // 3) Kumbara / birikim hedefi güncelleme veya hızlı para ekleme
    if ($islem === 'kumbara_guncelle') {
        $mevcut_ad      = $aktif_kullanici['hedef_ad'] ?? 'Acil Durum & Tatil Fonu';
        $mevcut_hedef   = (float)($aktif_kullanici['hedef_tutar'] ?? 35000);
        $mevcut_biriken = (float)($aktif_kullanici['hedef_biriken'] ?? 0);

        if (isset($_POST['hizli_ekle'])) {
            $ek = (float)tutar_coz($_POST['hizli_ekle']);
            $yeni_biriken = max(0, $mevcut_biriken + $ek);
            kumbara_guncelle($kullanici_id, $mevcut_ad, $mevcut_hedef, $yeni_biriken);
        } else {
            $yeni_ad      = trim($_POST['hedef_ad'] ?? '') ?: $mevcut_ad;
            $yeni_hedef   = (float)tutar_coz($_POST['hedef_tutar'] ?? $mevcut_hedef);
            $yeni_biriken = (float)tutar_coz($_POST['hedef_biriken'] ?? $mevcut_biriken);
            if ($yeni_hedef <= 0) {
                $yeni_hedef = $mevcut_hedef;
            }
            kumbara_guncelle($kullanici_id, mb_substr($yeni_ad, 0, 80, 'UTF-8'), $yeni_hedef, max(0, $yeni_biriken));
        }

        $donus_ay = isset($_POST['ay']) && $_POST['ay'] !== '' ? '&ay=' . urlencode($_POST['ay']) : '';
        header('Location: index.php?kumbara=1' . $donus_ay);
        exit;
    }
}

/* ---------- Verileri veritabanından al ---------- */
$tum_kayitlar = kayitlari_getir($kullanici_id);
$ay_listesi   = mevcut_aylar($tum_kayitlar);

/* ---------- GET: Dönem (Ay), Filtre ve Arama parametreleri ---------- */
// Varsayılan olarak içinde bulunduğumuz ayı göster; kullanıcı "tumu" seçerse tüm dönemleri göster
$secili_ay = isset($_GET['ay']) ? trim($_GET['ay']) : date('Y-m');
if ($secili_ay !== 'tumu' && !preg_match('/^\d{4}-\d{2}$/', $secili_ay)) {
    $secili_ay = date('Y-m');
}

// Seçili aya ait kayıtları ayır (üstteki özet kartlar, grafik ve maaş hesabı seçili döneme göre çalışır)
$donem_kayitlari = [];
foreach ($tum_kayitlar as $k) {
    if ($secili_ay === 'tumu' || substr((string)$k['tarih'], 0, 7) === $secili_ay) {
        $donem_kayitlari[] = $k;
    }
}

$filtre_kategori = isset($_GET['kategori']) ? trim($_GET['kategori']) : '';
$filtre_durum    = isset($_GET['durum']) ? trim($_GET['durum']) : '';
$arama           = isset($_GET['ara']) ? trim($_GET['ara']) : '';
$filtre_aktif    = ($filtre_kategori !== '' || $filtre_durum !== '' || $arama !== '' || $secili_ay !== date('Y-m'));

/* ---------- Tablo Filtreleme (foreach + if) ---------- */
$listelenen = [];
foreach ($donem_kayitlari as $kayit) {
    if ($filtre_kategori !== '' && $kayit['kategori'] !== $filtre_kategori) {
        continue;
    }
    if ($filtre_durum !== '' && $kayit['durum'] !== $filtre_durum) {
        continue;
    }
    if ($arama !== '') {
        $metin = $kayit['baslik'] . ' ' . $kayit['aciklama'];
        if (mb_stripos($metin, $arama, 0, 'UTF-8') === false) {
            continue;
        }
    }
    $listelenen[] = $kayit;
}

/* ---------- Hesaplamalar ---------- */
$ozet          = istatistik_hesapla($donem_kayitlari);
$dagilim       = kategori_dagilimi($donem_kayitlari);
$yaklasanlar   = yaklasan_odemeler($donem_kayitlari, 4);
$harcanan      = $ozet['odenen_tutar'] + $ozet['odenecek_tutar'];
$limit         = limit_durumu($harcanan, $aktif_kullanici['aylik_limit']);
$maas_tablo    = maas_ozeti($aktif_kullanici['aylik_maas'], $ozet['odenen_tutar'], $ozet['odenecek_tutar']);
$kiyas         = ay_karsilastir($tum_kayitlar, $secili_ay === 'tumu' ? date('Y-m') : $secili_ay);
$kumbara       = kumbara_ozeti($aktif_kullanici, $maas_tablo['kalan']);
$kategoriler   = array_unique(array_column($tum_kayitlar, 'kategori'));
sort($kategoriler);
$liste_toplami = array_sum(array_column($listelenen, 'tutar'));

// CSV indirme bağlantısı için mevcut filtreleri koru
$indir_sorgu = http_build_query(array_filter([
    'ay'       => $secili_ay,
    'kategori' => $filtre_kategori,
    'durum'    => $filtre_durum,
    'ara'      => $arama,
]));

$sayfa_basligi = 'Panel';
$aktif_sayfa   = 'panel';
require 'parcalar/ust.php';
?>

<!-- SAYFA BAŞI -->
<header class="sayfa-basi">
    <div>
        <p class="ust-etiket"><?= guvenli(uzun_tarih()) ?> · <?= guvenli(ay_etiketi($secili_ay)) ?></p>
        <h1 class="sayfa-manset"><?= guvenli(selamlama()) ?>, <em><?= guvenli($aktif_kullanici['ilk_ad']) ?></em>.</h1>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
        <a href="profil.php" class="dugme dugme-cizgi">Maaş &amp; Profil</a>
        <a href="ekle.php" class="dugme dugme-ana">+ Yeni kayıt</a>
    </div>
</header>

<!-- DÖNEM / AY SEÇİCİ & GEÇEN AY KARŞILAŞTIRMASI -->
<section class="donem-cubuk kagit">
    <div class="donem-sol">
        <span class="ust-etiket">Dönem:</span>
        <div class="donem-dugmeler">
            <?php foreach ($ay_listesi as $ym): ?>
                <a href="index.php?ay=<?= urlencode($ym) ?>"
                   class="donem-etiket <?= $secili_ay === $ym ? 'aktif' : '' ?>">
                    <?= guvenli(ay_etiketi($ym)) ?>
                </a>
            <?php endforeach; ?>
            <a href="index.php?ay=tumu" class="donem-etiket <?= $secili_ay === 'tumu' ? 'aktif' : '' ?>">
                Tüm Dönemler (<?= count($tum_kayitlar) ?>)
            </a>
        </div>
    </div>

    <div class="donem-sag">
        <?php if ($kiyas['onceki_toplam'] > 0): ?>
            <?php $artis = $kiyas['fark_tutar'] > 0; ?>
            <span class="kiyas-rozet <?= $artis ? 'artis' : 'azalis' ?>">
                <?= guvenli(ay_etiketi($kiyas['onceki_ay'])) ?> ayına göre
                <strong><?= $artis ? '▲ %' . abs($kiyas['fark_yuzde']) . ' daha fazla' : '▼ %' . abs($kiyas['fark_yuzde']) . ' tasarruf' ?></strong>
                (<?= para_formati($kiyas['onceki_toplam']) ?> → <?= para_formati($kiyas['bu_toplam']) ?>)
            </span>
        <?php else: ?>
            <span class="kucuk-not"><?= guvenli(ay_etiketi($kiyas['onceki_ay'])) ?> için karşılaştırma verisi yok.</span>
        <?php endif; ?>
    </div>
</section>

<!-- BİLDİRİMLER -->
<?php if (isset($_GET['hosgeldin'])): ?>
    <div class="bildirim bildirim-basari">Giriş başarılı. Defterin veritabanından yüklendi.</div>
<?php elseif (isset($_GET['yeni_hesap'])): ?>
    <div class="bildirim bildirim-basari">Hesabın oluşturuldu! İlk harcamanı ekleyerek başlayabilirsin.</div>
<?php elseif (isset($_GET['eklendi'])): ?>
    <div class="bildirim bildirim-basari">Yeni kayıt veritabanına işlendi.</div>
<?php elseif (isset($_GET['duzenlendi']) || isset($_GET['guncellendi'])): ?>
    <div class="bildirim bildirim-basari">Kayıt bilgileri başarıyla güncellendi.</div>
<?php elseif (isset($_GET['silindi'])): ?>
    <div class="bildirim bildirim-basari">Kayıt veritabanından silindi.</div>
<?php elseif (isset($_GET['durum_degisti'])): ?>
    <div class="bildirim bildirim-basari">Kaydın durumu <strong><?= guvenli($_GET['durum_degisti']) ?></strong> olarak güncellendi.</div>
<?php elseif (isset($_GET['aktarildi'])): ?>
    <?php $aktarilan = (int)$_GET['aktarildi']; ?>
    <div class="bildirim <?= $aktarilan > 0 ? 'bildirim-basari' : 'bildirim-bilgi' ?>">
        <?= $aktarilan > 0
            ? $aktarilan . ' adet tekrarlayan sabit gider bu aya (' . guvenli(ay_etiketi($secili_ay)) . ') Ödenecek olarak aktarıldı.'
            : 'Tüm sabit giderlerin bu ayda zaten mevcut; mükerrer kayıt eklenmedi.' ?>
    </div>
<?php elseif (isset($_GET['kumbara'])): ?>
    <div class="bildirim bildirim-basari">Kumbara ve birikim hedefi bilgilerin güncellendi.</div>
<?php elseif (isset($_GET['sifirlandi'])): ?>
    <div class="bildirim bildirim-bilgi">Defter örnek verilere sıfırlandı.</div>
<?php elseif (isset($_GET['hata'])): ?>
    <div class="bildirim bildirim-hata">İstenen işlem tamamlanamadı. Lütfen tekrar deneyin.</div>
<?php endif; ?>

<!-- MAAŞ & ÖZET BANDI -->
<section class="ozet-bandi ozet-bandi-5 kagit">
    <div class="ozet-ana">
        <span class="ust-etiket">Aylık Maaş / Gelir <a href="profil.php" class="ince-baglanti" style="margin-left:6px">düzenle</a></span>
        <strong class="dev-rakam"><?= para_formati($maas_tablo['maas']) ?></strong>
        <span class="kucuk-not">
            Ay sonu kalan:
            <strong class="<?= $maas_tablo['kalan'] < 0 ? 'metin-kirmizi' : '' ?>" style="font-family:var(--mono)">
                <?= para_formati($maas_tablo['kalan']) ?>
            </strong>
            (%<?= $maas_tablo['tasarruf_orani'] ?>)
        </span>
    </div>

    <div class="ozet-kalem">
        <span class="ust-etiket">Dönem Gideri</span>
        <strong class="orta-rakam"><?= para_formati($ozet['toplam_tutar']) ?></strong>
        <span class="kucuk-not"><?= $ozet['toplam_kayit'] ?> kayıt · <?= guvenli(ay_etiketi($secili_ay)) ?></span>
    </div>

    <div class="ozet-kalem">
        <span class="ust-etiket"><i class="nokta odendi"></i>Ödenen</span>
        <strong class="orta-rakam"><?= para_formati($ozet['odenen_tutar']) ?></strong>
        <span class="kucuk-not"><?= $ozet['odenen_adet'] ?> kayıt · %<?= $ozet['odenen_yuzde'] ?></span>
    </div>

    <div class="ozet-kalem">
        <span class="ust-etiket"><i class="nokta odenecek"></i>Bekleyen</span>
        <strong class="orta-rakam"><?= para_formati($ozet['odenecek_tutar']) ?></strong>
        <span class="kucuk-not"><?= $ozet['odenecek_adet'] ?> kayıt</span>
    </div>

    <div class="ozet-kalem">
        <span class="ust-etiket"><i class="nokta ertelendi"></i>Ertelenen</span>
        <strong class="orta-rakam"><?= para_formati($ozet['ertelenen_tutar']) ?></strong>
        <span class="kucuk-not"><?= $ozet['ertelenen_adet'] ?> kayıt</span>
    </div>

    <!-- Aylık limit çubuğu -->
    <div class="limit-alani">
        <div class="limit-ust">
            <span>Aylık harcama limiti <strong><?= para_formati($aktif_kullanici['aylik_limit']) ?></strong></span>
            <span class="limit-yuzde <?= $limit['seviye'] ?>">%<?= $limit['yuzde'] ?> kullanıldı</span>
        </div>
        <div class="limit-cubuk">
            <div class="limit-dolgu <?= $limit['seviye'] ?>" style="width: <?= $limit['cubuk'] ?>%"></div>
            <span class="limit-cizgi" style="left:25%"></span>
            <span class="limit-cizgi" style="left:50%"></span>
            <span class="limit-cizgi" style="left:75%"></span>
        </div>
        <div class="limit-alt">
            <span><?= guvenli($limit['mesaj']) ?></span>
            <span>
                <?php if ($limit['kalan'] >= 0): ?>
                    Limitten kalan: <strong><?= para_formati($limit['kalan']) ?></strong>
                <?php else: ?>
                    Limit aşımı: <strong class="metin-kirmizi"><?= para_formati(abs($limit['kalan'])) ?></strong>
                <?php endif; ?>
            </span>
        </div>
    </div>
</section>

<!-- KUMBARA / BİRİKİM HEDEFİ KARTI -->
<section class="kagit kart kumbara-kart">
    <div class="kumbara-ust">
        <div>
            <span class="ust-etiket">Kumbara &amp; Birikim Hedefi</span>
            <h2 class="kart-baslik"><?= guvenli($kumbara['ad']) ?></h2>
        </div>

        <div class="kumbara-rakamlar">
            <div>
                <span class="ust-etiket">Şu An Biriken</span>
                <strong class="orta-rakam metin-yesil"><?= para_formati($kumbara['biriken']) ?></strong>
            </div>
            <span class="kumbara-ayirici">/</span>
            <div>
                <span class="ust-etiket">Hedef Tutar</span>
                <strong class="orta-rakam"><?= para_formati($kumbara['hedef']) ?></strong>
            </div>
            <span class="kumbara-yuzde-etiket">%<?= $kumbara['yuzde'] ?></span>
        </div>
    </div>

    <!-- Çift katmanlı ilerleme çubuğu: mevcut birikim + ay sonu kalan maaş eklendiğinde ulaşılacak potansiyel -->
    <div class="kumbara-cubuk" title="Yeşil: Mevcut birikim (%<?= $kumbara['yuzde'] ?>) · Açık yeşil: Ay sonu kalan maaş eklendiğinde (%<?= $kumbara['potansiyel_yuzde'] ?>)">
        <div class="kumbara-potansiyel" style="width: <?= $kumbara['potansiyel_yuzde'] ?>%"></div>
        <div class="kumbara-dolgu" style="width: <?= $kumbara['yuzde'] ?>%"></div>
    </div>

    <div class="kumbara-alt">
        <span class="kucuk-not">
            <?php if ($kumbara['kalan'] <= 0): ?>
                🎉 <strong>Tebrikler!</strong> Bu birikim hedefine ulaştın.
            <?php else: ?>
                Hedefe kalan: <strong><?= para_formati($kumbara['kalan']) ?></strong>
                <?php if ($maas_tablo['kalan'] > 0): ?>
                    · Ay sonu kalan maaşını (<?= para_formati($maas_tablo['kalan']) ?>) eklersen hedef <strong>%<?= $kumbara['potansiyel_yuzde'] ?></strong> tamamlanır!
                <?php endif; ?>
            <?php endif; ?>
        </span>

        <div class="kumbara-aksiyonlar">
            <!-- Hızlı para ekleme düğmeleri -->
            <form method="POST" action="index.php" class="kumbara-hizli-form">
                <input type="hidden" name="islem" value="kumbara_guncelle">
                <input type="hidden" name="ay" value="<?= guvenli($secili_ay) ?>">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <button type="submit" name="hizli_ekle" value="500" class="dugme dugme-kucuk">+500 ₺ ekle</button>
                <button type="submit" name="hizli_ekle" value="1000" class="dugme dugme-kucuk">+1.000 ₺ ekle</button>
                <button type="submit" name="hizli_ekle" value="2500" class="dugme dugme-kucuk">+2.500 ₺ ekle</button>
            </form>

            <!-- Hedef düzenleme açılır kutusu -->
            <details class="kumbara-duzenle-acilir">
                <summary class="dugme dugme-kucuk">✎ Hedefi düzenle</summary>
                <form method="POST" action="index.php" class="kumbara-duzenle-form">
                    <input type="hidden" name="islem" value="kumbara_guncelle">
                    <input type="hidden" name="ay" value="<?= guvenli($secili_ay) ?>">
                    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">

                    <label class="alan">
                        <span class="alan-etiket">Hedef adı</span>
                        <input type="text" name="hedef_ad" class="girdi girdi-kucuk" maxlength="80"
                               value="<?= guvenli($kumbara['ad']) ?>" required>
                    </label>
                    <div class="alan-ikili">
                        <label class="alan">
                            <span class="alan-etiket">Hedef tutar (₺)</span>
                            <input type="text" name="hedef_tutar" class="girdi girdi-kucuk" inputmode="decimal"
                                   value="<?= number_format($kumbara['hedef'], 2, ',', '.') ?>" required>
                        </label>
                        <label class="alan">
                            <span class="alan-etiket">Şu an biriken (₺)</span>
                            <input type="text" name="hedef_biriken" class="girdi girdi-kucuk" inputmode="decimal"
                                   value="<?= number_format($kumbara['biriken'], 2, ',', '.') ?>" required>
                        </label>
                    </div>
                    <button type="submit" class="dugme dugme-ana" style="width:100%">Kumbarayı güncelle</button>
                </form>
            </details>
        </div>
    </div>
</section>

<!-- GRAFİK + YAKLAŞAN ÖDEMELER -->
<section class="ikili-izgara">

    <!-- Kategori halka grafiği -->
    <article class="kagit kart">
        <header class="kart-basi">
            <h2 class="kart-baslik">Para nereye gitti?</h2>
            <a href="rapor.php?ay=<?= urlencode($secili_ay) ?>" class="ince-baglanti">Detaylı rapor →</a>
        </header>

        <?php if (empty($dagilim)): ?>
            <p class="bos-metin">Bu dönemde henüz kayıt yok.</p>
        <?php else: ?>
            <div class="halka-duzen">
                <svg class="halka" viewBox="0 0 42 42" role="img" aria-label="Kategori dağılımı">
                    <circle class="halka-zemin" cx="21" cy="21" r="15.915" />
                    <?php
                    $birikim = 0;
                    foreach ($dagilim as $kat => $bilgi):
                        $ofset = 25 - $birikim;
                        $birikim += $bilgi['yuzde'];
                    ?>
                        <circle cx="21" cy="21" r="15.915" fill="none"
                                stroke="<?= kategori_rengi($kat) ?>" stroke-width="5"
                                stroke-dasharray="<?= $bilgi['yuzde'] ?> <?= 100 - $bilgi['yuzde'] ?>"
                                stroke-dashoffset="<?= $ofset ?>">
                            <title><?= guvenli($kat) ?>: %<?= $bilgi['yuzde'] ?></title>
                        </circle>
                    <?php endforeach; ?>
                    <text x="21" y="20" class="halka-sayi"><?= count($dagilim) ?></text>
                    <text x="21" y="25" class="halka-etiket">kategori</text>
                </svg>

                <ul class="lejant">
                    <?php foreach ($dagilim as $kat => $bilgi): ?>
                        <li>
                            <i class="lejant-renk" style="background: <?= kategori_rengi($kat) ?>"></i>
                            <span class="lejant-ad"><?= guvenli($kat) ?></span>
                            <span class="lejant-yuzde">%<?= $bilgi['yuzde'] ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </article>

    <!-- Yaklaşan ödemeler (üzerine tıklayıp doğrudan Ödendi yapılabilir) -->
    <article class="kagit kart">
        <header class="kart-basi">
            <h2 class="kart-baslik">Yaklaşan ödemeler</h2>
            <span class="ince-etiket"><?= $ozet['odenecek_adet'] ?> bekleyen</span>
        </header>

        <?php if (empty($yaklasanlar)): ?>
            <p class="bos-metin">Bekleyen ödemen yok. Tebrikler! ✓</p>
        <?php else: ?>
            <ol class="yaklasan-liste">
                <?php foreach ($yaklasanlar as $odeme): ?>
                    <?php
                    if ($odeme['kalan_gun'] < 0) {
                        $etiket = abs($odeme['kalan_gun']) . ' gün gecikti';
                        $sinif  = 'gecikti';
                    } elseif ($odeme['kalan_gun'] === 0) {
                        $etiket = 'Bugün';
                        $sinif  = 'bugun';
                    } elseif ($odeme['kalan_gun'] <= 3) {
                        $etiket = $odeme['kalan_gun'] . ' gün kaldı';
                        $sinif  = 'yakin';
                    } else {
                        $etiket = $odeme['kalan_gun'] . ' gün kaldı';
                        $sinif  = 'uzak';
                    }
                    ?>
                    <li class="yaklasan-oge <?= $sinif ?>">
                        <span class="takvim-yapragi">
                            <b><?= date('d', strtotime($odeme['tarih'])) ?></b>
                            <small><?= kisa_ay($odeme['tarih']) ?></small>
                        </span>
                        <span class="yaklasan-bilgi">
                            <strong><?= guvenli($odeme['baslik']) ?></strong>
                            <small><?= guvenli($odeme['kategori']) ?> · <?= guvenli($etiket) ?></small>
                        </span>
                        <span class="yaklasan-sag">
                            <span class="tutar"><?= para_formati($odeme['tutar']) ?></span>
                            <!-- Tek tıkla Ödendi damgası vur -->
                            <form action="durum.php" method="POST">
                                <input type="hidden" name="id" value="<?= (int)$odeme['id'] ?>">
                                <input type="hidden" name="durum" value="Ödendi">
                                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                                <button type="submit" class="hizli-odendi-btn" title="Ödendi olarak işaretle">✓ Ödendi yap</button>
                            </form>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </article>
</section>

<!-- KAYIT DEFTERİ (Sabit Gider Aktarma + Excel İndirme + Düzenleme + Dekont + Küçültülebilir Defter) -->
<section class="kagit defter" id="defterKutu">
    <header class="defter-basi">
        <div class="defter-baslik-grup">
            <h2 class="kart-baslik">Kayıt defteri</h2>
            <span class="kucuk-not">
                <?= count($listelenen) ?> kayıt · Toplam <?= para_formati($liste_toplami) ?><?= $filtre_aktif ? ' (filtreli)' : '' ?>
            </span>
        </div>

        <div class="defter-kontroller">
            <!-- Tekrarlayan sabit giderleri seçili aya aktar -->
            <form method="POST" action="index.php" style="display:inline">
                <input type="hidden" name="islem" value="tekrarlayan_aktar">
                <input type="hidden" name="hedef_ay" value="<?= guvenli($secili_ay === 'tumu' ? date('Y-m') : $secili_ay) ?>">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <button type="submit" class="dugme dugme-kucuk" title="Sabit (↻) olarak işaretlenmiş giderleri bu aya Ödenecek olarak kopyalar">
                    ↻ Sabit giderleri bu aya aktar
                </button>
            </form>

            <!-- Excel (CSV) olarak indir -->
            <a href="indir.php?<?= guvenli($indir_sorgu) ?>" class="dugme dugme-kucuk" title="Listelenen kayıtları Excel uyumlu CSV olarak indir">
                ⬇ Excel (CSV) indir
            </a>

            <!-- Kompakt satır görünümü düğmesi -->
            <button type="button" class="dugme dugme-kucuk" id="sikisikDugme" onclick="defterSikistir()" title="Satırları daralt / genişlet">
                ↕ Sıkışık görünüm
            </button>
            <!-- Defteri tamamen küçült / aç düğmesi -->
            <button type="button" class="dugme dugme-kucuk" id="kucultDugme" onclick="defterKucult()" aria-expanded="true">
                ▾ Defteri küçült
            </button>
        </div>
    </header>

    <div class="defter-govde" id="defterGovde">
        <!-- Filtre + arama çubuğu (GET) -->
        <div class="defter-filtre-cubuk">
            <form method="GET" action="index.php" class="filtre-formu">
                <input type="hidden" name="ay" value="<?= guvenli($secili_ay) ?>">

                <input type="search" name="ara" class="girdi girdi-kucuk" placeholder="Başlık veya açıklamada ara…"
                       value="<?= guvenli($arama) ?>">

                <select name="kategori" class="girdi girdi-kucuk">
                    <option value="">Tüm kategoriler</option>
                    <?php foreach ($kategoriler as $kat): ?>
                        <option value="<?= guvenli($kat) ?>" <?= $filtre_kategori === $kat ? 'selected' : '' ?>><?= guvenli($kat) ?></option>
                    <?php endforeach; ?>
                </select>

                <select name="durum" class="girdi girdi-kucuk">
                    <option value="">Tüm durumlar</option>
                    <?php foreach (['Ödendi', 'Ödenecek', 'Ertelendi'] as $d): ?>
                        <option value="<?= $d ?>" <?= $filtre_durum === $d ? 'selected' : '' ?>><?= $d ?></option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="dugme dugme-cizgi">Filtrele</button>
                <?php if ($filtre_kategori !== '' || $filtre_durum !== '' || $arama !== ''): ?>
                    <a href="index.php?ay=<?= urlencode($secili_ay) ?>" class="dugme dugme-sade">Temizle</a>
                <?php endif; ?>
            </form>

            <span class="kucuk-not ipucu-notu">💡 Durumu değiştirmek için <b>Ödenecek / Ödendi</b> etiketine, düzenlemek için <b>✎</b> simgesine tıkla.</span>
        </div>

        <div class="tablo-kaydir">
            <table class="defter-tablo">
                <thead>
                    <tr>
                        <th class="sutun-tarih">Tarih</th>
                        <th>Kayıt</th>
                        <th>Kategori</th>
                        <th>Durum (Tıkla &amp; Değiştir)</th>
                        <th class="sag">Tutar</th>
                        <th class="sutun-islem">İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($listelenen)): ?>
                        <tr>
                            <td colspan="6">
                                <div class="bos-durum">
                                    <span class="bos-isaret">∅</span>
                                    <strong>Bu dönemde kayıt bulunamadı.</strong>
                                    <span><?= $filtre_aktif ? 'Filtreleri değiştirmeyi veya tüm dönemlere geçmeyi deneyin.' : 'İlk kaydını ekleyerek başla.' ?></span>
                                    <div style="display:flex;gap:10px;margin-top:10px">
                                        <?php if ($secili_ay !== 'tumu'): ?>
                                            <a href="index.php?ay=tumu" class="dugme dugme-cizgi">Tüm dönemleri göster</a>
                                        <?php endif; ?>
                                        <a href="ekle.php" class="dugme dugme-ana">+ Yeni kayıt</a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($listelenen as $kayit): ?>
                            <?php
                            $durum = durum_bilgisi($kayit['durum']);
                            $zaman = strtotime($kayit['tarih']);
                            $kalan = gun_farki($kayit['tarih']);
                            ?>
                            <tr class="satir-<?= $durum['sinif'] ?>">
                                <td class="sutun-tarih">
                                    <span class="tarih-gun"><?= date('d.m', $zaman) ?></span>
                                    <span class="tarih-yil"><?= date('Y', $zaman) ?></span>
                                </td>
                                <td>
                                    <div class="kayit-baslik-satir">
                                        <strong class="kayit-baslik"><?= guvenli($kayit['baslik']) ?></strong>
                                        <?php if (!empty($kayit['tekrarlayan'])): ?>
                                            <span class="sabit-etiket" title="Her ay tekrarlayan sabit gider">↻ Sabit</span>
                                        <?php endif; ?>
                                        <?php if (!empty($kayit['dekont'])): ?>
                                            <a href="<?= guvenli($kayit['dekont']) ?>" target="_blank" rel="noopener"
                                               class="dekont-etiket" title="Yüklü fatura dekontunu / fişi aç">📎 Fiş</a>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($kayit['aciklama'] !== ''): ?>
                                        <span class="kayit-aciklama"><?= guvenli($kayit['aciklama']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="kategori-mur" style="--renk: <?= kategori_rengi($kayit['kategori']) ?>">
                                        <?= guvenli($kayit['kategori']) ?>
                                    </span>
                                </td>
                                <td class="durum-hucre">
                                    <!-- Üzerine tıklanınca açılan durum değiştirme kutusu (<details> ile saf HTML+CSS) -->
                                    <details class="durum-acilir">
                                        <summary class="durum-tetik" title="Durumu değiştirmek için tıkla">
                                            <?php if ($durum['sinif'] === 'odendi'): ?>
                                                <span class="muhur">Ödendi ▾</span>
                                            <?php else: ?>
                                                <span class="durum-etiket <?= $durum['sinif'] ?>"><?= guvenli($durum['etiket']) ?> ▾</span>
                                            <?php endif; ?>
                                        </summary>

                                        <div class="durum-balon">
                                            <span class="durum-balon-baslik">Durumu değiştir:</span>
                                            <form action="durum.php" method="POST" class="durum-balon-form">
                                                <input type="hidden" name="id" value="<?= (int)$kayit['id'] ?>">
                                                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">

                                                <button type="submit" name="durum" value="Ödendi"
                                                        class="durum-secim secim-odendi <?= $kayit['durum'] === 'Ödendi' ? 'aktif' : '' ?>">
                                                    ✓ Ödendi olarak damgala
                                                </button>
                                                <button type="submit" name="durum" value="Ödenecek"
                                                        class="durum-secim secim-odenecek <?= $kayit['durum'] === 'Ödenecek' ? 'aktif' : '' ?>">
                                                    ⏳ Ödenecek (bekliyor)
                                                </button>
                                                <button type="submit" name="durum" value="Ertelendi"
                                                        class="durum-secim secim-ertelendi <?= $kayit['durum'] === 'Ertelendi' ? 'aktif' : '' ?>">
                                                    ⏸ Ertele
                                                </button>
                                            </form>
                                        </div>
                                    </details>

                                    <?php if ($durum['sinif'] === 'odenecek' && $kalan < 0): ?>
                                        <span class="durum-alt metin-kirmizi"><?= abs($kalan) ?> gün gecikti</span>
                                    <?php elseif ($durum['sinif'] === 'odenecek'): ?>
                                        <span class="durum-alt"><?= $kalan === 0 ? 'bugün son gün' : $kalan . ' gün kaldı' ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="sag">
                                    <span class="tutar"><?= para_formati($kayit['tutar']) ?></span>
                                </td>
                                <td class="sutun-islem">
                                    <div class="islem-grup">
                                        <a href="duzenle.php?id=<?= (int)$kayit['id'] ?>" class="duzenle-dugme" title="Kaydı düzenle" aria-label="Düzenle">✎</a>
                                        <form action="sil.php" method="POST"
                                              onsubmit="return confirm('“<?= guvenli($kayit['baslik']) ?>” silinsin mi?');">
                                            <input type="hidden" name="id" value="<?= (int)$kayit['id'] ?>">
                                            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                                            <button type="submit" class="sil-dugme" title="Sil" aria-label="Sil">×</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <?php if (!empty($listelenen)): ?>
                    <tfoot>
                        <tr>
                            <td colspan="4" class="sag">Listelenen toplam</td>
                            <td class="sag"><span class="tutar cift-cizgi"><?= para_formati($liste_toplami) ?></span></td>
                            <td></td>
                        </tr>
                    </tfoot>
                <?php endif; ?>
            </table>
        </div>

        <footer class="defter-alt">
            <form method="POST" action="index.php" onsubmit="return confirm('Tüm kayıtlar silinip örnek veriler yüklensin mi?');">
                <input type="hidden" name="islem" value="sifirla">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <button type="submit" class="dugme dugme-sade">↺ Örnek verilere sıfırla</button>
            </form>
        </footer>
    </div>
</section>

<script>
// 1) Defteri küçült / genişlet ve Sıkışık görünüm (tercihler localStorage'da saklanır)
var defterKutu   = document.getElementById('defterKutu');
var kucultDugme  = document.getElementById('kucultDugme');
var sikisikDugme = document.getElementById('sikisikDugme');

function defterDurumUygula() {
    var kapali  = localStorage.getItem('defter_kapali') === '1';
    var sikisik = localStorage.getItem('defter_sikisik') === '1';

    defterKutu.classList.toggle('kapali', kapali);
    defterKutu.classList.toggle('sikisik', sikisik);
    kucultDugme.textContent = kapali ? '▸ Defteri genişlet' : '▾ Defteri küçült';
    kucultDugme.setAttribute('aria-expanded', kapali ? 'false' : 'true');
    sikisikDugme.classList.toggle('aktif-dugme', sikisik);
}

function defterKucult() {
    var yeni = localStorage.getItem('defter_kapali') === '1' ? '0' : '1';
    localStorage.setItem('defter_kapali', yeni);
    defterDurumUygula();
}

function defterSikistir() {
    var yeni = localStorage.getItem('defter_sikisik') === '1' ? '0' : '1';
    localStorage.setItem('defter_sikisik', yeni);
    defterDurumUygula();
}

defterDurumUygula();

// 2) Durum veya kumbara menülerinden biri açıldığında dışarı tıklamayı kapat
document.addEventListener('click', function (e) {
    document.querySelectorAll('details.durum-acilir[open], details.kumbara-duzenle-acilir[open]').forEach(function (acik) {
        if (!acik.contains(e.target)) {
            acik.removeAttribute('open');
        }
    });
});
</script>

<?php require 'parcalar/alt.php'; ?>

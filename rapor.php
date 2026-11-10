<?php
/**
 * BütçeTakip — Kategori ve Dönem Raporu (rapor.php)
 *
 * kategori_dagilimi(), istatistik_hesapla() ve ay_karsilastir() fonksiyonlarının
 * sonuçlarını tablo ve yatay çubuklar halinde gösterir. Grafikler tamamen PHP + CSS ile çizilir.
 */

require_once 'auth.php';

$tum_kayitlar = kayitlari_getir($kullanici_id);
$ay_listesi   = mevcut_aylar($tum_kayitlar);

$secili_ay = isset($_GET['ay']) ? trim($_GET['ay']) : 'tumu';
if ($secili_ay !== 'tumu' && !preg_match('/^\d{4}-\d{2}$/', $secili_ay)) {
    $secili_ay = 'tumu';
}

$kayitlar = [];
foreach ($tum_kayitlar as $k) {
    if ($secili_ay === 'tumu' || substr((string)$k['tarih'], 0, 7) === $secili_ay) {
        $kayitlar[] = $k;
    }
}

$ozet    = istatistik_hesapla($kayitlar);
$dagilim = kategori_dagilimi($kayitlar);
$kiyas   = ay_karsilastir($tum_kayitlar, $secili_ay === 'tumu' ? date('Y-m') : $secili_ay);

// En büyük tek harcama (foreach ile en büyüğü bulma)
$en_buyuk = null;
foreach ($kayitlar as $kayit) {
    if ($en_buyuk === null || $kayit['tutar'] > $en_buyuk['tutar']) {
        $en_buyuk = $kayit;
    }
}

$ortalama     = $ozet['toplam_kayit'] > 0 ? $ozet['toplam_tutar'] / $ozet['toplam_kayit'] : 0;
$ilk_kategori = array_key_first($dagilim);
$en_yuksek    = $ilk_kategori !== null ? $dagilim[$ilk_kategori]['toplam'] : 0;

// Durum dağılımı yüzdeleri (yığılmış çubuk için)
$durum_parcalari = [];
if ($ozet['toplam_tutar'] > 0) {
    $durum_parcalari = [
        'odendi'    => ['ad' => 'Ödendi',    'tutar' => $ozet['odenen_tutar']],
        'odenecek'  => ['ad' => 'Ödenecek',  'tutar' => $ozet['odenecek_tutar']],
        'ertelendi' => ['ad' => 'Ertelendi', 'tutar' => $ozet['ertelenen_tutar']],
    ];
    foreach ($durum_parcalari as $anahtar => $parca) {
        $durum_parcalari[$anahtar]['yuzde'] = round($parca['tutar'] / $ozet['toplam_tutar'] * 100, 1);
    }
}

$sayfa_basligi = 'Rapor';
$aktif_sayfa   = 'rapor';
require 'parcalar/ust.php';
?>

<header class="sayfa-basi">
    <div>
        <p class="ust-etiket">Defter · Rapor · <?= guvenli(ay_etiketi($secili_ay)) ?></p>
        <h1 class="sayfa-manset">Rakamların <em>anlattıkları</em>.</h1>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a href="indir.php?ay=<?= urlencode($secili_ay) ?>" class="dugme dugme-cizgi">⬇ Excel (CSV) indir</a>
        <button type="button" class="dugme dugme-cizgi" onclick="window.print()">Yazdır</button>
    </div>
</header>

<!-- DÖNEM / AY SEÇİCİ -->
<section class="donem-cubuk kagit">
    <div class="donem-sol">
        <span class="ust-etiket">Rapor dönemi:</span>
        <div class="donem-dugmeler">
            <a href="rapor.php?ay=tumu" class="donem-etiket <?= $secili_ay === 'tumu' ? 'aktif' : '' ?>">
                Tüm Dönemler (<?= count($tum_kayitlar) ?>)
            </a>
            <?php foreach ($ay_listesi as $ym): ?>
                <a href="rapor.php?ay=<?= urlencode($ym) ?>"
                   class="donem-etiket <?= $secili_ay === $ym ? 'aktif' : '' ?>">
                    <?= guvenli(ay_etiketi($ym)) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="donem-sag">
        <?php if ($kiyas['onceki_toplam'] > 0): ?>
            <?php $artis = $kiyas['fark_tutar'] > 0; ?>
            <span class="kiyas-rozet <?= $artis ? 'artis' : 'azalis' ?>">
                <?= guvenli(ay_etiketi($kiyas['secili_ay'])) ?> vs <?= guvenli(ay_etiketi($kiyas['onceki_ay'])) ?>:
                <strong><?= $artis ? '▲ %' . abs($kiyas['fark_yuzde']) . ' artış' : '▼ %' . abs($kiyas['fark_yuzde']) . ' tasarruf' ?></strong>
            </span>
        <?php endif; ?>
    </div>
</section>

<?php if (empty($kayitlar)): ?>
    <section class="kagit kart">
        <div class="bos-durum">
            <span class="bos-isaret">∅</span>
            <strong>Bu dönemde raporlanacak kayıt yok.</strong>
            <div style="display:flex;gap:10px;margin-top:10px">
                <?php if ($secili_ay !== 'tumu'): ?>
                    <a href="rapor.php?ay=tumu" class="dugme dugme-cizgi">Tüm dönemleri göster</a>
                <?php endif; ?>
                <a href="ekle.php" class="dugme dugme-ana">+ Yeni kayıt</a>
            </div>
        </div>
    </section>
<?php else: ?>

<!-- ÖNE ÇIKANLAR -->
<section class="uclu-izgara">
    <article class="kagit kart one-cikan">
        <span class="ust-etiket">En büyük kalem</span>
        <strong class="orta-rakam"><?= para_formati($en_buyuk['tutar']) ?></strong>
        <span class="kucuk-not"><?= guvenli($en_buyuk['baslik']) ?> · <?= tarih_formati($en_buyuk['tarih']) ?></span>
    </article>
    <article class="kagit kart one-cikan">
        <span class="ust-etiket">Kayıt başına ortalama</span>
        <strong class="orta-rakam"><?= para_formati($ortalama) ?></strong>
        <span class="kucuk-not"><?= $ozet['toplam_kayit'] ?> kayıt üzerinden</span>
    </article>
    <article class="kagit kart one-cikan">
        <span class="ust-etiket">En çok harcanan</span>
        <strong class="orta-rakam" style="color: <?= kategori_rengi($ilk_kategori) ?>"><?= guvenli($ilk_kategori) ?></strong>
        <span class="kucuk-not">Toplamın %<?= $dagilim[$ilk_kategori]['yuzde'] ?>’i</span>
    </article>
</section>

<!-- DURUM DAĞILIMI -->
<section class="kagit kart">
    <header class="kart-basi">
        <h2 class="kart-baslik">Ödeme durumu</h2>
        <span class="ince-etiket">Toplam <?= para_formati($ozet['toplam_tutar']) ?></span>
    </header>
    <div class="yigin-cubuk">
        <?php foreach ($durum_parcalari as $anahtar => $parca): ?>
            <?php if ($parca['yuzde'] > 0): ?>
                <span class="yigin-parca <?= $anahtar ?>" style="width: <?= $parca['yuzde'] ?>%"
                      title="<?= $parca['ad'] ?>: %<?= $parca['yuzde'] ?>"></span>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <ul class="yigin-lejant">
        <?php foreach ($durum_parcalari as $anahtar => $parca): ?>
            <li><i class="nokta <?= $anahtar ?>"></i><?= $parca['ad'] ?> <b>%<?= $parca['yuzde'] ?></b> <span><?= para_formati($parca['tutar']) ?></span></li>
        <?php endforeach; ?>
    </ul>
</section>

<!-- KATEGORİ TABLOSU -->
<section class="kagit defter">
    <header class="defter-basi">
        <h2 class="kart-baslik">Kategorilere göre</h2>
        <span class="kucuk-not"><?= count($dagilim) ?> kategori · <?= guvenli(ay_etiketi($secili_ay)) ?></span>
    </header>
    <div class="tablo-kaydir">
        <table class="defter-tablo rapor-tablo">
            <thead>
                <tr>
                    <th>Kategori</th>
                    <th class="sag">Adet</th>
                    <th class="cubuk-sutun">Pay</th>
                    <th class="sag">Toplam</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($dagilim as $kat => $bilgi): ?>
                    <tr>
                        <td><span class="kategori-mur" style="--renk: <?= kategori_rengi($kat) ?>"><?= guvenli($kat) ?></span></td>
                        <td class="sag tutar"><?= $bilgi['adet'] ?></td>
                        <td class="cubuk-sutun">
                            <span class="yatay-cubuk">
                                <!-- Çubuk uzunluğu en yüksek kategoriye göre oranlanır -->
                                <span style="width: <?= $en_yuksek > 0 ? round($bilgi['toplam'] / $en_yuksek * 100) : 0 ?>%; background: <?= kategori_rengi($kat) ?>"></span>
                            </span>
                            <small>%<?= $bilgi['yuzde'] ?></small>
                        </td>
                        <td class="sag"><span class="tutar"><?= para_formati($bilgi['toplam']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td>Genel toplam</td>
                    <td class="sag tutar"><?= $ozet['toplam_kayit'] ?></td>
                    <td></td>
                    <td class="sag"><span class="tutar cift-cizgi"><?= para_formati($ozet['toplam_tutar']) ?></span></td>
                </tr>
            </tfoot>
        </table>
    </div>
</section>

<?php endif; ?>

<?php require 'parcalar/alt.php'; ?>

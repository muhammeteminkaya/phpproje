<?php
/**
 * BütçeTakip — Harcama Kaydı Düzenleme (duzenle.php)
 *
 * Mevcut bir harcama kaydının başlığını, tutarını, tarihini, kategorisini,
 * durumunu, tekrarlayan (sabit gider) özelliğini ve fatura dekontunu günceller.
 */

require_once 'auth.php';

$kayit_id = isset($_GET['id']) ? (int)$_GET['id'] : (int)($_POST['id'] ?? 0);
$mevcut   = kayit_getir($kullanici_id, $kayit_id);

if (!$mevcut) {
    header('Location: index.php');
    exit;
}

$kategori_secenekleri = ['Kira & Barınma', 'Gıda & Market', 'Faturalar', 'Abonelikler',
                         'Ulaşım', 'Finans', 'Eğitim & Kitap', 'Sağlık', 'Eğlence & Hobi'];
$gecerli_durumlar     = ['Ödendi', 'Ödenecek', 'Ertelendi'];

$hatalar = [];

$baslik      = $mevcut['baslik'];
$kategori    = $mevcut['kategori'];
$tutar       = number_format((float)$mevcut['tutar'], 2, ',', '.');
$aciklama    = $mevcut['aciklama'];
$durum       = $mevcut['durum'];
$tarih       = $mevcut['tarih'];
$tekrarlayan = (int)$mevcut['tekrarlayan'];
$dekont_yolu = $mevcut['dekont'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $baslik      = trim($_POST['baslik'] ?? '');
    $kategori    = trim($_POST['kategori'] ?? '');
    $tutar       = trim($_POST['tutar'] ?? '');
    $aciklama    = trim($_POST['aciklama'] ?? '');
    $durum       = trim($_POST['durum'] ?? '');
    $tarih       = trim($_POST['tarih'] ?? '');
    $tekrarlayan = isset($_POST['tekrarlayan']) ? 1 : 0;
    $dekont_sil  = isset($_POST['dekont_sil']);

    if (!csrf_dogrula($_POST['csrf'] ?? '')) {
        $hatalar['genel'] = 'Oturum doğrulanamadı. Sayfayı yenileyip tekrar deneyin.';
    }

    if ($baslik === '') {
        $hatalar['baslik'] = 'Başlık boş bırakılamaz.';
    } elseif (mb_strlen($baslik, 'UTF-8') < 3 || mb_strlen($baslik, 'UTF-8') > 80) {
        $hatalar['baslik'] = 'Başlık 3–80 karakter olmalı.';
    }

    if ($kategori === '' || mb_strlen($kategori, 'UTF-8') > 40) {
        $hatalar['kategori'] = 'Geçerli bir kategori seçin ya da yazın (en fazla 40 karakter).';
    }

    $tutar_sayi = tutar_coz($tutar);
    if ($tutar === '' || !is_numeric($tutar_sayi) || (float)$tutar_sayi <= 0 || (float)$tutar_sayi > 10000000) {
        $hatalar['tutar'] = 'Geçerli ve sıfırdan büyük bir tutar girin.';
    }

    if (!in_array($durum, $gecerli_durumlar, true)) {
        $hatalar['durum'] = 'Geçerli bir durum seçin.';
    }

    $tarih_nesnesi = DateTime::createFromFormat('Y-m-d', $tarih);
    if ($tarih === '' || !$tarih_nesnesi || $tarih_nesnesi->format('Y-m-d') !== $tarih) {
        $hatalar['tarih'] = 'Geçerli bir tarih seçin.';
    }

    if (mb_strlen($aciklama, 'UTF-8') > 250) {
        $hatalar['aciklama'] = 'Açıklama en fazla 250 karakter olabilir.';
    }

    // Dekont silme veya yeni dekont yükleme ($_FILES)
    if ($dekont_sil && $dekont_yolu) {
        $eski = __DIR__ . '/' . $dekont_yolu;
        if (is_file($eski)) {
            @unlink($eski);
        }
        $dekont_yolu = null;
    }

    $dekont_hatasi = '';
    $yeni_dekont   = dekont_yukle($_FILES['dekont'] ?? null, $dekont_hatasi);
    if ($dekont_hatasi !== '') {
        $hatalar['dekont'] = $dekont_hatasi;
    } elseif ($yeni_dekont !== null) {
        if ($dekont_yolu && is_file(__DIR__ . '/' . $dekont_yolu)) {
            @unlink(__DIR__ . '/' . $dekont_yolu);
        }
        $dekont_yolu = $yeni_dekont;
    }

    if (empty($hatalar)) {
        kayit_guncelle(
            $kullanici_id,
            $kayit_id,
            $baslik,
            $kategori,
            round((float)$tutar_sayi, 2),
            $aciklama,
            $durum,
            $tarih,
            $tekrarlayan,
            $dekont_yolu
        );

        header('Location: index.php?duzenlendi=1');
        exit;
    }
}

function duzenle_hatasi($hatalar, $alan) {
    return isset($hatalar[$alan]) ? '<span class="alan-hata">' . guvenli($hatalar[$alan]) . '</span>' : '';
}

$sayfa_basligi = 'Kaydı düzenle';
$aktif_sayfa   = 'panel';
require 'parcalar/ust.php';
?>

<header class="sayfa-basi">
    <div>
        <p class="ust-etiket">Defter · Satır #<?= (int)$kayit_id ?> düzenleme</p>
        <h1 class="sayfa-manset">Kaydı <em>düzelt</em>.</h1>
    </div>
    <a href="index.php" class="dugme dugme-sade">← Panele dön</a>
</header>

<?php if (!empty($hatalar)): ?>
    <div class="bildirim bildirim-hata">
        <?= isset($hatalar['genel']) ? guvenli($hatalar['genel']) : count($hatalar) . ' alanda düzeltme gerekiyor.' ?>
    </div>
<?php endif; ?>

<div class="ekle-izgara">

    <form method="POST" action="duzenle.php?id=<?= (int)$kayit_id ?>" enctype="multipart/form-data"
          class="kagit kart form-kart" novalidate id="kayitFormu">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="id" value="<?= (int)$kayit_id ?>">

        <label class="alan <?= isset($hatalar['baslik']) ? 'hatali' : '' ?>">
            <span class="alan-etiket">Başlık <i>*</i></span>
            <input type="text" name="baslik" class="girdi" maxlength="80" value="<?= guvenli($baslik) ?>">
            <?= duzenle_hatasi($hatalar, 'baslik') ?>
        </label>

        <div class="alan-ikili">
            <label class="alan <?= isset($hatalar['tutar']) ? 'hatali' : '' ?>">
                <span class="alan-etiket">Tutar <i>*</i></span>
                <span class="tutar-sarmal">
                    <input type="text" name="tutar" class="girdi girdi-tutar" inputmode="decimal" value="<?= guvenli($tutar) ?>">
                    <span class="tutar-birim">₺</span>
                </span>
                <?= duzenle_hatasi($hatalar, 'tutar') ?>
            </label>

            <label class="alan <?= isset($hatalar['tarih']) ? 'hatali' : '' ?>">
                <span class="alan-etiket">Tarih <i>*</i></span>
                <input type="date" name="tarih" class="girdi" value="<?= guvenli($tarih) ?>">
                <?= duzenle_hatasi($hatalar, 'tarih') ?>
            </label>
        </div>

        <div class="alan <?= isset($hatalar['kategori']) ? 'hatali' : '' ?>">
            <label class="alan-etiket" for="kategori">Kategori <i>*</i></label>
            <input type="text" name="kategori" id="kategori" class="girdi" list="kategoriListesi" maxlength="40"
                   value="<?= guvenli($kategori) ?>">
            <datalist id="kategoriListesi">
                <?php foreach ($kategori_secenekleri as $secenek): ?>
                    <option value="<?= guvenli($secenek) ?>">
                <?php endforeach; ?>
            </datalist>
            <div class="cipler">
                <?php foreach ($kategori_secenekleri as $secenek): ?>
                    <button type="button" class="cip <?= $kategori === $secenek ? 'secili' : '' ?>"
                            style="--renk: <?= kategori_rengi($secenek) ?>"
                            data-kategori="<?= guvenli($secenek) ?>"><?= guvenli($secenek) ?></button>
                <?php endforeach; ?>
            </div>
            <?= duzenle_hatasi($hatalar, 'kategori') ?>
        </div>

        <fieldset class="alan <?= isset($hatalar['durum']) ? 'hatali' : '' ?>">
            <legend class="alan-etiket">Durum <i>*</i></legend>
            <div class="durum-secici">
                <?php foreach ($gecerli_durumlar as $d): ?>
                    <?php $bilgi = durum_bilgisi($d); ?>
                    <label class="durum-secenek <?= $bilgi['sinif'] ?>">
                        <input type="radio" name="durum" value="<?= $d ?>" <?= $durum === $d ? 'checked' : '' ?>>
                        <span><?= $d ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <?= duzenle_hatasi($hatalar, 'durum') ?>
        </fieldset>

        <!-- Tekrarlayan (sabit) gider seçeneği -->
        <label class="onay-satiri">
            <input type="checkbox" name="tekrarlayan" value="1" <?= $tekrarlayan ? 'checked' : '' ?>>
            <span>
                <strong>↻ Her ay tekrarlayan sabit gider</strong>
                <small>Kira, fatura, internet veya abonelik gibi her ay düzenli ödenen giderleri işaretleyin.</small>
            </span>
        </label>

        <!-- Fatura Dekont / Fotoğraf Ekleme ($_FILES) -->
        <div class="alan <?= isset($hatalar['dekont']) ? 'hatali' : '' ?>">
            <span class="alan-etiket">📎 Fatura dekontu / fiş fotoğrafı <small>(JPG, PNG, WEBP, PDF · maks. 5 MB)</small></span>
            <?php if ($dekont_yolu): ?>
                <div class="mevcut-dekont">
                    <a href="<?= guvenli($dekont_yolu) ?>" target="_blank" class="ince-baglanti">📎 Yüklü dekontu görüntüle</a>
                    <label style="font-size:12.5px;cursor:pointer">
                        <input type="checkbox" name="dekont_sil" value="1"> Mevcut dekontu kaldır
                    </label>
                </div>
            <?php endif; ?>
            <input type="file" name="dekont" class="girdi" accept=".jpg,.jpeg,.png,.webp,.gif,.pdf">
            <?= duzenle_hatasi($hatalar, 'dekont') ?>
        </div>

        <label class="alan <?= isset($hatalar['aciklama']) ? 'hatali' : '' ?>">
            <span class="alan-etiket">Açıklama <small>(isteğe bağlı)</small></span>
            <textarea name="aciklama" class="girdi" rows="3" maxlength="250"><?= guvenli($aciklama) ?></textarea>
            <?= duzenle_hatasi($hatalar, 'aciklama') ?>
        </label>

        <div class="form-alt">
            <a href="index.php" class="dugme dugme-sade">Vazgeç</a>
            <button type="submit" class="dugme dugme-ana">Değişiklikleri kaydet →</button>
        </div>
    </form>

    <!-- CANLI FİŞ ÖNİZLEMESİ -->
    <aside class="fis-alani" aria-label="Önizleme">
        <p class="ust-etiket">Önizleme · #<?= (int)$kayit_id ?></p>
        <div class="fis">
            <div class="fis-ust">
                <strong>BÜTÇETAKİP</strong>
                <span>DÜZENLENEN FİŞ</span>
            </div>
            <div class="fis-cizgi"></div>
            <div class="fis-satir"><span>Tarih</span><span id="onTarih">—</span></div>
            <div class="fis-satir"><span>Kategori</span><span id="onKategori">—</span></div>
            <div class="fis-satir"><span>Kullanıcı</span><span>@<?= guvenli($kullanici_adi) ?></span></div>
            <div class="fis-cizgi"></div>
            <p class="fis-baslik" id="onBaslik">—</p>
            <p class="fis-not" id="onAciklama"></p>
            <div class="fis-cizgi"></div>
            <div class="fis-toplam"><span>TOPLAM</span><span id="onTutar">0,00 ₺</span></div>
            <div class="fis-muhur" id="onDurum">Ödenecek</div>
            <div class="fis-barkod" aria-hidden="true"></div>
        </div>
    </aside>
</div>

<script>
(function () {
    var form = document.getElementById('kayitFormu');
    var aylar = ['Ocak','Şubat','Mart','Nisan','Mayıs','Haziran','Temmuz','Ağustos','Eylül','Ekim','Kasım','Aralık'];

    function deger(ad) {
        var alan = form.querySelector('[name="' + ad + '"]:checked') || form.querySelector('[name="' + ad + '"]');
        return alan ? alan.value.trim() : '';
    }

    function guncelle() {
        var ham = deger('tutar').replace(/\./g, '').replace(',', '.');
        var tutar = parseFloat(ham);
        document.getElementById('onTutar').textContent = isNaN(tutar) ? '0,00 ₺'
            : tutar.toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ₺';

        var t = deger('tarih').split('-');
        document.getElementById('onTarih').textContent = t.length === 3 ? (+t[2]) + ' ' + aylar[+t[1] - 1] + ' ' + t[0] : '—';

        document.getElementById('onBaslik').textContent = deger('baslik') || 'Başlık girilmedi';
        document.getElementById('onKategori').textContent = deger('kategori') || '—';
        document.getElementById('onAciklama').textContent = deger('aciklama');

        var durum = deger('durum') || 'Ödenecek';
        var muhur = document.getElementById('onDurum');
        muhur.textContent = durum;
        muhur.className = 'fis-muhur ' + ({ 'Ödendi': 'odendi', 'Ödenecek': 'odenecek', 'Ertelendi': 'ertelendi' })[durum];
    }

    form.addEventListener('input', guncelle);
    form.addEventListener('change', guncelle);

    document.querySelectorAll('.cip').forEach(function (cip) {
        cip.addEventListener('click', function () {
            document.getElementById('kategori').value = cip.dataset.kategori;
            document.querySelectorAll('.cip').forEach(function (c) { c.classList.remove('secili'); });
            cip.classList.add('secili');
            guncelle();
        });
    });

    guncelle();
})();
</script>

<?php require 'parcalar/alt.php'; ?>

<?php
/**
 * BütçeTakip — Yeni Kayıt Ekleme (ekle.php)
 *
 * Akış:
 *   1. Form POST (multipart/form-data) ile gönderilir.
 *   2. Her alan PHP tarafında doğrulanır; hatalar $hatalar dizisinde alan adına göre toplanır.
 *   3. Fatura dekontu / fiş fotoğrafı yüklendiyse dekont_yukle() ile kontrol edilip kaydedilir.
 *   4. Hata yoksa kayıt MySQL veritabanına eklenir ve panele yönlendirilir (PRG deseni).
 */

require_once 'auth.php';

$kategori_secenekleri = ['Kira & Barınma', 'Gıda & Market', 'Faturalar', 'Abonelikler',
                         'Ulaşım', 'Finans', 'Eğitim & Kitap', 'Sağlık', 'Eğlence & Hobi'];
$gecerli_durumlar     = ['Ödendi', 'Ödenecek', 'Ertelendi'];

$hatalar = [];

// Varsayılan form değerleri
$baslik      = '';
$kategori    = '';
$tutar       = '';
$aciklama    = '';
$durum       = 'Ödenecek';
$tarih       = date('Y-m-d');
$tekrarlayan = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1) Verileri al
    $baslik      = trim($_POST['baslik'] ?? '');
    $kategori    = trim($_POST['kategori'] ?? '');
    $tutar       = trim($_POST['tutar'] ?? '');
    $aciklama    = trim($_POST['aciklama'] ?? '');
    $durum       = trim($_POST['durum'] ?? '');
    $tarih       = trim($_POST['tarih'] ?? '');
    $tekrarlayan = isset($_POST['tekrarlayan']) ? 1 : 0;

    // 2) Doğrula
    if (!csrf_dogrula($_POST['csrf'] ?? '')) {
        $hatalar['genel'] = 'Oturum doğrulanamadı. Sayfayı yenileyip tekrar deneyin.';
    }

    if ($baslik === '') {
        $hatalar['baslik'] = 'Başlık boş bırakılamaz.';
    } elseif (mb_strlen($baslik, 'UTF-8') < 3) {
        $hatalar['baslik'] = 'Başlık en az 3 karakter olmalı.';
    } elseif (mb_strlen($baslik, 'UTF-8') > 80) {
        $hatalar['baslik'] = 'Başlık en fazla 80 karakter olabilir.';
    }

    if ($kategori === '') {
        $hatalar['kategori'] = 'Bir kategori seçin ya da yazın.';
    } elseif (mb_strlen($kategori, 'UTF-8') > 40) {
        $hatalar['kategori'] = 'Kategori adı en fazla 40 karakter olabilir.';
    }

    $tutar_sayi = tutar_coz($tutar); // "1.250,50" veya "1250.50" yazılırsa da kabul et
    if ($tutar === '') {
        $hatalar['tutar'] = 'Tutar boş bırakılamaz.';
    } elseif (!is_numeric($tutar_sayi)) {
        $hatalar['tutar'] = 'Tutar sayı olmalı (örn: 450 veya 1250,50).';
    } elseif ((float)$tutar_sayi <= 0) {
        $hatalar['tutar'] = 'Tutar sıfırdan büyük olmalı.';
    } elseif ((float)$tutar_sayi > 10000000) {
        $hatalar['tutar'] = 'Tutar çok büyük görünüyor, kontrol edin.';
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

    // Dekont / fiş dosyası kontrolü
    $dekont_hata = null;
    $dekont_yolu = null;
    if (empty($hatalar) && isset($_FILES['dekont']) && $_FILES['dekont']['error'] !== UPLOAD_ERR_NO_FILE) {
        $dekont_yolu = dekont_yukle($_FILES['dekont'], $dekont_hata);
        if ($dekont_hata !== null) {
            $hatalar['dekont'] = $dekont_hata;
        }
    }

    // 3) Hata yoksa veritabanına kaydet
    if (empty($hatalar)) {
        kayit_ekle(
            $kullanici_id,
            $baslik,
            $kategori,
            round((float)$tutar_sayi, 2),
            $aciklama,
            $durum,
            $tarih,
            $tekrarlayan,
            $dekont_yolu
        );

        header('Location: index.php?eklendi=1');
        exit;
    }
}

/**
 * Bir alan için hata varsa mesajı yazdıran küçük yardımcı (sadece bu sayfada kullanılır).
 */
function alan_hatasi($hatalar, $alan) {
    if (isset($hatalar[$alan])) {
        return '<span class="alan-hata">' . guvenli($hatalar[$alan]) . '</span>';
    }
    return '';
}

$sayfa_basligi = 'Yeni kayıt';
$aktif_sayfa   = 'ekle';
require 'parcalar/ust.php';
?>

<header class="sayfa-basi">
    <div>
        <p class="ust-etiket">Defter · Yeni satır</p>
        <h1 class="sayfa-manset">Bir harcamayı <em>deftere işle</em>.</h1>
    </div>
    <a href="index.php" class="dugme dugme-sade">← Panele dön</a>
</header>

<?php if (!empty($hatalar)): ?>
    <div class="bildirim bildirim-hata">
        <?= isset($hatalar['genel']) ? guvenli($hatalar['genel']) : count($hatalar) . ' alanda düzeltme gerekiyor. Kırmızı işaretli alanlara bakın.' ?>
    </div>
<?php endif; ?>

<div class="ekle-izgara">

    <!-- FORM (POST + Dosya Yükleme) -->
    <form method="POST" action="ekle.php" enctype="multipart/form-data" class="kagit kart form-kart" novalidate id="kayitFormu">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">

        <label class="alan <?= isset($hatalar['baslik']) ? 'hatali' : '' ?>">
            <span class="alan-etiket">Başlık <i>*</i></span>
            <input type="text" name="baslik" class="girdi" maxlength="80" data-onizle="baslik"
                   placeholder="Örn: Doğalgaz faturası" value="<?= guvenli($baslik) ?>">
            <?= alan_hatasi($hatalar, 'baslik') ?>
        </label>

        <div class="alan-ikili">
            <label class="alan <?= isset($hatalar['tutar']) ? 'hatali' : '' ?>">
                <span class="alan-etiket">Tutar <i>*</i></span>
                <span class="tutar-sarmal">
                    <input type="text" name="tutar" class="girdi girdi-tutar" inputmode="decimal" data-onizle="tutar"
                           placeholder="0,00" value="<?= guvenli($tutar) ?>">
                    <span class="tutar-birim">₺</span>
                </span>
                <?= alan_hatasi($hatalar, 'tutar') ?>
            </label>

            <label class="alan <?= isset($hatalar['tarih']) ? 'hatali' : '' ?>">
                <span class="alan-etiket">Tarih <i>*</i></span>
                <input type="date" name="tarih" class="girdi" data-onizle="tarih" value="<?= guvenli($tarih) ?>">
                <?= alan_hatasi($hatalar, 'tarih') ?>
            </label>
        </div>

        <div class="alan <?= isset($hatalar['kategori']) ? 'hatali' : '' ?>">
            <label class="alan-etiket" for="kategori">Kategori <i>*</i></label>
            <input type="text" name="kategori" id="kategori" class="girdi" list="kategoriListesi" maxlength="40"
                   data-onizle="kategori" placeholder="Seçin ya da yeni bir tane yazın" value="<?= guvenli($kategori) ?>">
            <datalist id="kategoriListesi">
                <?php foreach ($kategori_secenekleri as $secenek): ?>
                    <option value="<?= guvenli($secenek) ?>">
                <?php endforeach; ?>
            </datalist>
            <!-- Hızlı seçim çipleri -->
            <div class="cipler">
                <?php foreach ($kategori_secenekleri as $secenek): ?>
                    <button type="button" class="cip <?= $kategori === $secenek ? 'secili' : '' ?>"
                            style="--renk: <?= kategori_rengi($secenek) ?>"
                            data-kategori="<?= guvenli($secenek) ?>"><?= guvenli($secenek) ?></button>
                <?php endforeach; ?>
            </div>
            <?= alan_hatasi($hatalar, 'kategori') ?>
        </div>

        <fieldset class="alan <?= isset($hatalar['durum']) ? 'hatali' : '' ?>">
            <legend class="alan-etiket">Durum <i>*</i></legend>
            <div class="durum-secici">
                <?php foreach ($gecerli_durumlar as $d): ?>
                    <?php $bilgi = durum_bilgisi($d); ?>
                    <label class="durum-secenek <?= $bilgi['sinif'] ?>">
                        <input type="radio" name="durum" value="<?= $d ?>" data-onizle="durum" <?= $durum === $d ? 'checked' : '' ?>>
                        <span><?= $d ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <?= alan_hatasi($hatalar, 'durum') ?>
        </fieldset>

        <!-- Tekrarlayan (sabit) gider kutusu -->
        <label class="onay-satiri">
            <input type="checkbox" name="tekrarlayan" value="1" <?= $tekrarlayan ? 'checked' : '' ?>>
            <span>
                <strong>↻ Her ay tekrarlayan sabit gider</strong>
                <small>Kira, abonelik, internet gibi her ay düzenli ödenen bir giderse işaretle; yeni ayda tek tıkla aktarabilirsin.</small>
            </span>
        </label>

        <!-- Fatura dekontu / fiş fotoğrafı yükleme -->
        <div class="alan <?= isset($hatalar['dekont']) ? 'hatali' : '' ?>">
            <span class="alan-etiket">Fatura dekontu / fiş fotoğrafı <small>(isteğe bağlı · JPG, PNG, PDF)</small></span>
            <input type="file" name="dekont" class="girdi girdi-dosya" accept=".jpg,.jpeg,.png,.webp,.gif,.pdf">
            <?= alan_hatasi($hatalar, 'dekont') ?>
        </div>

        <label class="alan <?= isset($hatalar['aciklama']) ? 'hatali' : '' ?>">
            <span class="alan-etiket">Açıklama <small>(isteğe bağlı)</small></span>
            <textarea name="aciklama" class="girdi" rows="3" maxlength="250" data-onizle="aciklama"
                      placeholder="Kısa bir not…"><?= guvenli($aciklama) ?></textarea>
            <?= alan_hatasi($hatalar, 'aciklama') ?>
        </label>

        <div class="form-alt">
            <a href="index.php" class="dugme dugme-sade">Vazgeç</a>
            <button type="submit" class="dugme dugme-ana">Deftere işle →</button>
        </div>
    </form>

    <!-- CANLI FİŞ ÖNİZLEMESİ -->
    <aside class="fis-alani" aria-label="Önizleme">
        <p class="ust-etiket">Önizleme</p>
        <div class="fis">
            <div class="fis-ust">
                <strong>BÜTÇETAKİP</strong>
                <span>HARCAMA FİŞİ</span>
            </div>
            <div class="fis-cizgi"></div>
            <div class="fis-satir"><span>Tarih</span><span id="onTarih">—</span></div>
            <div class="fis-satir"><span>Kategori</span><span id="onKategori">—</span></div>
            <div class="fis-satir"><span>Kullanıcı</span><span>@<?= guvenli($kullanici_adi) ?></span></div>
            <div class="fis-cizgi"></div>
            <p class="fis-baslik" id="onBaslik">Başlık girilmedi</p>
            <p class="fis-not" id="onAciklama"></p>
            <div class="fis-cizgi"></div>
            <div class="fis-toplam"><span>TOPLAM</span><span id="onTutar">0,00 ₺</span></div>
            <div class="fis-muhur" id="onDurum">Ödenecek</div>
            <div class="fis-barkod" aria-hidden="true"></div>
        </div>
        <p class="kucuk-not">Önizleme yalnızca görseldir. Asıl doğrulama, form gönderildiğinde PHP tarafında yapılır.</p>
    </aside>
</div>

<script>
// --- Fiş önizlemesi: form alanları değiştikçe sağdaki fişi günceller (sadece görsel) ---
(function () {
    var form = document.getElementById('kayitFormu');
    var aylar = ['Ocak','Şubat','Mart','Nisan','Mayıs','Haziran','Temmuz','Ağustos','Eylül','Ekim','Kasım','Aralık'];

    function deger(ad) {
        var alan = form.querySelector('[name="' + ad + '"]:checked') || form.querySelector('[name="' + ad + '"]');
        return alan ? alan.value.trim() : '';
    }

    function guncelle() {
        var ham = deger('tutar').replace(/\s/g, '');
        if (ham.indexOf(',') !== -1) {
            ham = ham.replace(/\./g, '').replace(',', '.');
        }
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

    // Kategori çiplerine tıklanınca kategori alanını doldur
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

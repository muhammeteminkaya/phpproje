<?php
/**
 * BütçeTakip — Yardımcı Fonksiyonlar (functions.php)
 *
 * Projede tekrar eden işler burada fonksiyonlara ayrılmıştır:
 *   - Güvenlik:      guvenli(), csrf_token(), csrf_dogrula(), kullanici_dogrula()
 *   - Veritabanı:    kullanici_getir(), kullanici_olustur(), kullanici_adi_var_mi(),
 *                    kayitlari_getir(), kayit_ekle(), kayit_sil(), kayit_durum_guncelle()
 *   - Biçimlendirme: para_formati(), tarih_formati(), uzun_tarih(), selamlama()
 *   - Hesaplama:     istatistik_hesapla(), kategori_dagilimi(), yaklasan_odemeler(),
 *                    limit_durumu(), gun_farki()
 *   - Görünüm:       durum_bilgisi(), kategori_rengi()
 */

// Tarih/saat hesaplarının Türkiye saatine göre yapılması için
date_default_timezone_set('Europe/Istanbul');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/veriler.php';

/* =====================================================================
   1) GÜVENLİK
   ===================================================================== */

/**
 * Kullanıcı girdisini HTML'e yazdırmadan önce güvenli hale getirir (XSS önleme).
 * <, >, ", ', & karakterleri HTML varlıklarına (&lt; &gt; ...) dönüştürülür.
 */
function guvenli($veri) {
    if ($veri === null) {
        return '';
    }
    return htmlspecialchars(trim((string)$veri), ENT_QUOTES, 'UTF-8');
}

/**
 * Kullanıcı adı ve şifreyi veritabanındaki kayıtla karşılaştırır.
 * Şifre, password_verify() ile saklanan hash'e karşı kontrol edilir.
 *
 * @return int|null Doğruysa kullanıcı ID'si, yanlışsa null
 */
function kullanici_dogrula($kullanici_adi, $sifre) {
    $sorgu = db()->prepare('SELECT id, sifre_hash FROM kullanicilar WHERE kullanici_adi = ?');
    $sorgu->execute([mb_strtolower(trim($kullanici_adi), 'UTF-8')]);
    $satir = $sorgu->fetch();

    if ($satir && password_verify($sifre, $satir['sifre_hash'])) {
        return (int)$satir['id'];
    }
    return null;
}

/**
 * Formlar için tek kullanımlık olmayan oturum anahtarı (CSRF token) üretir.
 * Başka bir siteden gizlice gönderilen sahte formları engellemek için kullanılır.
 */
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Formdan gelen token ile oturumdaki token aynı mı kontrol eder.
 */
function csrf_dogrula($gelen_token) {
    return isset($_SESSION['csrf_token'])
        && is_string($gelen_token)
        && hash_equals($_SESSION['csrf_token'], $gelen_token);
}

/* =====================================================================
   2) VERİTABANI İŞLEMLERİ (PDO + hazırlanmış ifadeler)
   ===================================================================== */

/**
 * ID'ye göre kullanıcıyı getirir (şifre hash'i hariç).
 */
function kullanici_getir($id) {
    $sorgu = db()->prepare('SELECT id, kullanici_adi, ad_soyad, eposta, aylik_maas, aylik_limit,
                                   hedef_ad, hedef_tutar, hedef_biriken, olusturma
                            FROM kullanicilar WHERE id = ?');
    $sorgu->execute([$id]);
    $kullanici = $sorgu->fetch();
    return $kullanici ?: null;
}

/**
 * Kullanıcı adı daha önce alınmış mı? ($haric_id: profil düzenlerken kendisini sayma)
 */
function kullanici_adi_var_mi($kullanici_adi, $haric_id = 0) {
    $sorgu = db()->prepare('SELECT COUNT(*) FROM kullanicilar WHERE kullanici_adi = ? AND id <> ?');
    $sorgu->execute([$kullanici_adi, $haric_id]);
    return (int)$sorgu->fetchColumn() > 0;
}

/**
 * Yeni kullanıcıyı veritabanına ekler, oluşan ID'yi döndürür.
 * Şifre password_hash() ile özetlenerek saklanır.
 */
function kullanici_olustur($kullanici_adi, $ad_soyad, $eposta, $sifre, $maas, $limit) {
    $sorgu = db()->prepare('INSERT INTO kullanicilar (kullanici_adi, ad_soyad, eposta, sifre_hash, aylik_maas, aylik_limit)
                            VALUES (?, ?, ?, ?, ?, ?)');
    $sorgu->execute([$kullanici_adi, $ad_soyad, $eposta ?: null, password_hash($sifre, PASSWORD_DEFAULT), $maas, $limit]);
    return (int)db()->lastInsertId();
}

/**
 * Giriş yapan kullanıcının harcama kayıtlarını getirir.
 */
function kayitlari_getir($kullanici_id) {
    $sorgu = db()->prepare('SELECT id, baslik, kategori, tutar, aciklama, durum, tarih, odeme_tarihi, tekrarlayan, dekont
                            FROM harcamalar WHERE kullanici_id = ? ORDER BY tarih DESC, id DESC');
    $sorgu->execute([$kullanici_id]);
    $kayitlar = $sorgu->fetchAll();

    foreach ($kayitlar as $i => $k) {
        $kayitlar[$i]['tutar']       = (float)$k['tutar'];
        $kayitlar[$i]['tekrarlayan'] = (int)$k['tekrarlayan'];
    }
    return $kayitlar;
}

/**
 * Düzenleme sayfası için tek bir harcama kaydını getirir (kullanıcı kontrolüyle).
 */
function kayit_getir($kullanici_id, $kayit_id) {
    $sorgu = db()->prepare('SELECT id, baslik, kategori, tutar, aciklama, durum, tarih, odeme_tarihi, tekrarlayan, dekont
                            FROM harcamalar WHERE id = ? AND kullanici_id = ?');
    $sorgu->execute([$kayit_id, $kullanici_id]);
    $kayit = $sorgu->fetch();
    if ($kayit) {
        $kayit['tutar']       = (float)$kayit['tutar'];
        $kayit['tekrarlayan'] = (int)$kayit['tekrarlayan'];
    }
    return $kayit ?: null;
}

/**
 * Yeni harcama kaydı ekler.
 */
function kayit_ekle($kullanici_id, $baslik, $kategori, $tutar, $aciklama, $durum, $tarih, $tekrarlayan = 0, $dekont = null) {
    $odeme = $durum === 'Ödendi' ? date('Y-m-d') : null;
    $sorgu = db()->prepare('INSERT INTO harcamalar (kullanici_id, baslik, kategori, tutar, aciklama, durum, tarih, odeme_tarihi, tekrarlayan, dekont)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $sorgu->execute([$kullanici_id, $baslik, $kategori, $tutar, $aciklama, $durum, $tarih, $odeme, (int)$tekrarlayan, $dekont]);
}

/**
 * Mevcut harcama kaydını günceller (duzenle.php).
 */
function kayit_guncelle($kullanici_id, $kayit_id, $baslik, $kategori, $tutar, $aciklama, $durum, $tarih, $tekrarlayan = 0, $dekont = null) {
    $odeme = $durum === 'Ödendi' ? date('Y-m-d') : null;
    $sorgu = db()->prepare('UPDATE harcamalar
                            SET baslik = ?, kategori = ?, tutar = ?, aciklama = ?, durum = ?,
                                tarih = ?, odeme_tarihi = ?, tekrarlayan = ?, dekont = ?
                            WHERE id = ? AND kullanici_id = ?');
    $sorgu->execute([$baslik, $kategori, $tutar, $aciklama, $durum, $tarih, $odeme, (int)$tekrarlayan, $dekont, $kayit_id, $kullanici_id]);
    return $sorgu->rowCount() > 0;
}

/**
 * Kaydı siler (varsa yüklü dekont dosyasını da diskten temizler).
 */
function kayit_sil($kullanici_id, $kayit_id) {
    $mevcut = kayit_getir($kullanici_id, $kayit_id);
    if ($mevcut && !empty($mevcut['dekont'])) {
        $dosya_yolu = __DIR__ . '/' . $mevcut['dekont'];
        if (is_file($dosya_yolu)) {
            @unlink($dosya_yolu);
        }
    }
    $sorgu = db()->prepare('DELETE FROM harcamalar WHERE id = ? AND kullanici_id = ?');
    $sorgu->execute([$kayit_id, $kullanici_id]);
    return $sorgu->rowCount() > 0;
}

/**
 * Kaydın durumunu değiştirir (Ödendi / Ödenecek / Ertelendi).
 */
function kayit_durum_guncelle($kullanici_id, $kayit_id, $yeni_durum) {
    $odeme = $yeni_durum === 'Ödendi' ? date('Y-m-d') : null;
    $sorgu = db()->prepare('UPDATE harcamalar SET durum = ?, odeme_tarihi = ? WHERE id = ? AND kullanici_id = ?');
    $sorgu->execute([$yeni_durum, $odeme, $kayit_id, $kullanici_id]);
    return $sorgu->rowCount() > 0;
}

/**
 * Fatura dekontu / fiş fotoğrafı yükleme ($_FILES).
 * Yalnızca JPG, PNG, WEBP, GIF ve PDF dosyalarını (en fazla 5 MB) kabul eder.
 */
function dekont_yukle($dosya, &$hata) {
    if (empty($dosya) || !isset($dosya['error']) || $dosya['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($dosya['error'] !== UPLOAD_ERR_OK) {
        $hata = 'Dekont dosyası yüklenirken bir hata oluştu.';
        return null;
    }

    if ($dosya['size'] > 5 * 1024 * 1024) {
        $hata = 'Dekont dosyası en fazla 5 MB olabilir.';
        return null;
    }

    $uzanti = strtolower(pathinfo($dosya['name'], PATHINFO_EXTENSION));
    $izinli = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf'];
    if (!in_array($uzanti, $izinli, true)) {
        $hata = 'Yalnızca JPG, PNG, WEBP, GIF veya PDF formatında fiş/dekont yükleyebilirsiniz.';
        return null;
    }

    $klasor = __DIR__ . '/yuklemeler';
    if (!is_dir($klasor)) {
        mkdir($klasor, 0775, true);
    }

    $yeni_ad = 'dekont_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $uzanti;
    $hedef   = $klasor . '/' . $yeni_ad;

    if (!move_uploaded_file($dosya['tmp_name'], $hedef)) {
        $hata = 'Dekont dosyası sunucuya kaydedilemedi.';
        return null;
    }

    return 'yuklemeler/' . $yeni_ad;
}

/**
 * Kullanıcının tekrarlayan (sabit) giderlerini hedef aya ('YYYY-MM') kopyalar.
 * Aynı başlık o ayda zaten varsa mükerrer eklemez. Eklenen kayıt sayısını döndürür.
 */
function tekrarlayan_giderleri_aktar($kullanici_id, $hedef_ay = null) {
    if (!$hedef_ay || !preg_match('/^\d{4}-\d{2}$/', $hedef_ay)) {
        $hedef_ay = date('Y-m');
    }

    $pdo = db();
    // Kullanıcının tekrarlayan olarak işaretlenmiş benzersiz gider şablonlarını al
    $sorgu = $pdo->prepare('SELECT baslik, kategori, tutar, aciklama, tarih
                            FROM harcamalar
                            WHERE kullanici_id = ? AND tekrarlayan = 1
                            ORDER BY tarih DESC, id DESC');
    $sorgu->execute([$kullanici_id]);
    $sablonlar = $sorgu->fetchAll();

    // Hedef ayda mevcut olan başlıkları bul
    $mevcut_sorgu = $pdo->prepare("SELECT LOWER(baslik) FROM harcamalar WHERE kullanici_id = ? AND DATE_FORMAT(tarih, '%Y-%m') = ?");
    $mevcut_sorgu->execute([$kullanici_id, $hedef_ay]);
    $mevcut_basliklar = $mevcut_sorgu->fetchAll(PDO::FETCH_COLUMN);

    $gorulen = [];
    $eklenen = 0;

    foreach ($sablonlar as $s) {
        // Başlığın başındaki "Geçen Ay " ifadesini temizle
        $temiz_baslik = preg_replace('/^Geçen Ay\s+/iu', '', $s['baslik']);
        $anahtar      = mb_strtolower($temiz_baslik, 'UTF-8');

        if (isset($gorulen[$anahtar]) || in_array($anahtar, $mevcut_basliklar, true)) {
            continue;
        }
        $gorulen[$anahtar] = true;

        // Orijinal kaydın gününü koru (ayın son gününü aşmasın)
        $gun = (int)date('d', strtotime($s['tarih']));
        $ayin_son_gunu = (int)date('t', strtotime($hedef_ay . '-01'));
        $yeni_gun = str_pad((string)min($gun, $ayin_son_gunu), 2, '0', STR_PAD_LEFT);
        $yeni_tarih = $hedef_ay . '-' . $yeni_gun;

        kayit_ekle($kullanici_id, $temiz_baslik, $s['kategori'], (float)$s['tutar'], $s['aciklama'], 'Ödenecek', $yeni_tarih, 1, null);
        $eklenen++;
    }

    return $eklenen;
}

/**
 * Kullanıcının kumbara / birikim hedefini günceller.
 */
function kumbara_guncelle($kullanici_id, $hedef_ad, $hedef_tutar, $hedef_biriken) {
    $sorgu = db()->prepare('UPDATE kullanicilar SET hedef_ad = ?, hedef_tutar = ?, hedef_biriken = ? WHERE id = ?');
    $sorgu->execute([$hedef_ad, max(0, (float)$hedef_tutar), max(0, (float)$hedef_biriken), $kullanici_id]);
}

/* =====================================================================
   3) BİÇİMLENDİRME
   ===================================================================== */

/**
 * 14500.5 → "14.500,50 ₺"
 */
function para_formati($tutar) {
    return number_format((float)$tutar, 2, ',', '.') . ' ₺';
}

/**
 * "2026-11-06" → "6 Kasım 2026"
 */
function tarih_formati($tarih) {
    $zaman = strtotime((string)$tarih);
    if (!$zaman) {
        return '-';
    }
    $aylar = [1 => 'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran',
              'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];

    return date('j', $zaman) . ' ' . $aylar[(int)date('n', $zaman)] . ' ' . date('Y', $zaman);
}

/**
 * "2026-11-06" → "Kas" (takvim yaprağı için kısa ay adı)
 */
function kisa_ay($tarih) {
    $kisa = [1 => 'Oca', 'Şub', 'Mar', 'Nis', 'May', 'Haz', 'Tem', 'Ağu', 'Eyl', 'Eki', 'Kas', 'Ara'];
    return $kisa[(int)date('n', strtotime($tarih))];
}

/**
 * Bugünün tarihini gün adıyla döndürür: "6 Kasım 2026, Cuma"
 */
function uzun_tarih() {
    $gunler = ['Pazar', 'Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi'];
    return tarih_formati(date('Y-m-d')) . ', ' . $gunler[(int)date('w')];
}

/**
 * Saate göre selamlama metni döndürür (koşul yapısı örneği).
 */
function selamlama() {
    $saat = (int)date('H');

    if ($saat >= 5 && $saat < 12) {
        return 'Günaydın';
    } elseif ($saat >= 12 && $saat < 18) {
        return 'İyi günler';
    } elseif ($saat >= 18 && $saat < 22) {
        return 'İyi akşamlar';
    } else {
        return 'İyi geceler';
    }
}

/* =====================================================================
   4) HESAPLAMA
   ===================================================================== */

/**
 * Toplam kayıt, toplam tutar, ödenen / bekleyen tutar ve adetleri hesaplar.
 */
function istatistik_hesapla($kayitlar) {
    $ozet = [
        'toplam_kayit'   => count($kayitlar),
        'toplam_tutar'   => 0.0,
        'odenen_tutar'   => 0.0,
        'odenecek_tutar' => 0.0,
        'ertelenen_tutar'=> 0.0,
        'odenen_adet'    => 0,
        'odenecek_adet'  => 0,
        'ertelenen_adet' => 0,
        'odenen_yuzde'   => 0
    ];

    foreach ($kayitlar as $kayit) {
        $tutar = (float)$kayit['tutar'];
        $ozet['toplam_tutar'] += $tutar;

        if ($kayit['durum'] === 'Ödendi') {
            $ozet['odenen_tutar'] += $tutar;
            $ozet['odenen_adet']++;
        } elseif ($kayit['durum'] === 'Ödenecek') {
            $ozet['odenecek_tutar'] += $tutar;
            $ozet['odenecek_adet']++;
        } else {
            $ozet['ertelenen_tutar'] += $tutar;
            $ozet['ertelenen_adet']++;
        }
    }

    if ($ozet['toplam_tutar'] > 0) {
        $ozet['odenen_yuzde'] = round($ozet['odenen_tutar'] / $ozet['toplam_tutar'] * 100);
    }

    return $ozet;
}

/**
 * Kayıtları kategoriye göre gruplar: toplam tutar, adet ve yüzde.
 * Sonuç, en çok harcanan kategoriden en aza doğru sıralanır.
 * Halka grafik ve rapor sayfası bu fonksiyonu kullanır.
 */
function kategori_dagilimi($kayitlar) {
    $dagilim = [];
    $genel_toplam = 0;

    foreach ($kayitlar as $kayit) {
        $kat = $kayit['kategori'];
        if (!isset($dagilim[$kat])) {
            $dagilim[$kat] = ['toplam' => 0, 'adet' => 0, 'yuzde' => 0];
        }
        $dagilim[$kat]['toplam'] += (float)$kayit['tutar'];
        $dagilim[$kat]['adet']++;
        $genel_toplam += (float)$kayit['tutar'];
    }

    foreach ($dagilim as $kat => $bilgi) {
        $dagilim[$kat]['yuzde'] = $genel_toplam > 0 ? round($bilgi['toplam'] / $genel_toplam * 100, 1) : 0;
    }

    // Büyükten küçüğe sırala (anahtarları koruyarak)
    uasort($dagilim, function ($a, $b) {
        return $b['toplam'] <=> $a['toplam'];
    });

    return $dagilim;
}

/**
 * Bugün ile verilen tarih arasındaki gün farkı.
 * Pozitif: ileride, 0: bugün, negatif: geçmiş (gecikmiş).
 */
function gun_farki($tarih) {
    $bugun = strtotime(date('Y-m-d'));
    $hedef = strtotime($tarih);
    return (int)round(($hedef - $bugun) / 86400);
}

/**
 * Durumu "Ödenecek" olan kayıtları tarihe göre sıralar ve en yakın $adet tanesini döndürür.
 * Her kayda 'kalan_gun' alanı eklenir.
 */
function yaklasan_odemeler($kayitlar, $adet = 4) {
    $bekleyenler = [];

    foreach ($kayitlar as $kayit) {
        if ($kayit['durum'] === 'Ödenecek') {
            $kayit['kalan_gun'] = gun_farki($kayit['tarih']);
            $bekleyenler[] = $kayit;
        }
    }

    usort($bekleyenler, function ($a, $b) {
        return strcmp($a['tarih'], $b['tarih']);
    });

    return array_slice($bekleyenler, 0, $adet);
}

/**
 * Harcanan tutarın aylık limite oranını ve seviyesini döndürür.
 * Seviye: 'rahat' (< %60), 'dikkat' (%60–85), 'kritik' (> %85)
 */
function limit_durumu($harcanan, $limit) {
    $yuzde = $limit > 0 ? round($harcanan / $limit * 100) : 0;

    if ($yuzde < 60) {
        $seviye = 'rahat';
        $mesaj  = 'Bütçen rahat görünüyor.';
    } elseif ($yuzde <= 85) {
        $seviye = 'dikkat';
        $mesaj  = 'Limite yaklaşıyorsun, harcamalara dikkat.';
    } else {
        $seviye = 'kritik';
        $mesaj  = $yuzde > 100 ? 'Aylık limit aşıldı!' : 'Limitin neredeyse doldu.';
    }

    return [
        'yuzde'  => $yuzde,
        'cubuk'  => min($yuzde, 100), // Çubuk taşmasın diye en fazla %100
        'seviye' => $seviye,
        'mesaj'  => $mesaj,
        'kalan'  => $limit - $harcanan
    ];
}

/* =====================================================================
   5) GÖRÜNÜM YARDIMCILARI
   ===================================================================== */

/**
 * Duruma göre CSS sınıfı ve etiket döndürür.
 */
function durum_bilgisi($durum) {
    if ($durum === 'Ödendi') {
        return ['sinif' => 'odendi', 'etiket' => 'Ödendi'];
    } elseif ($durum === 'Ödenecek') {
        return ['sinif' => 'odenecek', 'etiket' => 'Ödenecek'];
    } elseif ($durum === 'Ertelendi') {
        return ['sinif' => 'ertelendi', 'etiket' => 'Ertelendi'];
    }
    return ['sinif' => 'belirsiz', 'etiket' => $durum];
}

/**
 * Her kategoriye sabit bir "mürekkep" rengi atar.
 * Listede olmayan (kullanıcının yazdığı) kategoriler için isimden türetilen bir renk seçilir.
 */
function kategori_rengi($kategori) {
    $renkler = [
        'Kira & Barınma' => '#6d4c7d',
        'Gıda & Market'  => '#3f7d58',
        'Faturalar'      => '#b7791f',
        'Abonelikler'    => '#2f6f8f',
        'Ulaşım'         => '#3b5ba9',
        'Finans'         => '#c2410c',
        'Eğitim & Kitap' => '#8a5a44',
        'Sağlık'         => '#b04a6b',
        'Eğlence & Hobi' => '#5b7d2a',
    ];

    if (isset($renkler[$kategori])) {
        return $renkler[$kategori];
    }

    $yedek = ['#7a6a53', '#4f6d7a', '#8c5e58', '#5e7a5c', '#6b5b95', '#9a7b4f'];
    return $yedek[abs(crc32($kategori)) % count($yedek)];
}

/**
 * Maaşa göre ay sonu tablosu:
 *   - odenen:     maaştan çoktan çıkan para
 *   - bekleyen:   ödenecek faturalar
 *   - kalan:      maaş − ödenen − bekleyen (ay sonunda cepte kalacak tahmini tutar)
 *   - yüzdeler:   çubuk grafiği için
 */
function maas_ozeti($maas, $odenen, $bekleyen) {
    $maas  = (float)$maas;
    $kalan = $maas - $odenen - $bekleyen;

    $yuzde = function ($deger) use ($maas) {
        return $maas > 0 ? max(0, min(100, round($deger / $maas * 100, 1))) : 0;
    };

    return [
        'maas'           => $maas,
        'kalan'          => $kalan,
        'odenen_yuzde'   => $yuzde($odenen),
        'bekleyen_yuzde' => $yuzde($bekleyen),
        'kalan_yuzde'    => $yuzde(max(0, $kalan)),
        'tasarruf_orani' => $maas > 0 ? round($kalan / $maas * 100) : 0,
    ];
}

/**
 * Kullanıcının yazdığı tutarı sayıya çevrilebilir metne dönüştürür.
 *   "42.000,50" → "42000.50"   (Türkçe yazım)
 *   "1250,5"    → "1250.5"
 *   "1250.5"    → "1250.5"     (noktalı ondalık da kabul)
 */
function tutar_coz($metin) {
    $metin = str_replace([' ', '₺'], '', trim((string)$metin));
    if (strpos($metin, ',') !== false) {
        $metin = str_replace('.', '', $metin);   // binlik ayırıcı noktaları at
        $metin = str_replace(',', '.', $metin);  // ondalık virgülü noktaya çevir
    }
    return $metin;
}

/**
 * Kullanıcının profil ve maaş/limit bilgilerini veritabanında günceller.
 */
function kullanici_profil_guncelle($id, $kullanici_adi, $ad_soyad, $eposta, $maas, $limit) {
    $sorgu = db()->prepare('UPDATE kullanicilar
                            SET kullanici_adi = ?, ad_soyad = ?, eposta = ?, aylik_maas = ?, aylik_limit = ?
                            WHERE id = ?');
    $sorgu->execute([$kullanici_adi, $ad_soyad, $eposta ?: null, $maas, $limit, $id]);
}

/**
 * Kullanıcının mevcut şifresini doğrulayıp yeni şifre hash'ini kaydeder.
 */
function kullanici_sifre_degistir($id, $mevcut_sifre, $yeni_sifre) {
    $sorgu = db()->prepare('SELECT sifre_hash FROM kullanicilar WHERE id = ?');
    $sorgu->execute([$id]);
    $hash = $sorgu->fetchColumn();

    if (!$hash || !password_verify($mevcut_sifre, $hash)) {
        return false;
    }

    $guncelle = db()->prepare('UPDATE kullanicilar SET sifre_hash = ? WHERE id = ?');
    $guncelle->execute([password_hash($yeni_sifre, PASSWORD_DEFAULT), $id]);
    return true;
}

/**
 * "2026-11" → "Kasım 2026" (dönem seçici için Türkçe ay-yıl etiketi)
 */
function ay_etiketi($yil_ay) {
    if (!preg_match('/^(\d{4})-(\d{2})$/', (string)$yil_ay, $m)) {
        return 'Tüm Dönemler';
    }
    $aylar = [1 => 'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran',
              'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
    $ay_no = (int)$m[2];
    return ($aylar[$ay_no] ?? $m[2]) . ' ' . $m[1];
}

/**
 * Kayıtlarda geçen tüm ayları ('YYYY-MM') yeniden eskiye döndürür (içinde bulunduğumuz ay dahil).
 */
function mevcut_aylar($kayitlar) {
    $aylar = [date('Y-m') => true];
    foreach ($kayitlar as $k) {
        $ym = substr((string)$k['tarih'], 0, 7);
        if (preg_match('/^\d{4}-\d{2}$/', $ym)) {
            $aylar[$ym] = true;
        }
    }
    $liste = array_keys($aylar);
    rsort($liste);
    return $liste;
}

/**
 * Seçilen ayın toplam giderini bir önceki ayla karşılaştırır.
 */
function ay_karsilastir($tum_kayitlar, $secili_ay) {
    if (!$secili_ay || !preg_match('/^\d{4}-\d{2}$/', $secili_ay)) {
        $secili_ay = date('Y-m');
    }
    $onceki_ay = date('Y-m', strtotime($secili_ay . '-01 -1 month'));

    $bu_toplam     = 0.0;
    $onceki_toplam = 0.0;

    foreach ($tum_kayitlar as $k) {
        $ym = substr((string)$k['tarih'], 0, 7);
        if ($ym === $secili_ay) {
            $bu_toplam += (float)$k['tutar'];
        } elseif ($ym === $onceki_ay) {
            $onceki_toplam += (float)$k['tutar'];
        }
    }

    $fark_tutar = $bu_toplam - $onceki_toplam;
    $fark_yuzde = $onceki_toplam > 0 ? round(($fark_tutar / $onceki_toplam) * 100, 1) : null;

    return [
        'secili_ay'     => $secili_ay,
        'onceki_ay'     => $onceki_ay,
        'bu_toplam'     => $bu_toplam,
        'onceki_toplam' => $onceki_toplam,
        'fark_tutar'    => $fark_tutar,
        'fark_yuzde'    => $fark_yuzde,
    ];
}

/**
 * Kumbara / birikim hedefinin tamamlanma yüzdesini ve kalan tutarını hesaplar.
 */
function kumbara_ozeti($kullanici, $ay_sonu_kalan_maas = 0) {
    $hedef   = max(1, (float)($kullanici['hedef_tutar'] ?? 30000));
    $biriken = max(0, (float)($kullanici['hedef_biriken'] ?? 0));
    $yuzde   = min(100, round(($biriken / $hedef) * 100, 1));
    $kalan   = max(0, $hedef - $biriken);

    $potansiyel = $biriken + max(0, (float)$ay_sonu_kalan_maas);
    $potansiyel_yuzde = min(100, round(($potansiyel / $hedef) * 100, 1));

    return [
        'ad'               => $kullanici['hedef_ad'] ?? 'Birikim Hedefi',
        'hedef'            => $hedef,
        'biriken'          => $biriken,
        'kalan'            => $kalan,
        'yuzde'            => $yuzde,
        'potansiyel'       => $potansiyel,
        'potansiyel_yuzde' => $potansiyel_yuzde,
    ];
}

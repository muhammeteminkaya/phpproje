<?php
/**
 * BütçeTakip — Başlangıç Verileri (veriler.php)
 *
 * Her kullanıcı ilk oluşturulduğunda veya "Örnek verilere sıfırla" dediğinde
 * bu kayıtlar veritabanına eklenir.
 * Tarihler bugüne göre hesaplanır; ayrıca dönem karşılaştırması için
 * geçen aydan da örnek kayıtlar içerir.
 */

/**
 * Kullanıcının tüm kayıtlarını siler ve örnek kayıtları veritabanına ekler.
 */
function ornek_verileri_yukle($kullanici_id, $kullanici_adi) {
    $pdo = db();

    $pdo->prepare('DELETE FROM harcamalar WHERE kullanici_id = ?')->execute([$kullanici_id]);

    $ekle = $pdo->prepare('INSERT INTO harcamalar (kullanici_id, baslik, kategori, tutar, aciklama, durum, tarih, odeme_tarihi, tekrarlayan)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');

    foreach (varsayilan_harcamalar($kullanici_adi) as $k) {
        $odeme  = $k['durum'] === 'Ödendi' ? $k['tarih'] : null;
        $tekrar = isset($k['tekrarlayan']) ? (int)$k['tekrarlayan'] : 0;
        $ekle->execute([$kullanici_id, $k['baslik'], $k['kategori'], $k['tutar'], $k['aciklama'], $k['durum'], $k['tarih'], $odeme, $tekrar]);
    }
}

/**
 * Kullanıcıya göre örnek harcama kayıtlarını döndürür.
 */
function varsayilan_harcamalar($kullanici_adi) {
    $gun = function ($fark) {
        return date('Y-m-d', strtotime($fark . ' days'));
    };

    if ($kullanici_adi === 'misafir') {
        return [
            ['id' => 1, 'baslik' => 'Yurt Aidatı', 'kategori' => 'Kira & Barınma', 'tutar' => 3200.00,
             'aciklama' => 'Aylık yurt ücreti.', 'durum' => 'Ödendi', 'tarih' => $gun(-4), 'tekrarlayan' => 1],
            ['id' => 2, 'baslik' => 'Kampüs Yemekhanesi Kartı', 'kategori' => 'Gıda & Market', 'tutar' => 650.00,
             'aciklama' => 'Yemek kartı bakiye yüklemesi.', 'durum' => 'Ödendi', 'tarih' => $gun(-2), 'tekrarlayan' => 0],
            ['id' => 3, 'baslik' => 'Müzik Aboneliği', 'kategori' => 'Abonelikler', 'tutar' => 59.99,
             'aciklama' => 'Öğrenci planı.', 'durum' => 'Ödenecek', 'tarih' => $gun(4), 'tekrarlayan' => 1],
        ];
    }

    return [
        // Bu ayın kayıtları
        ['id' => 1, 'baslik' => 'Ev Kirası', 'kategori' => 'Kira & Barınma', 'tutar' => 14500.00,
         'aciklama' => 'Kira bedeli ev sahibinin hesabına havale edildi.', 'durum' => 'Ödendi', 'tarih' => $gun(-6), 'tekrarlayan' => 1],
        ['id' => 2, 'baslik' => 'Haftalık Market Alışverişi', 'kategori' => 'Gıda & Market', 'tutar' => 1850.50,
         'aciklama' => 'Temel gıda, manav ve temizlik ürünleri.', 'durum' => 'Ödendi', 'tarih' => $gun(-4), 'tekrarlayan' => 0],
        ['id' => 3, 'baslik' => 'Elektrik Faturası', 'kategori' => 'Faturalar', 'tutar' => 940.00,
         'aciklama' => 'Son ödeme tarihi yaklaştı.', 'durum' => 'Ödenecek', 'tarih' => $gun(-1), 'tekrarlayan' => 1],
        ['id' => 4, 'baslik' => 'Fiber İnternet', 'kategori' => 'Abonelikler', 'tutar' => 379.90,
         'aciklama' => 'Aylık otomatik ödeme talimatı.', 'durum' => 'Ödendi', 'tarih' => $gun(-3), 'tekrarlayan' => 1],
        ['id' => 5, 'baslik' => 'Araç Yakıtı', 'kategori' => 'Ulaşım', 'tutar' => 1250.00,
         'aciklama' => 'İşe gidiş-geliş için yakıt dolumu.', 'durum' => 'Ödendi', 'tarih' => $gun(-2), 'tekrarlayan' => 0],
        ['id' => 6, 'baslik' => 'Kredi Kartı Ekstresi', 'kategori' => 'Finans', 'tutar' => 4650.00,
         'aciklama' => 'Dönem borcunun tamamı ödenecek.', 'durum' => 'Ödenecek', 'tarih' => $gun(3), 'tekrarlayan' => 0],
        ['id' => 7, 'baslik' => 'Doğalgaz Faturası', 'kategori' => 'Faturalar', 'tutar' => 820.00,
         'aciklama' => 'Kış dönemi tüketimi.', 'durum' => 'Ödenecek', 'tarih' => $gun(6), 'tekrarlayan' => 1],
        ['id' => 8, 'baslik' => 'Konser Bileti', 'kategori' => 'Eğlence & Hobi', 'tutar' => 1100.00,
         'aciklama' => 'Ödeme bir sonraki maaşa ertelendi.', 'durum' => 'Ertelendi', 'tarih' => $gun(12), 'tekrarlayan' => 0],
        // Geçen ayın örnek kayıtları (Dönem seçici ve ay karşılaştırması için)
        ['id' => 9, 'baslik' => 'Geçen Ay Ev Kirası', 'kategori' => 'Kira & Barınma', 'tutar' => 14500.00,
         'aciklama' => 'Geçen ay kira ödemesi.', 'durum' => 'Ödendi', 'tarih' => $gun(-35), 'tekrarlayan' => 1],
        ['id' => 10, 'baslik' => 'Geçen Ay Market & Mutfak', 'kategori' => 'Gıda & Market', 'tutar' => 3400.00,
         'aciklama' => 'Aylık mutfak alışverişleri toplamı.', 'durum' => 'Ödendi', 'tarih' => $gun(-30), 'tekrarlayan' => 0],
        ['id' => 11, 'baslik' => 'Geçen Ay Faturalar', 'kategori' => 'Faturalar', 'tutar' => 1520.00,
         'aciklama' => 'Elektrik, su ve internet.', 'durum' => 'Ödendi', 'tarih' => $gun(-28), 'tekrarlayan' => 1],
    ];
}

<?php
/**
 * BütçeTakip — Veritabanı Bağlantısı ve Otomatik Şema Kurucu (db.php)
 *
 * MySQL'e PDO ile bağlanır. İlk çalıştırmada:
 *   1. "butcetakip" veritabanını oluşturur (yoksa),
 *   2. "kullanicilar" ve "harcamalar" tablolarını oluşturur (yoksa),
 *   3. Yeni eklenen sütunları (hedef/kumbara, tekrarlayan gider, dekont) mevcut tabloya otomatik ekler,
 *   4. Tablo boşsa iki demo kullanıcıyı ve örnek kayıtlarını ekler.
 */

const DB_HOST  = '127.0.0.1';
const DB_PORT  = 3306;
const DB_ADI   = 'butcetakip';
const DB_KULL  = 'root';
const DB_SIFRE = '';

/**
 * Tek bir PDO bağlantısı döndürür.
 */
function db() {
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $ayarlar = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO('mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';charset=utf8mb4', DB_KULL, DB_SIFRE, $ayarlar);
        $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . DB_ADI . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_turkish_ci');
        $pdo->exec('USE `' . DB_ADI . '`');
    } catch (PDOException $e) {
        http_response_code(500);
        exit('<div style="font-family:sans-serif;max-width:560px;margin:60px auto;padding:20px;border-left:4px solid #c8442a;background:#fdf1ee">'
           . '<h2>Veritabanına bağlanılamadı</h2>'
           . '<p>XAMPP Control Panel\'den <b>MySQL</b> servisini başlatıp sayfayı yenileyin.</p>'
           . '<small>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</small></div>');
    }

    tablolari_hazirla($pdo);
    return $pdo;
}

/**
 * Bir tabloda belirtilen sütun yoksa ALTER TABLE ile ekler (mevcut verileri korur).
 */
function sutun_yoksa_ekle($pdo, $tablo, $sutun, $tanim) {
    $sorgu = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $sorgu->execute([DB_ADI, $tablo, $sutun]);
    if ((int)$sorgu->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE `$tablo` ADD COLUMN `$sutun` $tanim");
    }
}

/**
 * Tabloları ve yeni sütunları hazırlar; kullanıcı tablosu boşsa demo verileri ekler.
 */
function tablolari_hazirla($pdo) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS kullanicilar (
            id            INT AUTO_INCREMENT PRIMARY KEY,
            kullanici_adi VARCHAR(30)  NOT NULL UNIQUE,
            ad_soyad      VARCHAR(60)  NOT NULL,
            eposta        VARCHAR(120) NULL,
            sifre_hash    VARCHAR(255) NOT NULL,
            aylik_maas    DECIMAL(12,2) NOT NULL DEFAULT 0,
            aylik_limit   DECIMAL(12,2) NOT NULL DEFAULT 0,
            hedef_ad      VARCHAR(80)   NOT NULL DEFAULT 'Yeni Bilgisayar & Birikim',
            hedef_tutar   DECIMAL(12,2) NOT NULL DEFAULT 30000,
            hedef_biriken DECIMAL(12,2) NOT NULL DEFAULT 4500,
            olusturma     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS harcamalar (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            kullanici_id INT NOT NULL,
            baslik       VARCHAR(80)  NOT NULL,
            kategori     VARCHAR(40)  NOT NULL,
            tutar        DECIMAL(12,2) NOT NULL,
            aciklama     VARCHAR(250) NOT NULL DEFAULT '',
            durum        ENUM('Ödendi','Ödenecek','Ertelendi') NOT NULL DEFAULT 'Ödenecek',
            tarih        DATE NOT NULL,
            odeme_tarihi DATE NULL,
            tekrarlayan  TINYINT(1) NOT NULL DEFAULT 0,
            dekont       VARCHAR(255) NULL,
            INDEX (kullanici_id),
            FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci
    ");

    // Daha önce oluşturulmuş tablolara yeni sütunları güvenle ekle
    sutun_yoksa_ekle($pdo, 'kullanicilar', 'hedef_ad',      "VARCHAR(80) NOT NULL DEFAULT 'Yeni Bilgisayar & Birikim' AFTER `aylik_limit`");
    sutun_yoksa_ekle($pdo, 'kullanicilar', 'hedef_tutar',   "DECIMAL(12,2) NOT NULL DEFAULT 30000 AFTER `hedef_ad`");
    sutun_yoksa_ekle($pdo, 'kullanicilar', 'hedef_biriken', "DECIMAL(12,2) NOT NULL DEFAULT 4500 AFTER `hedef_tutar`");
    sutun_yoksa_ekle($pdo, 'harcamalar',   'tekrarlayan',   "TINYINT(1) NOT NULL DEFAULT 0 AFTER `odeme_tarihi`");
    sutun_yoksa_ekle($pdo, 'harcamalar',   'dekont',        "VARCHAR(255) NULL AFTER `tekrarlayan`");

    // Mevcut kayıtlarda kira/fatura/abonelik olanları varsayılan olarak tekrarlayan işaretle (eğer hiç tekrarlayan yoksa)
    $tekrar_sayisi = (int)$pdo->query('SELECT COUNT(*) FROM harcamalar WHERE tekrarlayan = 1')->fetchColumn();
    if ($tekrar_sayisi === 0) {
        $pdo->exec("UPDATE harcamalar SET tekrarlayan = 1 WHERE kategori IN ('Kira & Barınma', 'Faturalar', 'Abonelikler')");
    }

    $adet = (int)$pdo->query('SELECT COUNT(*) FROM kullanicilar')->fetchColumn();
    if ($adet > 0) {
        return;
    }

    // Demo hesaplar: emin / 1234  ve  misafir / misafir
    $demolar = [
        ['emin',    'Emin',    'emin@ornek.com', '1234',    42000, 25000, 'MacBook Pro Kumbarası', 45000, 12500],
        ['misafir', 'Misafir', null,             'misafir', 15000, 8000,  'Yaz Tatili Fonu',       12000, 2400],
    ];

    $ekle = $pdo->prepare('INSERT INTO kullanicilar (kullanici_adi, ad_soyad, eposta, sifre_hash, aylik_maas, aylik_limit, hedef_ad, hedef_tutar, hedef_biriken)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');

    foreach ($demolar as $d) {
        $ekle->execute([$d[0], $d[1], $d[2], password_hash($d[3], PASSWORD_DEFAULT), $d[4], $d[5], $d[6], $d[7], $d[8]]);
        ornek_verileri_yukle((int)$pdo->lastInsertId(), $d[0]);
    }
}

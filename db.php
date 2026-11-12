<?php
/**
 * BütçeTakip — Veritabanı Bağlantısı ve Otomatik Şema Kurucu (db.php)
 *
 * Hem yerel bilgisayarda (XAMPP) hem de canlı sunucuda (InfinityFree vb.) çalışır:
 *   - Bilgisayarında (localhost) çalışırken otomatik olarak XAMPP ayarlarını (root / butcetakip) kullanır.
 *   - InfinityFree'ye yüklediğinde aşağıdaki CANLI_DB_* bilgilerini kullanır ve
 *     phpMyAdmin'den .sql dosyası yüklemene bile gerek kalmadan tabloları + örnek verileri OTOMATİK kurar!
 */

/* =========================================================================
   1) INFINITYFREE (CANLI SUNUCU) BİLGİLERİ
   InfinityFree -> MySQL Databases ekranındaki 4 bilgiyi buraya yazman yeterli:
   ========================================================================= */
const CANLI_DB_HOST  = 'sql306.infinityfree.com';     // MySQL Hostname (örn: sql306.infinityfree.com)
const CANLI_DB_ADI   = 'if0_43413634_butce';          // MySQL Database Name (örn: if0_43413634_...)
const CANLI_DB_KULL  = 'if0_43413634';                // MySQL Username
const CANLI_DB_SIFRE = 'KUstQHAA95U0Cg';              // Hosting / vPanel şifren

/* =========================================================================
   2) YEREL BİLGİSAYAR (XAMPP) BİLGİLERİ (Değiştirmene gerek yok)
   ========================================================================= */
const YEREL_DB_HOST  = '127.0.0.1';
const YEREL_DB_PORT  = 3306;
const YEREL_DB_ADI   = 'butcetakip';
const YEREL_DB_KULL  = 'root';
const YEREL_DB_SIFRE = '';

/**
 * Sitenin kendi bilgisayarında (XAMPP) mı yoksa internette (InfinityFree) mi çalıştığını algılar.
 */
function yerel_ortam_mi() {
    $sunucu = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
    $temiz  = explode(':', $sunucu)[0];
    return in_array($temiz, ['localhost', '127.0.0.1', '::1'], true) || PHP_SAPI === 'cli';
}

/**
 * Tek bir PDO bağlantısı döndürür ve tablolar yoksa otomatik oluşturur.
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

    $yerel = yerel_ortam_mi();

    try {
        if ($yerel) {
            // XAMPP: Veritabanı yoksa otomatik oluşturup içine girer
            $pdo = new PDO('mysql:host=' . YEREL_DB_HOST . ';port=' . YEREL_DB_PORT . ';charset=utf8mb4', YEREL_DB_KULL, YEREL_DB_SIFRE, $ayarlar);
            $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . YEREL_DB_ADI . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $pdo->exec('USE `' . YEREL_DB_ADI . '`');
            $aktif_db = YEREL_DB_ADI;
        } else {
            // INFINITYFREE: CREATE DATABASE yasak olduğu için doğrudan oluşturduğun veritabanına bağlanır
            $dsn = 'mysql:host=' . CANLI_DB_HOST . ';dbname=' . CANLI_DB_ADI . ';charset=utf8mb4';
            $pdo = new PDO($dsn, CANLI_DB_KULL, CANLI_DB_SIFRE, $ayarlar);
            $aktif_db = CANLI_DB_ADI;
        }
    } catch (PDOException $e) {
        http_response_code(500);
        if ($yerel) {
            exit('<div style="font-family:sans-serif;max-width:560px;margin:60px auto;padding:20px;border-left:4px solid #c8442a;background:#fdf1ee">'
               . '<h2>Veritabanına bağlanılamadı (XAMPP)</h2>'
               . '<p>XAMPP Control Panel\'den <b>MySQL</b> servisini başlatıp sayfayı yenileyin.</p>'
               . '<small>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</small></div>');
        } else {
            exit('<div style="font-family:sans-serif;max-width:620px;margin:60px auto;padding:24px;border-left:4px solid #c8442a;background:#fdf1ee;line-height:1.6">'
               . '<h2>Veritabanı Bağlantı Ayarı Bekleniyor (InfinityFree)</h2>'
               . '<p><b>db.php</b> dosyasının en üstündeki 4 satırı InfinityFree &rarr; <b>MySQL Databases</b> sayfasındaki bilgilerle güncelleyin:</p>'
               . '<ul>'
               . '<li><code>CANLI_DB_HOST</code>: MySQL Hostname (örn: <code>sql100.infinityfree.com</code>)</li>'
               . '<li><code>CANLI_DB_ADI</code>: MySQL Database Name (örn: <code>if0_43413634_...</code>)</li>'
               . '<li><code>CANLI_DB_KULL</code>: MySQL Username (<code>if0_43413634</code>)</li>'
               . '<li><code>CANLI_DB_SIFRE</code>: Hosting şifreniz</li>'
               . '</ul>'
               . '<small>Teknik detay: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</small></div>');
        }
    }

    tablolari_hazirla($pdo, $aktif_db);
    return $pdo;
}

/**
 * Bir tabloda belirtilen sütun yoksa ALTER TABLE ile ekler (mevcut verileri korur).
 */
function sutun_yoksa_ekle($pdo, $db_adi, $tablo, $sutun, $tanim) {
    $sorgu = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $sorgu->execute([$db_adi, $tablo, $sutun]);
    if ((int)$sorgu->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE `$tablo` ADD COLUMN `$sutun` $tanim");
    }
}

/**
 * Tabloları ve yeni sütunları hazırlar; kullanıcı tablosu boşsa demo verileri otomatik ekler.
 */
function tablolari_hazirla($pdo, $db_adi) {
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Daha önce oluşturulmuş tablolara yeni sütunları güvenle ekle
    sutun_yoksa_ekle($pdo, $db_adi, 'kullanicilar', 'hedef_ad',      "VARCHAR(80) NOT NULL DEFAULT 'Yeni Bilgisayar & Birikim' AFTER `aylik_limit`");
    sutun_yoksa_ekle($pdo, $db_adi, 'kullanicilar', 'hedef_tutar',   "DECIMAL(12,2) NOT NULL DEFAULT 30000 AFTER `hedef_ad`");
    sutun_yoksa_ekle($pdo, $db_adi, 'kullanicilar', 'hedef_biriken', "DECIMAL(12,2) NOT NULL DEFAULT 4500 AFTER `hedef_tutar`");
    sutun_yoksa_ekle($pdo, $db_adi, 'harcamalar',   'tekrarlayan',   "TINYINT(1) NOT NULL DEFAULT 0 AFTER `odeme_tarihi`");
    sutun_yoksa_ekle($pdo, $db_adi, 'harcamalar',   'dekont',        "VARCHAR(255) NULL AFTER `tekrarlayan`");

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

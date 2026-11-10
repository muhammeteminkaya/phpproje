-- BütçeTakip — MySQL Veritabanı Şeması (veritabani.sql)
-- Not: db.php dosyası ilk çalıştırmada bu veritabanını, tabloları ve demo kayıtları
-- otomatik olarak oluşturur; phpMyAdmin'den elle içe aktarmanız şart değildir.

CREATE DATABASE IF NOT EXISTS `butcetakip`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_turkish_ci;

USE `butcetakip`;

CREATE TABLE IF NOT EXISTS `kullanicilar` (
    `id`            INT AUTO_INCREMENT PRIMARY KEY,
    `kullanici_adi` VARCHAR(30)  NOT NULL UNIQUE,
    `ad_soyad`      VARCHAR(60)  NOT NULL,
    `eposta`        VARCHAR(120) NULL,
    `sifre_hash`    VARCHAR(255) NOT NULL,
    `aylik_maas`    DECIMAL(12,2) NOT NULL DEFAULT 0,
    `aylik_limit`   DECIMAL(12,2) NOT NULL DEFAULT 0,
    `hedef_ad`      VARCHAR(80)   NOT NULL DEFAULT 'Yeni Bilgisayar & Birikim',
    `hedef_tutar`   DECIMAL(12,2) NOT NULL DEFAULT 30000,
    `hedef_biriken` DECIMAL(12,2) NOT NULL DEFAULT 4500,
    `olusturma`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

CREATE TABLE IF NOT EXISTS `harcamalar` (
    `id`           INT AUTO_INCREMENT PRIMARY KEY,
    `kullanici_id` INT NOT NULL,
    `baslik`       VARCHAR(80)  NOT NULL,
    `kategori`     VARCHAR(40)  NOT NULL,
    `tutar`        DECIMAL(12,2) NOT NULL,
    `aciklama`     VARCHAR(250) NOT NULL DEFAULT '',
    `durum`        ENUM('Ödendi','Ödenecek','Ertelendi') NOT NULL DEFAULT 'Ödenecek',
    `tarih`        DATE NOT NULL,
    `odeme_tarihi` DATE NULL,
    `tekrarlayan`  TINYINT(1) NOT NULL DEFAULT 0,
    `dekont`       VARCHAR(255) NULL,
    INDEX (`kullanici_id`),
    FOREIGN KEY (`kullanici_id`) REFERENCES `kullanicilar`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

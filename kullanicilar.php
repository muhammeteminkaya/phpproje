<?php
/**
 * BütçeTakip — Kullanıcı Listesi (kullanicilar.php)
 *
 * Bu sürümde veritabanı olmadığı için kullanıcılar bir PHP dizisinde tutulur.
 * Şifreler DÜZ METİN DEĞİL, password_hash() ile üretilmiş özet (hash) olarak saklanır.
 * Giriş sırasında password_verify() ile karşılaştırılır.
 *
 * Demo hesaplar:
 *   emin    / 1234
 *   misafir / misafir
 *
 * Yeni kullanıcı eklemek için terminalde şu komutla hash üretilebilir:
 *   php -r "echo password_hash('yeniSifre', PASSWORD_DEFAULT);"
 */

$kullanicilar = [
    'emin' => [
        'gorunen_ad' => 'Emin',
        'sifre_hash' => '$2y$10$xKrBN5V/6x0Urj32Mc7AgeDik.E5zBWqpbGKK3LZ4b7LUYFKxy4hC',
        'aylik_limit' => 25000
    ],
    'misafir' => [
        'gorunen_ad' => 'Misafir',
        'sifre_hash' => '$2y$10$z94WS1gtmkJqTHrl/DLqt.zI.uxdLuUg4zwHUYPdcm.M6sacaULue',
        'aylik_limit' => 8000
    ]
];

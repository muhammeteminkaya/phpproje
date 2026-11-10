<?php
/**
 * BütçeTakip — Excel / CSV Dışa Aktarma (indir.php)
 *
 * Kullanıcının harcama kayıtlarını (varsa aktif ay, kategori, durum ve arama filtrelerine göre)
 * Türkçe karakter uyumlu (UTF-8 BOM) ve noktalı virgül (;) ayrımlı CSV dosyası olarak indirir.
 * Microsoft Excel, LibreOffice ve Google E-Tablolar ile doğrudan açılır.
 */

require_once 'auth.php';

$kayitlar        = kayitlari_getir($kullanici_id);
$secili_ay       = isset($_GET['ay']) ? trim($_GET['ay']) : '';
$filtre_kategori = isset($_GET['kategori']) ? trim($_GET['kategori']) : '';
$filtre_durum    = isset($_GET['durum']) ? trim($_GET['durum']) : '';
$arama           = isset($_GET['ara']) ? trim($_GET['ara']) : '';

$aktarilacak = [];
$toplam      = 0.0;

foreach ($kayitlar as $k) {
    if ($secili_ay !== '' && $secili_ay !== 'tumu' && substr($k['tarih'], 0, 7) !== $secili_ay) {
        continue;
    }
    if ($filtre_kategori !== '' && $k['kategori'] !== $filtre_kategori) {
        continue;
    }
    if ($filtre_durum !== '' && $k['durum'] !== $filtre_durum) {
        continue;
    }
    if ($arama !== '' && mb_stripos($k['baslik'] . ' ' . $k['aciklama'], $arama, 0, 'UTF-8') === false) {
        continue;
    }
    $aktarilacak[] = $k;
    $toplam       += (float)$k['tutar'];
}

$dosya_adi = 'butcetakip_' . $kullanici_adi . '_' . ($secili_ay ?: 'tum_donemler') . '_' . date('Ymd') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $dosya_adi . '"');
header('Pragma: no-cache');
header('Expires: 0');

$cikti = fopen('php://output', 'w');

// Excel'in Türkçe karakterleri (ğ, ü, ş, ı, ö, ç, ₺) doğru tanıması için UTF-8 BOM
fwrite($cikti, "\xEF\xBB\xBF");

// Başlık satırı
fputcsv($cikti, ['ID', 'Tarih', 'Harcama Başlığı', 'Kategori', 'Durum', 'Sabit Gider', 'Tutar (TL)', 'Açıklama', 'Dekont'], ';');

foreach ($aktarilacak as $k) {
    fputcsv($cikti, [
        $k['id'],
        $k['tarih'],
        $k['baslik'],
        $k['kategori'],
        $k['durum'],
        !empty($k['tekrarlayan']) ? 'Evet' : 'Hayır',
        number_format((float)$k['tutar'], 2, ',', '.'),
        $k['aciklama'],
        !empty($k['dekont']) ? 'Var (' . basename($k['dekont']) . ')' : 'Yok',
    ], ';');
}

// Alt toplam satırı
fputcsv($cikti, ['', '', 'TOPLAM (' . count($aktarilacak) . ' kayıt)', '', '', '', number_format($toplam, 2, ',', '.'), '', ''], ';');

fclose($cikti);
exit;

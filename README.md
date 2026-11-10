# BütçeTakip — Kişisel Harcama ve Bütçe Defteri
**İnternet Programcılığı I (PHP) — 3 Haftalık Bireysel Proje Ödevi**

---

## 1. Projenin Amacı ve Tasarım Yaklaşımı
* **Proje Adı:** BütçeTakip (Kişisel Harcama ve Bütçe Takip Sistemi)
* **Ders:** İnternet Programcılığı I
* **Amacı:** Kullanıcıların kira, fatura, market, abonelik ve ulaşım gibi kişisel harcamalarını tek bir defterde kayıt altına almalarını, aylık bütçe limitlerini, yaklaşan ödemelerini ve kategori dağılımlarını takip edebilmelerini sağlamaktır.
* **Tasarım Yaklaşımı ("Defter"):** Standart hazır yönetim panellerinden ve tekdüze yapay zekâ şablonlarından ayrışmak için **"eski muhasebe defteri × modern dergi düzeni"** estetiği tasarlanmıştır:
  * Krem kâğıt dokulu zemin (veya mürekkep laciverti **Gece Modu**),
  * Başlıklarda editoryal serif (*Fraunces*), tutar ve tarihlerde tek aralıklı (*IBM Plex Mono*) yazı tipi,
  * Defter kenar çizgisi, çift çizgili muhasebe toplamı ve ödenmiş kayıtlarda **"ÖDENDİ" mühür damgası**,
  * Yeni kayıt eklerken sağ tarafta anlık güncellenen **gerçekçi harcama fişi önizlemesi**.

---

## 2. Projede Bulunan Özellikler

### Zorunlu Özellikler
1. **Ana Sayfa ve Gezinme (`index.php`, `parcalar/ust.php`):** Sol yan menü (Panel, Yeni Kayıt, Rapor, Çıkış), saate göre değişen karşılama mesajı (`Günaydın / İyi günler / İyi akşamlar`) ve gece/gündüz tema anahtarı.
2. **Kayıt Listeleme (`index.php`):** Her kayıtta `id`, `baslik`, `kategori`, `tutar`, `aciklama`, `durum` ve `tarih` olmak üzere **7 bilgi alanı** bulunur; `foreach` döngüsüyle tarih sırasına göre listelenir.
3. **Yeni Kayıt Ekleme (`ekle.php`):** `POST` yöntemiyle çalışan form, hızlı kategori çipleri ve canlı harcama fişi önizlemesi.
4. **Veri Doğrulama (`ekle.php`):** PHP tarafında boş alan, karakter uzunluğu (`mb_strlen`), sayısal ve pozitif tutar (`is_numeric`), geçerli durum (`in_array`) ve geçerli tarih (`DateTime::createFromFormat`) kontrolleri. Hata durumunda girilen veriler korunur (*sticky form*) ve her alanın altında hatası gösterilir.
5. **Kayıt Silme (`sil.php`):** Yalnızca `POST` yöntemi ve CSRF doğrulaması ile güvenli kayıt silme.
6. **Filtreleme ve Arama (`index.php`):** `GET` yöntemiyle kategoriye, ödeme durumuna ve başlık/açıklama metnine göre birlikte çalışabilen filtreleme.
7. **Özet Bilgiler ve Sayaçlar (`index.php`):** Toplam tutar, toplam kayıt adedi, ödenen tutar/adet/yüzde, bekleyen tutar/adet, ertelenen tutar/adet ve filtrelenen liste alt toplamı.
8. **Güvenli Çıktı (`functions.php` → `guvenli()`):** Kullanıcı kaynaklı tüm metinler `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` ile ekrana yazdırılır.

### Eklenen Üst Düzey Özellikler
9. **Kullanıcı Giriş Sistemi (`giris.php`, `kullanicilar.php`, `auth.php`, `cikis.php`):**
   * Şifreler düz metin değil, `password_hash()` (bcrypt) ile özetlenmiş olarak saklanır ve `password_verify()` ile doğrulanır.
   * Giriş başarılı olduğunda `session_regenerate_id(true)` ile oturum sabitleme saldırısı önlenir.
   * Her kullanıcının harcama defteri ve aylık bütçe limiti oturumda ayrı tutulur (`$_SESSION['kayitlar'][$kullanici_adi]`).
10. **Aylık Bütçe Limiti Çubuğu:** Kullanıcının aylık limitine göre harcama yüzdesini ve kalan/aşım tutarını hesaplar; %60 ve %85 eşiklerinde renk değiştirir (`rahat` / `dikkat` / `kritik`).
11. **PHP ile Hesaplanan SVG Halka Grafik:** Harici JS grafik kütüphanesi kullanmadan, PHP `foreach` döngüsüyle kategori yüzdelerini `stroke-dasharray` ve `stroke-dashoffset` değerlerine dönüştürerek saf SVG halka grafik çizer.
12. **Yaklaşan Ödemeler Kartı:** Durumu `Ödenecek` olan kayıtları vade tarihine göre sıralar; gecikenleri kırmızı, yaklaşanları kehribar takvim yaprağıyla uyarı olarak gösterir ve tek tıkla **"✓ Ödendi yap"** düğmesi sunar.
13. **Kategori Raporu Sayfası (`rapor.php`):** En büyük harcama kalemi, kayıt başına ortalama, ödeme durumu yığılmış çubuğu ve kategorilere göre pay tablosu (yazdırma destekli).
14. **Kayıt Düzenleme (`duzenle.php`):** Yanlış girilen bir tutarı, tarihi veya başlığı silmeden doğrudan düzenleyip güncelleyebilme.
15. **Tekrarlayan (Sabit) Giderler:** Kira, internet, abonelik gibi her ay tekrar eden giderleri `↻ Sabit` olarak işaretleyip, yeni ayda tek tıkla (**"↻ Sabit giderleri bu aya aktar"**) `Ödenecek` olarak deftere kopyalayabilme.
16. **Excel (CSV) Olarak İndirme (`indir.php`):** Seçili dönem ve filtrelere uygun harcamaları Türkçe karakter uyumlu (UTF-8 BOM, `;` ayraçlı) `.csv` tablosu olarak bilgisayara indirebilme.
17. **Kumbara & Birikim Hedefi Kutusu:** Hedef adı, hedef tutar ve biriken miktarı takip etme; çift katmanlı ilerleme çubuğu ile ay sonu kalan maaş eklendiğinde ulaşılacak potansiyel yüzdeyi görme ve tek tıkla (`+500 ₺`, `+1.000 ₺`, `+2.500 ₺`) kumbaraya para atabilme.
18. **Fatura Dekontu / Fiş Fotoğrafı Yükleme (`$_FILES`):** Harcama eklerken veya düzenlerken JPG, PNG, WEBP, GIF veya PDF formatında dekont/fiş yükleme (`yuklemeler/`) ve tablodaki **📎 Fiş** rozetinden görüntüleme.
19. **Dönem / Ay Seçici ve Geçen Ay Karşılaştırması:** Paneli ve raporu aylara (`Kasım 2026`, `Ekim 2026`, `Tüm Dönemler`) göre filtreleme ve bir önceki aya göre yüzde kaç tasarruf edildiğini veya artış olduğunu otomatik hesaplama (`ay_karsilastir()`).

---

## 3. Klasör ve Dosya Yapısı ("Hangi Dosya Ne Yapar?")

```text
C:\xampp\htdocs\phpproje/
│
├── index.php            -> Ana panel: dönem seçici, maaş & özet bandı, kumbara, SVG grafik, yaklaşan ödemeler, kayıt defteri
├── giris.php            -> Giriş yap ve Kayıt ol sekmeleri (POST + PDO MySQL + password_hash / password_verify)
├── profil.php           -> Profil, aylık maaş (gelir), harcama limiti ve şifre düzenleme + Gelir-Gider Bordrosu
├── ekle.php             -> Yeni kayıt formu (POST + multipart dekont yükleme + sabit gider seçeneği + canlı fiş önizlemesi)
├── duzenle.php          -> Mevcut harcama kaydını ve dekontunu düzenleme sayfası
├── durum.php            -> Faturaların durumunu (Ödendi / Ödenecek / Ertelendi) tıklayarak güncelleme (POST)
├── sil.php              -> Kayıt silme işlemi (POST + CSRF kontrolü + varsa dekont dosyasını temizleme)
├── indir.php            -> Filtrelenmiş kayıtları Excel uyumlu CSV olarak indirme
├── rapor.php            -> Dönem bazlı kategori ve ödeme durumu analiz raporu
├── cikis.php            -> Oturumu sonlandırma (session_destroy)
├── auth.php             -> Oturum koruma kalkanı (kullanıcı bilgilerini MySQL'den okur)
├── db.php               -> PDO MySQL bağlantısı ve otomatik veritabanı/tablo/sütun kurucu
├── veritabani.sql       -> MySQL veritabanı şeması (kullanicilar ve harcamalar tabloları)
├── veriler.php          -> Varsayılan örnek harcama kayıtları (bu ay + geçen ay karşılaştırma verileri)
├── functions.php        -> Projede kullanılan yardımcı fonksiyonlar (PDO sorguları, hesaplama, güvenlik, dosya yükleme)
├── yuklemeler/          -> Yüklenen fatura dekontu ve fiş fotoğraflarının saklandığı klasör
│
├── parcalar/
│   ├── ust.php          -> Ortak <head>, yan menü (sidebar), maaş kartı ve kullanıcı bilgisi
│   └── alt.php          -> Ortak alt bilgi (footer) ve gece/gündüz tema betiği
│
├── assets/
│   └── style.css        -> "Defter" tasarım sistemi (açık/koyu tema, mühür damgası, durum balonu, kumbara)
│
└── README.md            -> Proje kılavuzu ve soru-cevap rehberi
```

---

## 4. XAMPP Üzerinde Çalıştırma Adımları ve Demo Hesaplar

1. **XAMPP Control Panel** uygulamasını açıp **Apache** ve **MySQL** servislerini **Start** ile başlatın.
2. Proje klasörünü `C:\xampp\htdocs\phpproje` konumuna yerleştirin (`db.php` ilk açılışta `butcetakip` veritabanını, tabloları ve örnek kayıtları otomatik olarak oluşturur; ayrıca SQL içe aktarmaya gerek yoktur, ancak istenirse `veritabani.sql` dosyası da phpMyAdmin'den içe aktarılabilir).
3. Tarayıcıdan şu adrese gidin:
   ```text
   http://localhost/phpproje/
   ```
4. Karşınıza gelen giriş ekranında **Kayıt Ol** sekmesinden yeni hesap açabilir veya aşağıdaki **demo hesaplardan** biriyle giriş yapabilirsiniz:

| Kullanıcı Adı | Şifre | Aylık Maaş | Aylık Bütçe Limiti |
|---|---|---|---|
| `emin` | `1234` | 42.000,00 ₺ | 25.000,00 ₺ |
| `misafir` | `misafir` | 15.000,00 ₺ | 8.000,00 ₺ |

---

## 5. Projede Bilinen Eksikler ve Sonraki Geliştirme Planı
* **Bilinen Eksikler (İlk Sürüm Sınırları):**
  * Dövizli harcamalar (USD/EUR) için anlık kur dönüşümü henüz bulunmamaktadır; tüm tutarlar Türk Lirası (`₺`) cinsinden tutulmaktadır.
  * E-posta ile şifre sıfırlama bağlantısı gönderimi yerel sunucu (XAMPP) ortamında SMTP yapılandırması gerektirdiği için şifre değişimi mevcut şifre doğrulanarak `profil.php` üzerinden yapılmaktadır.
* **Sonraki Aşamada Planlananlar:**
  * Çoklu para birimi desteği ve aile/ortak bütçe paylaşım yetkilendirmesi.

---

## 6. Canlı Gösterim ve Soru-Cevap Rehberi (Bölüm 8 Sorularının Tamamı)

* **Soru 1: Projenizde PHP hangi işlemleri gerçekleştiriyor?**
  * *Cevap:* Kullanıcı giriş/kayıt doğrulamasını (`password_hash` / `password_verify`), oturum yönetimini (`$_SESSION`), PDO ile MySQL veritabanı işlemlerini, kayıtların `foreach` ile listelenmesini, `GET` ile dönem/kategori/durum/arama filtrelemesini, `POST` ile kayıt ekleme-düzenleme-silme-durum güncelleme işlemlerini, dosya yüklemeyi (`$_FILES`), CSV dışa aktarımını ve SVG halka grafiğinin açı hesaplamalarını gerçekleştiriyor.
* **Soru 2: Bu formdan gelen veriler hangi yöntemle gönderiliyor? GET ve POST arasındaki fark nedir?**
  * *Cevap:* Veri değiştiren tüm formlar (`giris.php`, `ekle.php`, `duzenle.php`, `sil.php`, `durum.php`, `profil.php`) `POST` yöntemiyle gönderiliyor. `GET` yöntemi verileri URL adres çubuğunda (`?ay=2026-11&kategori=Faturalar&ara=Elektrik`) taşır ve yalnızca okuma/filtreleme işlemlerinde kullanılır. `POST` ise verileri HTTP isteğinin gövdesinde gizli taşır ve veritabanında değişiklik yapan işlemlerde kullanılır.
* **Soru 3: `$_SESSION` neden kullanıldı?**
  * *Cevap:* HTTP durumsuz (stateless) bir protokol olduğu için, giriş yapan kullanıcının kimliğini (`$_SESSION['kullanici_id']`) ve güvenlik anahtarını (`$_SESSION['csrf_token']`) sayfa yenilendiğinde veya sayfalar arasında geçiş yapıldığında korumak için `$_SESSION` kullanıldı.
* **Soru 4: `foreach` döngüsü ne işe yarıyor?**
  * *Cevap:* Veritabanından gelen harcama kayıtları dizisini (`$kayitlar` ve `$listelenen`) tek tek dolaşarak hem HTML tablo satırlarını (`<tr>`) ekrana yazdırmak hem de toplam tutar, kategori dağılımı ve durum sayaçlarını hesaplamak için kullanılıyor.
* **Soru 5: Kayıt ekleme işlemi hangi dosyada gerçekleştiriliyor? Boş veri gönderildiğinde hangi kod çalışıyor?**
  * *Cevap:* `ekle.php` dosyasında gerçekleştiriliyor. Kullanıcı formu boş gönderdiğinde `if ($baslik === '')`, `if ($tutar === '')` gibi sunucu taraflı PHP koşul blokları çalışarak `$hatalar` dizisine ilgili alanın hata mesajını ekliyor; `$hatalar` dizisi boş olmadığı için `if (empty($hatalar))` bloğuna girilmiyor ve form girilen veriler korunarak hata mesajlarıyla tekrar gösteriliyor.
* **Soru 6: Kayıt silme işlemi nasıl gerçekleştiriliyor?**
  * *Cevap:* Tablodaki `×` düğmesi bir `POST` formu içinde kaydın `id` değerini ve `csrf` anahtarını `sil.php` dosyasına gönderir. `sil.php`, CSRF anahtarını doğruladıktan sonra `kayit_sil($kullanici_id, $id)` fonksiyonuyla yalnızca giriş yapan kullanıcıya ait olan ilgili kaydı (ve varsa dekont dosyasını) silip `index.php?silindi=1` adresine yönlendirir.
* **Soru 7: `htmlspecialchars()` fonksiyonunun görevi nedir?**
  * *Cevap:* Kullanıcının girdiği `<`, `>`, `"`, `'`, `&` gibi özel HTML karakterlerini güvenli HTML varlıklarına (`&lt;`, `&gt;` vb.) dönüştürerek XSS (Cross-Site Scripting) saldırılarını önler. Projede `functions.php` içindeki `guvenli()` fonksiyonu aracılığıyla tüm çıktılarda kullanılmıştır.
* **Soru 8: Projenize yeni bir alan (örneğin "Ödeme Yöntemi: Nakit / Kart") eklemeniz gerekirse hangi dosyaları değiştirirsiniz?**
  * *Cevap:* 1) `db.php` / `veritabani.sql` içinde `harcamalar` tablosuna yeni sütunu eklerim, 2) `ekle.php` ve `duzenle.php` içine form alanını ve doğrulamasını eklerim, 3) `functions.php` içindeki `kayit_ekle()` ve `kayit_guncelle()` sorgularına eklerim, 4) `index.php` içindeki tabloya yeni sütunu yazdırırım.

---

## 7. Yapay Zekâ Kullanım Açıklaması (Bölüm 7 Kuralları)
* **Kullanılan Yapay Zekâ Araçları:** Antigravity AI Kodlama Asistanı.
* **Kullanım Amaçları:** Özgün "Defter" arayüz tasarımının CSS ile kodlanması, SVG halka grafik matematiğinin kurgulanması, `password_hash` / `password_verify` tabanlı giriş sisteminin yapılandırılması ve uç durum testlerinin yapılması.
* **Örnek İstem (Prompt):**
  > "PHP ile geliştirdiğim BütçeTakip projesini klasik hazır panellere benzemeyen, muhasebe defteri ve modern dergi estetiğine sahip bir arayüzle yeniden tasarlayalım. password_verify() kullanan bir giriş ekranı, kullanıcıya özel $_SESSION yapısı, PHP ile hesaplanan SVG kategori grafiği, yaklaşan ödemeler kartı ve kategori raporu sayfası ekleyelim."
* **Öğrencinin Kendi Katkısı:** Proje konusunun ve veri alanlarının seçimi, tasarım yönünün ("Defter" konsepti ve giriş sistemi) belirlenmesi, XAMPP üzerinde testlerin yapılması ve kod yapısının incelenerek sunuma hazırlanılması.

# PrestaShop için MorPOS

[![PrestaShop](https://img.shields.io/badge/PrestaShop-1.7%2B-DF0067.svg)](https://www.prestashop.com/)
[![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-777bb4.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

**PrestaShop için MorPOS**, **Morpara MorPOS** ödeme sistemini PrestaShop mağazalarıyla entegre eden güvenli ve kullanımı kolay bir ödeme geçidi modülüdür. Müşteriler siparişlerini tamamlarken güvenli **Hosted Payment Page (HPP)** akışıyla yönlendirilir veya **Gömülü Ödeme Formu** kullanabilirler.

![MorPOS Payment Gateway](modules/morposgateway/views/img/morpos-logo.png)

## ✨ Özellikler

- 🛒 **PrestaShop Entegrasyonu**: MorPOS'u sorunsuz şekilde ödeme yöntemi olarak ekler
- 🔒 **Güvenli Ödemeler**: Maksimum güvenlik için Hosted Payment Page (HPP)
- 🎨 **Esnek Ödeme Formları**: Hosted veya Embedded ödeme arayüzü seçimi
- 🌍 **Çoklu Para Birimi**: TRY, USD, EUR, GBP para birimlerini destekler
- 💳 **Çoklu Ödeme Seçenekleri**: Kredi kartı, banka kartı ve taksitli ödemeler
- 🔄 **Akıllı Yeniden Deneme Sistemi**: Başarısız ödemeler için otomatik yeniden deneme mekanizması
- 🧪 **Sandbox Modu**: Geliştirme için test ortamı
- 🔧 **Kolay Yapılandırma**: Bağlantı testi ile basit yönetici paneli kurulumu
- 🛡️ **Güvenlik Özellikleri**: TLS 1.2+ gereksinimi, imzalı API iletişimi
- 📱 **Çoklu Mağaza Desteği**: PrestaShop'un çoklu mağaza özelliği ile uyumlu

## 📋 Gereksinimler

### Sunucu Gereksinimleri

| Bileşen | Minimum | Önerilen |
|---------|---------|----------|
| **PrestaShop** | 1.7.0 | 8.2+ |
| **PHP** | 7.4 | 8.2+ |
| **TLS** | 1.2 | 1.3 |

### PHP Uzantıları

- `cURL` - API iletişimi için gerekli
- `json` - Veri işleme için gerekli
- `hash` - Güvenlik imzaları için gerekli
- `openssl` - Güvenli bağlantılar için gerekli

### PrestaShop Özellikleri

- **SSL Sertifikası**: Üretim ortamları için şiddetle önerilir
- **URL Yeniden Yazma**: Ödeme geri çağrıları için gerekli
- **Desteklenen Para Birimleri**: TRY, USD, EUR veya GBP'den en az biri etkin olmalıdır

## 🚀 Kurulum

### Yöntem 1: PrestaShop Modül Yöneticisi (Önerilen)

1. **Modülü İndirin**
   - En son sürüm ZIP dosyasını [GitHub Releases](https://github.com/morpara/morpos-prestashop/releases) sayfasından indirin

2. **Yönetici Paneli Üzerinden Yükleyin**
   - PrestaShop yönetici paneline gidin → **Modüller** → **Modül Yöneticisi**
   - **Modül Yükle** butonuna tıklayın
   - ZIP dosyasını sürükleyip bırakın veya seçin
   - Kurulumdan sonra **Yapılandır** butonuna tıklayın

### Yöntem 2: Manuel Kurulum (Geliştiriciler)

1. **Modülü İndirin**
   ```bash
   git clone https://github.com/morpara/morpos-prestashop.git
   ```

2. **PrestaShop'a Yükleyin**
   ```bash
   cp -r morpos-prestashop/modules/morposgateway/ /path/to/prestashop/modules/
   ```

3. **Modülü Kurun**
   - PrestaShop yönetici paneline gidin → **Modüller** → **Modül Yöneticisi**
   - "MorPOS" araması yapın
   - **Kur** butonuna tıklayın
   - Kurulumdan sonra **Yapılandır** butonuna tıklayın

### Yöntem 3: FTP/SSH Yükleme

1. Modül ZIP dosyasını çıkarın
2. `morposgateway` klasörünü `/modules/` dizinine yükleyin
3. PrestaShop yönetici paneline gidin → **Modüller** → **Modül Yöneticisi**
4. "MorPOS Payment Plugin" araması yapın
5. **Kur** → **Yapılandır** butonlarına tıklayın

## ⚙️ Yapılandırma

### 1. Temel Kurulum

**Modüller** → **Modül Yöneticisi** → **"MorPOS"** araması yapın → **Yapılandır** butonuna tıklayın

### 2. Gerekli Ayarlar

Aşağıdaki zorunlu alanları doldurun:

| Alan | Açıklama | Örnek |
|------|----------|-------|
| **Merchant ID** | Benzersiz bayi kimliğiniz | `12345` |
| **Client ID** | OAuth istemci kimliği | `your_client_id` |
| **Client Secret** | OAuth istemci şifresi | `your_client_secret` |
| **API Key** | API istekleri için kimlik doğrulama anahtarı | `your_api_key` |

### 3. Ortam Ayarları

- **Durum**: Ödeme modülünü etkinleştirin veya devre dışı bırakın
- **Test Modu**: Geliştirme/test için etkinleştirin
  - Sandbox uç noktalarını kullanır
  - Gerçek işlem yapılmaz
  - Test kart numaraları kabul edilir

- **Form Türü**: Ödeme arayüzünü seçin
  - `Hosted`: MorPOS ödeme sayfasına yönlendirme (önerilen)
  - `Embedded`: Ödeme sayfanızda gömülü ödeme formu

### 4. Sipariş Durumu Ayarları

Farklı ödeme sonuçları için sipariş durumlarını yapılandırın:
- **Başarı Durumu**: Ödeme başarılı olduğunda sipariş durumu (varsayılan: Ödeme Kabul Edildi)
- **Hata Durumu**: Ödeme başarısız olduğunda sipariş durumu (varsayılan: Ödeme Hatası)

### 5. Bağlantı Testi

Kimlik bilgilerini girdikten sonra:
1. **Bağlantıyı Test Et** düğmesine tıklayın
2. Yeşil onay işaretinin görünmesini doğrulayın ("Bağlantı Başarılı")
3. Formun altındaki sistem gereksinimlerinin durumunu kontrol edin

Sistem şunları kontrol eder:
- ✅ PHP sürüm uyumluluğu (7.4+)
- ✅ PrestaShop sürüm uyumluluğu (1.7+)
- ✅ TLS sürüm desteği (1.2+)
- ✅ Gerekli PHP uzantıları (cURL, JSON, OpenSSL, Hash)

## 🛠️ Geliştirme ve Hata Ayıklama

### Loglama

PrestaShop'ta hata ayıklama modunu `/config/defines.inc.php` dosyasını düzenleyerek etkinleştirin:

```php
define('_PS_MODE_DEV_', true);
```

Loglar PrestaShop kurulumunuzdaki `/var/logs/` dizinine yazılacaktır.

### Veritabanı Tabloları

Modül, ödeme denemelerini izlemek için özel bir tablo oluşturur:
- `ps_morpos_conversation_attempt` - Conversation ID'leri ve ödeme yeniden deneme verilerini saklar

### Ödeme Akışı

1. **İlk Sipariş Oluşturma**: Sipariş "Ödeme Bekleniyor" durumu ile oluşturulur
2. **Ödeme Yönlendirmesi**: Müşteri MorPOS ödeme sayfasına yönlendirilir
3. **Ödeme İşleme**: Müşteri MorPOS'ta ödemeyi tamamlar
4. **Callback İşleme**: MorPOS ödeme sonucunu callback URL'ine gönderir
5. **Sipariş Güncelleme**: Ödeme sonucuna göre sipariş durumu güncellenir
6. **Yeniden Deneme Mekanizması**: Başarısız ödemeler aynı sipariş ID'si ile yeniden denenebilir

## 🔍 Sorun Giderme

### Yaygın Sorunlar

#### "Ödeme başlatılırken bir hata oluştu." Hatası

Bu genellikle yapılandırma sorunlarını belirtir:

1. **Kimlik Bilgilerini Kontrol Edin**: Tüm API kimlik bilgilerinin doğru olduğunu doğrulayın
2. **Bağlantı Testi**: Modül yapılandırmasında bağlantı testi özelliğini kullanın
3. **Gereksinimleri Kontrol Edin**: Sunucunun minimum gereksinimleri karşıladığından emin olun
4. **SSL/TLS**: TLS 1.2+ desteklendiğini doğrulayın
5. **Zaman Senkronizasyonu**: Sunucu saatinin doğru olduğundan emin olun
6. **İzinler**: PHP'nin `/var/logs/` dizinine yazma erişimi olduğunu doğrulayın

#### Ödeme İşlenmiyor

1. **Para Birimi Desteği**: Para biriminin desteklendiğinden emin olun (TRY, USD, EUR, GBP)
2. **Tutar Limitleri**: Minimum/maksimum işlem limitlerini kontrol edin
3. **Ağ**: Giden HTTPS bağlantılarına izin verildiğini doğrulayın
4. **Loglar**: API hataları için `/var/logs/` içindeki PrestaShop loglarını kontrol edin
5. **Modül Durumu**: Yapılandırmada modülün etkin olduğundan emin olun

#### Ödeme Sayfası Sorunları

1. **URL Yeniden Yazma**: PrestaShop'ta friendly URL'lerin etkin olduğundan emin olun
2. **Çoklu Mağaza**: Modülün aktif mağaza bağlamı için yapılandırıldığını doğrulayın
3. **Çakışmalar**: Test etmek için diğer ödeme modüllerini geçici olarak devre dışı bırakın
4. **Tema**: Varsayılan PrestaShop teması ile test edin
5. **Önbellek**: PrestaShop önbelleğini temizleyin (Gelişmiş Parametreler → Performans)

#### Callback/Return URL Sorunları

1. **Güvenlik Duvarı**: Callback URL'lerinin güvenlik duvarı tarafından engellenMediğinden emin olun
2. **SSL**: SSL sertifikasının geçerli ve kendi imzalı olmadığını doğrulayın
3. **URL Formatı**: Callback URL'leri herkese açık olarak erişilebilir olmalıdır
4. **Ödeme Yöntemi**: Ödeme yönteminin müşterinin para birimi için etkin olduğunu kontrol edin

### Sistem Gereksinimleri Kontrolü

Modül, yapılandırma sayfasından erişilebilen yerleşik bir sistem gereksinimleri kontrolörü içerir. Şunları doğrular:

- ✅ PHP sürüm uyumluluğu (7.4+)
- ✅ PrestaShop sürüm uyumluluğu (1.7+)
- ✅ TLS sürüm desteği (1.2+)
- ✅ Gerekli PHP uzantıları (cURL, JSON, OpenSSL, Hash)

### Hata Ayıklama Modu

Detaylı loglama etkinleştirin:

```php
// config/defines.inc.php
define('_PS_MODE_DEV_', true);

// Logları kontrol edin: var/logs/
```

### Veritabanı Sorunları

Ödeme izleme tablosu bozuksa veya eksikse:

```sql
-- Conversation attempt tablosunu yeniden oluşturun
DROP TABLE IF EXISTS ps_morpos_conversation_attempt;

CREATE TABLE ps_morpos_conversation_attempt (
    id INT(11) NOT NULL AUTO_INCREMENT,
    order_id INT(11) NOT NULL,
    attempt_seq INT(11) NOT NULL DEFAULT 0,
    conversation_id VARCHAR(64) NOT NULL,
    data TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY order_conversation_unique (order_id, conversation_id),
    KEY order_id_idx (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## 🌐 Uluslararasılaştırma

Modül birden fazla dili destekler:

- **Türkçe (tr_TR)**: Tam çeviri mevcut
- **İngilizce (en_US)**: Varsayılan dil

### Çeviri Ekleme

PrestaShop, çevirileri yerleşik sistemi aracılığıyla yönetir. Yeni çeviriler eklemek için:

1. **Çeviri Dosyalarını Dışa Aktarın**
   - **Uluslararası** → **Çeviriler** sayfasına gidin
   - "Yüklü modül çevirileri"ni seçin
   - Dilinizi seçin
   - "MorPOS Payment Plugin"i bulun ve çevirin

2. **Manuel Çeviri** (geliştiriciler için)
   - Çeviri dosyaları `/modules/morposgateway/translations/` dizininde bulunur
   - İngilizce: `en.php`
   - Türkçe: `tr.php`
   - Türkçe (resmi): `tr-TR/` dizininde XLIFF dosyaları

3. **Çevirileri Gönderin**
   - Repository'yi fork edin
   - Çeviri dosyalarınızı ekleyin
   - GitHub pull request'leri aracılığıyla gönderin

## 🤝 Katkıda Bulunma

Katkılarınızı bekliyoruz! Başlamak için:

### Geliştirme Kurulumu

1. **Repository'yi Fork Edin**
   ```bash
   git clone https://github.com/YOUR_USERNAME/morpos-prestashop.git
   cd morpos-prestashop
   ```

2. **Yerel PrestaShop Kurun**
   - PrestaShop'u yerel olarak kurun (sürüm 1.7+)
   - Modülü `modules/` dizinine kopyalayın:
     ```bash
     cp -r morpos-prestashop/modules/morposgateway/ /path/to/prestashop/modules/
     ```
   - PrestaShop yönetici panelinden modülü kurun

3. **Değişiklik Yapın**
   - PrestaShop kodlama standartlarını takip edin
   - Uygun dokümantasyon ekleyin
   - Farklı PrestaShop sürümleriyle test edin (1.7.x ve 8.x)
   - Hem Hosted hem Embedded ödeme formlarını test edin

4. **Pull Request Gönderin**
   - Feature branch oluşturun: `git checkout -b feature/your-feature`
   - Değişiklikleri commit edin: `git commit -m "Add your feature"`
   - Branch'i push edin: `git push origin feature/your-feature`
   - GitHub'da pull request açın

### Kodlama Standartları

- [PrestaShop Kodlama Standartları](https://devdocs.prestashop.com/1.7/development/coding-standards/)'nı takip edin
- Anlamlı değişken isimleri ve yorumlar kullanın
- Desteklenen PrestaShop sürümleriyle uyumluluğu test edin
- Fonksiyonlar ve sınıflar için PHPDoc yorumları ekleyin
- Göndermeden önce PrestaShop Validator ile doğrulayın

### Test Kontrol Listesi

Göndermeden önce:
- ✅ Modül hatasız kurulur
- ✅ Modül temiz bir şekilde kaldırılır
- ✅ Ödeme hem Hosted hem Embedded formlarla çalışır
- ✅ Test modu doğru çalışır
- ✅ Bağlantı testi kimlik bilgilerini doğrular
- ✅ Ödeme yeniden deneme mekanizması çalışır
- ✅ Tüm desteklenen para birimleri çalışır (TRY, USD, EUR, GBP)
- ✅ Çoklu mağaza uyumluluğu (varsa)
- ✅ Çeviriler doğru yüklenir

## 📄 Lisans

Bu proje **MIT** Lisansı altında lisanslanmıştır - detaylar için [LICENSE](LICENSE) dosyasına bakın.

## 🆘 Destek

- **Dokümantasyon**: Bu README ve kod içi yorumları kontrol edin
- **Sorunlar**: [GitHub Issues](https://github.com/morpara/morpos-prestashop/issues)
- **PrestaShop Forum**: [PrestaShop Addons](https://addons.prestashop.com/)
- **İletişim**: [Morpara Destek](https://morpara.com/support)

## 🙏 Teşekkürler

- **PrestaShop Ekibi** - Mükemmel e-ticaret platformu için
- **PrestaShop Topluluğu** - Sağlam ekosistem ve destek için
- **Morpara** - Güvenli ödeme altyapısı için

---

**❤️ ile [Morpara](https://morpara.com/) tarafından yapılmıştır**

MorPOS ödeme çözümleri hakkında daha fazla bilgi için [morpara.com](https://morpara.com/)'u ziyaret edin.
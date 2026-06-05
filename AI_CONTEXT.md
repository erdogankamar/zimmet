# Zimmet Plugin AI Context

Bu dosya Gemini, Codex, Claude ve benzeri yapay zeka asistanlarının Zimmet
plugin projesini aynı bağlamla anlaması için kanonik başvuru dosyasıdır.

## Proje Ayrımı

- `hscdestek.com.tr` ayrı bir GLPI ana proje/deploy çalışma alanıdır.
- `zimmet` ayrı ve bağımsız bir GLPI plugin projesidir.
- Bu plugin ana GLPI çekirdeğinin parçası değildir; GLPI içine
  `plugins/zimmet/` klasörü olarak kurulur.
- Plugin geliştirme sırasında `hscdestek.com.tr/plugins/zimmet` altında
  bulunabilir, ancak bağımsız GitHub projesi `erdogankamar/zimmet` reposudur.
- Release, tag ve plugin kaynak kodu için hedef repo:
  `https://github.com/erdogankamar/zimmet`
- `hscdestek` reposu, plugin'in test/deploy ortamını içerebilir; Zimmet'in
  kanonik proje reposu olarak kullanılmamalıdır.

## Kimlik ve Marka

- Plugin adı: `Zimmet`
- Kısa ad: `zimmet`
- Geliştiren marka: `Artsution`
- Lisans: `GPLv3+`
- Güncel sürüm: `1.4.7`
- Sürümleme: Semantic Versioning (`MAJOR.MINOR.PATCH`)
- Tag formatı: `vX.Y.Z`
- Release asset adı: `zimmet.zip`

## Uyumluluk

- GLPI minimum: `10.0.0`
- GLPI maksimum: `10.0.99`
- PHP minimum: `7.4`
- PHP önerilen: GLPI 10.0.x ile uyumlu kararlı PHP sürümü
- PDF Türkçe karakter desteği için varsayılan font: `dejavusans`

## Temel Amaç

Zimmet plugin'i, GLPI varlık envanterinden personele atanmış ekipmanları alarak
ISO 27001 uyumlu zimmet ve teslim-tesellüm tutanakları üretir.

Ana işlevler:

- Tekil personel için zimmet/tesellüm tutanağı oluşturma
- Toplu personel seçimiyle çoklu tutanak üretimi
- Kurum/entity bazlı belge şablonu kullanma
- PDF üretme ve yazdırma
- Islak imzalı kopyaları arşivleme
- SHA-256 bütünlük kaydı ve denetim izi tutma
- GitHub veya manuel ZIP ile plugin güncelleme

## Önemli Dosyalar

- `setup.php`: Plugin sürümü, GLPI hook kayıtları, metadata ve uyumluluk.
- `hook.php`: Kurulum/kaldırma, veritabanı tabloları ve profil hakları.
- `inc/config.class.php`: Genel ayarlar, varlık türleri ve PDF font ayarı.
- `inc/document.class.php`: Tutanak ana modeli, varlık çekme, kayıt ve PDF akışı.
- `inc/documentitem.class.php`: Tutanak ekipman satırı snapshot kayıtları.
- `inc/template.class.php`: Kurum/entity bazlı belge şablonları.
- `inc/pdf.class.php`: PDF motoru ve kurumsal tutanak tasarımı.
- `inc/archive.class.php`: Arşivleme, hash ve denetim izi.
- `inc/update.class.php`: GitHub/manuel ZIP güncelleme motoru.
- `inc/menu.class.php`: Plugin menü yapısı.
- `inc/profile.class.php`: Profil ve yetki yönetimi.
- `front/`: Kullanıcı arayüzü sayfaları.
- `ajax/`: AJAX uçları.
- `css/zimmet.css`: Plugin arayüz tasarımı.
- `js/zimmet.js`: İstemci tarafı yardımcı fonksiyonlar.
- `locales/`: Çeviri dosyaları.
- `README.md`: Kullanıcı dokümantasyonu.
- `CHANGELOG.md`: Sürüm geçmişi.
- `LICENSE`: GPLv3 lisansı.

## Veritabanı Tabloları

- `glpi_plugin_zimmet_documents`: Tutanak başlıkları.
- `glpi_plugin_zimmet_documentitems`: Tutanak ekipman satırları.
- `glpi_plugin_zimmet_templates`: Kurum/entity bazlı şablonlar.
- `glpi_plugin_zimmet_configs`: Genel ayarlar.

## Yapılandırma Ayarları

`PluginZimmetConfig` üzerinden yönetilen temel ayarlar:

- `asset_types`: Otomatik çekilecek varlık türleri.
  Varsayılan: `Computer,Monitor,Peripheral,Phone,Printer`
- `pdf_font`: PDF fontu.
  Varsayılan: `dejavusans`

Desteklenen varlık türleri:

- `Computer`
- `Monitor`
- `Peripheral`
- `Phone`
- `Printer`

## Tasarım ve UI Kuralları

- Plugin arayüzü `.zimmet-app` kapsayıcısı altında stillenir.
- GLPI çekirdek CSS/JS dosyaları doğrudan değiştirilmemelidir.
- UI değişiklikleri mümkün olduğunca `css/zimmet.css` içinde kalmalıdır.
- Seçili varlık türü chip'leri işaretsiz olanlarla aynı buton formunda
  görünür; seçili durum yalnızca checkbox check işaretiyle belirtilir.
- Tarayıcı `alert()` ve `confirm()` kullanılmaz; GLPI uyumlu panel/modal tercih
  edilir.

## Geliştirme Kuralları

- Cevaplar Türkçe verilmelidir.
- Kod değişikliği yapmadan önce istek analiz edilmeli ve kapsam netleştirilmelidir.
- GLPI çekirdek dosyalarına dokunulmamalıdır.
- `hscdestek.com.tr` ana projesindeki plugin dışı dosyalara açık talimat
  olmadan dokunulmamalıdır.
- Plugin kodu bağımsız repo mantığıyla geliştirilmelidir.
- Sürüm artışı yapılacaksa `setup.php` ve `CHANGELOG.md` birlikte
  güncellenmelidir.
- Release yapılacaksa `zimmet.zip` yeniden oluşturulmalıdır.
- Gizli token, parola veya kişisel erişim anahtarı hiçbir dosyaya yazılmamalıdır.

## Doğrulama

Dar kapsamlı değişikliklerde en az şu kontroller yapılmalıdır:

```bash
php -l setup.php
php -l inc/config.class.php
```

Değişiklik yapılan PHP dosyaları için ayrıca `php -l <dosya>` çalıştırılmalıdır.
UI/CSS değişikliklerinde ilgili dosya farkı gözle kontrol edilmelidir.

## Paketleme

Plugin ZIP paketi root klasörü `zimmet/` olacak şekilde hazırlanmalıdır.

Ana proje çalışma alanından örnek:

```bash
cd /Users/erdogankamar/projects/hscdestek.com.tr/plugins
zip -r /private/tmp/zimmet-X.Y.Z.zip zimmet
cp /private/tmp/zimmet-X.Y.Z.zip /Users/erdogankamar/projects/hscdestek.com.tr/zimmet.zip
```

Bağımsız repo root'unda paket üretilecekse üst dizinden paketlenmelidir:

```bash
cd ..
zip -r /private/tmp/zimmet-X.Y.Z.zip zimmet
```

## Release Akışı

1. Plugin dosyalarını güncelle.
2. Gerekirse `setup.php` içinde `PLUGIN_ZIMMET_VERSION` değerini artır.
3. Gerekirse `CHANGELOG.md` içine yeni sürüm notunu ekle.
4. PHP lint kontrollerini çalıştır.
5. `zimmet.zip` paketini yeniden üret.
6. Bağımsız repo `erdogankamar/zimmet` üzerinde commit oluştur.
7. `vX.Y.Z` tag oluştur ve push et.
8. GitHub Release oluştur.
9. `zimmet.zip` dosyasını release asset olarak yükle.

## GitHub Reposu

Kanonik plugin reposu:

```text
https://github.com/erdogankamar/zimmet
```

Ana GLPI/deploy proje reposu ayrı olabilir:

```text
https://github.com/erdogankamar/hscdestek
```

AI asistanları release, tag ve plugin odaklı kaynak güncellemelerinde
`erdogankamar/zimmet` reposunu hedeflemelidir.

## Yapay Zeka Dosyaları

Bu dosya kanonik kaynaktır. Aşağıdaki dosyalar aynı bağlama yönlendirme yapar:

- `AGENTS.md`
- `CODEX.md`
- `GEMINI.md`
- `CLAUDE.md`

Bu dosyalarda çelişki varsa `AI_CONTEXT.md` esas alınmalıdır.

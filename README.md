# Zimmet — GLPI Eklentisi

> **Artsution tarafından geliştirilmiştir.**

GLPI varlık envanterinden, **ISO 27001** uyumlu **Zimmet Teslim Tutanağı** ve
**Teslim-Tesellüm Tutanağı** PDF'leri üretir. Tekil veya toplu (çoklu personel)
üretim, kurum (entity) bazlı şablon ve otomatik arşivleme destekler.

## Özellikler

- **Otomatik veri çekme:** Personel seçildiğinde GLPI'de o kişiye zimmetli
  (`users_id`) tüm cihazlar — Seri No (`serial`), Stok/Envanter No
  (`otherserial`), Durum (`states_id`) — otomatik gelir.
- **Manuel satır:** GLPI'de kayıtlı olmayan ekipman elle eklenebilir.
- **Çoklu kurum:** Her entity (kurum/şirket) için ayrı başlık, doküman no ve
  taahhüt metni; tutanak başlığı kişinin kurumuna göre otomatik seçilir.
- **Düzenlenebilir şablon:** Taahhütname metni (TinyMCE), doküman no ve
  revizyon arayüzden yönetilir.
- **Toplu üretim:** Kurum + grup filtresiyle personel listesi, çoklu seçim ve
  tek birleşik PDF.
- **Islak imza:** PDF'te boş "Teslim Eden / Teslim Alan" imza kutuları;
  ad-soyad ve ünvan otomatik basılır.
- **Arşivleme & bütünlük (ISO 27001):** Üretilen PDF, GLPI Document olarak
  personele bağlanır; SHA-256 özeti, üretim zamanı ve denetim günlüğü tutulur.
- **Türkçe:** Tam Türkçe arayüz; PDF'te DejaVu Sans ile sorunsuz Türkçe karakter.

## Gereksinimler

- GLPI 10.0.x
- PHP 7.4 – 8.3 (GLPI 10.0.19 PHP 8.5 desteklemez; sunucuda PHP 8.3 önerilir)

## Kurulum

1. Bu klasörü `glpi/plugins/zimmet/` altına koyun.
2. Yönetim → Eklentiler → "Zimmet" → Kur → Etkinleştir.
3. Profil yetkileri otomatik tanımlanır; kuran profile tam yetki verilir.
4. (İsteğe bağlı) Yönetim → Zimmet → Şablonlar'dan kuruma özel şablon ekleyin.

Konsoldan: `php bin/console plugin:install zimmet && php bin/console plugin:activate zimmet`

## Kullanım

- **Tekil:** Zimmet → Yeni Tutanak → personel seç → cihazları onayla → kaydet →
  "PDF Görüntüle / Yazdır" → ıslak imza → "İmzalı kopyayı arşivle".
- **Toplu:** Zimmet → Toplu Üretim → kurum/grup seç → personeli listele → seç →
  "Kayıtları oluştur & birleşik PDF üret".

## Veri tabanı tabloları

| Tablo | Açıklama |
|-------|----------|
| `glpi_plugin_zimmet_documents` | Tutanak başlıkları (kişi, tip, durum, hash) |
| `glpi_plugin_zimmet_documentitems` | Cihaz satırları (üretim anı snapshot'ı) |
| `glpi_plugin_zimmet_templates` | Kurum bazlı şablon (başlık, doküman no, metin) |
| `glpi_plugin_zimmet_configs` | Genel ayarlar (varlık türleri, PDF fontu) |

## Mimari notu

Eklenti tamamen bağımsızdır; **GLPI çekirdek dosyalarına dokunmaz**. Tüm
entegrasyon `$PLUGIN_HOOKS` ve resmi GLPI API'leri (`CommonDBTM`, `Document`,
`Document_Item`, `Search`, `Profile`) üzerinden yapılır → GLPI yükseltmelerinde
güvenli kalır.

## Kurumsal uyarı standardı

- Tarayıcı `alert()` ve `confirm()` kullanılmaz.
- Başarı, hata, uyarı ve onay mesajları GLPI uyumlu panel veya modal içinde gösterilir.
- Her mesaj kısa başlık, net sonuç ve gerekiyorsa kullanıcı aksiyonunu içerir.
- Kritik işlemlerde işlem etkisi açıkça yazılır; onay butonu eylemi doğrudan adlandırır.
- Teknik hata metinleri kullanıcıya sebep ve kontrol edilecek noktayı anlatacak şekilde yazılır.

## Lisans

Bu proje **GPLv3+** lisansı ile dağıtılır. Ayrıntılar için [LICENSE](LICENSE) dosyasına bakın.

## Katkı & İletişim

Geliştiren: **[Artsution](https://github.com/erdogankamar/zimmet)**
© 2026 Artsution. Tüm hakları saklıdır.

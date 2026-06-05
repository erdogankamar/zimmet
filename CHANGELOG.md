# Değişiklik Günlüğü

Bu projedeki önemli değişiklikler bu dosyada belgelenir.
Sürümleme [Semantic Versioning](https://semver.org/lang/tr/) yaklaşımını izler.

## [1.4.0] - 2026-06-05

### Eklendi
- **GitHub'dan güncelleme:** Güncelleme Merkezi'nden tek tıkla en son sürüm
  doğrudan GitHub deposundan indirilip uygulanabilir. "Sürümü kontrol et" ile
  kurulu sürüm ile depodaki sürüm karşılaştırılır (release → tag → dal sırası).
  Kurumsal ortamlar için GLPI proxy ayarları desteklenir.

### Düzeltildi
- **Güncelleme sonrası beyaz sayfa:** İşlem bitince artık Güncelleme Merkezi'ne
  dönülüp sonuç özeti gösteriliyor (önceden ana sayfaya yönlendirip boş sayfada
  kalabiliyordu).
- **OPcache bayatlığı:** Dosyalar değiştirildikten sonra OPcache sıfırlanıyor;
  `validate_timestamps=0` olan sunucularda güncelleme sonrası oluşan
  fatal/beyaz sayfa hatası giderildi.

### Değişti
- Güncelleme motoru paketi izole geçici dizine açıp kopyalayacak şekilde
  yeniden yazıldı; hem `zimmet/` hem de GitHub arşivlerinin `zimmet-<sürüm>/`
  üst klasör yapısı desteklenir. Zip-slip koruması korunur.

## [1.3.0] - 2026-06-05

### Değişti
- **Marka:** Proje **Artsution** markası altında yayına hazırlandı; tüm kaynak
  dosya başlıklarına "Artsution tarafından geliştirilmiştir" imzası eklendi.
- Kuruma özel varsayılanlar genel hale getirildi (PDF varsayılan başlığı,
  varsayılan şablon doküman numaraları, fiş öneki varsayılanları).

### Eklendi
- GPLv3 `LICENSE` dosyası.
- `README.md` Artsution markası, lisans ve katkı bölümleriyle güncellendi.
- `CHANGELOG.md` ve `.gitignore`.

## [1.2.1] - 2026-06-05

### Değişti
- Çoklu sayfa (devam sayfası) alt imza/paraf kutuları, ilk sayfadaki imza
  kutularıyla aynı kurumsal düzene getirildi (üst grup toplu, isim + ünvan
  alt bölgede ortalı, otomatik küçültme). Devam sayfası imza yüksekliği
  20mm → 25mm.

## [1.2.0] - 2026-06-05

### Değişti
- Toplu Üretim filtresinde **GRUP** etiketinin yanına kurumsal görünümlü
  `(opsiyonel)` ibaresi eklendi.

## [1.1.9] - 2026-06-05

### Değişti
- İmza kutuları (Teslim Eden / Teslim Alan) yeniden tasarlandı: başlık +
  ayırıcı + etiket üstte toplandı, isim ve ünvan alt bölgede dikey ortalandı.
  Kutu yüksekliği 28mm → 30mm.

## [1.1.8] - 2026-06-05

### Eklendi
- İmza kutularında ünvan, ad-soyadın altına ikinci satır olarak basılır.
- Teslim eden kişinin ünvanı PDF'e aktarılır.

## [1.1.7] - 2026-06-05

### Değişti
- Tüm kutular için ortak köşe radüsü (2.5mm) ve süsleme şeritlerinin radüse
  tam uyumu sağlandı.
- İmza/paraf kutularındaki uzun isimler için otomatik font küçültme.

## [1.1.6] - 2026-06-05

### Eklendi
- DEPARTMAN / GÖREV kutusunda departman ve görev iki ayrı satırda gösterilir.
- Kimlik kutularında uzun değerler için otomatik font küçültme.

## [1.1.5] - 2026-06-04

### Eklendi
- İlk yayın temeli: ISO 27001 uyumlu Zimmet Teslim ve Teslim-Tesellüm tutanağı
  üretimi, tekil/toplu üretim, kurum bazlı şablon, arşivleme ve denetim izi.

[1.4.0]: https://github.com/erdogankamar/zimmet/releases/tag/v1.4.0
[1.3.0]: https://github.com/erdogankamar/zimmet/releases/tag/v1.3.0
[1.2.1]: https://github.com/erdogankamar/zimmet/releases/tag/v1.2.1
[1.2.0]: https://github.com/erdogankamar/zimmet/releases/tag/v1.2.0
[1.1.9]: https://github.com/erdogankamar/zimmet/releases/tag/v1.1.9
[1.1.8]: https://github.com/erdogankamar/zimmet/releases/tag/v1.1.8
[1.1.7]: https://github.com/erdogankamar/zimmet/releases/tag/v1.1.7
[1.1.6]: https://github.com/erdogankamar/zimmet/releases/tag/v1.1.6
[1.1.5]: https://github.com/erdogankamar/zimmet/releases/tag/v1.1.5

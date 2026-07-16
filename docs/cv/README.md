# Zeynep Saltık — Savunma Sanayi CV

Bu klasörde iki sayfalık, metin tabanlı ve A4 ölçüsünde hazırlanmış özgeçmişin düzenlenebilir HTML kaynağı ile başvuruya hazır PDF çıktısı bulunur.

## Başvuru öncesi zorunlu kontroller

HTML dosyasında `[` karakterini aratın ve aşağıdaki alanları doğrulanmış bilgilerle doldurun:

- Telefon, profesyonel e-posta, şehir ve portfolyo/LinkedIn bağlantısı
- Son üç işverenin adı, şehirleri ve çalışma tarihleri
- Üniversite ve lise başlangıç/mezuniyet tarihleri
- ASPİLSAN Enerji ve Pavelsis için yapılan işlerin kapsamı

İş tanımlarındaki maddeler adayın gerçek sorumluluklarıyla karşılaştırılmalı; yapılmayan bir görev varsa çıkarılmalıdır. Kullanılan yapay zekâ ürünlerinin adları biliniyorsa “Yapay Zekâ Araçları” bölümündeki kategori adlarına eklenebilir.

## PDF üretme

Google Chrome ile:

```bash
google-chrome \
  --headless \
  --disable-gpu \
  --user-data-dir="/tmp/zeynep-cv-chrome" \
  --no-pdf-header-footer \
  --print-to-pdf="zeynep-saltik-savunma-sanayi-cv.pdf" \
  "file://$(pwd)/zeynep-saltik-savunma-sanayi-cv.html"
```

Yazdırma ayarları değiştirilirse kâğıt boyutu `A4`, kenar boşlukları `Yok` ve arka plan grafikleri `Açık` olmalıdır.

## ATS yaklaşımı

- CV’deki içerik görsel yerine seçilebilir gerçek metindir.
- Standart bölüm adları ve savunma sanayi ilanlarında karşılaşılan rol anahtar kelimeleri kullanılmıştır.
- Yetkinlik seviyeleri yalnızca grafikle değil, “İleri” metniyle de belirtilmiştir.
- İletişim bilgileri üstbilgi/altbilgi alanlarına gömülmemiştir.
- Tablo kullanılmamıştır.

İki sütunlu premium düzen bazı eski ATS sistemlerinde okuma sırasını etkileyebilir. Kritik çevrim içi başvurularda, sistem alanları ayrıca elle doldurulmalı ve portföy bağlantısı düz metin olarak eklenmelidir.

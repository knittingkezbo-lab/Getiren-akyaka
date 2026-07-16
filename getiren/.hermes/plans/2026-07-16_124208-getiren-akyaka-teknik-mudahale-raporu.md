# Getiren Akyaka Teknik Müdahale ve Güvenli Canlıya Çıkış Planı

> **For Hermes:** Bu planı TDD ile görev görev uygula; her iş paketinden sonra kapsam uyumu ve kod kalitesi incelemesi yap.

**Tarih:** 16 Temmuz 2026  
**Durum:** Uygulama demo için çalışıyor; gerçek ödeme ve kontrolsüz halka açık pilot için **NO-GO**.  
**Amaç:** Denetimde bulunan açıkları, veri bütünlüğü sorunlarını ve operasyon eksiklerini risk sırasına koymak; önce hızlı koruyucu müdahaleleri, sonra gerçek ödeme mimarisini, ardından pilot operasyon gereksinimlerini tamamlamak.

**Mimari yaklaşım:** İlk aşamada kapsamı küçük, geri alınabilir ve test edilebilir müdahalelerle sistem “fail-closed” hale getirilecek. Sipariş ve ödeme durum geçişleri tek otorite, satır kilidi ve atomik koşullar üzerinden yürütülecek. Gerçek PayTR entegrasyonu, mevcut senkron demo akışına birkaç HTTP çağrısı eklenerek değil; kalıcı payment-attempt kayıtları, idempotency, imzalı webhook, outbox ve reconciliation ile ayrı bir fazda kurulacak.

**Teknoloji:** Laravel 13, PHP 8.4, Vue 3, Inertia, MySQL 8, Docker, PHPUnit.

---

## 1. Yönetici özeti

Denetim sonucu çıkan işler aynı anda ele alınmamalıdır. İki farklı problem sınıfı vardır:

1. **Bugün düzeltilebilecek uygulama kusurları:** Tahmin tutarı uyuşmazlığı, adressiz sipariş, kapalı sipariş ayarının çalışmaması, eksik/tekrarlı fiş satırları, iki kuryenin aynı işi üstlenmesi, stale durum geçişleri, güvensiz admin ataması, sipariş kodu ve giriş rate-limit eksikliği.
2. **Hızlı yama ile çözülemeyecek ödeme mimarisi:** PayTR’nin asenkron provizyonu, dış PSP ile DB’nin atomik olmaması, callback güvenliği, retry/idempotency, ek ödeme, reconciliation ve ödeme kanıt zinciri.

Bu ayrım kritiktir. Birinci grubu hızla düzeltmek demo ve kontrollü test ortamını ciddi biçimde güvenli hale getirir. Ancak bu düzeltmeler **tek başına gerçek kart açmak için yeterli değildir**.

### Önerilen karar

- **Şimdi:** Aşama A ve B uygulanır; demo ödeme ile akış korunur.
- **Bu sırada:** PayTR callback’i ve gerçek sürücü fail-closed tutulur.
- **Sonra:** Fon akışı kararı ve PayTR ön provizyon dokümanı/hesap yetkisi alınır.
- **Gerçek ödeme:** Yalnızca Aşama C kabul kriterleri tamamlandıktan sonra açılır.
- **Halka açık pilot:** Aşama D’nin asgari operasyon maddeleri tamamlandıktan sonra başlatılır.

---

## 2. Öncelik ve canlıya çıkış kapıları

| Seviye | Anlamı | Sonuç |
|---|---|---|
| **P0-Acil** | Sömürülebilir açık, yanlış tahsilat veya temel veri bütünlüğü riski | İlk müdahale paketinde çözülmeli |
| **P0-Ödeme** | Gerçek kart işlemini güvensiz veya imkânsız yapan mimari eksik | Gerçek ödeme açılmadan çözülmeli |
| **P1-Pilot** | Sahada siparişi, teslimatı veya mutabakatı bozacak eksik | Kontrolsüz pilot öncesi çözülmeli |
| **P2-Sertleştirme** | Güvenlik, veri koruma, ölçek ve bakım kalitesi | Pilot öncesi mümkün olanlar; kalanlar takvimli borç |

### Kapı G0 — Güvenli demo

Aşama A tamamlanmış, testler ve build yeşil, PayTR yolu kapalı olmalıdır.

### Kapı G1 — Kontrollü saha denemesi (gerçek kart olmadan)

Aşama A + B tamamlanmış; kurye durum geçişleri, fiş bütünlüğü ve teslim akışı doğrulanmış olmalıdır.

### Kapı G2 — Gerçek ödeme

Aşama C’nin tamamı; PayTR test hesabında uçtan uca provizyon/capture/void, imzalı callback, replay testleri, idempotency ve reconciliation kanıtlanmış olmalıdır.

### Kapı G3 — Halka açık pilot

Aşama D’nin asgari operasyon akışları, hukuki/fon akışı onayı ve günlük mutabakat prosedürü hazır olmalıdır.

---

# AŞAMA A — Hızlı koruyucu müdahaleler

**Hedef süre:** İlk teknik sprint.  
**Amaç:** Gerçek PayTR’ye dokunmadan doğrudan müşteri, kurye ve veri bütünlüğü risklerini azaltmak.

## A1. PayTR yolunu fail-closed hale getir

**Neden:** `/odeme/paytr/geri-bildirim` şu an herkese açık ve imza doğrulamıyor. `PayTRGateway` ise tüm gerçek işlemlerde exception atıyor. Üretim şablonu buna rağmen `PAYMENT_DRIVER=paytr` öneriyor. Yanlış yapılandırma ya siparişi tamamen durdurur ya da callback açığını görünür bırakır.

**Yapılacaklar:**

- Tam entegrasyon bitene kadar callback rotasını yapılandırma bayrağı arkasına al; varsayılanı kapalı olsun ve kapalıyken `404` dönsün.
- Üretimde `demo` sürücüsünün yanlışlıkla kullanılmasını engelleyen boot-time guard ekle.
- Tamamlanmamış `paytr` sürücüsünün üretim şablonunda “hazır” gibi seçilmesini kaldır; şablonu güvenli varsayılanla güncelle.
- README’de “`PAYMENT_DRIVER=paytr` yeter” ve “çift tahsil imkânsız” gibi doğrulanmamış ifadeleri düzelt.

**Muhtemel dosyalar:**

- `config/payments.php`
- `app/Providers/AppServiceProvider.php`
- `routes/web.php`
- `app/Http/Controllers/PaymentCallbackController.php`
- `.env.example`
- `.env.production.example`
- `README.md`
- Yeni test: `tests/Feature/PaymentCallbackDisabledTest.php`
- Yeni test: `tests/Feature/ProductionPaymentGuardTest.php`

**Kabul kriterleri:**

- Bayrak kapalıyken callback her payload için 404 döner ve hiçbir ödeme/sipariş kaydı değişmez.
- `APP_ENV=production + PAYMENT_DRIVER=demo` uygulamayı sessizce gerçek sistem gibi başlatmaz.
- Varsayılan geliştirme/demo testleri çalışmaya devam eder.
- Hiçbir doküman PayTR’yi tamamlanmış göstermemelidir.

---

## A2. Tahmini yalnızca sunucudan üret

**Neden:** Vue bilinmeyen ürünü 40 TL ve %15 payla; PHP ise varsayılan 60 TL ve %35 payla hesaplıyor. Kullanıcının onayladığı tutarla sunucunun provizyona aldığı tutar farklı olabilir. Finansal onay ekranında iki ayrı algoritma kabul edilemez.

**Tercih edilen çözüm:** Frontend’e daha fazla ayar gönderip aynı algoritmayı ikinci kez kopyalamak yerine, debounce edilmiş bir sunucu tahmin endpoint’i kullanmak. Vue yalnızca dönen sonucu göstermeli.

**Yapılacaklar:**

- Kimliği doğrulanmış müşteri için `POST /musteri/siparis/tahmin` endpoint’i ekle.
- Endpoint `raw_text` ve aktif `zone_id` doğrulasın; `OrderEstimator` sonucunun müşteri için gerekli alanlarını JSON dönsün.
- `OrderNew.vue` içindeki yerel fiyat hesaplamasını kaldır; yazı/bölge değişiminde debounce ile endpoint’i çağır.
- Gönder butonunda gösterilen toplam, sunucu tahmin yanıtından gelsin.
- Son POST sırasında sunucu yeniden hesaplamaya devam etsin; önizleme güvenlik otoritesi olmasın.
- Bilinmeyen ürün sayısı ve uygulanan gerçek pay ekranda açıkça gösterilsin.

**Muhtemel dosyalar:**

- `routes/web.php`
- `app/Http/Controllers/Customer/OrderController.php`
- `app/Services/OrderEstimator.php`
- `resources/js/Pages/Customer/OrderNew.vue`
- Yeni test: `tests/Feature/OrderEstimateEndpointTest.php`
- Genişlet: `tests/Unit/OrderEstimatorTest.php`

**Kabul kriterleri:**

- `Ayışığı'ndan 2 adana` dahil her girişte ekrandaki toplam ile `store()` hesap sonucu aynıdır.
- Bilinmeyen ürün varsa %35 (veya güncel ayar) ekranda görünür; sabit 40 TL kodu kalmaz.
- Geçersiz/pasif zone ile tahmin üretilemez.
- Network hatasında eski veya uydurma toplamla sipariş onaylanamaz.

---

## A3. Teslimat adresini zorunlu ve görünür yap

**Neden:** Backend adresi nullable kabul ediyor; kayıtlı adresi olmayan müşteriye frontend adres alanı göstermiyor. Kurye teslimat konumu olmayan geçerli sipariş alabiliyor.

**Yapılacaklar:**

- `address_text` alanını sunucuda `required|string|max:255` yap.
- Adres etiketi opsiyonel kalabilir; fakat adres snapshot’ı zorunlu olmalı.
- Kayıtlı adres yoksa açık metin adres alanı göster.
- Kayıtlı adres varsa seçimden sonra adres metnini görünür ve düzenlenebilir yap.
- Validation hatasını alanın altında göster.

**Muhtemel dosyalar:**

- `app/Http/Controllers/Customer/OrderController.php`
- `resources/js/Pages/Customer/OrderNew.vue`
- `tests/Feature/OrderFlowTest.php`

**Kabul kriterleri:**

- Boş/yalnızca boşluk adresle sipariş oluşturulamaz.
- Kayıtlı adresi olmayan müşteri formu tamamlayabilir.
- Sipariş, gönderim anındaki adres metnini snapshot olarak saklar.

---

## A4. `accepting_orders` ayarını gerçek güvenlik kapısı yap

**Neden:** Yönetici sipariş kabulünü kapattığını sanarken `store()` sipariş almaya devam ediyor. Bu, kapasite/mesai/acil durum yönetimini yanıltır.

**Yapılacaklar:**

- Hem yeni sipariş sayfasında kapalı durum mesajı göster hem `store()` içinde tekrar kontrol et.
- Kontrol yalnızca UI’da kalmasın; doğrudan POST da reddedilsin.
- Kapalı durumda ödeme gateway çağrılmamalı ve sipariş oluşmamalı.
- `auto_assign_courier` henüz uygulanmayacaksa admin ekranında “yakında” olarak işaretle veya ayarı kaldır; çalışan özellik gibi görünmesin.

**Muhtemel dosyalar:**

- `app/Http/Controllers/Customer/OrderController.php`
- `resources/js/Pages/Customer/OrderNew.vue`
- `app/Http/Controllers/Admin/SettingController.php`
- İlgili admin Vue sayfası
- `tests/Feature/OrderFlowTest.php`

**Kabul kriterleri:**

- Ayar kapalıyken GET açıklayıcı durum döner, POST sipariş/authorization yaratmadan reddedilir.
- Ayar yeniden açıldığında normal akış çalışır.
- Çalışmayan otomatik atama seçeneği kullanıcıyı yanıltmaz.

---

## A5. Fiş payload bütünlüğünü zorunlu kıl

**Neden:** Kurye eksik kalem gönderebilir, aynı kalemi tekrarlayabilir veya yabancı ID’leri sessizce ekleyebilir. Bu doğrudan yanlış capture ve fiyat sözlüğünün zehirlenmesi demektir.

**Yapılacaklar:**

- `items.*.id` için `distinct` kullan.
- Gönderilen ID kümesinin kilitli siparişin item ID kümesine **tam eşit** olmasını zorunlu kıl.
- Başka siparişe ait/bilinmeyen ID’yi sessizce atlama; tüm isteği 422 ile reddet.
- Mevcut modelde “bulunamadı” durumu olmadığı için geçici olarak her kalemde pozitif gerçek fiyat iste; sıfır fiyatın anlamını ileriki item-fulfillment modeli çözsün.
- Toplamı doğrulanmış ve kilitli DB kalemleri üzerinden bir kez hesapla.
- Validation başarısızsa hiçbir item, sipariş veya authorization değişmemeli.

**Muhtemel dosyalar:**

- `app/Http/Controllers/Courier/JobController.php`
- Tercihen yeni Form Request: `app/Http/Requests/SettleOrderRequest.php`
- `tests/Feature/CourierSettleTest.php`

**Zorunlu testler:**

- Eksik item listesi reddedilir.
- Duplicate ID reddedilir.
- Yabancı sipariş item ID’si reddedilir.
- Bilinmeyen ID reddedilir.
- Sıfır/negatif fiyat reddedilir.
- Tam ve geçerli payload mevcut davranışı korur.

**Kabul kriterleri:** Reddedilen hiçbir payload capture, order update veya fiyat öğrenme yan etkisi yaratmaz.

---

## A6. Sipariş durum geçişlerini kilitle ve atomik hale getir

**Neden:** `accept()`, `advance()` ve `settle()` stale route-model nesnesini kontrol edip güncelliyor. İki kurye aynı işi alabilir; iptal edilmiş sipariş eski nesneyle yeniden aktifleşebilir; eşzamanlı settle çift PSP isteği oluşturabilir.

**Yapılacaklar:**

- Her yazma işleminde transaction başında siparişi DB’den `lockForUpdate()` ile yeniden oku.
- Yetki, kurye sahipliği ve mevcut durum kontrollerini kilitli nesne üzerinde tekrar yap.
- `accept()` için mümkünse tek koşullu update (`reserved + courier_id null`) ve etkilenen satır sayısı kontrolü kullan.
- `activeAuthorization()` için kilitlenebilir ayrı sorgu/metot ekle; payment satırı da işlem boyunca kilitli olsun.
- Bildirimleri transaction commit olduktan sonra gönder; başarısız işlem bildirim üretmesin.
- Hızlı aşamada settle yalnızca `OnTheWay` durumunda yapılabilsin; `Shopping` durumundan doğrudan “teslim edildi” geçişini kapat.

**Muhtemel dosyalar:**

- `app/Http/Controllers/Courier/JobController.php`
- `app/Http/Controllers/Customer/OrderController.php`
- `app/Models/Order.php`
- `tests/Feature/CourierSettleTest.php`
- Yeni test: `tests/Feature/OrderTransitionTest.php`
- MySQL yarış testi: `tests/Integration/OrderConcurrencyTest.php` veya küçük doğrulama script’i

**Kabul kriterleri:**

- Aynı siparişi iki kurye denediğinde yalnızca biri kazanır.
- İptal edilmiş sipariş `advance/settle` ile tekrar aktif hale gelemez.
- Aynı sipariş için yalnızca bir settlement başlatılabilir.
- SQLite feature testlerine ek olarak MySQL üzerinde gerçek iki bağlantılı yarış testi çalışır.

---

## A7. Admin atamasını backend invariants ile koru

**Neden:** UI yalnızca uygun siparişte buton gösteriyor, fakat endpoint teslim edilmiş/iptal edilmiş siparişi veya onaysız kuryeyi kabul ediyor. Geçmiş kazanç ve müşteri verisi yanlış kişiye taşınabilir.

**Yapılacaklar:**

- Siparişi transaction içinde kilitle.
- Yalnızca `Reserved + courier_id null` siparişe atama izin ver.
- Yalnızca `role=courier + approved_at not null` kullanıcı seç.
- Admin listesinde de yalnızca onaylı kuryeleri göster.
- Başarısız atamada audit ve bildirim oluşturma.

**Muhtemel dosyalar:**

- `app/Http/Controllers/Admin/OrderController.php`
- `tests/Feature/AdminOrderAssignmentTest.php`

**Kabul kriterleri:** Teslim edilmiş, iptal edilmiş, atanmış sipariş ve onaysız kurye senaryoları 422/404 ile yan etkisiz reddedilir.

---

## A8. Sipariş kodunu yarışa dayanıklı yap

**Neden:** `A24-` sabiti ve `MAX(id)+114` aynı anda iki istekte aynı kodu üretebilir. Unique constraint isteklerden birini 500’e düşürür; ayrıca yıl 2024’e sabittir.

**Tercih:** DB id’sinden sonra kod üretmek yerine baştan benzersiz ULID tabanlı, kullanıcıya okunabilir bir kod üretmek. Mevcut gösterim korunacaksa benzersizlik ihlalinde kontrollü retry gerekir.

**Muhtemel dosyalar:**

- `app/Http/Controllers/Customer/OrderController.php`
- Tercihen yeni servis: `app/Services/OrderCodeGenerator.php`
- `tests/Feature/OrderFlowTest.php`

**Kabul kriterleri:** Hızlı ardışık ve eşzamanlı siparişlerde kod çakışması ve 500 oluşmaz; kodda sabit `A24` kalmaz.

---

## A9. Login brute-force sınırı ekle

**Neden:** Login POST sınırsız parola denemesine açık. İleride proxy IP güveni düzeltilmeden yalnız IP’ye dayanmak da yeterli değildir.

**Yapılacaklar:**

- Normalized email + istemci IP birleşimi için Laravel RateLimiter tanımla.
- Başarılı girişte limiti temizle; başarısız denemede artır.
- Kullanıcıya kalan süreyi açıklayan standart validation mesajı göster.
- Route’a uygun throttle/özel limiter middleware’i bağla.

**Muhtemel dosyalar:**

- `app/Providers/AppServiceProvider.php` veya ayrı auth service provider
- `app/Http/Controllers/Auth/LoginController.php`
- `routes/web.php`
- Yeni test: `tests/Feature/LoginRateLimitTest.php`

**Kabul kriterleri:** Belirlenen deneme sayısından sonra aynı email+IP geçici engellenir; farklı hesaplar gereksiz yere global kilitlenmez; başarılı giriş sayacı temizler.

---

# AŞAMA B — Durum makinesi ve kontrollü pilot bütünlüğü

## B1. Fiş girişi ile fiziksel teslimatı ayır

**Neden:** Bugün `settle()` hem fiş kaydı, hem capture, hem `Delivered` geçişi yapıyor. Kurye alışveriş biter bitmez teslim edilmemiş ürünü tamamlanmış gösterebilir.

**Yapılacaklar:**

- Fiş kaydını ayrı bir eylem olarak modelle; sipariş `OnTheWay` durumuna geçsin ancak henüz capture/delivered olmasın.
- Teslim için ayrı onay endpoint’i ve en azından müşteri OTP’si kullan.
- Capture yalnızca teslim teyidinden sonra başlasın.
- `delivered_at` yalnızca gerçek teslim teyidinde yazılsın.

**Muhtemel dosyalar:**

- `app/Enums/OrderStatus.php`
- Yeni migration: receipt/fulfillment durum alanları veya yeni `order_receipts` tablosu
- `app/Http/Controllers/Courier/JobController.php`
- `resources/js/Pages/Courier/Order.vue`
- `resources/js/Pages/Customer/OrderTrack.vue`
- Yeni testler: `tests/Feature/DeliveryConfirmationTest.php`

**Kabul kriterleri:** Fiş girişi tek başına teslim/capture yapmaz; yanlış OTP teslim oluşturmaz; doğru teyit yalnız bir kez tamamlar.

---

## B2. Ek ödeme akışını fiziksel teslimattan ayır

**Neden:** `RequiresExtraPayment` kuryenin aktif listesinden düşüyor; müşteri ödeme yapınca sipariş doğrudan `Delivered` oluyor. Para sonucu fiziksel teslimatın yerine geçmiş durumda.

**Yapılacaklar:**

- Ödeme durumu ile operasyon durumunu ayrı alanlarda tut (`order_status` ve `payment_status`).
- Ek ödeme bekleyen iş kuryenin aktif işlerinde görünmeye devam etsin.
- Ek ödeme tamamlanması yalnız ödeme durumunu güncellesin; teslimat ayrıca teyit edilsin.
- Ek ödeme reddi/timeout ve ürünün ne olacağı için açık operasyon kararı yazılsın.

**Kabul kriterleri:** Ek ödeme başarı/başarısızlığı tek başına siparişi teslim edilmiş yapmaz; kurye işi kaybetmez.

---

## B3. Fiş kanıt zinciri ve fiyat öğrenme karantinası

**Neden:** Tek kanıt kuryenin elle yazdığı fiyat. İlk gözlem global fiyatı doğrudan eziyor ve müşteri ek ödemeyi kabul etmeden öğreniliyor.

**Yapılacaklar:**

- Fiş görseli, satıcı/mağaza, fiş toplamı ve zaman bilgisini saklayacak kayıt oluştur.
- Fiyat öğrenmeyi doğrudan global sözlüğe yazmak yerine review queue’ya al.
- Yalnızca tamamlanmış ve mümkünse admin/doğrulama eşiğini geçmiş receipt observation yayınlansın.
- Kuryenin yaptığı düzeltmelerin geçmişini koru.
- Hukuki metni gerçek işlenen veriyle eşleştir.

**Kabul kriterleri:** Kanıtsız/itirazlı/ödenmemiş fiş global fiyatı değiştirmez; her gözlem kaynak sipariş ve fişe kadar izlenebilir.

---

# AŞAMA C — Gerçek PayTR ödeme mimarisi

> Bu aşama PayTR mağaza hesabı, Ön Provizyon yetkisi ve güncel resmi entegrasyon dokümanı olmadan uygulanmış sayılmaz. Endpoint ve hash alanları tahmin edilmemelidir.

## C1. Fon akışı karar belgesi

Koddan önce şu sorular yazılı cevaplanmalı:

- Müşteriden hukuken kim tahsil ediyor?
- Mağazaya kim, hangi ödeme aracıyla ödüyor?
- Kurye ürün bedelini kendi cebinden mi finanse ediyor?
- Kurye hakedişi ne zaman doğuyor ve ne zaman ödeniyor?
- Platform geliri, kurye payı, PSP komisyonu ve vergi nasıl ayrılıyor?
- İade/chargeback zararı kimde?
- Fiş/fatura kimin adına?
- Marketplace/split payment veya lisanslı ödeme hizmeti ihtiyacı var mı?

**Çıktı:** `docs/fund-flow.md` ve avukat/mali müşavir/PSP teyidi. Bu belge olmadan payout/ledger kodlanmamalı.

---

## C2. Ödeme ve sipariş durumlarını ayır

**Neden:** PayTR `authorize()` senkron başarı garantisi vermez. Sipariş callback gelmeden `Reserved` olmamalı.

**Yapılacaklar:**

- Siparişe `PaymentPending` veya ayrı payment state ekle.
- `authorize` isteği payment attempt’i `initiated/provider_pending` durumuna getirir.
- Yalnızca doğrulanmış sağlayıcı sonucu siparişi kuryelere açar.
- Başarısız ödeme operasyon siparişini güvenli terminal duruma getirir; gecikmiş callback ilerlemiş durumu geri alamaz.

---

## C3. Kalıcı payment attempt ve idempotency modeli

**Önerilen durumlar:** `initiated`, `provider_pending`, `succeeded`, `failed`, `unknown`, `reconciliation_required`.

**Gerekli alanlar:**

- Uygulama idempotency key’i
- Sağlayıcı ve provider reference
- İşlem türü: authorize/capture/void/extra
- Beklenen kuruş ve para birimi
- Provider request/response özeti (hassas veri hariç)
- Deneme sayısı, son hata, timestamps
- İlişkili authorization ve order

**Kabul kriterleri:** Aynı idempotency key ile retry yeni tahsilat yaratmaz; timeout “başarısız” diye varsayılmaz, `unknown` olarak mutabakat bekler.

---

## C4. İmzalı, idempotent ve kilitli PayTR callback

**Yapılacaklar:**

- Güncel PayTR dokümanındaki alan sırası ve HMAC algoritmasını birebir uygula.
- `hash_equals` kullan.
- Status allowlist, beklenen kuruş, para birimi, merchant/order reference doğrula.
- Transaction içinde authorization/payment attempt satırını `lockForUpdate()` ile oku.
- Aynı callback replay’inde `OK` dön ama yan etkiyi tekrar etme.
- Çelişkili/gecikmiş callback mevcut ileri durumu geri çekmesin; reconciliation flag’i üret.
- Geçersiz imzayı logla fakat sırları/payload’daki hassas veriyi yazma.

**Zorunlu testler:** Geçerli success, geçerli fail, bozuk hash, yanlış amount, bilinmeyen status, replay, eşzamanlı success/fail, gecikmiş callback.

---

## C5. PSP çağrılarını DB transaction’dan saga/outbox modeline taşı

**Neden:** DB rollback, PSP’deki para hareketini geri alamaz.

**Yapılacaklar:**

- Transaction yalnızca niyet/payment attempt/outbox kaydını commit etsin.
- Queue job idempotency key ile PSP’ye gitsin.
- Provider sonucu ayrı transaction’da kalıcılaştırılsın.
- Başarılı provider + başarısız DB senaryosu provider query/reconciliation ile iyileştirilsin.
- Bildirimler de commit sonrası outbox üzerinden gönderilsin.

**Altyapı:** `QUEUE_CONNECTION=database` veya güvenilir queue + worker/supervisor + retry/backoff + failed jobs alarmı.

---

## C6. Reconciliation ve provizyon zaman aşımı

**Yapılacaklar:**

- Sağlayıcıdan açık/başarılı işlemleri sorgulayıp yerel kayıtla karşılaştıran Artisan command/job.
- `provider_pending`, `unknown`, açık authorization ve süresi geçmiş siparişler için rapor.
- Otomatik düzeltme yalnız kesin durumlarda; diğerleri admin incelemesine.
- Günlük toplam: authorize, capture, release/void, failed, unknown.

**Kabul kriterleri:** PSP başarılı/DB başarısız ve DB başarılı/PSP belirsiz senaryoları raporda görünür ve güvenli retry edilebilir.

---

# AŞAMA D — Operasyon, veri koruma ve ürün hazırlığı

## D1. Ürün bulunamadı, ikame ve kısmi temin

Her sipariş kaleminde fulfillment durumu (`requested/found/substituted/unavailable`) olmalı. Kurye yeni miktar veya ikame teklif edebilmeli; müşteri bütçe artışını ve ikameyi onaylamalı. Mevcut `actual_price=0` yaklaşımı bu anlamları taşıyamaz.

## D2. Kurye hakediş ve payout ledger

`service_fee` doğrudan “kurye kazancı” sayılmamalı. Ürün masrafı, kurye hizmet payı, platform payı, PSP komisyonu, iade/chargeback ve payout ayrı immutable ledger hareketleri olmalı.

## D3. Finansal veriyi koru

- IBAN için gerçek mod-97 checksum validasyonu.
- IBAN ve hesap sahibinde encrypted cast; admin ekranında maskeleme.
- Kullanıcı/sipariş silme cascade’lerini finansal kayıt saklama politikasına göre değiştir.
- Para hesaplarını kuruş integer veya kesin decimal value object’e taşı.

## D4. Güvenilir proxy ve audit

`trustProxies('*')` yerine gerçek reverse proxy/CIDR listesi kullan. Cloudflare veya nginx zinciri netleşmeden IP tabanlı audit/rate limit kesin güvenilir kabul edilmemeli.

## D5. CI ve gerçek veritabanı testleri

- README’de var denilen `.github/workflows/ci.yml` gerçekten oluşturulmalı.
- PHPUnit + frontend build + Pint + Composer audit + npm audit koşmalı.
- Kritik transaction/concurrency testleri MySQL 8 servisinde çalışmalı; SQLite tek başına yeterli değildir.
- Tarayıcı E2E: müşteri oluşturma, kurye accept, fiş, teslim teyidi, iptal ve ek ödeme.

## D6. Parser ve fiyat modelini yeniden konumlandır

Kısa vadede parser “fiyat önizleme yardımcısı” olarak anlatılmalı; doğal dil motoru gibi sunulmamalı. Orta vadede kalemler kullanıcıya chip/satır halinde gösterilip adet, birim ve ürün düzenletilmeli. Fiyat gözlemlerine mağaza, bölge, gramaj/varyant ve tarih bağlamı eklenmeli.

---

## 3. Uygulama sırası — önerilen görev listesi

### Paket 1 — Güvenli temel

1. PayTR callback/sürücü fail-closed + doküman düzeltmesi.
2. Tahmin endpoint’i ve Vue’da tek sunucu sonucu.
3. Zorunlu adres ve adres girişi.
4. `accepting_orders` backend guard.
5. Paket testleri + build + Pint.

### Paket 2 — Sipariş bütünlüğü

1. Fiş exact-set doğrulama testleri ve implementasyonu.
2. Atomik kurye accept.
3. Kilitli advance/cancel/settle protokolü.
4. Settle’ı yalnız `OnTheWay` ile sınırla.
5. Admin atama guard’ları.
6. Güvenli order code.
7. MySQL concurrency doğrulaması.

### Paket 3 — Güvenlik sertleştirmesi

1. Login rate limit.
2. IBAN checksum + encryption migrasyon planı.
3. Proxy allowlist.
4. CI workflow ve MySQL test job’ı.

### Paket 4 — Operasyon durumu

1. Fiş kaydı ve teslim teyidini ayır.
2. OTP/teslim kanıtı.
3. Ek ödeme ile teslimat durumunu ayır.
4. Fiş görseli ve price-learning review queue.

### Paket 5 — Gerçek ödeme

1. Fon akışı belgesi ve dış teyitler.
2. Payment state + payment attempts.
3. PayTR sandbox authorize/capture/void.
4. İmzalı callback + replay/concurrency testleri.
5. Outbox/queue/idempotency.
6. Reconciliation ve expiry.
7. Kontrollü test ödemeleri ve go-live checklist.

---

## 4. Test ve doğrulama standardı

Her uygulama görevi şu döngüyü izlemelidir:

1. Mevcut açığı gösteren failing test yaz.
2. Yalnız ilgili testi çalıştır ve beklenen nedenle kırıldığını doğrula.
3. Minimum güvenli implementasyonu yap.
4. İlgili test dosyasını çalıştır.
5. Tam PHP paketini çalıştır.
6. Frontend etkisi varsa production build al.
7. Pint ve güvenlik audit’lerini çalıştır.
8. Git diff’i kapsam dışı değişiklikler için incele.

**Komutlar:**

```bash
docker compose exec -T app php artisan test
docker compose exec -T app vendor/bin/pint --test
docker compose exec -T app composer audit --locked
docker compose exec -T node npm run build
docker compose exec -T node npm audit --omit=dev
```

Concurrency işleri için ayrıca MySQL kullanan iki bağlantılı integration test çalıştırılmalıdır; yalnız SQLite test sonucuna güvenilmemelidir.

---

## 5. Kapsam dışı bırakılmaması gereken riskler

- Hızlı müdahaleler tamamlanınca “üretime hazır” denmemeli; yalnız G0/G1 sağlanmış olur.
- PayTR anahtarları gelmeden endpoint/parametre adları tahmin edilmemeli.
- DB transaction içine harici ödeme çağrısı koyarak atomiklik sağlandığı varsayılmamalı.
- UI guard’ı backend iş kuralının yerine geçmemeli.
- Bildirim başarısızlığı finansal işlemi başarısız göstermemeli.
- “Fazlası iade” metni, bankanın provizyon çözme süresini garanti eder biçimde kullanılmamalı.
- Çalışmayan ayarlar ve README iddiaları özellik gibi sunulmamalı.

---

## 6. İlk uygulama paketinin net kabul tanımı

Aşağıdakilerin tamamı sağlandığında **Hızlı Müdahale Paketi tamamlandı** denebilir:

- [ ] PayTR callback varsayılan olarak erişilemez ve üretim demo sürücüsü fail-closed.
- [ ] Ekrandaki tahmin sunucudan gelir; store sonucu ile aynıdır.
- [ ] Adressiz sipariş oluşturulamaz; adresi olmayan kullanıcı formu tamamlayabilir.
- [ ] `accepting_orders=false` doğrudan POST’u da durdurur.
- [ ] Eksik, duplicate, yabancı veya sıfır fiyatlı fiş payload’ı yan etkisiz reddedilir.
- [ ] Aynı siparişi yalnız bir kurye üstlenebilir.
- [ ] `accept/advance/cancel/settle/admin assign` kilitli mevcut durum üzerinden karar verir.
- [ ] `Shopping` durumundan doğrudan settlement/delivery yapılamaz.
- [ ] Sipariş kodu çakışmaya ve sabit yıla dayanmaz.
- [ ] Login deneme sınırı vardır.
- [ ] Tam test paketi, frontend build, Pint ve audit kontrolleri geçer.
- [ ] README gerçek ürün durumunu doğru anlatır.

---

## 7. Sonuç

İlk teknik hedef PayTR’yi “hemen çalıştırmak” olmamalıdır. En yüksek getirili sıra şudur:

1. Kullanıcının gördüğü ve sunucunun işlediği tutarı eşitle.
2. Adres, sipariş kabulü ve fiş bütünlüğünü güvenceye al.
3. Tüm sipariş geçişlerini atomik ve kilitli hale getir.
4. Teslimat ile ödeme sonucunu birbirinden ayır.
5. Son olarak gerçek PSP’yi idempotent, webhook tabanlı ve mutabakatlı mimariyle bağla.

Bu sıra, hızlı görünür iyileştirmeler sağlarken finansal mimaride tehlikeli kısa yollar alınmasını önler. **Önerilen ilk uygulama kapsamı Paket 1 ve Paket 2’dir; PayTR gerçek entegrasyonu ayrı bir proje fazı olarak tutulmalıdır.**

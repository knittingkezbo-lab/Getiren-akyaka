# Getiren · Akyaka — Tema adayları

Laravel + Vue geliştirmesinden önce görsel yönü belirlemek için hazırlanan responsive HTML mockup'ları.
Her tema tek dosya, bağımsız (harici font/JS yok) ve doğrudan tarayıcıda açılır. Her biri 4 ekranı içerir:
**Giriş · Müşteri · Kurye · Yönetici**. "Giriş yap" ile uygulamaya girip üstteki/yan menüden roller arasında geçilir.

| Sürüm | Yön | Düzen | Palet |
|---|---|---|---|
| [version-1](version-1/index.html) | **Deniz Esintisi** | Üstte sekmeli navigasyon, landing + app hissi | Sıcak sahil: teal + mercan + kum |
| [version-2](version-2/index.html) | **Pusula** | Masaüstünde yan menü → mobilde alt navigasyon, dashboard hissi | Serin/minimal: emerald-teal + gökyüzü |
| [version-3](version-3/login.html) | **Gün Batımı** | **Çok sayfalı, statik, rol-bazlı** (müşteri / kurye / yönetici ayrı) | Sıcak boutique: terracotta + amber + erik, serif başlık, fiş motifi |

### Version 3 hakkında (rol-bazlı, statik)
**JS içermez** (tepkisiz, saf HTML+CSS); ortak tasarım sistemi [version-3/assets/theme.css](version-3/assets/theme.css). Giriş: [version-3/login.html](version-3/login.html) → "Demo olarak gir: Müşteri · Kurye · Yönetici" ile her role atlanabilir. Her rolün kendi kenar menüsü var; kenar menüsü altındaki mini "rol geçişi" ile roller arasında gezinilir.

**Müşteri** (`customer/`) — sadeleştirilmiş akış:
`dashboard` (ferah genel bakış) · **`order-new`** (yığılmayı çözen 3 adımlı sipariş + yapışkan fiş özeti) · `order-track` (harita + canlı durum) · `orders` (geçmiş) · `wallet` (bakiye + ledger hareketleri) · `profile`

**Kurye** (`courier/`): `dashboard` (müsait + aktif işler, günlük kazanç) · `order` (kalem fiyatı girme, durum ilerletme, fiş → tahsil/iade)

**Yönetici** (`admin/`): `dashboard` (KPI + grafik + bekleyen atamalar) · `orders` (filtre + kurye atama) · `couriers` (ekip yönetimi) · `settings` (bölge/hizmet bedeli, %güvenlik payı, tahmin sözlüğü)

**Ortak:** `login` · `register` · `components` (tüm arayüz objelerinin sergilendiği UI kit).

## Ortak özellikler
- Mobil / tablet / masaüstü uyumlu (mobile-first, esnek grid).
- Çalışan tahmin: serbest metin → ürün tahmini + %15 güvenlik payı + bölge bedeli = bloke tutarı.
- Bölgeler: Akyaka · Gökova · Akçapınar (çevre bölgeler sonra).
- Sipariş durum çizgisi (bloke → atandı → alışverişte → yolda → teslim), yönetici tablosu, kurye satır düzenleme.

## Önizleme
Dosyayı doğrudan çift tıklayarak açabilir ya da:
```bash
npm start   # theme/version-1/ altında python http.server; http://localhost:5173/theme/version-1/
```

Beğenilen sürümü seçince onu Laravel (Inertia) + Vue bileşenlerine taşıyıp Docker kurulumuna geçeceğiz.

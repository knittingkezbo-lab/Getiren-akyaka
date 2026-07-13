<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class LegalController extends Controller
{
    public function show(string $page): Response
    {
        $pages = $this->pages();
        abort_unless(isset($pages[$page]), 404);

        return Inertia::render('Legal/Show', [
            'slug' => $page,
            'doc' => $pages[$page],
            'nav' => collect($pages)->map(fn ($p, $slug) => [
                'slug' => $slug,
                'title' => $p['title'],
            ])->values()->all(),
        ]);
    }

    /**
     * TASLAK içerik — nihai metinler avukat onayından sonra girilecek.
     * Yapı hazır; her sayfa taslak bandıyla işaretli.
     */
    private function pages(): array
    {
        return [
            'kullanim-sartlari' => [
                'title' => 'Kullanım Şartları',
                'blocks' => [
                    ['h' => 'Hizmetin niteliği', 'p' => [
                        'Getiren Akyaka bir ürün satıcısı değildir. Talep ettiğiniz ürünleri sizin adınıza temin eder ve yerel kurye/concierge hizmeti verir.',
                        'Ürünlerin satıcısı, alışverişin yapıldığı market, eczane, fırın vb. işletmedir. Getiren Akyaka yalnızca temin ve teslimat hizmetinden sorumludur.',
                    ]],
                    ['h' => 'Tutar ve gerçek fiş', 'p' => [
                        'Sipariş anında gösterilen ürün bedeli tahminidir. Gerçek tutar, alışveriş yapılan işletmenin fişine göre kesinleşir.',
                        'Hizmet ve teslimat bedeli ayrıca gösterilir. Fazla tutar çözülür/iade edilir; eksik kalırsa ek ödeme istenir.',
                    ]],
                    ['h' => 'Yükümlülükler', 'p' => [
                        'Sipariş vererek doğru bilgi verdiğinizi ve ödeme yükümlülüğünü kabul ettiğinizi beyan edersiniz.',
                        'Yasaklı ürünler ve iptal/iade koşulları ilgili sayfalarda düzenlenmiştir.',
                    ]],
                ],
            ],
            'kvkk' => [
                'title' => 'KVKK Aydınlatma Metni',
                'blocks' => [
                    ['h' => 'Veri sorumlusu', 'p' => [
                        '[İşletme unvanı, adres, iletişim bilgileri buraya girilecek.]',
                    ]],
                    ['h' => 'İşlenen kişisel veriler', 'list' => [
                        'Kimlik ve iletişim: ad soyad, telefon, e-posta',
                        'Teslimat: adres, sipariş metni',
                        'İşlem: fiş görseli, ödeme işlem referansı, banka/IBAN bilgisi (iade/çekim için), kurye bilgisi',
                        'Tercihler: bildirim ve iletişim tercihleri',
                    ]],
                    ['h' => 'İşleme amaçları ve hukuki sebepler', 'p' => [
                        'Veriler; siparişin oluşturulması ve ifası, teslimat, müşteri iletişimi ve yasal yükümlülüklerin yerine getirilmesi amacıyla işlenir.',
                        'Hukuki sebep genellikle sözleşmenin ifası ve hukuki yükümlülüktür. Kampanya/ticari ileti gönderimi yalnızca ayrıca vereceğiniz açık rıza ile yapılır.',
                    ]],
                    ['h' => 'Aktarım', 'p' => [
                        'Veriler; hizmetin ifası için kurye, ödeme kuruluşu ve yasal olarak yetkili mercilerle sınırlı olarak paylaşılabilir.',
                    ]],
                    ['h' => 'Haklarınız', 'p' => [
                        'KVKK m. 11 kapsamındaki haklarınızı kullanmak için iletişim kanallarımızdan bize ulaşabilirsiniz.',
                    ]],
                ],
            ],
            'gizlilik' => [
                'title' => 'Gizlilik Politikası',
                'blocks' => [
                    ['h' => 'Hangi verileri topluyoruz', 'p' => [
                        'Yalnızca hizmeti sunmak için gerekli verileri toplarız: hesap, sipariş, teslimat ve ödeme işlem bilgileri.',
                    ]],
                    ['h' => 'Saklama ve güvenlik', 'p' => [
                        'Veriler yalnızca gerekli süre boyunca saklanır ve makul teknik/idari tedbirlerle korunur. Fiş görselleri sınırlı erişimle tutulur.',
                    ]],
                    ['h' => 'Çerezler', 'p' => [
                        'Oturum ve temel işlevsellik için zorunlu çerezler kullanılır.',
                    ]],
                ],
            ],
            'iptal-iade' => [
                'title' => 'İptal ve İade',
                'blocks' => [
                    ['h' => 'İptal', 'p' => [
                        'Kurye alışverişe başlamadan önce sipariş iptal edilebilir ve provizyona alınan tutar çözülür.',
                        'Alışveriş başladıktan sonra iptal/iade, ürünün niteliğine ve alışveriş yapılan işletmenin koşullarına bağlıdır. Başlamış hizmet bedeli iade edilmeyebilir.',
                    ]],
                    ['h' => 'İade', 'p' => [
                        'Gerçek fiş tahmini tutarın altında kalırsa fark otomatik olarak çözülür/iade edilir.',
                    ]],
                ],
            ],
            'yasakli-urunler' => [
                'title' => 'Yasaklı Ürünler',
                'blocks' => [
                    ['h' => 'Temin edilemeyecek ürünler', 'p' => [
                        'Güvenlik ve mevzuat gereği aşağıdaki ürünler temin edilmez:',
                    ], 'list' => [
                        'Reçeteli ilaç ve tıbbi ürünler',
                        'Alkol, tütün ve elektronik sigara',
                        'Yaş sınırı olan ürünler',
                        'Silah, kesici alet ve tehlikeli maddeler',
                        'Hukuka aykırı ürünler',
                        'Canlı hayvan, çok değerli eşya',
                        'Özel taşıma veya soğuk zincir gerektiren ürünler',
                    ]],
                    ['h' => 'Uygulama', 'p' => [
                        'Kurye, uygun olmayan ürünlerde siparişi reddedebilir.',
                    ]],
                ],
            ],
            'iletisim' => [
                'title' => 'İletişim ve İşletme Bilgileri',
                'blocks' => [
                    ['h' => 'İşletme', 'p' => [
                        '[İşletme unvanı — şahıs işletmesi]',
                        '[Vergi dairesi / vergi no]',
                        '[Adres]',
                    ]],
                    ['h' => 'İletişim', 'p' => [
                        '[E-posta] · [Telefon]',
                        'ETBİS kayıt bilgisi tamamlandığında burada yer alacaktır.',
                    ]],
                ],
            ],
        ];
    }
}

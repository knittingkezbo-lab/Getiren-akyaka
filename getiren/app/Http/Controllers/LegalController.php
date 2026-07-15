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
            'draft' => (bool) config('company.legal_draft', true),
            'nav' => collect($pages)->map(fn ($p, $slug) => [
                'slug' => $slug,
                'title' => $p['title'],
            ])->values()->all(),
        ]);
    }

    /** config('company.*') değerini döndürür; boşsa neyin eksik olduğunu gösteren yer tutucu. */
    private function co(string $key, string $missing): string
    {
        $value = trim((string) config("company.{$key}"));

        return $value !== '' ? $value : $missing;
    }

    /**
     * Hukuki sayfa içerikleri. Kimlik alanları config/company.php'den (env) gelir.
     * Metinler TASLAK'tır — nihai hâli avukat onayından sonra girilir; LEGAL_DRAFT=false
     * yapılınca "taslak" bandı kalkar. Özellikle cayma hakkı ve sözleşme maddelerinin
     * kesin ifadesi avukatça belirlenmelidir.
     */
    private function pages(): array
    {
        $name = $this->co('name', 'Getiren Akyaka');
        $legalName = $this->co('legal_name', '[İşletme unvanı girilecek]');
        $addr = $this->co('address', '[Adres girilecek]');
        $phone = $this->co('phone', '[Telefon girilecek]');
        $email = $this->co('email', '[E-posta girilecek]');
        $taxOffice = $this->co('tax_office', '[Vergi dairesi]');
        $taxNo = $this->co('tax_no', '[Vergi/TC no]');
        $areas = $this->co('service_areas', 'Akyaka, Gökova, Akçapınar');
        $hours = $this->co('hours', '[Çalışma saatleri]');

        return [
            'kullanim-sartlari' => [
                'title' => 'Kullanım Şartları',
                'blocks' => [
                    ['h' => 'Hizmetin niteliği', 'p' => [
                        "{$name} bir ürün satıcısı değildir. Talep ettiğiniz ürünleri sizin adınıza temin eder ve yerel kurye/concierge hizmeti verir.",
                        'Ürünlerin satıcısı; alışverişin yapıldığı market, fırın vb. işletmedir. '.$name.' yalnızca temin ve teslimat hizmetinden sorumludur.',
                    ]],
                    ['h' => 'Tutar ve gerçek fiş', 'p' => [
                        'Sipariş anında gösterilen ürün bedeli tahminidir. Gerçek tutar, alışveriş yapılan işletmenin fişine göre kesinleşir.',
                        'Hizmet ve teslimat bedeli ayrıca gösterilir. Fazla tutar çözülür/iade edilir; eksik kalırsa ek ödeme istenir.',
                    ]],
                    ['h' => 'Yükümlülükler', 'p' => [
                        'Sipariş vererek doğru bilgi verdiğinizi ve ödeme yükümlülüğünü kabul ettiğinizi beyan edersiniz.',
                        'Yasaklı ürünler, teslimat ve iptal/iade koşulları ilgili sayfalarda düzenlenmiştir.',
                    ]],
                ],
            ],

            'mesafeli-sozlesme' => [
                'title' => 'Mesafeli Hizmet Sözleşmesi ve Ön Bilgilendirme',
                'blocks' => [
                    ['h' => 'Taraflar ve hizmet sağlayıcı', 'p' => [
                        "Hizmet sağlayıcı: {$legalName} ({$name}). Adres: {$addr}. Telefon: {$phone}. E-posta: {$email}. Vergi dairesi/no: {$taxOffice} / {$taxNo}.",
                        'Müşteri: hizmeti sipariş eden gerçek kişi.',
                    ]],
                    ['h' => 'Sözleşmenin konusu', 'p' => [
                        "Bu sözleşme, müşterinin serbest metinle bildirdiği ürünlerin {$name} tarafından sizin adınıza temin edilmesi ve belirtilen adrese teslimi hizmetini kapsar. {$name} ürünlerin satıcısı değil, temin ve teslimat aracısıdır.",
                    ]],
                    ['h' => 'Bedel ve ödeme', 'p' => [
                        'Ürün bedeli tahmini olarak provizyona alınır; gerçek tutar işletme fişine göre kesinleşir. Hizmet/teslimat bedeli ayrıca belirtilir.',
                        'Ödeme, lisanslı ödeme kuruluşu (PayTR) altyapısıyla alınır. Kart bilgileriniz ödeme kuruluşunda işlenir; '.$name.' kart bilgisi görmez/saklamaz.',
                    ]],
                    ['h' => 'İfa ve teslimat', 'p' => [
                        "Hizmet bölgesi: {$areas}. Çalışma saatleri: {$hours}. Teslimat, stok ve işletme durumuna göre makul sürede yapılır.",
                    ]],
                    ['h' => 'Cayma hakkı', 'p' => [
                        'Kurye alışverişe başlamadan önce sipariş iptal edilebilir. Niteliği gereği ifasına başlanmış/tamamlanmış hizmetlerde ve müşteri talebiyle temin edilen ürünlerde cayma hakkının kapsamı mevzuata göre sınırlanabilir.',
                        '[Cayma hakkının kesin kapsamı ve istisnaları avukat onayıyla bu bölümde netleştirilecektir.]',
                    ]],
                    ['h' => 'Şikayet ve uyuşmazlık', 'p' => [
                        'Talep ve şikayetlerinizi yukarıdaki iletişim kanallarına iletebilirsiniz. Uyuşmazlıklarda, ilgili parasal sınırlar dahilinde Tüketici Hakem Heyetleri ve Tüketici Mahkemeleri yetkilidir.',
                    ]],
                ],
            ],

            'teslimat' => [
                'title' => 'Teslimat Koşulları',
                'blocks' => [
                    ['h' => 'Hizmet bölgesi', 'p' => [
                        "Şu an hizmet verilen bölgeler: {$areas}. Bölge dışı talepler karşılanamayabilir.",
                    ]],
                    ['h' => 'Süre', 'p' => [
                        "Teslimat, {$hours} saatleri içinde; ürün bulunurluğu, yoğunluk ve mesafeye göre makul sürede yapılır. Tahmini süre sipariş sırasında paylaşılır.",
                    ]],
                    ['h' => 'Teslimat bedeli', 'p' => [
                        'Teslimat/hizmet bedeli bölgeye göre değişir ve sipariş özetinde ayrıca gösterilir; onayınız olmadan işlem tamamlanmaz.',
                    ]],
                    ['h' => 'Teslim edilemeyen durumlar', 'p' => [
                        'Adreste ulaşılamama, hatalı adres veya ürünün temin edilememesi hâlinde sizinle iletişime geçilir; alınmamış tutar için provizyon çözülür.',
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
                        'Gerçek fiş tahmini tutarın altında kalırsa fark otomatik olarak çözülür/iade edilir. İadeler ödeme yönteminize yapılır.',
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

            'kvkk' => [
                'title' => 'KVKK Aydınlatma Metni',
                'blocks' => [
                    ['h' => 'Veri sorumlusu', 'p' => [
                        "{$legalName} ({$name}). Adres: {$addr}. E-posta: {$email}.",
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
                        'Veriler; hizmetin ifası için kurye, ödeme kuruluşu (PayTR) ve yasal olarak yetkili mercilerle sınırlı olarak paylaşılabilir.',
                    ]],
                    ['h' => 'Haklarınız', 'p' => [
                        "KVKK m. 11 kapsamındaki haklarınızı kullanmak için {$email} üzerinden bize ulaşabilirsiniz.",
                    ]],
                ],
            ],

            'gizlilik' => [
                'title' => 'Gizlilik ve Çerez Politikası',
                'blocks' => [
                    ['h' => 'Hangi verileri topluyoruz', 'p' => [
                        'Yalnızca hizmeti sunmak için gerekli verileri toplarız: hesap, sipariş, teslimat ve ödeme işlem bilgileri.',
                    ]],
                    ['h' => 'Saklama ve güvenlik', 'p' => [
                        'Veriler yalnızca gerekli süre boyunca saklanır ve makul teknik/idari tedbirlerle korunur. Fiş görselleri sınırlı erişimle tutulur.',
                        'Ödeme sırasında kart bilgileriniz ödeme kuruluşunda işlenir; sistemimizde saklanmaz.',
                    ]],
                    ['h' => 'Çerezler', 'p' => [
                        'Oturum ve temel işlevsellik için zorunlu çerezler kullanılır. İsteğe bağlı/analitik çerez kullanılması hâlinde ayrıca bilgilendirme yapılır.',
                    ]],
                ],
            ],

            'iletisim' => [
                'title' => 'İletişim ve İşletme Bilgileri',
                'blocks' => [
                    ['h' => 'İşletme', 'p' => array_values(array_filter([
                        $legalName,
                        config('company.type') ? 'Tür: '.config('company.type') : null,
                        "Vergi dairesi / no: {$taxOffice} / {$taxNo}",
                        config('company.mersis') ? 'MERSİS: '.config('company.mersis') : null,
                        config('company.nace') ? 'Faaliyet/NACE: '.config('company.nace') : null,
                        "Adres: {$addr}",
                    ]))],
                    ['h' => 'İletişim', 'p' => array_values(array_filter([
                        "E-posta: {$email}",
                        "Telefon: {$phone}",
                        config('company.kep') ? 'KEP: '.config('company.kep') : null,
                        'Web: '.$this->co('website', 'getirenakyaka.com'),
                        config('company.etbis')
                            ? 'ETBİS: '.config('company.etbis')
                            : 'ETBİS kaydı, site yayına girdiğinde tamamlanıp burada belirtilecektir.',
                    ]))],
                ],
            ],
        ];
    }
}

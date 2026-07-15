<?php

/*
|------------------------------------------------------------------------------
| İşletme / şirket kimlik bilgileri — TEK KAYNAK
|------------------------------------------------------------------------------
|
| Hukuki sayfalar, iletişim sayfası, footer ve e-postalar bu değerleri kullanır.
| Değerler .env'den okunur; hassas veriler (vergi no, adres) repoya GİRMEZ, gerçek
| .env üretimde gitignore'ludur. Bir kez doldurulur, her yere yansır.
|
| ETBİS: e-ticaret sitesi yayına girince alınır; kayıt no gelince COMPANY_ETBIS'e yaz.
|
*/

return [

    'name' => env('COMPANY_NAME', 'Getiren Akyaka'),        // marka adı
    'legal_name' => env('COMPANY_LEGAL_NAME'),              // işletme/ticari unvan (şahıs: ad soyad veya ticari ad)
    'owner' => env('COMPANY_OWNER'),                        // işletme sahibi (şahıs işletmesi)
    'type' => env('COMPANY_TYPE', 'şahıs işletmesi'),      // şahıs işletmesi / limited şirket vb.

    'tax_office' => env('COMPANY_TAX_OFFICE'),              // vergi dairesi
    'tax_no' => env('COMPANY_TAX_NO'),                      // vergi no / TCKN
    'mersis' => env('COMPANY_MERSIS'),                      // MERSİS no (varsa)
    'etbis' => env('COMPANY_ETBIS'),                        // ETBİS kayıt (yayına girince)
    'nace' => env('COMPANY_NACE'),                          // faaliyet / NACE kodu

    'address' => env('COMPANY_ADDRESS'),                    // açık iş yeri adresi
    'phone' => env('COMPANY_PHONE'),
    'email' => env('COMPANY_EMAIL'),
    'kep' => env('COMPANY_KEP'),                            // KEP adresi (varsa)
    'website' => env('COMPANY_WEBSITE', 'getirenakyaka.com'),
    'hours' => env('COMPANY_HOURS', 'Her gün 09:00–21:00'),

    'service_areas' => env('COMPANY_SERVICE_AREAS', 'Akyaka, Gökova, Akçapınar (Muğla)'),

    // Hukuki metinler avukat tarafından kesinleşince false yapılır → "TASLAK" bandı kalkar
    'legal_draft' => (bool) env('LEGAL_DRAFT', true),

];

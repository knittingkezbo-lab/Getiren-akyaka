<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Testler asla geliştirme/üretim şemasına bağlanmamalı.
 *
 * RefreshDatabase her koşuda migrate:fresh çalıştırır — yani bağlandığı şemadaki
 * TÜM TABLOLARI DÜŞÜRÜR. Yapılandırma yanlışlıkla `getiren`e dönerse tek bir
 * `artisan test` gerçek veriyi siler. Bu test o kapıyı bekler.
 *
 * DİKKAT: Bu sınıf bilerek RefreshDatabase KULLANMAZ; kullansaydı, doğrulamak
 * istediği hasarı doğrulamadan önce kendisi yapardı.
 */
class TestDatabaseSafetyTest extends TestCase
{
    public function test_tests_never_point_at_a_real_schema(): void
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();
        $database = $connection->getDatabaseName();

        if ($driver === 'sqlite') {
            $this->assertSame(':memory:', $database, 'sqlite testleri bellek dışında bir dosyaya yazıyor!');

            return;
        }

        $this->assertSame('mysql', $driver, "Beklenmeyen test sürücüsü: {$driver}");
        $this->assertSame(
            'getiren_test',
            $database,
            "Testler '{$database}' şemasına bağlı! RefreshDatabase bu şemayı silerdi. ".
            'phpunit.mysql.xml içindeki force="true" DB_DATABASE ayarını kontrol et.'
        );
    }
}

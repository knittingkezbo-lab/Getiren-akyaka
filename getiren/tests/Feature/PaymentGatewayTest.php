<?php

namespace Tests\Feature;

use App\Enums\AuthorizationStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Zone;
use App\Payments\PaymentException;
use App\Payments\PaymentGateway;
use Database\Seeders\ZoneSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentGatewayTest extends TestCase
{
    use RefreshDatabase;

    private PaymentGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ZoneSeeder::class);
        $this->gateway = app(PaymentGateway::class);
    }

    private function order(): Order
    {
        return $this->makeCustomer()->ordersAsCustomer()->create([
            'code' => 'T-1',
            'zone_id' => Zone::where('key', 'akyaka')->value('id'),
            'raw_text' => 'ekmek',
            'items_total' => 100,
            'safety_buffer' => 0,
            'service_fee' => 0,
            'reserved_amount' => 100,
            'status' => OrderStatus::Reserved,
        ]);
    }

    public function test_authorize_opens_an_authorization_without_capturing(): void
    {
        $order = $this->order();

        $auth = $this->gateway->authorize($order, 500, 'Sipariş provizyonu');

        $this->assertEquals(AuthorizationStatus::Authorized, $auth->status);
        $this->assertEquals(500.0, (float) $auth->amount);
        $this->assertNull($auth->captured_amount);
        $this->assertNotNull($auth->authorized_at);
        $this->assertEquals(0.0, $auth->releasedAmount()); // henüz kapanmadı
    }

    public function test_partial_capture_releases_the_remainder(): void
    {
        $auth = $this->gateway->authorize($this->order(), 500);

        $this->gateway->capture($auth, 380);

        $this->assertEquals(AuthorizationStatus::Captured, $auth->status);
        $this->assertEquals(380.0, (float) $auth->captured_amount);
        $this->assertEquals(120.0, $auth->releasedAmount()); // "fazlasını iade et"
    }

    public function test_void_releases_the_whole_authorization(): void
    {
        $auth = $this->gateway->authorize($this->order(), 500);

        $this->gateway->void($auth);

        $this->assertEquals(AuthorizationStatus::Voided, $auth->status);
        $this->assertEquals(0.0, (float) $auth->captured_amount);
        $this->assertEquals(500.0, $auth->releasedAmount());
    }

    public function test_capture_cannot_exceed_the_authorized_amount(): void
    {
        $auth = $this->gateway->authorize($this->order(), 500);

        $this->expectException(PaymentException::class);
        $this->gateway->capture($auth, 500.01);
    }

    /** Kapanmış provizyona ikinci kez dokunulamaz — çift tahsilin kök çözümü. */
    public function test_captured_authorization_cannot_be_captured_again(): void
    {
        $auth = $this->gateway->authorize($this->order(), 500);
        $this->gateway->capture($auth, 300);

        $this->expectException(PaymentException::class);
        $this->gateway->capture($auth, 200);
    }

    public function test_voided_authorization_cannot_be_captured(): void
    {
        $auth = $this->gateway->authorize($this->order(), 500);
        $this->gateway->void($auth);

        $this->expectException(PaymentException::class);
        $this->gateway->capture($auth, 100);
    }

    public function test_captured_authorization_cannot_be_voided(): void
    {
        $auth = $this->gateway->authorize($this->order(), 500);
        $this->gateway->capture($auth, 500);

        $this->expectException(PaymentException::class);
        $this->gateway->void($auth);
    }

    public function test_zero_amount_is_rejected(): void
    {
        $this->expectException(PaymentException::class);
        $this->gateway->authorize($this->order(), 0);
    }
}

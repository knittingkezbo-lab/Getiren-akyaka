<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Girişte deneme sınırı yoktu: bir saldırgan aynı hesaba sınırsız şifre deneyebilirdi.
 *
 * Sınır e-posta+IP çiftine bağlı. Sadece IP'ye bağlamak iki yönden yanlış olurdu:
 * Akyaka'da ortak bir bağlantıdan giren masum kullanıcılar birbirini kilitler, buna
 * karşılık IP değiştiren bir saldırgan sınırı hiç görmezdi.
 */
class LoginThrottleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('login|kurban@ornek.com|127.0.0.1');
    }

    private function attempt(string $email, string $password = 'yanlis-sifre')
    {
        return $this->post('/login', ['email' => $email, 'password' => $password]);
    }

    public function test_repeated_failed_logins_are_throttled(): void
    {
        User::factory()->create(['email' => 'kurban@ornek.com', 'role' => UserRole::Customer]);

        for ($i = 0; $i < 5; $i++) {
            $this->attempt('kurban@ornek.com')->assertSessionHasErrors('email');
            $this->flushSession();
        }

        // 6. deneme artık şifreyi bile denemeden reddedilmeli
        $response = $this->attempt('kurban@ornek.com');
        $response->assertSessionHasErrors('email');

        $message = session('errors')->first('email');
        $this->assertStringContainsString('deneme', mb_strtolower($message), "Sınır mesajı beklenirken: {$message}");
    }

    /** Sınır hesabı kilitlememeli: doğru şifreyle giren başka kullanıcı etkilenmemeli. */
    public function test_throttle_does_not_block_a_different_account(): void
    {
        User::factory()->create(['email' => 'kurban@ornek.com', 'role' => UserRole::Customer]);
        $other = User::factory()->create(['email' => 'baskasi@ornek.com', 'role' => UserRole::Customer]);

        for ($i = 0; $i < 6; $i++) {
            $this->attempt('kurban@ornek.com');
            $this->flushSession();
        }

        $this->post('/login', ['email' => $other->email, 'password' => 'password'])
            ->assertRedirect();

        $this->assertAuthenticatedAs($other);
    }

    /** Başarılı giriş sayacı sıfırlamalı — yoksa normal kullanıcı yavaş yavaş kilitlenir. */
    public function test_successful_login_clears_the_counter(): void
    {
        $user = User::factory()->create(['email' => 'kurban@ornek.com', 'role' => UserRole::Customer]);

        for ($i = 0; $i < 3; $i++) {
            $this->attempt('kurban@ornek.com');
            $this->flushSession();
        }

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect();
        $this->assertAuthenticatedAs($user);

        $this->assertSame(0, RateLimiter::attempts('login|kurban@ornek.com|127.0.0.1'));
    }
}

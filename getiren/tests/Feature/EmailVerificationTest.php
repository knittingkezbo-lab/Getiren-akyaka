<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function registerPayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Yeni',
            'last_name' => 'Kullanıcı',
            'email' => 'yeni@getiren.test',
            'phone' => '555 000 00 00',
            'role' => 'customer',
            'password' => 'sifre-1234',
            'password_confirmation' => 'sifre-1234',
            'terms' => true,
        ], $overrides);
    }

    public function test_registration_auto_verifies_when_feature_off(): void
    {
        config(['features.email_verification' => false]);

        $this->post('/register', $this->registerPayload())
            ->assertRedirect(route('customer.dashboard'));

        $user = User::where('email', 'yeni@getiren.test')->firstOrFail();
        $this->assertTrue($user->hasVerifiedEmail());
    }

    public function test_registration_requires_verification_when_feature_on(): void
    {
        config(['features.email_verification' => true]);
        Notification::fake();

        $this->post('/register', $this->registerPayload())
            ->assertRedirect(route('verification.notice'));

        $user = User::where('email', 'yeni@getiren.test')->firstOrFail();
        $this->assertFalse($user->hasVerifiedEmail());
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_unverified_user_is_redirected_to_notice(): void
    {
        config(['features.email_verification' => true]);
        $user = User::factory()->unverified()->create(['role' => UserRole::Customer]);
        $user->wallet()->create(['balance' => 0, 'reserved' => 0, 'currency' => 'TRY']);

        $this->actingAs($user)->get('/musteri')->assertRedirect(route('verification.notice'));
    }

    public function test_signed_link_verifies_email_and_grants_access(): void
    {
        config(['features.email_verification' => true]);
        $user = User::factory()->unverified()->create(['role' => UserRole::Customer]);

        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $this->actingAs($user)->get($url)->assertRedirect(route('customer.dashboard'));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }
}

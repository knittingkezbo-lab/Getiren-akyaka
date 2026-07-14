<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/musteri')->assertRedirect('/login');
    }

    public function test_customer_cannot_access_courier_area(): void
    {
        $this->actingAs($this->makeCustomer())->get('/kurye')->assertForbidden();
    }

    public function test_courier_cannot_access_admin_area(): void
    {
        $this->actingAs($this->makeCourier())->get('/yonetici')->assertForbidden();
    }

    public function test_login_redirects_user_to_role_home(): void
    {
        $courier = $this->makeCourier(); // factory şifresi 'password'

        $this->post('/login', ['email' => $courier->email, 'password' => 'password'])
            ->assertRedirect(route('courier.dashboard'));

        $this->assertAuthenticatedAs($courier);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $customer = $this->makeCustomer();

        $this->post('/login', ['email' => $customer->email, 'password' => 'yanlis'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_registration_creates_customer(): void
    {
        $this->post('/register', [
            'first_name' => 'Test',
            'last_name' => 'Kullanıcı',
            'email' => 'yeni@ornek.com',
            'phone' => '5551234567',
            'role' => 'customer',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'terms' => true,
        ])->assertRedirect(route('customer.dashboard'));

        $user = User::where('email', 'yeni@ornek.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals(UserRole::Customer, $user->role);
        $this->assertAuthenticatedAs($user);
    }

    public function test_registration_requires_accepted_terms(): void
    {
        $this->post('/register', [
            'first_name' => 'Test',
            'last_name' => 'Kullanıcı',
            'email' => 'redol@ornek.com',
            'role' => 'customer',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'terms' => false,
        ])->assertSessionHasErrors('terms');

        $this->assertDatabaseMissing('users', ['email' => 'redol@ornek.com']);
    }
}

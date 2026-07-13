<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourierApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function payload(string $role, string $email): array
    {
        return [
            'first_name' => 'Test',
            'last_name' => 'Kişi',
            'email' => $email,
            'phone' => '555 000 00 00',
            'role' => $role,
            'password' => 'sifre-1234',
            'password_confirmation' => 'sifre-1234',
            'terms' => true,
        ];
    }

    public function test_courier_registration_starts_pending(): void
    {
        config(['features.email_verification' => false]);

        $this->post('/register', $this->payload('courier', 'kurye@example.com'))
            ->assertRedirect(route('courier.pending'));

        $courier = User::where('email', 'kurye@example.com')->firstOrFail();
        $this->assertFalse($courier->isApproved());
    }

    public function test_customer_registration_is_auto_approved(): void
    {
        config(['features.email_verification' => false]);

        $this->post('/register', $this->payload('customer', 'musteri@example.com'))
            ->assertRedirect(route('customer.dashboard'));

        $this->assertTrue(User::where('email', 'musteri@example.com')->firstOrFail()->isApproved());
    }

    public function test_unapproved_courier_is_gated_from_courier_area(): void
    {
        $courier = User::factory()->unapproved()->create(['role' => UserRole::Courier]);

        $this->actingAs($courier)->get('/kurye')->assertRedirect(route('courier.pending'));
    }

    public function test_admin_approves_courier_and_grants_access(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $courier = User::factory()->unapproved()->create(['role' => UserRole::Courier]);

        $this->actingAs($admin)->post("/yonetici/kuryeler/{$courier->id}/onayla")->assertRedirect();

        $this->assertTrue($courier->fresh()->isApproved());
        $this->actingAs($courier->fresh())->get('/kurye')->assertOk();
    }

    public function test_admin_rejects_pending_courier(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $courier = User::factory()->unapproved()->create(['role' => UserRole::Courier]);

        $this->actingAs($admin)->post("/yonetici/kuryeler/{$courier->id}/reddet")->assertRedirect();

        $this->assertDatabaseMissing('users', ['id' => $courier->id]);
    }
}

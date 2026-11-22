<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DashboardAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_read_or_create_purchases(): void
    {
        $this->get('/api/purchases')->assertUnauthorized();
        $this->getJson('/api/dashboard')->assertUnauthorized();
        $this->getJson('/api/purchases')->assertUnauthorized();
        $this->postJson('/api/purchases', [])->assertUnauthorized();
        $this->getJson('/api/me')->assertUnauthorized();
    }

    public function test_session_login_and_logout(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'password' => 'secure-password-123']);
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong-password'])->assertUnprocessable();
        $this->postJson('/api/login', ['email' => strtoupper($user->email), 'password' => 'secure-password-123'])
            ->assertOk()->assertJsonPath('user.role', 'admin')->assertHeader('Cache-Control', 'no-store, private');
        $this->getJson('/api/me')->assertOk()->assertJsonPath('user.name', $user->name);
        $this->postJson('/api/logout')->assertNoContent();
        $this->getJson('/api/me')->assertUnauthorized();
    }

    public function test_login_limits_repeated_failed_attempts(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/login', ['email' => 'unknown@example.test', 'password' => 'incorrect'])->assertUnprocessable();
        }

        $this->postJson('/api/login', ['email' => 'unknown@example.test', 'password' => 'incorrect'])->assertTooManyRequests();
    }

    public function test_login_rejects_passwords_longer_than_bcrypt_limit(): void
    {
        $password = str_repeat('a', 72);
        $user = User::factory()->create(['role' => 'admin', 'password' => $password]);

        $this->postJson('/api/login', ['email' => $user->email, 'password' => $password.'extra'])->assertUnprocessable();
        $this->postJson('/api/login', ['email' => $user->email, 'password' => $password])->assertOk();
    }

    public function test_user_creation_hashes_password_and_sets_role(): void
    {
        putenv('DASHBOARD_TEST_PASSWORD=example-only-password-123');
        try {
            $this->artisan('dashboard:create-user', [
                '--name' => 'Leitor', '--email' => 'reader@example.test',
                '--role' => 'reader', '--password-env' => 'DASHBOARD_TEST_PASSWORD',
            ])->assertSuccessful();
        } finally {
            putenv('DASHBOARD_TEST_PASSWORD');
        }

        $user = User::where('email', 'reader@example.test')->firstOrFail();
        $this->assertSame('reader', $user->role);
        $this->assertNotSame('example-only-password-123', $user->password);
        $this->assertTrue(Hash::check('example-only-password-123', $user->password));
    }

    public function test_reader_cannot_create_or_see_or_search_personal_data(): void
    {
        app(PurchaseService::class)->store([
            'reference' => 'PED-PRIVATE', 'amount' => '10.00', 'discount' => '0.00',
            'purchased_at' => '2026-01-15T12:00:00Z',
            'customer' => ['name' => 'Ana Silva', 'email' => 'private@example.test', 'cpf' => '12345678901'],
            'products' => [['name' => 'Produto', 'quantity' => 1, 'unit_price' => '10.00']],
            'notes' => 'Dados de entrega',
        ]);
        $this->actingAs(User::factory()->create(['role' => 'reader']));

        $this->getJson('/api/dashboard')->assertOk();
        $this->getJson('/api/purchases?customer=ana%20silva')->assertOk()->assertJsonPath('total', 1)
            ->assertJsonMissingPath('data.0.customer_email')->assertJsonMissingPath('data.0.customer_cpf')
            ->assertJsonMissingPath('data.0.notes')->assertHeader('Cache-Control', 'no-store, private');
        $this->getJson('/api/purchases?customer=private%40example.test')->assertOk()->assertJsonPath('total', 0);
        $this->getJson('/api/purchases?customer=123.456.789-01')->assertOk()->assertJsonPath('total', 0);
        $this->postJson('/api/purchases', [])->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->getJson('/api/purchases?customer=private%40example.test')->assertOk()
            ->assertJsonPath('data.0.customer_cpf', '12345678901')->assertJsonPath('data.0.notes', 'Dados de entrega');
    }
}

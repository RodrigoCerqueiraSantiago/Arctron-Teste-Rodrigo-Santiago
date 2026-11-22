<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PersonalDataMigrationTest extends TestCase
{
    public function test_existing_data_is_encrypted_and_can_be_restored(): void
    {
        $previousConnection = DB::getDefaultConnection();
        config(['database.connections.migration_test' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]]);
        DB::setDefaultConnection('migration_test');

        try {
            $original = require database_path('migrations/2026_01_01_000000_create_purchase_tables.php');
            $original->up();
            $customerId = DB::table('customers')->insertGetId([
                'cpf' => '12345678901', 'name' => 'Cliente', 'email' => 'cliente@example.test',
            ]);
            DB::table('purchases')->insert([
                'reference' => 'LEGADO-1', 'customer_id' => $customerId,
                'customer_name' => 'Cliente', 'customer_email' => 'cliente@example.test',
                'customer_cpf' => '12345678901', 'amount_cents' => 1000, 'discount_cents' => 0,
                'purchased_at' => '2026-01-01 12:00:00', 'notes' => 'Nota fictícia',
            ]);
            $migration = require database_path('migrations/2026_11_22_000001_encrypt_personal_data.php');
            $migration->up();

            $customer = DB::table('customers')->first();
            $purchase = DB::table('purchases')->first();
            $this->assertNotSame('12345678901', $customer->cpf);
            $this->assertSame('12345678901', Crypt::decryptString($customer->cpf));
            $this->assertSame('cliente@example.test', Crypt::decryptString($purchase->customer_email));
            $this->assertSame('Nota fictícia', Crypt::decryptString($purchase->notes));

            $migration->down();
            $this->assertSame('12345678901', DB::table('customers')->first()->cpf);
            $this->assertSame('cliente@example.test', DB::table('purchases')->first()->customer_email);
            $this->assertSame('Nota fictícia', DB::table('purchases')->first()->notes);
        } finally {
            DB::setDefaultConnection($previousConnection);
            DB::purge('migration_test');
        }
    }
}

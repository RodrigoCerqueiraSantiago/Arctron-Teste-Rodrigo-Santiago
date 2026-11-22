<?php

use App\Support\CustomerCpfIndex;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['cpf']);
            $table->text('cpf')->change();
            $table->text('email')->change();
            $table->string('cpf_hash', 64)->nullable()->unique();
        });
        Schema::table('purchases', function (Blueprint $table) {
            $table->text('customer_cpf')->change();
            $table->text('customer_email')->change();
        });

        DB::table('customers')->orderBy('id')->chunkById(100, function ($customers) {
            foreach ($customers as $customer) {
                DB::table('customers')->where('id', $customer->id)->update([
                    'cpf_hash' => CustomerCpfIndex::hash($customer->cpf),
                    'cpf' => Crypt::encryptString($customer->cpf),
                    'email' => Crypt::encryptString($customer->email),
                ]);
            }
        });
        DB::table('purchases')->orderBy('id')->chunkById(100, function ($purchases) {
            foreach ($purchases as $purchase) {
                DB::table('purchases')->where('id', $purchase->id)->update([
                    'customer_cpf' => Crypt::encryptString($purchase->customer_cpf),
                    'customer_email' => Crypt::encryptString($purchase->customer_email),
                    'notes' => $purchase->notes === null ? null : Crypt::encryptString($purchase->notes),
                ]);
            }
        });
        Schema::table('customers', function (Blueprint $table) {
            $table->string('cpf_hash', 64)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        DB::table('customers')->orderBy('id')->chunkById(100, function ($customers) {
            foreach ($customers as $customer) {
                DB::table('customers')->where('id', $customer->id)->update([
                    'cpf' => Crypt::decryptString($customer->cpf),
                    'email' => Crypt::decryptString($customer->email),
                ]);
            }
        });
        DB::table('purchases')->orderBy('id')->chunkById(100, function ($purchases) {
            foreach ($purchases as $purchase) {
                DB::table('purchases')->where('id', $purchase->id)->update([
                    'customer_cpf' => Crypt::decryptString($purchase->customer_cpf),
                    'customer_email' => Crypt::decryptString($purchase->customer_email),
                    'notes' => $purchase->notes === null ? null : Crypt::decryptString($purchase->notes),
                ]);
            }
        });
        Schema::table('purchases', function (Blueprint $table) {
            $table->string('customer_cpf', 11)->change();
            $table->string('customer_email')->change();
        });
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['cpf_hash']);
            $table->dropColumn('cpf_hash');
            $table->string('cpf', 11)->unique()->change();
            $table->string('email')->change();
        });
    }
};

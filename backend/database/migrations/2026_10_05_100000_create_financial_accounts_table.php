<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Financial accounts (spec 0189): one table with a `type` discriminator
 * (bank_account | card | cash) and per-type nullable columns. The card CVV and
 * PIN are deliberately NOT columns (PCI DSS); `card_number` holds the
 * encrypted number (model cast), `card_last_four` the clear last digits used
 * for the masked display. A card's `linked_account_id` restricts the delete of
 * the bank account it points at; every other FK is nullOnDelete.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->index();
            $table->string('name');
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('iban', 34)->nullable()->unique();
            $table->string('account_number', 50)->nullable();
            $table->string('address_line')->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->foreignId('state_id')->nullable()->constrained('states')->nullOnDelete();
            $table->foreignId('province_id')->nullable()->constrained('provinces')->nullOnDelete();
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->string('card_type', 20)->nullable();
            $table->string('card_circuit', 20)->nullable();
            $table->foreignId('linked_account_id')->nullable()->constrained('financial_accounts')->restrictOnDelete();
            $table->string('card_holder')->nullable();
            $table->text('card_number')->nullable();
            $table->char('card_last_four', 4)->nullable();
            $table->string('card_expiry', 7)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_accounts');
    }
};

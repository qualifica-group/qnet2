<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Manual treasury movements (spec 0360): income, expense, transfer, adjustment.
 * Exactly one of the three sub-account columns is set (enforced by the request).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treasury_movements', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->index();
            $table->string('direction', 3)->nullable();
            $table->date('movement_date');
            $table->foreignId('financial_account_id')->constrained('financial_accounts')->restrictOnDelete();
            $table->foreignId('counterpart_account_id')->nullable()->constrained('financial_accounts')->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('description', 255);
            $table->foreignId('cost_type_id')->nullable()->constrained('cost_types')->restrictOnDelete();
            $table->foreignId('revenue_type_id')->nullable()->constrained('revenue_types')->restrictOnDelete();
            $table->foreignId('treasury_type_id')->nullable()->constrained('treasury_types')->restrictOnDelete();
            $table->foreignId('registry_id')->nullable()->constrained('registries')->nullOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['financial_account_id', 'movement_date']);
            $table->index(['counterpart_account_id', 'movement_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treasury_movements');
    }
};

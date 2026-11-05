<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saved bank reconciliations (spec 0363): "bank balance at <date>" of an account
 * with the reconciled balance and the difference computed when it was saved.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financial_account_id')->constrained('financial_accounts')->restrictOnDelete();
            $table->date('statement_date');
            $table->decimal('bank_balance', 15, 2);
            $table->decimal('reconciled_balance', 15, 2);
            $table->decimal('difference', 15, 2);
            $table->foreignId('adjustment_movement_id')->nullable()->constrained('treasury_movements')->nullOnDelete();
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['financial_account_id', 'statement_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_reconciliations');
    }
};

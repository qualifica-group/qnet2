<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_line_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_line_id')->constrained('quote_lines')->cascadeOnDelete();
            $table->foreignId('commission_configuration_id')->nullable()
                ->constrained('commission_configurations')->restrictOnDelete();
            $table->string('recipient_role', 32);
            $table->nullableMorphs('recipient');
            $table->string('commission_type', 32);
            $table->decimal('value', 15, 4);
            $table->decimal('calculated_amount', 15, 2);
            $table->text('internal_note')->nullable();
            $table->string('origin', 32);
            $table->timestamps();

            $table->unique(['quote_line_id', 'recipient_role']);
            $table->index(['recipient_type', 'recipient_id', 'recipient_role'], 'quote_commission_recipient_role_index');
            $table->index(['recipient_role', 'calculated_amount'], 'quote_commission_role_amount_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_line_commissions');
    }
};

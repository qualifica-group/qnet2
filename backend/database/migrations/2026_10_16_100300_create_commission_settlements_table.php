<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agent commission settlement statement per (referent, company) (spec 0213,
 * D-1..D-5): snapshot of rates and bases plus the computed amounts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commission_settlement_run_id')->nullable()->constrained('commission_settlement_runs')->nullOnDelete();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('referent_id')->constrained('referents')->restrictOnDelete();
            $table->foreignId('agent_fiscal_profile_id')->constrained('agent_fiscal_profiles')->restrictOnDelete();
            $table->string('status', 16)->index('commission_settlements_status_index');
            $table->unsignedInteger('number')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->date('period_from');
            $table->date('period_to');
            $table->decimal('accruals_amount', 15, 2)->default(0);
            $table->decimal('adjustments_amount', 15, 2)->default(0);
            $table->decimal('taxable_amount', 15, 2)->default(0);
            $table->decimal('vat_rate', 5, 2)->nullable();
            $table->decimal('vat_amount', 15, 2)->default(0);
            $table->boolean('withholding_enabled')->default(false);
            $table->decimal('withholding_rate', 5, 2)->nullable();
            $table->decimal('withholding_base_percent', 5, 2)->nullable();
            $table->decimal('withholding_amount', 15, 2)->default(0);
            $table->boolean('enasarco_enabled')->default(false);
            $table->string('enasarco_mandate_type', 16)->nullable();
            $table->decimal('enasarco_base_amount', 15, 2)->default(0);
            $table->decimal('enasarco_agent_amount', 15, 2)->default(0);
            $table->decimal('enasarco_principal_amount', 15, 2)->default(0);
            $table->decimal('net_payable', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason', 500)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'year', 'number'], 'commission_settlements_number_unique');
            $table->index(['referent_id', 'company_id', 'status'], 'commission_settlements_referent_company_index');
            $table->index(['status', 'period_to'], 'commission_settlements_status_period_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_settlements');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Monthly personnel cost per person and company, imported from the labour
 * consultant's cost file (spec 0430). One period = one file: re-importing a
 * period replaces all its rows (D-2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->char('period', 7);
            $table->string('tax_code', 16);
            $table->decimal('salary_cost', 12, 2);
            $table->decimal('contribution_cost', 12, 2);
            $table->decimal('insurance_cost', 12, 2);
            $table->decimal('total_cost', 12, 2);
            $table->decimal('ordinary_hours', 7, 2);
            $table->date('hired_on')->nullable();
            $table->date('terminated_on')->nullable();
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'user_id', 'period']);
            $table->index(['period', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_costs');
    }
};

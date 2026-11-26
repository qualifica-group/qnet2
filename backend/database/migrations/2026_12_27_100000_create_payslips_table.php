<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payslips (spec 0431, D-2): the payroll detail of a `payslip` payable
 * document, one per company, person, month and payslip type; and the
 * supplier registry a person is paid through (D-4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_invoice_id')->unique()->constrained('purchase_invoices')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->char('period', 7);
            $table->string('payslip_type', 50);
            $table->string('employee_code', 20)->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'user_id', 'period', 'payslip_type']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('supplier_registry_id')->nullable()->constrained('registries')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_registry_id');
        });

        Schema::dropIfExists('payslips');
    }
};

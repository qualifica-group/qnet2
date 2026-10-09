<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Purchase requests, "RDA" (spec 0208): header and footer of a request for
 * purchase. The totals are computed server-side from the lines (D-12) and are
 * never written by the client; `status` is open until the last line reaches a
 * terminal state or the request is closed by hand (D-7).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->string('subject');
            $table->date('requested_at');
            $table->string('priority', 20);
            $table->foreignId('requester_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('function_manager_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('registries')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('registries')->nullOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('company_site_id')->constrained('company_sites')->restrictOnDelete();
            $table->foreignId('operational_site_id')->constrained('operational_sites')->restrictOnDelete();
            $table->foreignId('business_function_id')->constrained('business_functions')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->text('delivery_terms')->nullable();
            $table->text('procurement_plan')->nullable();
            $table->text('technical_requirements')->nullable();
            $table->text('special_conditions')->nullable();
            $table->decimal('taxable_total', 14, 2)->default(0);
            $table->decimal('vat_total', 14, 2)->default(0);
            $table->decimal('grand_total', 14, 2)->default(0);
            $table->string('status', 20)->default('open')->index();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->text('close_reason')->nullable();
            $table->timestamps();

            $table->index('requested_at');
            $table->index('priority');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_requests');
    }
};

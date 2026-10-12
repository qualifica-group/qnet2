<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Purchase orders, "ODA" (spec 0210): an order to a supplier. `number`/`year`
 * are assigned once on creation (D-11) and the totals are computed server-side
 * from the lines (D-12): none of them is ever written by the client.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('number');
            $table->unsignedSmallInteger('year');
            $table->foreignId('supplier_id')->constrained('registries')->restrictOnDelete();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('company_site_id')->constrained('company_sites')->restrictOnDelete();
            $table->foreignId('operational_site_id')->nullable()->constrained('operational_sites')->nullOnDelete();
            $table->date('order_date');
            $table->string('description');
            $table->text('general_notes')->nullable();
            $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->nullOnDelete();
            $table->boolean('is_internal_user')->default(false);
            $table->foreignId('customer_id')->nullable()->constrained('registries')->nullOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $table->foreignId('quote_id')->nullable()->constrained('quotes')->nullOnDelete();
            $table->foreignId('opportunity_id')->nullable()->constrained('opportunities')->nullOnDelete();
            $table->string('sector_notes', 500)->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->decimal('taxable_total', 14, 2)->default(0);
            $table->decimal('vat_total', 14, 2)->default(0);
            $table->decimal('grand_total', 14, 2)->default(0);
            $table->uuid('copy_group_id')->nullable()->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'year', 'number']);
            $table->index('order_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};

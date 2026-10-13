<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0246: deleting a cost type must not silently declassify the purchase
 * invoices that use it. CostTypeService guards the delete; the FK is the last
 * line of defence. The column stays nullable (an invoice may have no type).
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->rewire(restrict: true);
    }

    public function down(): void
    {
        $this->rewire(restrict: false);
    }

    private function rewire(bool $restrict): void
    {
        Schema::table('purchase_invoices', function (Blueprint $table) use ($restrict): void {
            $table->dropForeign(['cost_type_id']);

            $fk = $table->foreign('cost_type_id')->references('id')->on('cost_types');
            $restrict ? $fk->restrictOnDelete() : $fk->nullOnDelete();
        });
    }
};

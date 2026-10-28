<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Work order workflow configurator (spec 0322, D-6): a named, activatable
 * set of criteria + working-state statuses applied to a WorkOrder, the twin
 * of `quote_workflows`. Unlike it, no `criteria_signature` column: the
 * "same criteria combination" rule is enforced by the service layer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_order_workflows', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_workflows');
    }
};

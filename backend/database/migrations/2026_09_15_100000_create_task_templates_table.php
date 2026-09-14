<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TaskTemplate entity (spec 0124, D-1): the "Modello di Task" header — a
 * lean, full-CRUD configurator (name/description/is_active) an admin builds
 * ahead of time, so `App\Services\WorkOrders\WorkOrderTaskGenerator` can
 * later stamp a whole set of Tasks onto a Commessa in one shot (D-7).
 *
 * `is_active` gates BOTH the for-select (only active models are pickable,
 * AC-009) and generation (D-8: 422 on an inactive `task_template_id`) —
 * deactivating a model that has already generated Commesse (D-5) is the
 * intended way to retire it without losing the 409 delete guard's audit
 * trail.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_templates');
    }
};

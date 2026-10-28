<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One matching criterion of a WorkOrderWorkflow (spec 0322, D-8): `field` is
 * an allow-list key (App\Support\WorkOrderWorkflows\
 * WorkOrderCriterionFieldRegistry), `value_id` the chosen value's id. A
 * workflow matches a work order only when EVERY criterion matches (AND), so
 * each `field` appears at most once per workflow — enforced by the composite
 * unique index, not just by validation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_order_workflow_criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_workflow_id')->constrained('work_order_workflows')->cascadeOnDelete();
            $table->string('field', 191);
            $table->unsignedBigInteger('value_id');
            $table->timestamps();

            $table->unique(['work_order_workflow_id', 'field'], 'wow_criteria_workflow_field_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_workflow_criteria');
    }
};

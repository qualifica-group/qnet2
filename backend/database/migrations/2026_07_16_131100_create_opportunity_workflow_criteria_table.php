<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One matching criterion of an OpportunityWorkflow (spec 0047): `field` is an
 * allow-list key (App\Support\OpportunityWorkflows\CriterionFieldRegistry —
 * state_id/source_id/business_function_id/product_category_id), `value_id`
 * the chosen value's id. A workflow matches an Opportunity only when EVERY
 * one of its criteria matches (AND, AC-013); the resolver picks the workflow
 * with the most matching criteria (AC-011), so each `field` may appear at
 * most once per workflow (BR, AC-008) — enforced by the composite unique
 * index below, not just FormRequest validation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opportunity_workflow_criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_workflow_id')->constrained('opportunity_workflows')->cascadeOnDelete();
            // 191, not 64: a custom-field criterion's key is namespaced
            // (App\CustomFields\CustomFieldProvider::KEY_PREFIX `custom.` + the
            // definition's own key, itself validated max:64), so it can reach
            // 71 chars.
            $table->string('field', 191);
            $table->unsignedBigInteger('value_id');
            $table->timestamps();

            $table->unique(['opportunity_workflow_id', 'field'], 'ow_criteria_workflow_field_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunity_workflow_criteria');
    }
};

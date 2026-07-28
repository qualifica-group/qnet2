<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Product category tree (spec 0017): unlimited-depth parent/child hierarchy.
 * `parent_id` restricts on delete (restrictOnDelete) — a category with
 * children must be reparented/deleted first, mirroring the service-level
 * restrictive-delete guard (ProductCategoryService::delete) that also checks
 * for associated products.
 *
 * The attribute-inheritance barrier is PER USAGE CONTEXT (spec 0061 follow-up):
 * a category can keep inheriting its ancestors' Opportunity attributes while
 * cutting itself off from their Product ones, and vice versa. Both flags default
 * to true (always inherit); a false one makes the category an inheritance ROOT
 * for that context, and — because CategoryHierarchy walks the chain node by node
 * — that barrier also cuts its descendants off from everything above it.
 * AttributeContext::inheritanceColumn() is the only place that knows these
 * column names.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();

            // The external system's id for a row migrated from it (spec 0013):
            // also resolves the SELF-referential `parent_id` remap. NULL for
            // native qnet rows, unique among migrated ones.
            $table->unsignedBigInteger('old_id')->nullable()->unique();

            $table->string('name', 191);
            $table->foreignId('parent_id')->nullable()->constrained('product_categories')->restrictOnDelete();
            $table->boolean('inherits_product_attributes')->default(true);
            $table->boolean('inherits_opportunity_attributes')->default(true);

            // The category's OWN business function assignment (spec 0023) —
            // never the inherited one, which CategoryHierarchy resolves at read
            // time.
            $table->foreignId('business_function_id')->nullable()->constrained()->nullOnDelete();

            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['parent_id', 'name']);
            $table->index('business_function_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_categories');
    }
};

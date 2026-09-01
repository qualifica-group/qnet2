<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * User directive 2026-09-01: the "Regione" field is removed from the
 * Opportunity and from the Product. It was already invisible on the
 * opportunity form (directive 2026-08-05) and is now gone from the data
 * contract entirely — model, FormRequest, Resource, DTO, grid column and
 * field-permission definition all drop with it.
 *
 * Two orphan sets are cleaned in the same pass:
 * - `quote_workflow_criteria` rows on the `state_id` criterion, which
 *   resolved by inheritance from the parent Opportunity
 *   (App\Support\QuoteWorkflows\QuoteCriterionFieldRegistry). A workflow that
 *   relied on it simply becomes less specific.
 * - `role_field_permissions` rows on `products.state_id`, whose
 *   FieldDefinition no longer exists (App\Authorization\ProductsAuthorization).
 *
 * DESTRUCTIVE: the per-record Regione and the criteria above are not
 * recoverable. `down()` restores the STRUCTURE only, the same precedent as
 * `2026_08_05_120400_drop_opportunity_workflow_status_id_from_opportunities_table`.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('quote_workflow_criteria')->where('field', 'state_id')->delete();

        DB::table('role_field_permissions')
            ->where('resource', 'products')
            ->where('field', 'state_id')
            ->delete();

        Schema::table('opportunities', function (Blueprint $table): void {
            $table->dropForeign(['state_id']);
            $table->dropColumn('state_id');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropForeign(['state_id']);
            $table->dropColumn('state_id');
        });
    }

    public function down(): void
    {
        Schema::table('opportunities', function (Blueprint $table): void {
            $table->foreignId('state_id')->nullable()->constrained('states')->nullOnDelete();
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->foreignId('state_id')->nullable()->constrained('states')->nullOnDelete();
        });
    }
};

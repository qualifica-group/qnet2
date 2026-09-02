<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0094 (D-2): full replacement, same discipline already applied to the
 * Opportunity (spec 0040 amendment rev.3) and to the Project (previous
 * migration). Every STANDALONE campaign that carries BOTH
 * `business_function_id` and `product_category_id` gets exactly one row in
 * `campaign_product_lines`; the two scalar columns are then dropped. A
 * campaign linked to a project has both columns NULL by design (BR-2) and
 * is naturally excluded by the `whereNotNull` filter below — it never gets a
 * row here, it keeps reading the project's own collection.
 *
 * `down()` restores the STRUCTURE at its original position (right after
 * `pipeline_status_id` / `business_function_id`, same nullable + nullOnDelete as
 * `2026_07_13_110200_create_campaigns_table.php`) and repopulates each
 * campaign from the FIRST row (ordered by id) of `campaign_product_lines`:
 * DESTRUCTIVE for any campaign that ended up with 2+ rows, the extra rows
 * are not recoverable into a single pair. Linked campaigns stay NULL, as
 * before.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->migrateCampaignsToProductLines();

        Schema::table('campaigns', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('business_function_id');
            $table->dropConstrainedForeignId('product_category_id');
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table): void {
            $table->foreignId('business_function_id')->nullable()
                ->after('pipeline_status_id')
                ->constrained('business_functions')
                ->nullOnDelete();

            $table->foreignId('product_category_id')->nullable()
                ->after('business_function_id')
                ->constrained('product_categories')
                ->nullOnDelete();
        });

        $this->backfillCampaignsFromFirstLine();
    }

    private function migrateCampaignsToProductLines(): void
    {
        $now = now();

        $rows = DB::table('campaigns')
            ->whereNotNull('business_function_id')
            ->whereNotNull('product_category_id')
            ->get(['id', 'business_function_id', 'product_category_id'])
            ->map(fn ($campaign) => [
                'campaign_id' => $campaign->id,
                'business_function_id' => $campaign->business_function_id,
                'product_category_id' => $campaign->product_category_id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

        if ($rows->isNotEmpty()) {
            DB::table('campaign_product_lines')->insert($rows->all());
        }
    }

    private function backfillCampaignsFromFirstLine(): void
    {
        DB::table('campaign_product_lines')
            ->orderBy('id')
            ->get(['campaign_id', 'business_function_id', 'product_category_id'])
            ->unique('campaign_id')
            ->each(function ($line): void {
                DB::table('campaigns')
                    ->where('id', $line->campaign_id)
                    ->update([
                        'business_function_id' => $line->business_function_id,
                        'product_category_id' => $line->product_category_id,
                    ]);
            });
    }
};

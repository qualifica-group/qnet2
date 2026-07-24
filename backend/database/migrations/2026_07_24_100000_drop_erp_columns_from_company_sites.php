<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * De-verticalization (point 1): drops the 9 client-specific ERP columns from
 * `company_sites` — the 4 `responsible_*` user FKs, the proforma/invoice
 * progressives and the 3 `quotation_*` references. They are re-provisioned as
 * company-sites custom fields (spec 0021, QualificaTemplateSeeder), the same
 * treatment already applied to the former "Altro" section.
 *
 * `up()` backfills every site's non-null column values into its
 * `custom_field_values` row BEFORE dropping the columns, so no data is lost —
 * `custom_field_values` is plain JSON storage, independent of whether the
 * QualificaTemplateSeeder definitions have been (re-)run yet.
 */
return new class extends Migration
{
    /**
     * Old column => new custom-field key.
     *
     * @var array<string, string>
     */
    private const array COLUMN_TO_KEY = [
        'responsible_rda_id' => 'responsible_rda',
        'responsible_tickets_id' => 'responsible_tickets',
        'responsible_validation_contracts_id' => 'responsible_validation_contracts',
        'responsible_validation_contracts_two_id' => 'responsible_validation_contracts_two',
        'proforma_progressive' => 'proforma_progressive',
        'invoice_progressive' => 'invoice_progressive',
        'quotation_layout_id' => 'quotation_layout',
        'quotation_header_id' => 'quotation_header',
        'quotation_footer_id' => 'quotation_footer',
    ];

    private const string ENTITY_TYPE = 'company-sites';

    public function up(): void
    {
        $this->backfillCustomFieldValues();

        Schema::table('company_sites', function (Blueprint $table): void {
            $table->dropForeign(['responsible_rda_id']);
            $table->dropForeign(['responsible_tickets_id']);
            $table->dropForeign(['responsible_validation_contracts_id']);
            $table->dropForeign(['responsible_validation_contracts_two_id']);
            $table->dropColumn(array_keys(self::COLUMN_TO_KEY));
        });
    }

    /**
     * Data is NOT restored on rollback (acceptable — see class docblock);
     * only the schema shape is reversed, mirroring the original create
     * migration's column definitions exactly.
     */
    public function down(): void
    {
        Schema::table('company_sites', function (Blueprint $table): void {
            $table->foreignId('responsible_rda_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('responsible_tickets_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('responsible_validation_contracts_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('responsible_validation_contracts_two_id')->nullable()->constrained('users')->nullOnDelete();
            $table->integer('proforma_progressive')->nullable();
            $table->integer('invoice_progressive')->nullable();
            $table->bigInteger('quotation_layout_id')->nullable();
            $table->bigInteger('quotation_header_id')->nullable();
            $table->bigInteger('quotation_footer_id')->nullable();
        });
    }

    /**
     * Copies every site's 9 column values (skipping nulls) into its
     * `custom_field_values` JSON row, merging into whatever is already
     * stored there (e.g. the former "Altro" section fields) rather than
     * overwriting it.
     */
    private function backfillCustomFieldValues(): void
    {
        DB::table('company_sites')
            ->select(['id', ...array_keys(self::COLUMN_TO_KEY)])
            ->chunkById(200, function ($sites): void {
                foreach ($sites as $site) {
                    $this->backfillSite($site);
                }
            });
    }

    private function backfillSite(object $site): void
    {
        $values = [];

        foreach (self::COLUMN_TO_KEY as $column => $key) {
            if ($site->{$column} !== null) {
                $values[$key] = $site->{$column};
            }
        }

        if ($values === []) {
            return;
        }

        $existing = DB::table('custom_field_values')
            ->where('entity_type', self::ENTITY_TYPE)
            ->where('entity_id', $site->id)
            ->first();

        if ($existing === null) {
            DB::table('custom_field_values')->insert([
                'entity_type' => self::ENTITY_TYPE,
                'entity_id' => $site->id,
                'values' => json_encode($values),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return;
        }

        $merged = [...json_decode((string) $existing->values, true), ...$values];

        DB::table('custom_field_values')
            ->where('id', $existing->id)
            ->update(['values' => json_encode($merged), 'updated_at' => now()]);
    }
};

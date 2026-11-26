<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0430 — a commission rule points at SEVERAL product categories or SEVERAL
 * products through two pivots, which replace the single `product_category_id` /
 * `product_id` columns. up() copies every non-null value into the pivots first;
 * down() restores the columns with the MIN id of each pivot, so a rule that
 * holds more than one entry loses the others (declared loss, D-4).
 */
return new class extends Migration
{
    private const string SCOPE_CHECK = 'commission_configurations_scope_check';

    private const string TABLE = 'commission_configurations';

    /** @var array<string, array{0: string, 1: string}> column => [pivot table, referenced table] */
    private const array PIVOTS = [
        'product_category_id' => ['commission_configuration_product_category', 'product_categories'],
        'product_id' => ['commission_configuration_product', 'products'],
    ];

    /** Resolution indexes that include the two columns, dropped before them. */
    private const array INDEXES = [
        'commission_config_product_resolution_index',
        'commission_config_category_resolution_index',
        'commission_config_recipient_product_index',
        'commission_config_recipient_category_index',
    ];

    public function up(): void
    {
        // Step 1: free the columns from their constraints. On SQLite this rebuilds the table, which would
        // cascade-delete pivot rows already written: hence the pivots only come afterwards.
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE '.self::TABLE.' DROP CONSTRAINT '.self::SCOPE_CHECK);
        }

        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->dropForeign(['product_category_id']);
            $table->dropForeign(['product_id']);
            foreach (self::INDEXES as $index) {
                $table->dropIndex($index);
            }
        });

        // Step 2: create the pivots and copy every non-null value
        foreach (self::PIVOTS as $column => [$pivot, $referenced]) {
            Schema::create($pivot, function (Blueprint $table) use ($column, $referenced) {
                $table->id();
                $short = $column === 'product_id' ? 'product' : 'category';
                // Explicit names: the generated ones exceed MySQL's 64-character limit.
                $table->foreignId('commission_configuration_id')->constrained(self::TABLE, 'id', "cc_{$short}_config_fk")->cascadeOnDelete();
                $table->foreignId($column)->constrained($referenced, 'id', "cc_{$short}_target_fk")->restrictOnDelete();

                $table->unique(['commission_configuration_id', $column], $this->uniqueName($column));
            });

            DB::table($pivot)->insertUsing(
                ['commission_configuration_id', $column],
                DB::table(self::TABLE)->whereNotNull($column)->select('id', $column),
            );
        }

        // Step 3: drop the columns
        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->dropColumn(['product_category_id', 'product_id']);
        });
    }

    public function down(): void
    {
        // Step 1: plain columns first, filled with the MIN id of each pivot
        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->unsignedBigInteger('product_category_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
        });

        foreach (self::PIVOTS as $column => [$pivot]) {
            DB::statement('UPDATE '.self::TABLE." SET {$column} = (SELECT MIN(p.{$column}) FROM {$pivot} p WHERE p.commission_configuration_id = ".self::TABLE.'.id)');
        }

        // Step 2: drop the pivots before the constraints come back (a SQLite table rebuild would cascade into them)
        foreach (self::PIVOTS as [$pivot]) {
            Schema::dropIfExists($pivot);
        }

        // Step 3: foreign keys, indexes and the scope check as before
        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->foreign('product_category_id')->references('id')->on('product_categories')->restrictOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->restrictOnDelete();
            $table->index(
                ['recipient_role', 'application_scope', 'status', 'product_id', 'priority', 'valid_from', 'valid_until', 'id'],
                'commission_config_product_resolution_index',
            );
            $table->index(
                ['recipient_role', 'application_scope', 'status', 'product_category_id', 'priority', 'valid_from', 'valid_until', 'id'],
                'commission_config_category_resolution_index',
            );
            $table->index(
                ['recipient_type', 'recipient_id', 'recipient_role', 'application_scope', 'status', 'product_id', 'priority', 'valid_from', 'valid_until', 'id'],
                'commission_config_recipient_product_index',
            );
            $table->index(
                ['recipient_type', 'recipient_id', 'recipient_role', 'application_scope', 'status', 'product_category_id', 'priority', 'valid_from', 'valid_until', 'id'],
                'commission_config_recipient_category_index',
            );
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE '.self::TABLE.' ADD CONSTRAINT '.self::SCOPE_CHECK." CHECK ((application_scope = 'PRODUCT' AND product_id IS NOT NULL AND product_category_id IS NULL) OR (application_scope = 'PRODUCT_CATEGORY' AND product_category_id IS NOT NULL AND product_id IS NULL) OR (application_scope = 'RECIPIENT' AND product_id IS NULL AND product_category_id IS NULL AND recipient_type IS NOT NULL AND recipient_id IS NOT NULL))");
        }
    }

    private function uniqueName(string $column): string
    {
        return 'cc_'.($column === 'product_id' ? 'product' : 'category').'_unique';
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0089 — a commission rule may target one specific recipient.
 *
 * The columns are declared by hand instead of `nullableMorphs()`: that helper
 * would add a [recipient_type, recipient_id] index that the two composite
 * resolution indexes below already cover as a prefix.
 *
 * The check constraints are dropped with DROP CONSTRAINT, not DROP CHECK:
 * DROP CHECK is MySQL-8-only syntax and raises a 1064 on MariaDB, which
 * `DB::getDriverName()` still reports as `mysql` when the connection uses
 * the mysql driver. DROP CONSTRAINT is valid on MariaDB 10.2+ and MySQL
 * 8.0.19+, so the same statement works on every supported engine.
 */
return new class extends Migration
{
    private const string SCOPE_CHECK = 'commission_configurations_scope_check';

    private const string RECIPIENT_CHECK = 'commission_configurations_recipient_check';

    public function up(): void
    {
        Schema::table('commission_configurations', function (Blueprint $table) {
            $table->string('recipient_type', 32)->nullable()->after('recipient_role');
            $table->unsignedBigInteger('recipient_id')->nullable()->after('recipient_type');

            $table->index(
                ['recipient_type', 'recipient_id', 'recipient_role', 'application_scope', 'status', 'product_id', 'priority', 'valid_from', 'valid_until', 'id'],
                'commission_config_recipient_product_index',
            );
            $table->index(
                ['recipient_type', 'recipient_id', 'recipient_role', 'application_scope', 'status', 'product_category_id', 'priority', 'valid_from', 'valid_until', 'id'],
                'commission_config_recipient_category_index',
            );
        });

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE commission_configurations DROP CONSTRAINT '.self::SCOPE_CHECK);
        DB::statement('ALTER TABLE commission_configurations ADD CONSTRAINT '.self::SCOPE_CHECK." CHECK ((application_scope = 'PRODUCT' AND product_id IS NOT NULL AND product_category_id IS NULL) OR (application_scope = 'PRODUCT_CATEGORY' AND product_category_id IS NOT NULL AND product_id IS NULL) OR (application_scope = 'RECIPIENT' AND product_id IS NULL AND product_category_id IS NULL AND recipient_type IS NOT NULL AND recipient_id IS NOT NULL))");
        DB::statement('ALTER TABLE commission_configurations ADD CONSTRAINT '.self::RECIPIENT_CHECK.' CHECK ((recipient_type IS NULL AND recipient_id IS NULL) OR (recipient_type IS NOT NULL AND recipient_id IS NOT NULL))');
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE commission_configurations DROP CONSTRAINT '.self::RECIPIENT_CHECK);
            DB::statement('ALTER TABLE commission_configurations DROP CONSTRAINT '.self::SCOPE_CHECK);
            DB::statement('ALTER TABLE commission_configurations ADD CONSTRAINT '.self::SCOPE_CHECK." CHECK ((application_scope = 'PRODUCT' AND product_id IS NOT NULL AND product_category_id IS NULL) OR (application_scope = 'PRODUCT_CATEGORY' AND product_category_id IS NOT NULL AND product_id IS NULL))");
        }

        Schema::table('commission_configurations', function (Blueprint $table) {
            $table->dropIndex('commission_config_recipient_product_index');
            $table->dropIndex('commission_config_recipient_category_index');
            $table->dropColumn(['recipient_type', 'recipient_id']);
        });
    }
};

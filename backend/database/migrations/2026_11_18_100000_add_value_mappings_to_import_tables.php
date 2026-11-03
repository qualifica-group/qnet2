<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Value mappings (spec 0348): the wizard's "file value -> existing record or
 * new record" decisions, keyed by value-mapping field. `import_runs` keeps the
 * full set (including "create" entries) for the commit phase; templates only
 * ever store the reusable `{id}` entries.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_runs', function (Blueprint $table) {
            $table->json('value_mappings')->nullable()->after('global_config');
        });

        Schema::table('import_mapping_templates', function (Blueprint $table) {
            $table->json('value_mappings')->nullable()->after('column_mapping');
        });
    }

    public function down(): void
    {
        Schema::table('import_mapping_templates', function (Blueprint $table) {
            $table->dropColumn('value_mappings');
        });

        Schema::table('import_runs', function (Blueprint $table) {
            $table->dropColumn('value_mappings');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Partner commerciale" (spec 0417): a second commercial Referent next to
 * `commercial_id`, with the same FK discipline per table — the anagrafica just
 * forgets a deleted referent, opportunities and quotes refuse the delete.
 */
return new class extends Migration
{
    /** @var array<string, bool> table => nullOnDelete (false = restrictOnDelete) */
    private const array TABLES = [
        'registries' => true,
        'opportunities' => false,
        'quotes' => false,
    ];

    public function up(): void
    {
        foreach (self::TABLES as $tableName => $nullOnDelete) {
            Schema::table($tableName, function (Blueprint $table) use ($nullOnDelete) {
                $column = $table->foreignId('commercial_partner_id')->nullable()->after('commercial_id')->constrained('referents');
                $nullOnDelete ? $column->nullOnDelete() : $column->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (array_keys(self::TABLES) as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('commercial_partner_id');
            });
        }
    }
};

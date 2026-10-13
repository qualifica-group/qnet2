<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Which proforma request a product typology feeds (spec 0247, D-6): values of
 * App\Enums\ProformaRequestKind, null = its lines are never billed through a
 * proforma request. The proforma and invoice-draft services read this column
 * instead of the typology code.
 *
 * Data backfill, plain query builder: the two typologies those services used
 * to resolve by code keep today's behaviour.
 */
return new class extends Migration
{
    /** @var array<string, string> typology code => proforma kind */
    private const array BACKFILL = [
        'consultancy' => 'consultancy',
        'institution' => 'institution',
    ];

    public function up(): void
    {
        Schema::table('product_typologies', function (Blueprint $table) {
            $table->string('proforma_kind', 16)->nullable()->after('supplier_commission_direction');
        });

        foreach (self::BACKFILL as $code => $kind) {
            DB::table('product_typologies')->where('code', $code)->update(['proforma_kind' => $kind]);
        }
    }

    public function down(): void
    {
        Schema::table('product_typologies', function (Blueprint $table) {
            $table->dropColumn('proforma_kind');
        });
    }
};

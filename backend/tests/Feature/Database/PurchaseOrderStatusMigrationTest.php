<?php

use App\Enums\PurchaseOrderStatus;
use App\Support\BadgeTokens;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0248 AC-001. The real migration file is run against the in-memory
 * database (no RefreshDatabase: the test owns the schema it creates).
 */
function purchaseOrderStatusMigration(): Migration
{
    return require database_path('migrations/2026_10_17_150000_create_purchase_order_statuses_table.php');
}

it('AC-001: creates one row per enum case with the names, colors, icons and order the interface used to hard-code', function () {
    purchaseOrderStatusMigration()->up();

    $rows = DB::table('purchase_order_statuses')->orderBy('sort_order')->get();

    expect($rows->pluck('system_key')->all())->toBe(PurchaseOrderStatus::values())
        ->and($rows->pluck('name')->all())->toBe(['Bozza', 'In validazione', 'Approvato', 'Inviato al fornitore', 'Parzialmente evaso', 'Evaso', 'Annullato'])
        ->and($rows->pluck('color')->diff(BadgeTokens::colors()))->toBeEmpty()
        ->and($rows->pluck('icon')->diff(BadgeTokens::icons()))->toBeEmpty()
        ->and($rows->pluck('sort_order')->unique())->toHaveCount(7);
});

it('AC-001: rolling back drops the table', function () {
    $migration = purchaseOrderStatusMigration();
    $migration->up();

    $migration->down();

    expect(Schema::hasTable('purchase_order_statuses'))->toBeFalse();
});

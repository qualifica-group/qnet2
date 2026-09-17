<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/**
 * Spec 0136 AC-002: the `normalized_value` backfill inside
 * `2026_09_16_110000_add_normalized_value_to_contacts_table` must catch up
 * every contact already in the table with the exact semantics of
 * `ContactValueNormalizer::contact()`.
 *
 * RefreshDatabase already runs every migration (including this one) once
 * before the test starts, so the migration file is `require`d a second time
 * here purely to call its `up()`/`down()` by hand: `down()` first (drops
 * column + index), then raw rows are seeded the way legacy data looks
 * (bypassing the model, since the model's `saving` hook — spec 0136 D-1 — is
 * a different microtask), then `up()` again exercises the real backfill.
 * Both calls run inside the per-test transaction RefreshDatabase already
 * opened, so nothing needs restoring afterwards — the rollback at the end of
 * the test undoes the manual down()/up() too.
 */
function normalizedValueMigration(): Migration
{
    return require database_path('migrations/2026_09_16_110000_add_normalized_value_to_contacts_table.php');
}

/**
 * @return array<string, mixed>
 */
function rawContactRow(string $type, string $value): array
{
    $now = now();

    return [
        'contactable_type' => null,
        'contactable_id' => null,
        'type' => $type,
        'label' => null,
        'value' => $value,
        'is_primary' => false,
        'created_at' => $now,
        'updated_at' => $now,
    ];
}

it('backfills normalized_value for every contact and leaves value untouched', function () {
    $migration = normalizedValueMigration();
    $migration->down();

    expect(Schema::hasColumn('contacts', 'normalized_value'))->toBeFalse();

    DB::table('contacts')->insert([
        rawContactRow('email', ' Mario@X.it '),
        rawContactRow('phone', '333 123-4567'),
        rawContactRow('legacy_channel', 'something'),
    ]);

    $migration->up();

    expect(Schema::hasColumn('contacts', 'normalized_value'))->toBeTrue();

    $rows = DB::table('contacts')->orderBy('id')->get(['type', 'value', 'normalized_value'])->keyBy('type');

    expect($rows['email']->normalized_value)->toBe('mario@x.it')
        ->and($rows['email']->value)->toBe(' Mario@X.it ')
        ->and($rows['phone']->normalized_value)->toBe('3331234567')
        ->and($rows['phone']->value)->toBe('333 123-4567')
        ->and($rows['legacy_channel']->normalized_value)->toBeNull()
        ->and($rows['legacy_channel']->value)->toBe('something');
});

it('rolls back by dropping the normalized_value column and its index', function () {
    $migration = normalizedValueMigration();

    expect(Schema::hasColumn('contacts', 'normalized_value'))->toBeTrue();

    $migration->down();

    expect(Schema::hasColumn('contacts', 'normalized_value'))->toBeFalse();

    $migration->up();

    expect(Schema::hasColumn('contacts', 'normalized_value'))->toBeTrue();
});

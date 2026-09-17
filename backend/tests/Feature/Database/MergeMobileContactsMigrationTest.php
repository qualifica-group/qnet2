<?php

use App\Models\ImportMappingTemplate;
use App\Models\ImportRun;
use App\Models\ImportRunRow;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Spec 0139 AC-001..AC-003: the two data migrations that fold the dropped
 * `mobile` contact type into `phone`. RefreshDatabase already ran them on an
 * empty table, so each file is `require`d again and its `up()` called by hand
 * on legacy-shaped rows inserted raw (the enum no longer accepts `mobile`).
 */
function mobileMigration(string $file): Migration
{
    return require database_path("migrations/{$file}.php");
}

function insertRawContact(int $ownerId, string $type, string $value, bool $primary, ?string $label = null): int
{
    return DB::table('contacts')->insertGetId([
        'contactable_type' => 'personal_data',
        'contactable_id' => $ownerId,
        'type' => $type,
        'label' => $label,
        'value' => $value,
        'is_primary' => $primary,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function rawContact(int $id): object
{
    return DB::table('contacts')->where('id', $id)->first();
}

it('AC-001: retypes mobiles to phone, keeping the former mobile as the primary phone', function () {
    $landline = insertRawContact(1, 'phone', '021234567', true, 'Casa');
    $mobile = insertRawContact(1, 'mobile', '3331234567', true, 'Cellulare');
    $untouchedLabel = insertRawContact(2, 'mobile', '3339999999', true, 'Lavoro');

    mobileMigration('2026_09_17_130000_merge_mobile_contacts_into_phone')->up();

    expect(DB::table('contacts')->where('type', 'mobile')->exists())->toBeFalse()
        ->and(rawContact($mobile)->type)->toBe('phone')
        ->and((bool) rawContact($mobile)->is_primary)->toBeTrue()
        ->and(rawContact($mobile)->label)->toBeNull()
        ->and((bool) rawContact($landline)->is_primary)->toBeFalse()
        ->and(rawContact($landline)->label)->toBe('Casa')
        ->and(rawContact($untouchedLabel)->label)->toBe('Lavoro');
});

it('AC-002: promotes the lowest-id email and phone of an owner that has no primary of that type', function () {
    $firstEmail = insertRawContact(3, 'email', 'a@example.test', false);
    $secondEmail = insertRawContact(3, 'email', 'b@example.test', false);
    $phone = insertRawContact(3, 'phone', '3330000000', false);
    $pec = insertRawContact(3, 'pec', 'a@pec.example.test', false);
    $alreadyPrimary = insertRawContact(4, 'phone', '3331111111', true);
    $secondaryOfPrimaryOwner = insertRawContact(4, 'phone', '3332222222', false);

    mobileMigration('2026_09_17_130000_merge_mobile_contacts_into_phone')->up();

    expect((bool) rawContact($firstEmail)->is_primary)->toBeTrue()
        ->and((bool) rawContact($secondEmail)->is_primary)->toBeFalse()
        ->and((bool) rawContact($phone)->is_primary)->toBeTrue()
        ->and((bool) rawContact($pec)->is_primary)->toBeFalse()
        ->and((bool) rawContact($alreadyPrimary)->is_primary)->toBeTrue()
        ->and((bool) rawContact($secondaryOfPrimaryOwner)->is_primary)->toBeFalse();
});

it('AC-003: remaps a stored `mobile` import mapping onto phone, or drops it when phone is taken', function () {
    $free = ImportMappingTemplate::factory()->create([
        'columns' => ['Nome', 'Cellulare'],
        'column_mapping' => ['Nome' => 'full_name', 'Cellulare' => 'mobile'],
    ]);
    $taken = ImportMappingTemplate::factory()->create([
        'columns' => ['Telefono', 'Cellulare'],
        'column_mapping' => ['Telefono' => 'phone', 'Cellulare' => 'mobile'],
    ]);

    mobileMigration('2026_09_17_130100_remap_mobile_import_field_to_phone')->up();

    expect($free->fresh()->column_mapping)->toBe(['Nome' => 'full_name', 'Cellulare' => 'phone'])
        ->and($taken->fresh()->column_mapping)->toBe(['Telefono' => 'phone']);
});

it('AC-003: moves a staged row mobile value into an empty phone, and drops it otherwise', function () {
    $run = ImportRun::factory()->create(['resource' => 'leads']);
    $emptyPhone = ImportRunRow::factory()->create([
        'import_run_id' => $run->id,
        'mapped_values' => ['full_name' => 'Mario Rossi', 'phone' => '', 'mobile' => '3331234567'],
    ]);
    $filledPhone = ImportRunRow::factory()->create([
        'import_run_id' => $run->id,
        'mapped_values' => ['phone' => '021234567', 'mobile' => '3331234567'],
    ]);

    mobileMigration('2026_09_17_130100_remap_mobile_import_field_to_phone')->up();

    expect($emptyPhone->fresh()->mapped_values)->toBe(['full_name' => 'Mario Rossi', 'phone' => '3331234567'])
        ->and($filledPhone->fresh()->mapped_values)->toBe(['phone' => '021234567']);
});

<?php

declare(strict_types=1);

use App\Models\Attribute;
use App\Models\User;
use Database\Seeders\QualificaLinkedAttributesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// The "Campo attuale" link of an attribute (spec 0429): AC-008, AC-009, AC-010.

uses(RefreshDatabase::class);

if (! function_exists('attributeUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function attributeUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("attributes.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("attributes.{$ability}");
        }

        return $user;
    }
}

it('saves, exposes and clears a valid link (AC-008)', function () {
    $current = Attribute::factory()->ofType('decimal')->create(['code' => 'iso_quotation', 'name' => 'Quotazione']);
    $legacy = Attribute::factory()->ofType('integer')->create();
    Sanctum::actingAs(attributeUserWith(['view', 'update']));

    $linked = $this->patchJson("/api/attributes/{$legacy->id}", ['current_attribute_id' => $current->id])->assertOk();
    $shown = $this->getJson("/api/attributes/{$legacy->id}")->assertOk();
    $this->patchJson("/api/attributes/{$legacy->id}", ['current_attribute_id' => null])->assertOk();

    expect($linked->json('data.current_attribute'))->toBe(['id' => $current->id, 'code' => 'iso_quotation', 'name' => 'Quotazione'])
        ->and($shown->json('data.current_attribute_id'))->toBe($current->id)
        ->and($legacy->fresh()->current_attribute_id)->toBeNull();
});

it('creates a field already linked (AC-008)', function () {
    $current = Attribute::factory()->ofType('date')->create();
    Sanctum::actingAs(attributeUserWith(['create']));

    $response = $this->postJson('/api/attributes', ['code' => 'old_date', 'name' => 'Data', 'type' => 'date', 'current_attribute_id' => $current->id])->assertCreated();

    expect($response->json('data.current_attribute_id'))->toBe($current->id);
});

it('refuses a link to itself, a chain or incompatible types (AC-008)', function () {
    $current = Attribute::factory()->ofType('date')->create();
    $legacy = Attribute::factory()->ofType('date')->create(['current_attribute_id' => $current->id]);
    $other = Attribute::factory()->ofType('date')->create();
    $text = Attribute::factory()->ofType('text')->create();
    $enum = Attribute::factory()->enum(2)->create();
    $enumTarget = Attribute::factory()->enum(2)->create();
    Sanctum::actingAs(attributeUserWith(['update']));

    $this->patchJson("/api/attributes/{$other->id}", ['current_attribute_id' => $other->id])->assertUnprocessable()->assertJsonValidationErrors('current_attribute_id');
    $this->patchJson("/api/attributes/{$other->id}", ['current_attribute_id' => $legacy->id])->assertUnprocessable()->assertJsonValidationErrors('current_attribute_id');
    $this->patchJson("/api/attributes/{$current->id}", ['current_attribute_id' => $other->id])->assertUnprocessable()->assertJsonValidationErrors('current_attribute_id');
    $this->patchJson("/api/attributes/{$text->id}", ['current_attribute_id' => $current->id])->assertUnprocessable()->assertJsonValidationErrors('current_attribute_id');
    $this->patchJson("/api/attributes/{$enum->id}", ['current_attribute_id' => $enumTarget->id])->assertUnprocessable()->assertJsonValidationErrors('current_attribute_id');

    expect($other->fresh()->current_attribute_id)->toBeNull()
        ->and($current->fresh()->current_attribute_id)->toBeNull();
});

it('refuses a type change that breaks a link, in either direction (AC-008)', function () {
    $current = Attribute::factory()->ofType('date')->create();
    $legacy = Attribute::factory()->ofType('date')->create(['current_attribute_id' => $current->id]);
    Sanctum::actingAs(attributeUserWith(['update']));

    $this->patchJson("/api/attributes/{$current->id}", ['type' => 'text'])->assertUnprocessable()->assertJsonValidationErrors('type');
    $this->patchJson("/api/attributes/{$legacy->id}", ['type' => 'text'])->assertUnprocessable()->assertJsonValidationErrors('type');
    $this->patchJson("/api/attributes/{$legacy->id}", ['type' => 'text', 'current_attribute_id' => null])->assertOk();

    expect($legacy->fresh()->type)->toBe('text');
});

it('lists only the fields that can be current, searching name and code (AC-009)', function () {
    $assignment = Attribute::factory()->ofType('date')->create(['code' => 'iso_assignment_date', 'name' => 'Data di assegnazione']);
    $edited = Attribute::factory()->ofType('date')->create(['code' => 'edited', 'name' => 'Data modificata']);
    $linked = Attribute::factory()->ofType('date')->create(['code' => 'old_assignment', 'name' => 'Data di assegnazione', 'current_attribute_id' => $assignment->id]);
    Sanctum::actingAs(attributeUserWith([]));

    $byName = $this->getJson('/api/attributes/for-select?search=Data&exclude_id='.$edited->id)->assertOk()->json('items');
    $byCode = $this->getJson('/api/attributes/for-select?search=iso_assign')->assertOk()->json('items');
    $hydrated = $this->getJson('/api/attributes/for-select?search=zzz&ids[]='.$linked->id)->assertOk()->json('items');

    expect(array_column($byName, 'id'))->toBe([$assignment->id])
        ->and($byName[0]['label'])->toBe('Data di assegnazione (iso_assignment_date)')
        ->and(array_column($byCode, 'id'))->toBe([$assignment->id])
        ->and(array_column($hydrated, 'id'))->toBe([$linked->id]);
});

it('requires authentication on the for-select (AC-009)', function () {
    $this->getJson('/api/attributes/for-select')->assertUnauthorized();
});

it('links the mapped pairs once and skips the ones it cannot link (AC-010)', function () {
    $assignment = Attribute::factory()->ofType('date')->create(['code' => 'iso_assignment_date']);
    $legacyAssignment = Attribute::factory()->ofType('date')->create(['code' => 'data_inizio_lavorazione']);
    $quotation = Attribute::factory()->ofType('decimal')->create(['code' => 'iso_quotation']);
    $legacyQuotation = Attribute::factory()->ofType('integer')->create(['code' => 'quotazione']);
    Attribute::factory()->ofType('decimal')->create(['code' => 'iso_work_hours']);
    $wrongType = Attribute::factory()->ofType('text')->create(['code' => 'ore_lavorazione']);
    $other = Attribute::factory()->ofType('text')->create();
    $alreadyElsewhere = Attribute::factory()->ofType('text')->create(['code' => 'ente_riferimento', 'current_attribute_id' => $other->id]);
    Attribute::factory()->ofType('text')->create(['code' => 'iso_reference_body']);

    $this->seed(QualificaLinkedAttributesSeeder::class);
    $this->seed(QualificaLinkedAttributesSeeder::class);

    expect($legacyAssignment->fresh()->current_attribute_id)->toBe($assignment->id)
        ->and($legacyQuotation->fresh()->current_attribute_id)->toBe($quotation->id)
        ->and($wrongType->fresh()->current_attribute_id)->toBeNull()
        ->and($alreadyElsewhere->fresh()->current_attribute_id)->toBe($other->id)
        ->and(Attribute::query()->whereNotNull('current_attribute_id')->count())->toBe(3);
});

it('rolls the link column back and forth (spec 0429 D-1)', function () {
    $steps = migrationStepsFrom('2026_12_24_100000_add_current_attribute_id_to_attributes_table.php');

    expect(Artisan::call('migrate:rollback', ['--step' => $steps]))->toBe(0)
        ->and(Schema::hasColumn('attributes', 'current_attribute_id'))->toBeFalse()
        ->and(Artisan::call('migrate'))->toBe(0)
        ->and(Schema::hasColumn('attributes', 'current_attribute_id'))->toBeTrue();
});

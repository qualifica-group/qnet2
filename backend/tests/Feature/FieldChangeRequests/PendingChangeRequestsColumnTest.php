<?php

declare(strict_types=1);

use App\Models\FieldChangeRequest;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('pendingColumnActorWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function pendingColumnActorWith(array $abilities): User
    {
        foreach (['viewAny', 'viewAll'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("request-management.{$ability}");
        }

        return $user;
    }
}

// AC-037: the `pending_change_requests` column on the request-management
// table counts only the STILL-OPEN requests on the record. Spec 0086, D-10
// (corrected in execution): the subject is now the QUOTE, so two sibling
// offers of the same opportunity carry independent badges — every fixture
// below sets `subject_type`/`subject_id` explicitly, rather than relying on
// FieldChangeRequestFactory's own (still Opportunity-shaped) default.

it('rows: `pending_change_requests` counts N pending requests, 0 for a record with none (AC-037)', function () {
    $actor = pendingColumnActorWith(['viewAny', 'viewAll']);

    // Two DISTINCT fields on purpose: `pending_key` (D-5) is UNIQUE on
    // (subject_type, subject_id, field) while pending — two pending rows on
    // the SAME field would collide. `other_field` is a plain DB value here,
    // not a real registered ProtectedField: this test only exercises the
    // COUNT, never the registry.
    $withTwoPending = Quote::factory()->create();
    FieldChangeRequest::factory()->create(['subject_type' => 'quote', 'subject_id' => $withTwoPending->id]);
    FieldChangeRequest::factory()->create(['subject_type' => 'quote', 'subject_id' => $withTwoPending->id, 'field' => 'other_field']);

    $withNone = Quote::factory()->create();

    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/request-management/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $rows = collect($response->json('items'))->keyBy('id');

    expect($rows[$withTwoPending->id]['pending_change_requests'])->toBe(2)
        ->and($rows[$withNone->id]['pending_change_requests'])->toBe(0);
});

it('rows: `pending_change_requests` excludes approved/rejected requests (AC-037)', function () {
    $actor = pendingColumnActorWith(['viewAny', 'viewAll']);

    $quote = Quote::factory()->create();
    FieldChangeRequest::factory()->create(['subject_type' => 'quote', 'subject_id' => $quote->id]); // pending, counted
    FieldChangeRequest::factory()->approved()->create(['subject_type' => 'quote', 'subject_id' => $quote->id]);
    FieldChangeRequest::factory()->rejected()->create(['subject_type' => 'quote', 'subject_id' => $quote->id]);

    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/request-management/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('id', $quote->id);

    expect($row['pending_change_requests'])->toBe(1);
});

it('columns: `pending_change_requests` is present, not editable, not sortable (AC-037)', function () {
    $actor = pendingColumnActorWith(['viewAny']);
    Sanctum::actingAs($actor);

    $columns = collect($this->getJson('/api/tables/request-management/columns')->assertOk()->json('data.columns'))
        ->keyBy('id');

    expect($columns)->toHaveKey('pending_change_requests')
        ->and($columns['pending_change_requests']['editable'] ?? false)->toBeFalse()
        ->and($columns['pending_change_requests']['sortable'])->toBeFalse();
});

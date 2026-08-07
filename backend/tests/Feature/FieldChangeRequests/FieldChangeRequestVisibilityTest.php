<?php

use App\Models\FieldChangeRequest;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// GET /api/field-change-requests/for-record, GET /{id}, `can` block (spec
// 0078): AC-038/039/040. Spec 0086, D-10 (corrected in execution): the
// request-management field change request's subject is the QUOTE.

uses(RefreshDatabase::class);

if (! function_exists('fcrVisibilityEnsurePermissions')) {
    function fcrVisibilityEnsurePermissions(): void
    {
        foreach (['viewAny', 'view', 'create', 'manage', 'export', 'viewActivity'] as $ability) {
            Permission::findOrCreate("field-change-requests.{$ability}");
        }
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'viewActivity', 'viewAll', 'updateSource'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }
    }
}

if (! function_exists('fcrVisibilityActorWith')) {
    /**
     * @param  array<int, string>  $fieldChangeAbilities
     * @param  array<int, string>  $requestManagementAbilities
     */
    function fcrVisibilityActorWith(array $fieldChangeAbilities, array $requestManagementAbilities = ['view', 'viewAll']): User
    {
        fcrVisibilityEnsurePermissions();

        $user = User::factory()->create();

        foreach ($fieldChangeAbilities as $ability) {
            $user->givePermissionTo("field-change-requests.{$ability}");
        }

        foreach ($requestManagementAbilities as $ability) {
            $user->givePermissionTo("request-management.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('fcrVisibilityQuote')) {
    function fcrVisibilityQuote(): Quote
    {
        $opportunity = Opportunity::factory()->create(['source_id' => Source::factory()->create()->id]);

        return Quote::factory()->for($opportunity)->create();
    }
}

if (! function_exists('fcrRequestOn')) {
    /**
     * @param  array<string, mixed>  $overrides
     */
    function fcrRequestOn(Quote $quote, User $requester, array $overrides = []): FieldChangeRequest
    {
        return FieldChangeRequest::factory()->create([
            'resource' => 'request-management',
            'subject_type' => 'quote',
            'subject_id' => $quote->id,
            'field' => 'source_id',
            'requested_by_id' => $requester->id,
            ...$overrides,
        ]);
    }
}

// ---------------------------------------------------------------------------
// AC-038 — for-record listing, pending first, viewAny-or-requester
// ---------------------------------------------------------------------------

it('AC-038: for-record lists the record\'s requests, pending first', function () {
    $viewer = fcrVisibilityActorWith(['viewAny']);
    $requester = fcrVisibilityActorWith(['create']);
    $quote = fcrVisibilityQuote();

    $approved = fcrRequestOn($quote, $requester, ['status' => 'approved', 'pending_key' => null, 'created_at' => now()->subDay()]);
    $pending = fcrRequestOn($quote, $requester, ['status' => 'pending', 'pending_key' => "quote:{$quote->id}:source_id", 'created_at' => now()]);
    Sanctum::actingAs($viewer);

    $response = $this->getJson("/api/field-change-requests/for-record?resource=request-management&subject_id={$quote->id}")
        ->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toBe([$pending->id, $approved->id]);
});

it('AC-038: a viewer without viewAny and no request of their own on the record -> 403', function () {
    $stranger = fcrVisibilityActorWith([]);
    $requester = fcrVisibilityActorWith(['create']);
    $quote = fcrVisibilityQuote();
    fcrRequestOn($quote, $requester);
    Sanctum::actingAs($stranger);

    $this->getJson("/api/field-change-requests/for-record?resource=request-management&subject_id={$quote->id}")
        ->assertForbidden();
});

it('AC-038: a requester without viewAny still sees the record\'s list (scoped to their own)', function () {
    $requester = fcrVisibilityActorWith(['create']);
    $otherRequester = fcrVisibilityActorWith(['create']);
    $quote = fcrVisibilityQuote();
    $mine = fcrRequestOn($quote, $requester);
    fcrRequestOn($quote, $otherRequester, ['status' => 'rejected', 'pending_key' => null]);
    Sanctum::actingAs($requester);

    $response = $this->getJson("/api/field-change-requests/for-record?resource=request-management&subject_id={$quote->id}")
        ->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toBe([$mine->id]);
});

// ---------------------------------------------------------------------------
// AC-039 — show: the requester reads their own without .view, 403 on another's
// ---------------------------------------------------------------------------

it('AC-039: the requester reads their OWN request without field-change-requests.view (200)', function () {
    $requester = fcrVisibilityActorWith(['create']);
    $quote = fcrVisibilityQuote();
    $own = fcrRequestOn($quote, $requester);
    Sanctum::actingAs($requester);

    $this->getJson("/api/field-change-requests/{$own->id}")->assertOk();
});

it('AC-039: without .view, another user\'s request -> 403', function () {
    $requester = fcrVisibilityActorWith(['create']);
    $stranger = fcrVisibilityActorWith(['create']);
    $quote = fcrVisibilityQuote();
    $theirs = fcrRequestOn($quote, $requester);
    Sanctum::actingAs($stranger);

    $this->getJson("/api/field-change-requests/{$theirs->id}")->assertForbidden();
});

// ---------------------------------------------------------------------------
// AC-040 — `can.approve`/`can.reject`
// ---------------------------------------------------------------------------

it('AC-040: can.approve/reject are true only for manage + pending', function () {
    $manager = fcrVisibilityActorWith(['manage', 'view'], ['view', 'viewAll', 'update', 'updateSource']);
    $requester = fcrVisibilityActorWith(['create']);
    $quote = fcrVisibilityQuote();
    $pending = fcrRequestOn($quote, $requester, ['status' => 'pending', 'pending_key' => "quote:{$quote->id}:source_id"]);
    Sanctum::actingAs($manager);

    $this->getJson("/api/field-change-requests/{$pending->id}")
        ->assertOk()
        ->assertJsonPath('data.can.approve', true)
        ->assertJsonPath('data.can.reject', true);
});

it('AC-040: can.approve/reject are false for a handled request, even for a manager', function () {
    $manager = fcrVisibilityActorWith(['manage', 'view'], ['view', 'viewAll', 'update', 'updateSource']);
    $requester = fcrVisibilityActorWith(['create']);
    $quote = fcrVisibilityQuote();
    $handled = fcrRequestOn($quote, $requester, ['status' => 'approved', 'pending_key' => null]);
    Sanctum::actingAs($manager);

    $this->getJson("/api/field-change-requests/{$handled->id}")
        ->assertOk()
        ->assertJsonPath('data.can.approve', false)
        ->assertJsonPath('data.can.reject', false);
});

it('AC-040: can.approve/reject are false for the requester themself, even pending', function () {
    $requester = fcrVisibilityActorWith(['create', 'view']);
    $quote = fcrVisibilityQuote();
    $own = fcrRequestOn($quote, $requester, ['status' => 'pending', 'pending_key' => "quote:{$quote->id}:source_id"]);
    Sanctum::actingAs($requester);

    $this->getJson("/api/field-change-requests/{$own->id}")
        ->assertOk()
        ->assertJsonPath('data.can.approve', false)
        ->assertJsonPath('data.can.reject', false);
});

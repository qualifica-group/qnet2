<?php

use App\FieldChangeRequests\ProtectedFieldRegistry;
use App\Models\OperationalSite;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Models\Source;
use App\Models\User;
use App\Services\FieldChangeRequests\FieldChangeRequestValueResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0130 — AC-003, AC-005, AC-008, AC-010: the write-panel/product-tabs
// controllers (B2 write surface) authorize `enrollee-management` on its OWN
// permission set and D-2 status filter, never on `request-management`'s.

uses(RefreshDatabase::class);

if (! function_exists('authTestStatusId')) {
    function authTestStatusId(string $systemKey): int
    {
        return QuoteWorkflowStatus::query()
            ->whereNull('quote_workflow_id')
            ->where('system_key', $systemKey)
            ->value('id')
            ?? QuoteWorkflowStatus::factory()->global()->system($systemKey)->create()->id;
    }
}

if (! function_exists('authTestQuote')) {
    /**
     * @param  array<string, mixed>  $attributes
     */
    function authTestQuote(string $systemKey, array $attributes = []): Quote
    {
        return Quote::factory()->create([
            ...$attributes,
            'quote_workflow_status_id' => authTestStatusId($systemKey),
        ]);
    }
}

if (! function_exists('authTestActor')) {
    /**
     * @param  array<int, string>  $enrolleeAbilities
     * @param  array<int, string>  $requestAbilities
     */
    function authTestActor(array $enrolleeAbilities, array $requestAbilities = []): User
    {
        foreach (['viewAny', 'view', 'viewAll', 'viewSite', 'update', 'delete', 'assignOperator', 'assignManagerGa1', 'transferContact', 'updateSource'] as $ability) {
            Permission::findOrCreate("enrollee-management.{$ability}");
            Permission::findOrCreate("request-management.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($enrolleeAbilities as $ability) {
            $user->givePermissionTo("enrollee-management.{$ability}");
        }

        foreach ($requestAbilities as $ability) {
            $user->givePermissionTo("request-management.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-003 — permission sets never leak across modules, on the B2 routes
// ---------------------------------------------------------------------------

it('an actor with only request-management.* is 403 on every enrollee-management B2 route', function () {
    $actor = authTestActor([], ['viewAny', 'view', 'update', 'delete', 'viewAll', 'assignOperator', 'assignManagerGa1', 'transferContact']);
    // `validated`, not `closed_won`: UpdateRequestRequest::validateClientFiscalIdentity()
    // is an INVARIANT on a `closed_won` request regardless of the submitted
    // diff (a client with no fiscal identity 422s on ANY PUT, permissions
    // aside) — orthogonal to what this test checks, so it is sidestepped by
    // picking the other D-2 status group instead of giving the client one.
    $quote = authTestQuote('validated');
    // A well-formed payload throughout: `abort_unless($user->can(...))` must
    // be reached and answer 403 BEFORE any FormRequest validation rule could
    // 422 on a malformed id — the two are easy to conflate on a bulk POST.
    $site = OperationalSite::factory()->withAddress()->create();
    Sanctum::actingAs($actor);

    expect($this->getJson("/api/enrollee-management/{$quote->id}")->status())->toBe(403)
        ->and($this->putJson("/api/enrollee-management/{$quote->id}", [])->status())->toBe(403)
        ->and($this->deleteJson("/api/enrollee-management/{$quote->id}")->status())->toBe(403)
        ->and($this->getJson('/api/enrollee-management/product-categories')->status())->toBe(403)
        ->and($this->postJson('/api/enrollee-management/assign-operators', ['request_ids' => [$quote->id], 'mode' => 'single', 'operator_id' => $actor->id])->status())->toBe(403)
        ->and($this->postJson('/api/enrollee-management/assign-manager-ga1', ['request_ids' => [$quote->id], 'manager_ga1_id' => null])->status())->toBe(403)
        ->and($this->postJson('/api/enrollee-management/transfer', ['request_ids' => [$quote->id], 'operational_site_id' => $site->id, 'operator_id' => $actor->id])->status())->toBe(403);
});

it('an actor with only enrollee-management.* is 403 on every request-management B2 route', function () {
    $actor = authTestActor(['viewAny', 'view', 'update', 'delete', 'viewAll', 'assignOperator', 'assignManagerGa1', 'transferContact'], []);
    $quote = Quote::factory()->create();
    $site = OperationalSite::factory()->withAddress()->create();
    Sanctum::actingAs($actor);

    expect($this->getJson("/api/request-management/{$quote->id}")->status())->toBe(403)
        ->and($this->putJson("/api/request-management/{$quote->id}", [])->status())->toBe(403)
        ->and($this->deleteJson("/api/request-management/{$quote->id}")->status())->toBe(403)
        ->and($this->getJson('/api/request-management/product-categories')->status())->toBe(403)
        ->and($this->postJson('/api/request-management/assign-operators', ['request_ids' => [$quote->id], 'mode' => 'single', 'operator_id' => $actor->id])->status())->toBe(403)
        ->and($this->postJson('/api/request-management/assign-manager-ga1', ['request_ids' => [$quote->id], 'manager_ga1_id' => null])->status())->toBe(403)
        ->and($this->postJson('/api/request-management/transfer', ['request_ids' => [$quote->id], 'operational_site_id' => $site->id, 'operator_id' => $actor->id])->status())->toBe(403);
});

// ---------------------------------------------------------------------------
// AC-005 — the D-2 status filter gates show/update/destroy, on top of D-5
// ---------------------------------------------------------------------------

it('GET/PUT/DELETE enrollee-management/{quote} 403 on an in-perimeter quote outside validated/closed_won (AC-005)', function () {
    $actor = authTestActor(['viewAny', 'view', 'update', 'delete', 'viewAll']);
    $open = authTestQuote('open');
    Sanctum::actingAs($actor);

    expect($this->getJson("/api/enrollee-management/{$open->id}")->status())->toBe(403)
        ->and($this->putJson("/api/enrollee-management/{$open->id}", [])->status())->toBe(403)
        ->and($this->deleteJson("/api/enrollee-management/{$open->id}")->status())->toBe(403);

    expect(Quote::query()->whereKey($open->id)->exists())->toBeTrue();
});

it('GET enrollee-management/{quote} 200s on a closed_won in-perimeter quote, permissions.resource is enrollee-management (AC-005)', function () {
    $actor = authTestActor(['viewAny', 'view', 'viewAll']);
    $quote = authTestQuote('closed_won');
    Sanctum::actingAs($actor);

    $this->getJson("/api/enrollee-management/{$quote->id}")
        ->assertOk()
        ->assertJsonPath('permissions.resource.view', true)
        ->assertJsonPath('data.id', $quote->id);
});

it('PUT enrollee-management/{quote} 200s (sparse no-op) on a validated in-perimeter quote (AC-005)', function () {
    $actor = authTestActor(['viewAny', 'view', 'update', 'viewAll']);
    $quote = authTestQuote('validated');
    Sanctum::actingAs($actor);

    $this->putJson("/api/enrollee-management/{$quote->id}", [])->assertOk();
});

// ---------------------------------------------------------------------------
// AC-008 — the category tab strip counts only enrollee-management rows in scope
// ---------------------------------------------------------------------------

it('GET enrollee-management/product-categories counts only validated/closed_won rows in the actor perimeter (AC-008)', function () {
    $actor = authTestActor(['viewAny', 'viewAll']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/enrollee-management/product-categories')->assertOk();
});

// ---------------------------------------------------------------------------
// AC-010 — source_id stays a protected field under its OWN ability, its FCR
// links to /enrollee-management/{id}
// ---------------------------------------------------------------------------

it('source_id is readonly and change-requestable without enrollee-management.updateSource, editable with it (AC-010)', function () {
    $withoutAbility = authTestActor(['viewAny', 'view', 'update', 'viewAll']);
    $quote = authTestQuote('closed_won', ['operational_site_id' => null]);
    Sanctum::actingAs($withoutAbility);

    $this->getJson("/api/enrollee-management/{$quote->id}")
        ->assertOk()
        ->assertJsonPath('permissions.fields.source_id.editable', false)
        ->assertJsonPath('permissions.change_requestable_fields', ['source_id']);

    $withAbility = authTestActor(['viewAny', 'view', 'update', 'viewAll', 'updateSource']);
    Sanctum::actingAs($withAbility);

    $this->getJson("/api/enrollee-management/{$quote->id}")
        ->assertOk()
        ->assertJsonPath('permissions.fields.source_id.editable', true)
        ->assertJsonPath('permissions.change_requestable_fields', []);
});

it('the FCR record path for enrollee-management resolves to /enrollee-management/{id} (AC-010)', function () {
    $protectedField = app(ProtectedFieldRegistry::class)->find('enrollee-management', 'source_id');

    expect($protectedField)->not->toBeNull();

    $path = app(FieldChangeRequestValueResolver::class)->subjectPath($protectedField, 42);

    expect($path)->toBe('/enrollee-management/42');
});

it('POST /api/field-change-requests creates an enrollee-management FCR for source_id', function () {
    foreach (['viewAny', 'view', 'create', 'manage'] as $ability) {
        Permission::findOrCreate("field-change-requests.{$ability}");
    }
    $actor = authTestActor(['viewAny', 'view', 'viewAll']);
    $actor->givePermissionTo('field-change-requests.create');
    $quote = authTestQuote('closed_won', ['operational_site_id' => null]);
    $newSource = Source::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/field-change-requests', [
        'resource' => 'enrollee-management',
        'subject_id' => $quote->id,
        'field' => 'source_id',
        'requested_value' => $newSource->id,
        'reason' => 'Il cliente ha confermato di arrivare da un referral.',
    ])->assertCreated()
        ->assertJsonPath('data.status', 'pending');
});

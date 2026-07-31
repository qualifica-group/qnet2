<?php

declare(strict_types=1);

use App\Models\Attribute;
use App\Models\AttributeLayout;
use App\Models\BusinessFunction;
use App\Models\Note;
use App\Models\Opportunity;
use App\Models\OpportunityWorkflowStatus;
use App\Models\ProductCategory;
use App\Models\Registry;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// User directive 2026-07-31 ("la create il piu' simile possibile al pannello"):
// the create form now carries the five operative fields the work panel edits —
// working status (+ its mandatory note), next callback, note generali and the
// dynamic attribute values — plus the POST /api/request-management/form-context
// preview that lets it render the status select and the dynamic fields BEFORE
// anything is persisted.

uses(RefreshDatabase::class);

if (! function_exists('requestCreateOperativeActor')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function requestCreateOperativeActor(array $abilities = ['create'], bool $canCreateNotes = false): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'viewAll'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }
        Permission::findOrCreate('notes.create');

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("request-management.{$ability}");
        }

        if ($canCreateNotes) {
            $user->givePermissionTo('notes.create');
        }

        return $user;
    }
}

if (! function_exists('categoryWithAttribute')) {
    /**
     * A category carrying one `opportunity`-context attribute, plus the
     * product-line row that references it.
     *
     * @return array{line: array{business_function_id: int, product_category_id: int}, category: ProductCategory}
     */
    function categoryWithAttribute(string $code, bool $required = false): array
    {
        $businessFunction = BusinessFunction::factory()->create();
        $category = ProductCategory::factory()->create(['business_function_id' => $businessFunction->id]);
        $attribute = Attribute::factory()->create(['code' => $code]);
        $category->attributes()->attach($attribute->id, [
            'is_required' => $required,
            'sort_order' => 0,
            'context' => 'opportunity',
        ]);

        return [
            'line' => ['business_function_id' => $businessFunction->id, 'product_category_id' => $category->id],
            'category' => $category,
        ];
    }
}

// ---------------------------------------------------------------------------
// POST /api/request-management/form-context — the create form's preview
// ---------------------------------------------------------------------------

it('form-context: without request-management.create -> 403', function () {
    Sanctum::actingAs(requestCreateOperativeActor([]));

    $this->postJson('/api/request-management/form-context', ['product_lines' => []])
        ->assertForbidden();
});

it('form-context: returns the resolved workflow statuses, the applicable attributes and the create layout', function () {
    $actor = requestCreateOperativeActor();
    ['line' => $line, 'category' => $category] = categoryWithAttribute('material');
    AttributeLayout::factory()->for($category, 'productCategory')
        ->withCodes(['material'])
        ->create(['context' => 'opportunity', 'form_mode' => 'create']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/request-management/form-context', [
        'source_id' => Source::factory()->create()->id,
        'product_lines' => [$line],
    ])->assertOk();

    expect(collect($response->json('data.applicable_attributes'))->pluck('code')->all())->toBe(['material']);
    // The global default set (no workflow matches these criteria), same rows
    // the work panel would offer for the same request.
    expect(collect($response->json('data.workflow_statuses'))->pluck('system_key')->all())
        ->toBe(['open', 'validated', 'closed_won', 'closed_lost']);
    expect($response->json('data.attribute_layout.sections'))->not->toBeEmpty();
});

it('form-context: a half-filled product line is dropped, not rejected', function () {
    $actor = requestCreateOperativeActor();
    ['line' => $line] = categoryWithAttribute('material');
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/request-management/form-context', [
        'product_lines' => [
            ['business_function_id' => $line['business_function_id'], 'product_category_id' => null],
        ],
    ])->assertOk();

    expect($response->json('data.applicable_attributes'))->toBe([]);
    expect($response->json('data.attribute_layout'))->toBeNull();
});

it('form-context: an unknown product category -> 422', function () {
    Sanctum::actingAs(requestCreateOperativeActor());

    $this->postJson('/api/request-management/form-context', [
        'product_lines' => [['business_function_id' => null, 'product_category_id' => 99999]],
    ])->assertStatus(422)->assertJsonValidationErrors('product_lines.0.product_category_id');
});

// ---------------------------------------------------------------------------
// POST /api/request-management — the operative fields at creation
// ---------------------------------------------------------------------------

it('create: next_callback_at and general_notes are persisted and read back', function () {
    $actor = requestCreateOperativeActor();
    ['line' => $line] = categoryWithAttribute('material');
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/request-management', [
        'registry_id' => Registry::factory()->create()->id,
        'source_id' => Source::factory()->create()->id,
        'product_lines' => [$line],
        'next_callback_at' => '2026-09-01T10:30',
        'general_notes' => 'Il cliente richiama a settembre.',
    ])->assertCreated();

    expect($response->json('data.next_callback_at'))->toBe('2026-09-01T10:30');
    expect($response->json('data.context.general_notes'))->toBe('Il cliente richiama a settembre.');

    $opportunity = Opportunity::query()->sole();
    expect($opportunity->next_callback_at->format('Y-m-d\TH:i'))->toBe('2026-09-01T10:30')
        ->and($opportunity->general_notes)->toBe('Il cliente richiama a settembre.');
});

it('create: attribute_values are validated against the applicable set and persisted', function () {
    $actor = requestCreateOperativeActor();
    ['line' => $line] = categoryWithAttribute('material');
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/request-management', [
        'registry_id' => Registry::factory()->create()->id,
        'source_id' => Source::factory()->create()->id,
        'product_lines' => [$line],
        'attribute_values' => ['material' => 'steel'],
    ])->assertCreated();

    expect($response->json('data.attribute_values'))->toBe(['material' => 'steel']);
    expect(Opportunity::query()->sole()->attribute_values)->toBe(['material' => 'steel']);
});

it('create: an attribute_value whose code is not applicable -> 422, nothing created', function () {
    $actor = requestCreateOperativeActor();
    ['line' => $line] = categoryWithAttribute('material');
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management', [
        'registry_id' => Registry::factory()->create()->id,
        'source_id' => Source::factory()->create()->id,
        'product_lines' => [$line],
        'attribute_values' => ['not_applicable_here' => 'x'],
    ])->assertStatus(422);

    expect(Opportunity::count())->toBe(0);
});

it('create: a working status outside the resolved set -> 422', function () {
    $actor = requestCreateOperativeActor();
    ['line' => $line] = categoryWithAttribute('material');
    // Belongs to a workflow's OWN set, never to the global default one the
    // submitted criteria resolve to.
    $foreign = OpportunityWorkflowStatus::factory()->create(['sort_order' => 99]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management', [
        'registry_id' => Registry::factory()->create()->id,
        'source_id' => Source::factory()->create()->id,
        'product_lines' => [$line],
        'opportunity_workflow_status_id' => $foreign->id,
    ])->assertStatus(422)->assertJsonValidationErrors('opportunity_workflow_status_id');

    expect(Opportunity::count())->toBe(0);
});

it('create: a working status of the resolved set is persisted', function () {
    $actor = requestCreateOperativeActor();
    ['line' => $line] = categoryWithAttribute('material');
    $target = OpportunityWorkflowStatus::query()
        ->whereNull('opportunity_workflow_id')
        ->where('system_key', 'validated')
        ->sole();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/request-management', [
        'registry_id' => Registry::factory()->create()->id,
        'source_id' => Source::factory()->create()->id,
        'product_lines' => [$line],
        'opportunity_workflow_status_id' => $target->id,
    ])->assertCreated();

    expect($response->json('data.workflow_status.id'))->toBe($target->id);
    expect(Opportunity::query()->sole()->opportunity_workflow_status_id)->toBe($target->id);
});

it('create: a requires_note working status without a note -> 422, nothing created', function () {
    // The status-change note is created through the SAME collaborative-notes
    // mechanism the panel uses, which authorizes READ access to its host: the
    // creator must also be able to view the request they just opened.
    $actor = requestCreateOperativeActor(['create', 'view', 'viewAll'], canCreateNotes: true);
    ['line' => $line] = categoryWithAttribute('material');
    $target = OpportunityWorkflowStatus::factory()->create([
        'opportunity_workflow_id' => null,
        'requires_note' => true,
        'sort_order' => 99,
    ]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management', [
        'registry_id' => Registry::factory()->create()->id,
        'source_id' => Source::factory()->create()->id,
        'product_lines' => [$line],
        'opportunity_workflow_status_id' => $target->id,
    ])->assertStatus(422)->assertJsonValidationErrors('note');

    expect(Opportunity::count())->toBe(0);
});

it('create: a requires_note working status WITH a note -> 201, status set and note created', function () {
    // The status-change note is created through the SAME collaborative-notes
    // mechanism the panel uses, which authorizes READ access to its host: the
    // creator must also be able to view the request they just opened.
    $actor = requestCreateOperativeActor(['create', 'view', 'viewAll'], canCreateNotes: true);
    ['line' => $line] = categoryWithAttribute('material');
    $target = OpportunityWorkflowStatus::factory()->create([
        'opportunity_workflow_id' => null,
        'requires_note' => true,
        'sort_order' => 99,
    ]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management', [
        'registry_id' => Registry::factory()->create()->id,
        'source_id' => Source::factory()->create()->id,
        'product_lines' => [$line],
        'opportunity_workflow_status_id' => $target->id,
        'note' => 'Gia validata al primo contatto.',
    ])->assertCreated();

    $opportunity = Opportunity::query()->sole();
    expect($opportunity->opportunity_workflow_status_id)->toBe($target->id);

    $note = Note::query()
        ->where('notable_type', $opportunity->getMorphClass())
        ->where('notable_id', $opportunity->id)
        ->sole();

    expect($note->body)->toBe('Gia validata al primo contatto.')
        ->and($note->user_id)->toBe($actor->id);
});

it('create: none of the operative fields submitted leaves the record exactly as before', function () {
    $actor = requestCreateOperativeActor();
    ['line' => $line] = categoryWithAttribute('material');
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management', [
        'registry_id' => Registry::factory()->create()->id,
        'source_id' => Source::factory()->create()->id,
        'product_lines' => [$line],
    ])->assertCreated();

    $opportunity = Opportunity::query()->sole();
    expect($opportunity->next_callback_at)->toBeNull()
        ->and($opportunity->general_notes)->toBeNull()
        ->and($opportunity->attribute_values ?? [])->toBe([])
        // Still the resolver's own choice (the initial `open` row), untouched.
        ->and($opportunity->workflowStatus->system_key)->toBe('open');
});

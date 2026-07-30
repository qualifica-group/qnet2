<?php

use App\Models\DocumentLayout;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\QuoteStatus;
use App\Models\Referent;
use App\Models\Role;
use App\Models\User;
use Database\Factories\DocumentLayoutFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * quotes.layout_id (spec 0070, MT-11): the schema (AC-202/AC-203), the D-3
 * default resolution at create (AC-210/211/212), the module/active
 * server-side guard (AC-213/214/216), the explicit-null write on PATCH
 * (AC-215), the D-8 regression on the 4 pre-existing snapshot fields
 * (AC-217), the GET /api/quotes/{id} additive shape (AC-218) and the field
 * permission ceiling (AC-219).
 */
uses(RefreshDatabase::class);

if (! function_exists('quoteLayoutUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function quoteLayoutUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
            Permission::findOrCreate("quotes.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("quotes.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('quoteLayoutNewStatus')) {
    function quoteLayoutNewStatus(): QuoteStatus
    {
        return QuoteStatus::where('system_key', 'new')->sole();
    }
}

if (! function_exists('quoteLayoutDefault')) {
    /** An active, default `quotes` layout. */
    function quoteLayoutDefault(): DocumentLayout
    {
        return DocumentLayout::factory()->create(['module' => 'quotes', 'is_active' => true, 'is_default' => true]);
    }
}

if (! function_exists('quoteLayoutInactive')) {
    /** An inactive, non-default `quotes` layout. */
    function quoteLayoutInactive(): DocumentLayout
    {
        return DocumentLayout::factory()->create(['module' => 'quotes', 'is_active' => false, 'is_default' => false]);
    }
}

if (! function_exists('quoteLayoutOtherModule')) {
    /**
     * A DocumentLayout row belonging to a module OTHER than `quotes`,
     * inserted directly (the only enum case today is Quotes: the write API
     * itself would reject any other module value, so this must bypass it —
     * exactly the "wrong module" row the guard must reject without crashing
     * on the model's enum cast, see ValidatesQuoteLayout's own docblock).
     */
    function quoteLayoutOtherModule(): int
    {
        return (int) DB::table('document_layouts')->insertGetId([
            'name' => 'Foreign module layout',
            'code' => 'foreign_module_layout',
            'description' => null,
            'module' => 'invoices',
            'is_active' => 1,
            'is_default' => 0,
            'config' => json_encode(DocumentLayoutFactory::minimalConfig()),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

// ---------------------------------------------------------------------------
// AC-202/AC-203 — schema
// ---------------------------------------------------------------------------

it('AC-202: quotes.layout_id is nullable with a FK to document_layouts', function () {
    $layout = DocumentLayout::factory()->create(['module' => 'quotes']);
    $quote = Quote::factory()->create(['layout_id' => $layout->id]);

    expect($quote->fresh()->layout_id)->toBe($layout->id);

    $this->assertDatabaseHas('quotes', ['id' => $quote->id, 'layout_id' => $layout->id]);
});

it('AC-203: deleting a DocumentLayout at the DB level nulls layout_id and the quote survives', function () {
    $layout = DocumentLayout::factory()->create(['module' => 'quotes']);
    $quote = Quote::factory()->create(['layout_id' => $layout->id]);

    DB::table('document_layouts')->where('id', $layout->id)->delete();

    expect($quote->fresh())
        ->not->toBeNull()
        ->layout_id->toBeNull();
});

// ---------------------------------------------------------------------------
// AC-210/211/212 — default resolution at create (D-3)
// ---------------------------------------------------------------------------

it('AC-210: create without layout_id resolves the module\'s active default layout', function () {
    quoteLayoutNewStatus();
    $default = quoteLayoutDefault();
    $opportunity = Opportunity::factory()->create();
    Sanctum::actingAs(quoteLayoutUserWith(['create']));

    $this->postJson('/api/quotes', ['title' => 'Senza layout', 'opportunity_id' => $opportunity->id])
        ->assertCreated()
        ->assertJsonPath('data.layout_id', $default->id);
});

it('AC-211: create without layout_id when no active default exists succeeds with layout_id null', function () {
    quoteLayoutNewStatus();
    $opportunity = Opportunity::factory()->create();
    Sanctum::actingAs(quoteLayoutUserWith(['create']));

    $this->postJson('/api/quotes', ['title' => 'Nessun layout', 'opportunity_id' => $opportunity->id])
        ->assertCreated()
        ->assertJsonPath('data.layout_id', null);
});

it('AC-212: an explicit layout_id wins over the module default', function () {
    quoteLayoutNewStatus();
    quoteLayoutDefault();
    $picked = DocumentLayout::factory()->create(['module' => 'quotes', 'is_active' => true]);
    $opportunity = Opportunity::factory()->create();
    Sanctum::actingAs(quoteLayoutUserWith(['create']));

    $this->postJson('/api/quotes', [
        'title' => 'Layout esplicito',
        'opportunity_id' => $opportunity->id,
        'layout_id' => $picked->id,
    ])
        ->assertCreated()
        ->assertJsonPath('data.layout_id', $picked->id);
});

// ---------------------------------------------------------------------------
// AC-213/214 — module/active guard on create
// ---------------------------------------------------------------------------

it('AC-213: a layout_id from another module is rejected with layout_module_mismatch', function () {
    quoteLayoutNewStatus();
    $foreignId = quoteLayoutOtherModule();
    $opportunity = Opportunity::factory()->create();
    Sanctum::actingAs(quoteLayoutUserWith(['create']));

    $this->postJson('/api/quotes', [
        'title' => 'Layout altro modulo',
        'opportunity_id' => $opportunity->id,
        'layout_id' => $foreignId,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['layout_id']);
});

it('AC-213: a nonexistent layout_id is rejected with 422', function () {
    quoteLayoutNewStatus();
    $opportunity = Opportunity::factory()->create();
    Sanctum::actingAs(quoteLayoutUserWith(['create']));

    $this->postJson('/api/quotes', [
        'title' => 'Layout inesistente',
        'opportunity_id' => $opportunity->id,
        'layout_id' => 999999,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['layout_id']);
});

it('AC-214: an inactive layout_id is rejected with layout_inactive on create', function () {
    quoteLayoutNewStatus();
    $inactive = quoteLayoutInactive();
    $opportunity = Opportunity::factory()->create();
    Sanctum::actingAs(quoteLayoutUserWith(['create']));

    $this->postJson('/api/quotes', [
        'title' => 'Layout disattivo',
        'opportunity_id' => $opportunity->id,
        'layout_id' => $inactive->id,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['layout_id']);

    expect(Quote::count())->toBe(0);
});

// ---------------------------------------------------------------------------
// AC-215/216 — PATCH: explicit null wins, persisted-but-deactivated exception
// ---------------------------------------------------------------------------

it('AC-215: PATCH {layout_id: null} clears a previously set layout', function () {
    $layout = DocumentLayout::factory()->create(['module' => 'quotes']);
    $quote = Quote::factory()->create(['layout_id' => $layout->id]);
    Sanctum::actingAs(quoteLayoutUserWith(['view', 'update']));

    $this->patchJson("/api/quotes/{$quote->id}", ['layout_id' => null])
        ->assertOk()
        ->assertJsonPath('data.layout_id', null);
});

it('AC-216: PATCH without layout_id on a quote whose persisted layout is now inactive stays 200', function () {
    $inactive = quoteLayoutInactive();
    $quote = Quote::factory()->create(['layout_id' => $inactive->id]);
    Sanctum::actingAs(quoteLayoutUserWith(['view', 'update']));

    $this->patchJson("/api/quotes/{$quote->id}", ['title' => 'Titolo aggiornato'])
        ->assertOk()
        ->assertJsonPath('data.layout_id', $inactive->id)
        ->assertJsonPath('data.title', 'Titolo aggiornato');
});

it('AC-216: PATCH resubmitting the SAME now-inactive layout_id stays 200', function () {
    $inactive = quoteLayoutInactive();
    $quote = Quote::factory()->create(['layout_id' => $inactive->id]);
    Sanctum::actingAs(quoteLayoutUserWith(['view', 'update']));

    $this->patchJson("/api/quotes/{$quote->id}", ['layout_id' => $inactive->id])
        ->assertOk()
        ->assertJsonPath('data.layout_id', $inactive->id);
});

it('AC-216: PATCH to a DIFFERENT inactive layout_id is rejected', function () {
    $currentInactive = quoteLayoutInactive();
    $otherInactive = quoteLayoutInactive();
    $quote = Quote::factory()->create(['layout_id' => $currentInactive->id]);
    Sanctum::actingAs(quoteLayoutUserWith(['view', 'update']));

    $this->patchJson("/api/quotes/{$quote->id}", ['layout_id' => $otherInactive->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['layout_id']);

    expect($quote->fresh()->layout_id)->toBe($currentInactive->id);
});

// ---------------------------------------------------------------------------
// AC-217 — D-8 regression: applySnapshotDefaults() untouched, no inheritance
// ---------------------------------------------------------------------------

it('AC-217: layout_id is NOT inherited from the Opportunity, while the 4 snapshot fields still are', function () {
    quoteLayoutNewStatus();
    $commercial = Referent::factory()->create();
    $reporter = Referent::factory()->create();
    $supervisor = User::factory()->create();
    $opportunity = Opportunity::factory()->create([
        'commercial_id' => $commercial->id,
        'reporter_id' => $reporter->id,
        'supervisor_id' => $supervisor->id,
    ]);
    Sanctum::actingAs(quoteLayoutUserWith(['create']));

    $response = $this->postJson('/api/quotes', [
        'title' => 'Regressione D-8',
        'opportunity_id' => $opportunity->id,
    ])->assertCreated();

    $response
        ->assertJsonPath('data.commercial_id', $commercial->id)
        ->assertJsonPath('data.reporter_id', $reporter->id)
        ->assertJsonPath('data.supervisor_id', $supervisor->id)
        ->assertJsonPath('data.layout_id', null);
});

// ---------------------------------------------------------------------------
// AC-218 — GET /api/quotes/{id}: additive shape
// ---------------------------------------------------------------------------

it('AC-218: GET show exposes layout_id/layout, null when unset, without altering existing keys', function () {
    $layout = DocumentLayout::factory()->create(['module' => 'quotes', 'name' => 'Offerta economica']);
    $withLayout = Quote::factory()->create(['layout_id' => $layout->id]);
    $withoutLayout = Quote::factory()->create(['layout_id' => null]);
    Sanctum::actingAs(quoteLayoutUserWith(['view']));

    $this->getJson("/api/quotes/{$withLayout->id}")
        ->assertOk()
        ->assertJsonPath('data.layout_id', $layout->id)
        ->assertJsonPath('data.layout.id', $layout->id)
        ->assertJsonPath('data.layout.name', 'Offerta economica')
        ->assertJsonPath('data.code', $withLayout->code)
        ->assertJsonPath('data.title', $withLayout->title);

    $this->getJson("/api/quotes/{$withoutLayout->id}")
        ->assertOk()
        ->assertJsonPath('data.layout_id', null)
        ->assertJsonPath('data.layout', null);
});

// ---------------------------------------------------------------------------
// AC-219 — field permission ceiling
// ---------------------------------------------------------------------------

it('AC-219: PATCH changing layout_id is 422 when the role denies it via role_field_permissions', function () {
    foreach (['viewAny', 'view', 'update'] as $ability) {
        Permission::findOrCreate("quotes.{$ability}");
    }

    $role = Role::create(['name' => 'quote-layout-locked']);
    $role->givePermissionTo(['quotes.view', 'quotes.update']);
    $role->fieldPermissions()->create([
        'resource' => 'quotes',
        'field' => 'layout_id',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);

    $actor = User::factory()->create();
    $actor->assignRole($role);

    $original = DocumentLayout::factory()->create(['module' => 'quotes']);
    $another = DocumentLayout::factory()->create(['module' => 'quotes']);
    $target = Quote::factory()->create(['layout_id' => $original->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/quotes/{$target->id}", ['layout_id' => $another->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('layout_id');

    expect($target->fresh()->layout_id)->toBe($original->id);
});

it('AC-219: GET /api/meta/quotes exposes layout_id with the expected ceiling', function () {
    $actor = quoteLayoutUserWith(['viewAny', 'create']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/meta/quotes')->assertOk();
    $fields = $response->json('permissions.fields');

    expect($fields)->toHaveKey('layout_id')
        ->and($fields['layout_id']['editable'])->toBeTrue()
        ->and($fields['layout_id']['visible'])->toBeTrue()
        ->and($fields['layout_id']['required'])->toBeFalse();
});

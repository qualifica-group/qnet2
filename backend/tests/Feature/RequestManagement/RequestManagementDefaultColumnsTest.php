<?php

use App\Enums\ContactTypeEnum;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Direttiva utente 2026-09-17: the grid's default worklist is exactly thirteen
// visible columns, in a fixed order, "Email" among them (a new client column,
// the twin of "Telefono"); every other column stays in the catalogue, hidden.

uses(RefreshDatabase::class);

if (! function_exists('defaultColumnsActor')) {
    /** @param  array<int, string>  $abilities */
    function defaultColumnsActor(array $abilities): User
    {
        foreach (['viewAny', 'view', 'update', 'viewAll'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("request-management.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('defaultColumnsRequest')) {
    /** A quote whose client card carries the given primary email (and a primary phone). */
    function defaultColumnsRequest(?string $email, string $lastName = 'Rossi', ?User $operator = null): Quote
    {
        $registry = Registry::factory()->create();
        $card = $registry->personalData()->create(['type' => 'individual', 'first_name' => 'Mario', 'last_name' => $lastName]);
        $card->contacts()->create(['type' => 'phone', 'value' => '021234567', 'is_primary' => true]);

        if ($email !== null) {
            $card->contacts()->create(['type' => 'email', 'value' => $email, 'is_primary' => true]);
        }

        $opportunity = Opportunity::factory()->create(['registry_id' => $registry->id]);

        return Quote::factory()->for($opportunity)->create(['operator_id' => $operator?->id]);
    }
}

if (! function_exists('defaultColumnsRowIds')) {
    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, int>
     */
    function defaultColumnsRowIds(array $payload): array
    {
        return collect(
            test()->postJson('/api/tables/request-management/rows', ['startRow' => 0, 'endRow' => 25, ...$payload])
                ->assertOk()
                ->json('items')
        )->pluck('id')->all();
    }
}

// ---------------------------------------------------------------------------
// Default layout
// ---------------------------------------------------------------------------

it('shows exactly the requested columns, in the requested order, and hides the rest', function () {
    Sanctum::actingAs(defaultColumnsActor(['viewAny', 'viewAll']));

    $columns = collect($this->getJson('/api/tables/request-management/columns')->assertOk()->json('data.columns'));

    expect($columns->where('visible', true)->pluck('id')->all())->toBe([
        'product_categories',
        'operator_ga2',
        'quote_workflow_status',
        'next_callback_at',
        'offer_lines',
        'first_name',
        'last_name',
        'phone',
        'email',
        'tax_code',
        'general_notes',
        'source',
        'manager_ga1',
    ])->and($columns->where('visible', false)->pluck('id')->all())->toBe([
        // The engine's own hidden row-id column (AbstractTableDefinition).
        'id',
        'pending_change_requests',
        'operational_site',
        'is_transferred',
        'vat_number',
        'created_at',
    ]);
});

it('gives every visible column its requested default width, and none to the hidden ones', function () {
    Sanctum::actingAs(defaultColumnsActor(['viewAny', 'viewAll']));

    $columns = collect($this->getJson('/api/tables/request-management/columns')->assertOk()->json('data.columns'));

    expect($columns->where('visible', true)->pluck('width', 'id')->all())->toBe([
        'product_categories' => 214,
        'operator_ga2' => 201,
        'quote_workflow_status' => 212,
        'next_callback_at' => 198,
        'offer_lines' => 214,
        'first_name' => 120,
        'last_name' => 137,
        'phone' => 145,
        'email' => 206,
        'tax_code' => 171,
        'general_notes' => 239,
        'source' => 127,
        'manager_ga1' => 177,
    ])->and($columns->where('visible', false)->pluck('width')->unique()->all())->toBe([null]);
});

it('keeps the hidden operational_site on the row, so the Operatore picker scope still resolves', function () {
    Sanctum::actingAs(defaultColumnsActor(['viewAny', 'viewAll']));
    defaultColumnsRequest('mario@example.test');

    $row = $this->postJson('/api/tables/request-management/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()
        ->json('items.0');

    expect($row)->toHaveKey('operational_site');
});

// ---------------------------------------------------------------------------
// Email: read, search, filter, sort, value list
// ---------------------------------------------------------------------------

it('projects the primary email contact, never a non-primary one', function () {
    Sanctum::actingAs(defaultColumnsActor(['viewAny', 'viewAll']));
    $quote = defaultColumnsRequest('mario@example.test');
    $card = $quote->opportunity->registry->personalData;
    $card->contacts()->create(['type' => 'email', 'value' => 'altro@example.test', 'is_primary' => false]);

    $this->postJson('/api/tables/request-management/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()
        ->assertJsonPath('items.0.email', 'mario@example.test');
});

it('advertises email as an editable, sortable, text-filterable, searchable column', function () {
    Sanctum::actingAs(defaultColumnsActor(['viewAny', 'viewAll', 'update']));

    $response = $this->getJson('/api/tables/request-management/columns')->assertOk();
    $column = collect($response->json('data.columns'))->keyBy('id')['email'];

    expect($column['editable'])->toBeTrue()
        ->and($column['sortable'])->toBeTrue()
        ->and($column['filterable'])->toBeTrue()
        ->and($column['filterType'])->toBe('text')
        ->and($response->json('data.searchable'))->toContain('email');
});

it('matches the primary email in the global search and the text filter', function () {
    Sanctum::actingAs(defaultColumnsActor(['viewAny', 'viewAll']));
    $match = defaultColumnsRequest('mario@example.test');
    defaultColumnsRequest('giulia@example.test');

    expect(defaultColumnsRowIds(['search' => 'mario@']))->toBe([$match->id])
        ->and(defaultColumnsRowIds([
            'filterModel' => ['email' => ['filterType' => 'text', 'type' => 'contains', 'filter' => 'mario@']],
        ]))->toBe([$match->id]);
});

it('sorts by the primary email', function () {
    Sanctum::actingAs(defaultColumnsActor(['viewAny', 'viewAll']));
    $zulu = defaultColumnsRequest('zeno@example.test');
    $alpha = defaultColumnsRequest('anna@example.test');

    expect(defaultColumnsRowIds(['sortModel' => [['colId' => 'email', 'sort' => 'asc']]]))->toBe([$alpha->id, $zulu->id])
        ->and(defaultColumnsRowIds(['sortModel' => [['colId' => 'email', 'sort' => 'desc']]]))->toBe([$zulu->id, $alpha->id]);
});

it('lists the distinct primary emails, with the blank entry for a client without one', function () {
    Sanctum::actingAs(defaultColumnsActor(['viewAny', 'viewAll']));
    defaultColumnsRequest('mario@example.test');
    $blank = defaultColumnsRequest(null, 'Bianchi');

    $values = $this->postJson('/api/tables/request-management/values', ['columnId' => 'email'])->assertOk()->json('data.values');

    expect($values)->toContain('mario@example.test')
        ->and($values)->toContain(null)
        ->and($values)->not->toContain('021234567')
        ->and(defaultColumnsRowIds([
            'filterModel' => ['email' => ['filterType' => 'set', 'values' => [null]]],
        ]))->toBe([$blank->id]);
});

// ---------------------------------------------------------------------------
// Email: inline edit (update in place, create, clear, validation)
// ---------------------------------------------------------------------------

it('updates the primary email in place, canonical lowercase, leaving the phone alone', function () {
    $actor = defaultColumnsActor(['viewAny', 'update']);
    $quote = defaultColumnsRequest('vecchia@example.test', operator: $actor);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'email',
        'value' => '  Mario.Rossi@Example.TEST ',
    ])->assertOk()->assertJsonPath('data.email', 'mario.rossi@example.test');

    $contacts = $quote->opportunity->registry->personalData->contacts()->get();

    expect($contacts)->toHaveCount(2)
        ->and($contacts->firstWhere('type', ContactTypeEnum::Email)->value)->toBe('mario.rossi@example.test')
        ->and($contacts->firstWhere('type', ContactTypeEnum::Phone)->value)->toBe('021234567');
});

it('creates a primary email contact when the client has none', function () {
    $actor = defaultColumnsActor(['viewAny', 'update']);
    $quote = defaultColumnsRequest(null, operator: $actor);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'email',
        'value' => 'nuova@example.test',
    ])->assertOk();

    /** @var Contact $created */
    $created = $quote->opportunity->registry->personalData->contacts()->where('type', ContactTypeEnum::Email->value)->firstOrFail();

    expect($created->value)->toBe('nuova@example.test')
        ->and((bool) $created->is_primary)->toBeTrue();
});

it('clearing email removes the email row and keeps the phone', function () {
    $actor = defaultColumnsActor(['viewAny', 'update']);
    $quote = defaultColumnsRequest('mario@example.test', operator: $actor);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'email',
        'value' => null,
    ])->assertOk()->assertJsonPath('data.email', null);

    expect($quote->opportunity->registry->personalData->contacts()->pluck('type')->all())
        ->toBe([ContactTypeEnum::Phone]);
});

it('rejects a malformed email with 422 and keeps the stored one', function () {
    $actor = defaultColumnsActor(['viewAny', 'update']);
    $quote = defaultColumnsRequest('mario@example.test', operator: $actor);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'email',
        'value' => 'non-una-mail',
    ])->assertStatus(422);

    expect($quote->opportunity->registry->personalData->contacts()->where('type', 'email')->value('value'))
        ->toBe('mario@example.test');
});

it('without request-management.update the email cell is read-only and a PATCH is 403', function () {
    $actor = defaultColumnsActor(['viewAny']);
    $quote = defaultColumnsRequest('mario@example.test', operator: $actor);
    Sanctum::actingAs($actor);

    $column = collect($this->getJson('/api/tables/request-management/columns')->assertOk()->json('data.columns'))
        ->keyBy('id')['email'];

    expect($column['editable'])->toBeFalse();

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'email',
        'value' => 'altra@example.test',
    ])->assertForbidden();
});

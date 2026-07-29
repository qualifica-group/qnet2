<?php

use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\QuoteStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * `quotes.code` (spec 0065, D-13): the sequential/manual QUO-0001 pattern,
 * verified end-to-end over HTTP (POST/PATCH/next-code), mirroring
 * ProjectCodeTest/ProductCodeTest.
 */
uses(RefreshDatabase::class);

if (! function_exists('quoteCodeUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function quoteCodeUserWith(array $abilities): User
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

if (! function_exists('quoteCodeNewStatus')) {
    function quoteCodeNewStatus(): QuoteStatus
    {
        return QuoteStatus::where('system_key', 'new')->sole();
    }
}

it('AC-066: creating without a code (absent) assigns the sequential QUO-0001', function () {
    quoteCodeNewStatus();
    $opportunity = Opportunity::factory()->create();
    $actor = quoteCodeUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/quotes', ['title' => 'Offerta', 'opportunity_id' => $opportunity->id])
        ->assertCreated()
        ->assertJsonPath('data.code', 'QUO-0001');
});

it('AC-066: an explicit null or empty-string code also falls back to the sequential generator', function () {
    quoteCodeNewStatus();
    $opportunity = Opportunity::factory()->create();
    $actor = quoteCodeUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/quotes', ['title' => 'Uno', 'opportunity_id' => $opportunity->id, 'code' => null])
        ->assertCreated()->assertJsonPath('data.code', 'QUO-0001');

    $this->postJson('/api/quotes', ['title' => 'Due', 'opportunity_id' => $opportunity->id, 'code' => ''])
        ->assertCreated()->assertJsonPath('data.code', 'QUO-0002');
});

it('AC-067: a manual code is persisted as-is and does not break the sequence', function () {
    quoteCodeNewStatus();
    $opportunity = Opportunity::factory()->create();
    $actor = quoteCodeUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/quotes', ['title' => 'Manuale', 'opportunity_id' => $opportunity->id, 'code' => 'OFF-2026/1'])
        ->assertCreated()
        ->assertJsonPath('data.code', 'OFF-2026/1');

    $this->postJson('/api/quotes', ['title' => 'Sequenziale', 'opportunity_id' => $opportunity->id])
        ->assertCreated()
        ->assertJsonPath('data.code', 'QUO-0001');
});

it('AC-068: a duplicate code produces a 422 on the code field, no row created', function () {
    quoteCodeNewStatus();
    $opportunity = Opportunity::factory()->create();
    Quote::factory()->create(['opportunity_id' => $opportunity->id, 'code' => 'QUO-DUP']);
    $actor = quoteCodeUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/quotes', ['title' => 'Duplicato', 'opportunity_id' => $opportunity->id, 'code' => 'QUO-DUP'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('code');

    expect(Quote::where('code', 'QUO-DUP')->count())->toBe(1);
});

it('AC-068: a 33-character code produces a 422 (max 32)', function () {
    $opportunity = Opportunity::factory()->create();
    $actor = quoteCodeUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/quotes', [
        'title' => 'Troppo lungo',
        'opportunity_id' => $opportunity->id,
        'code' => str_repeat('A', 33),
    ])->assertStatus(422)->assertJsonValidationErrors('code');
});

it('AC-069: PATCH with a code different from the persisted one is rejected', function () {
    quoteCodeNewStatus();
    $quote = Quote::factory()->create(['code' => 'QUO-0001']);
    $actor = quoteCodeUserWith(['update']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/quotes/{$quote->id}", ['code' => 'QUO-9999'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('code');

    expect($quote->fresh()->code)->toBe('QUO-0001');
});

it('AC-069: PATCH resubmitting the SAME code is a no-op that passes', function () {
    quoteCodeNewStatus();
    $quote = Quote::factory()->create(['code' => 'QUO-0001', 'title' => 'Before']);
    $actor = quoteCodeUserWith(['update']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/quotes/{$quote->id}", ['code' => 'QUO-0001', 'title' => 'After'])
        ->assertOk()
        ->assertJsonPath('data.code', 'QUO-0001')
        ->assertJsonPath('data.title', 'After');
});

// ---------------------------------------------------------------------------
// GET /api/quotes/next-code (AC-069b)
// ---------------------------------------------------------------------------

it('AC-069b: next-code returns QUO-0001 on an empty table and the next free code after N quotes', function () {
    quoteCodeNewStatus();
    $opportunity = Opportunity::factory()->create();
    $actor = quoteCodeUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/quotes/next-code')->assertOk()->assertJsonPath('data.code', 'QUO-0001');

    // 3 sequentially-generated quotes (no manual code), so the next free
    // code is deterministically QUO-0004 — a factory-made row would carry a
    // RANDOM code (QuoteFactory::configure()), unsuited to this assertion.
    foreach (range(1, 3) as $n) {
        $this->postJson('/api/quotes', ['title' => "Quote {$n}", 'opportunity_id' => $opportunity->id])->assertCreated();
    }

    $this->getJson('/api/quotes/next-code')->assertOk()->assertJsonPath('data.code', 'QUO-0004');
});

it('AC-069b: next-code is 403 without quotes.create', function () {
    $actor = quoteCodeUserWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/quotes/next-code')->assertForbidden();
});

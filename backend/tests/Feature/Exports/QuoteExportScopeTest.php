<?php

use App\Models\ExportRun;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * `POST /api/exports/quotes` scoped to one Opportunity (spec 0067, D-5,
 * AC-015..AC-018). `QUEUE_CONNECTION=sync` in phpunit.xml runs
 * GenerateExportJob inline within the HTTP request, so these are true
 * end-to-end assertions on the generated file — not just on ExportRun::state.
 */
uses(RefreshDatabase::class);

if (! function_exists('quoteExportActorWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function quoteExportActorWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export'] as $ability) {
            Permission::findOrCreate("quotes.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("quotes.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('quoteExportPayload')) {
    /**
     * @return array<string, mixed>
     */
    function quoteExportPayload(array $overrides = []): array
    {
        return array_merge([
            'format' => 'csv',
            'columns' => [
                ['colId' => 'code', 'header' => 'Code'],
            ],
        ], $overrides);
    }
}

if (! function_exists('csvExportRows')) {
    /**
     * @return array<int, array<int, string>>
     */
    function csvExportRows(string $csv): array
    {
        $lines = array_filter(explode("\n", trim($csv, "\xEF\xBB\xBF\n")));

        return array_map(static fn (string $line): array => str_getcsv($line, ',', '"', ''), $lines);
    }
}

// ---------------------------------------------------------------------------
// AC-015 — opportunityId scopes both ExportRun::state AND the generated file
// ---------------------------------------------------------------------------

it('freezes opportunityId in ExportRun::state and generates a file with ONLY that Opportunity\'s Offerte', function () {
    Storage::fake('local');
    $actor = quoteExportActorWith(['viewAny', 'export']);
    Sanctum::actingAs($actor);

    $opportunityA = Opportunity::factory()->create();
    $opportunityB = Opportunity::factory()->create();
    $quotesA = Quote::factory()->count(3)->create(['opportunity_id' => $opportunityA->id]);
    Quote::factory()->count(2)->create(['opportunity_id' => $opportunityB->id]);

    $response = $this->postJson('/api/exports/quotes', quoteExportPayload(['opportunityId' => $opportunityA->id]))
        ->assertCreated();

    $run = ExportRun::findOrFail($response->json('data.export_run.id'));

    expect($run->state['opportunityId'])->toBe($opportunityA->id)
        ->and($run->fresh()->row_count)->toBe(3);

    $rows = csvExportRows(Storage::disk('local')->get($run->fresh()->file_path));

    expect($rows)->toHaveCount(4); // header + 3 Offerte of A
    $exportedCodes = array_column(array_slice($rows, 1), 0);
    expect($exportedCodes)->toEqualCanonicalizing($quotesA->pluck('code')->all());
});

// ---------------------------------------------------------------------------
// AC-016 — non-regression: omitting opportunityId still exports every Offerta
// ---------------------------------------------------------------------------

it('exports every Offerta when opportunityId is omitted (non-regression)', function () {
    Storage::fake('local');
    $actor = quoteExportActorWith(['viewAny', 'export']);
    Sanctum::actingAs($actor);

    $opportunityA = Opportunity::factory()->create();
    $opportunityB = Opportunity::factory()->create();
    Quote::factory()->count(3)->create(['opportunity_id' => $opportunityA->id]);
    Quote::factory()->count(2)->create(['opportunity_id' => $opportunityB->id]);

    $response = $this->postJson('/api/exports/quotes', quoteExportPayload())->assertCreated();

    $run = ExportRun::findOrFail($response->json('data.export_run.id'));

    expect($run->state)->not->toHaveKey('opportunityId')
        ->and($run->fresh()->row_count)->toBe(5);
});

// ---------------------------------------------------------------------------
// AC-017 — unknown opportunityId => 422, no ExportRun created
// ---------------------------------------------------------------------------

it('422s on an unknown opportunityId and creates no ExportRun', function () {
    Storage::fake('local');
    $actor = quoteExportActorWith(['viewAny', 'export']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/exports/quotes', quoteExportPayload(['opportunityId' => 999999]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('opportunityId');

    expect(ExportRun::query()->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// AC-018 — 403 without quotes.export even with opportunityId set
// ---------------------------------------------------------------------------

it('403s without quotes.export even when opportunityId is passed', function () {
    Storage::fake('local');
    $actor = quoteExportActorWith(['viewAny']);
    Sanctum::actingAs($actor);

    $opportunity = Opportunity::factory()->create();

    $this->postJson('/api/exports/quotes', quoteExportPayload(['opportunityId' => $opportunity->id]))
        ->assertForbidden();

    expect(ExportRun::query()->count())->toBe(0);
});

<?php

use App\Jobs\GenerateExportJob;
use App\Models\ExportRun;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0130, D-7/AC-013 — `POST /api/exports/enrollee-management` is
// authorized against its OWN `enrollee-management.export` ability (never
// `quotes.export`, never `request-management.export`), and the generated
// file/row_count is scoped to D-2 (validated/closed_won) + D-5 (perimeter),
// exactly like `POST /api/tables/enrollee-management/rows`.

uses(RefreshDatabase::class);

if (! function_exists('enrolleeStatusId')) {
    function enrolleeStatusId(string $systemKey): int
    {
        return QuoteWorkflowStatus::query()
            ->whereNull('quote_workflow_id')
            ->where('system_key', $systemKey)
            ->value('id')
            ?? QuoteWorkflowStatus::factory()->global()->system($systemKey)->create()->id;
    }
}

if (! function_exists('enrolleeQuote')) {
    /**
     * @param  array<string, mixed>  $attributes
     */
    function enrolleeQuote(string $systemKey, array $attributes = []): Quote
    {
        return Quote::factory()->create([
            ...$attributes,
            'quote_workflow_status_id' => enrolleeStatusId($systemKey),
        ]);
    }
}

if (! function_exists('enrolleeExportActorWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function enrolleeExportActorWith(array $abilities): User
    {
        foreach (['viewAny', 'viewAll', 'export'] as $ability) {
            Permission::findOrCreate("enrollee-management.{$ability}");
            Permission::findOrCreate("request-management.{$ability}");
        }
        Permission::findOrCreate('quotes.export');

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo($ability);
        }

        return $user;
    }
}

if (! function_exists('enrolleeExportPayload')) {
    /**
     * @return array<string, mixed>
     */
    function enrolleeExportPayload(array $overrides = []): array
    {
        return array_merge([
            'format' => 'csv',
            // `is_transferred`: a real, always-populated boolean column
            // (`source`, a nullable relation QuoteFactory leaves unset, would
            // render every row blank and make a line-count assertion
            // meaningless); `id` is NOT exportable — CreateExportRequest's
            // allow-list reads the raw `columns()` catalogue, which never
            // carries InjectsDefaultIdColumn's grid-only default.
            'columns' => [
                ['colId' => 'is_transferred', 'header' => 'Trasferito'],
            ],
        ], $overrides);
    }
}

// ---------------------------------------------------------------------------
// AC-013 — authorization: {domain}.export, never quotes.export nor the other
// module's own export ability
// ---------------------------------------------------------------------------

it('201s for an actor holding ONLY enrollee-management.export', function () {
    Queue::fake();
    Sanctum::actingAs(enrolleeExportActorWith(['enrollee-management.export']));

    $this->postJson('/api/exports/enrollee-management', enrolleeExportPayload())->assertCreated();
});

it('403s an actor holding ONLY quotes.export (D-7: no longer sufficient)', function () {
    Queue::fake();
    Sanctum::actingAs(enrolleeExportActorWith(['quotes.export']));

    $this->postJson('/api/exports/enrollee-management', enrolleeExportPayload())->assertForbidden();

    Queue::assertNotPushed(GenerateExportJob::class);
});

it('403s an actor holding ONLY request-management.export on the enrollee-management export (AC-003/AC-013)', function () {
    Queue::fake();
    Sanctum::actingAs(enrolleeExportActorWith(['request-management.export']));

    $this->postJson('/api/exports/enrollee-management', enrolleeExportPayload())->assertForbidden();
});

it('403s an actor holding ONLY enrollee-management.export on the request-management export (AC-003/AC-013)', function () {
    Queue::fake();
    Sanctum::actingAs(enrolleeExportActorWith(['enrollee-management.export']));

    $this->postJson('/api/exports/request-management', enrolleeExportPayload())->assertForbidden();
});

it('201s the request-management export for its own request-management.export ability, unaffected by D-7 (regression)', function () {
    Queue::fake();
    Sanctum::actingAs(enrolleeExportActorWith(['request-management.export']));

    $this->postJson('/api/exports/request-management', enrolleeExportPayload())->assertCreated();
});

// ---------------------------------------------------------------------------
// AC-013 — the generated file/row_count carry ONLY D-2 + D-5 rows
// ---------------------------------------------------------------------------

it('the generated export contains ONLY validated/closed_won rows in the actor\'s D-5 perimeter (AC-013)', function () {
    Storage::fake('local');
    $actor = enrolleeExportActorWith(['enrollee-management.viewAny', 'enrollee-management.viewAll', 'enrollee-management.export']);
    Sanctum::actingAs($actor);

    enrolleeQuote('validated');
    enrolleeQuote('closed_won');
    enrolleeQuote('open');
    enrolleeQuote('closed_lost');

    $response = $this->postJson('/api/exports/enrollee-management', enrolleeExportPayload())->assertCreated();
    $run = ExportRun::findOrFail($response->json('data.export_run.id'));

    expect($run->fresh()->row_count)->toBe(2);

    $csv = Storage::disk('local')->get($run->fresh()->file_path);
    $lines = array_filter(explode("\n", trim($csv, "\xEF\xBB\xBF\n")));
    expect($lines)->toHaveCount(3); // header + validated + closed_won
});

it('the export scopes rows to the actor\'s own operated offers without viewAll (D-5)', function () {
    Storage::fake('local');
    $actor = enrolleeExportActorWith(['enrollee-management.viewAny', 'enrollee-management.export']);
    Sanctum::actingAs($actor);

    $mine = enrolleeQuote('validated', ['operator_id' => $actor->id]);
    enrolleeQuote('closed_won');

    $response = $this->postJson('/api/exports/enrollee-management', enrolleeExportPayload())->assertCreated();
    $run = ExportRun::findOrFail($response->json('data.export_run.id'));

    expect($run->fresh()->row_count)->toBe(1);
});

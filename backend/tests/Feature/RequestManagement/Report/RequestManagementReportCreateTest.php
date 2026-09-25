<?php

use App\Enums\ExportFormat;
use App\Enums\ExportStatus;
use App\Jobs\GenerateRequestManagementReportJob;
use App\Models\ExportRun;
use App\Models\ProductCategory;
use App\Models\User;
use App\Services\RequestManagement\Report\ReportBranchResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// POST /api/request-management/report (spec 0106 data_contract, AC-001/002/
// 003/025; rev-2 data_contract_delta, AC-030/031/035).

uses(RefreshDatabase::class);

if (! function_exists('reportActorWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function reportActorWith(array $abilities): User
    {
        foreach (['report', 'viewAny', 'viewAll'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("request-management.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('reportCreateCategoryTree')) {
    /**
     * Two reportable categories (spec 0131) — the FormRequest's category_keys
     * allow-list is empty without at least one, which would 422 a payload
     * before the controller's own authorization gate ever runs.
     *
     * @return array<string, ProductCategory>
     */
    function reportCreateCategoryTree(): array
    {
        return [
            'gol' => ProductCategory::factory()->reportable()->create(['name' => 'GOL']),
            'consulenza' => ProductCategory::factory()->reportable()->create(['name' => 'Consulenza']),
        ];
    }
}

if (! function_exists('reportPayload')) {
    /**
     * A valid POST body: the two dates plus rev-2's two REQUIRED fields
     * (category_keys/row_mode) and the file format, overridable per test.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function reportPayload(array $overrides = []): array
    {
        return array_merge([
            'date_from' => '2026-09-01',
            'date_to' => '2026-09-30',
            'category_keys' => app(ReportBranchResolver::class)->keys(),
            'row_mode' => 'all',
            'format' => 'csv',
        ], $overrides);
    }
}

// ---------------------------------------------------------------------------
// AC-001
// ---------------------------------------------------------------------------

it('201s with a processing run whose state freezes the dates, category_keys, row_mode and the actor locale (AC-001)', function () {
    $categories = reportCreateCategoryTree();
    $actor = reportActorWith(['report']);
    Sanctum::actingAs($actor);
    Queue::fake();

    $selectedKeys = [(string) $categories['gol']->id, (string) $categories['consulenza']->id];

    $response = $this->withHeader('Accept-Language', 'it')
        ->postJson('/api/request-management/report', reportPayload(['category_keys' => $selectedKeys, 'row_mode' => 'total_only']))
        ->assertCreated();

    $response->assertJsonPath('success', true)
        ->assertJsonPath('data.export_run.status', 'processing');

    $run = ExportRun::query()->findOrFail($response->json('data.export_run.id'));

    expect($run->resource)->toBe('request-management-report')
        ->and($run->status)->toBe(ExportStatus::Processing)
        ->and($run->state)->toBe([
            'date_from' => '2026-09-01',
            'date_to' => '2026-09-30',
            'locale' => 'it',
            'category_keys' => $selectedKeys,
            'row_mode' => 'total_only',
        ]);

    Queue::assertPushed(GenerateRequestManagementReportJob::class);
});

// ---------------------------------------------------------------------------
// AC-002
// ---------------------------------------------------------------------------

it('403s on create without request-management.report (AC-002)', function () {
    reportCreateCategoryTree();
    $actor = reportActorWith([]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/report', reportPayload())
        ->assertForbidden()
        ->assertJsonPath('success', false);
});

it('never leaks an internal class/model name in the 403 envelope (AC-002)', function () {
    reportCreateCategoryTree();
    $actor = reportActorWith([]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/request-management/report', reportPayload())->assertForbidden();

    expect($response->json('message'))->not->toContain('App\\');
});

// ---------------------------------------------------------------------------
// AC-003
// ---------------------------------------------------------------------------

// Spec 0169 D-2/D-4: either bound may be left open (absent, null or empty),
// the run freezes the open side as null and the file name drops it.
it('accepts an open date bound and names the file after the bounds it has', function (array $dates, ?string $from, ?string $to, string $fileName) {
    reportCreateCategoryTree();
    $actor = reportActorWith(['report']);
    Sanctum::actingAs($actor);
    Queue::fake();

    $payload = array_merge(reportPayload(), $dates);
    foreach (['date_from', 'date_to'] as $field) {
        if (! array_key_exists($field, $dates)) {
            unset($payload[$field]);
        }
    }

    $response = $this->postJson('/api/request-management/report', $payload)->assertCreated();

    $run = ExportRun::query()->findOrFail($response->json('data.export_run.id'));

    expect($run->state['date_from'])->toBe($from)
        ->and($run->state['date_to'])->toBe($to)
        ->and($run->original_filename)->toBe($fileName);
})->with([
    'only date_to' => [['date_to' => '2026-09-25'], null, '2026-09-25', 'request-management-report-to-2026-09-25.csv'],
    'only date_from' => [['date_from' => '2026-09-01'], '2026-09-01', null, 'request-management-report-from-2026-09-01.csv'],
    'neither' => [[], null, null, 'request-management-report.csv'],
    'both null' => [['date_from' => null, 'date_to' => null], null, null, 'request-management-report.csv'],
    'both empty' => [['date_from' => '', 'date_to' => ''], null, null, 'request-management-report.csv'],
]);

it('422s on a malformed date format (AC-003)', function () {
    $actor = reportActorWith(['report']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/report', reportPayload(['date_from' => '01/09/2026']))
        ->assertStatus(422)->assertJsonValidationErrors('date_from');
});

it('422s when date_to is before date_from (AC-003)', function () {
    $actor = reportActorWith(['report']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/report', reportPayload(['date_from' => '2026-09-30', 'date_to' => '2026-09-01']))
        ->assertStatus(422)->assertJsonValidationErrors('date_to');
});

// ---------------------------------------------------------------------------
// AC-030 (rev-2) — category_keys allow-list
// ---------------------------------------------------------------------------

it('422s when category_keys is missing (AC-030)', function () {
    $actor = reportActorWith(['report']);
    Sanctum::actingAs($actor);

    $payload = reportPayload();
    unset($payload['category_keys']);

    $this->postJson('/api/request-management/report', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors('category_keys');
});

it('422s when category_keys is empty (AC-030)', function () {
    $actor = reportActorWith(['report']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/report', reportPayload(['category_keys' => []]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('category_keys');
});

it('422s when category_keys contains a key not in config, and never queries with it (AC-030)', function () {
    $actor = reportActorWith(['report']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/report', reportPayload(['category_keys' => ['gol', 'not-a-real-branch']]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('category_keys.1');

    expect(ExportRun::query()->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// AC-031 (rev-2) — row_mode allow-list
// ---------------------------------------------------------------------------

it('422s when row_mode is missing (AC-031)', function () {
    $actor = reportActorWith(['report']);
    Sanctum::actingAs($actor);

    $payload = reportPayload();
    unset($payload['row_mode']);

    $this->postJson('/api/request-management/report', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors('row_mode');
});

it('422s when row_mode is not one of total_only|operators_only|all (AC-031)', function () {
    $actor = reportActorWith(['report']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/report', reportPayload(['row_mode' => 'bogus']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('row_mode');
});

// ---------------------------------------------------------------------------
// AC-025 — no throttle on the report routes
// ---------------------------------------------------------------------------

it('never throttles the create endpoint across many rapid requests (AC-025)', function () {
    reportCreateCategoryTree();
    $actor = reportActorWith(['report']);
    Sanctum::actingAs($actor);
    Queue::fake();

    for ($i = 0; $i < 20; $i++) {
        $this->postJson('/api/request-management/report', reportPayload())->assertCreated();
    }
});

// ---------------------------------------------------------------------------
// Format allow-list (user directive 2026-09-08)
// ---------------------------------------------------------------------------

it('stores the requested xlsx format on the run, extension included', function () {
    reportCreateCategoryTree();
    $actor = reportActorWith(['report']);
    Sanctum::actingAs($actor);
    Queue::fake();

    $response = $this->postJson('/api/request-management/report', reportPayload(['format' => 'xlsx']))
        ->assertCreated();

    $run = ExportRun::query()->findOrFail($response->json('data.export_run.id'));

    expect($run->format)->toBe(ExportFormat::Xlsx)
        ->and($run->original_filename)->toBe('request-management-report-2026-09-01_2026-09-30.xlsx');
});

it('stores the requested csv format on the run, extension included', function () {
    reportCreateCategoryTree();
    $actor = reportActorWith(['report']);
    Sanctum::actingAs($actor);
    Queue::fake();

    $response = $this->postJson('/api/request-management/report', reportPayload(['format' => 'csv']))
        ->assertCreated();

    $run = ExportRun::query()->findOrFail($response->json('data.export_run.id'));

    expect($run->format)->toBe(ExportFormat::Csv)
        ->and($run->original_filename)->toBe('request-management-report-2026-09-01_2026-09-30.csv');
});

it('422s when format is missing', function () {
    $actor = reportActorWith(['report']);
    Sanctum::actingAs($actor);

    $payload = reportPayload();
    unset($payload['format']);

    $this->postJson('/api/request-management/report', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors('format');
});

it('422s when format is outside config(exports.formats)', function () {
    $actor = reportActorWith(['report']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/report', reportPayload(['format' => 'pdf']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('format');
});

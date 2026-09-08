<?php

use App\Enums\ExportStatus;
use App\Jobs\GenerateRequestManagementReportJob;
use App\Models\ExportRun;
use App\Models\User;
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

if (! function_exists('reportPayload')) {
    /**
     * A valid POST body: the two dates plus rev-2's two REQUIRED fields
     * (category_keys/row_mode), overridable per test.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function reportPayload(array $overrides = []): array
    {
        return array_merge([
            'date_from' => '2026-09-01',
            'date_to' => '2026-09-30',
            'category_keys' => array_keys((array) config('request-management-report.branches')),
            'row_mode' => 'all',
        ], $overrides);
    }
}

// ---------------------------------------------------------------------------
// AC-001
// ---------------------------------------------------------------------------

it('201s with a processing run whose state freezes the dates, category_keys, row_mode and the actor locale (AC-001)', function () {
    $actor = reportActorWith(['report']);
    Sanctum::actingAs($actor);
    Queue::fake();

    $response = $this->withHeader('Accept-Language', 'it')
        ->postJson('/api/request-management/report', reportPayload(['category_keys' => ['gol', 'consulenza'], 'row_mode' => 'total_only']))
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
            'category_keys' => ['gol', 'consulenza'],
            'row_mode' => 'total_only',
        ]);

    Queue::assertPushed(GenerateRequestManagementReportJob::class);
});

// ---------------------------------------------------------------------------
// AC-002
// ---------------------------------------------------------------------------

it('403s on create without request-management.report (AC-002)', function () {
    $actor = reportActorWith([]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/report', reportPayload())
        ->assertForbidden()
        ->assertJsonPath('success', false);
});

it('never leaks an internal class/model name in the 403 envelope (AC-002)', function () {
    $actor = reportActorWith([]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/request-management/report', reportPayload())->assertForbidden();

    expect($response->json('message'))->not->toContain('App\\');
});

// ---------------------------------------------------------------------------
// AC-003
// ---------------------------------------------------------------------------

it('422s when date_from is missing (AC-003)', function () {
    $actor = reportActorWith(['report']);
    Sanctum::actingAs($actor);

    $payload = reportPayload();
    unset($payload['date_from']);

    $this->postJson('/api/request-management/report', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors('date_from');
});

it('422s when date_to is missing (AC-003)', function () {
    $actor = reportActorWith(['report']);
    Sanctum::actingAs($actor);

    $payload = reportPayload();
    unset($payload['date_to']);

    $this->postJson('/api/request-management/report', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors('date_to');
});

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
    $actor = reportActorWith(['report']);
    Sanctum::actingAs($actor);
    Queue::fake();

    for ($i = 0; $i < 20; $i++) {
        $this->postJson('/api/request-management/report', reportPayload())->assertCreated();
    }
});

<?php

use App\Enums\HealthStatusEnum;
use App\Services\System\Checks\HealthCheck;
use App\Services\System\SystemHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Integrations\Formalab\Health\FormalabHealthCheck;
use Integrations\Formalab\Models\FormalabCallLog;
use Integrations\Formalab\Models\FormalabSyncRun;

uses(RefreshDatabase::class);

function formalabHealth(): array
{
    return app(FormalabHealthCheck::class)->run();
}

function formalabHealthDetail(array $result, string $key): array
{
    return collect($result['details'])->firstWhere('key', $key);
}

function formalabRecentSync(string $outcome = 'success', int $minutesAgo = 1): void
{
    FormalabSyncRun::query()->create(['trigger' => 'schedule', 'outcome' => $outcome, 'started_at' => now()->subMinutes($minutesAgo), 'finished_at' => now()->subMinutes($minutesAgo)]);
}

// AC-011
it('is down when the configuration is missing, without calling Formalab', function () {
    formalabEnable(['user_email' => '']);
    Http::preventStrayRequests();

    $result = formalabHealth();

    expect($result['status'])->toBe(HealthStatusEnum::Down)
        ->and(formalabHealthDetail($result, 'configuration')['status'])->toBe(HealthStatusEnum::Down)
        ->and(FormalabCallLog::query()->count())->toBe(0);
});

it('is ok with a live connection and a recent successful sync', function () {
    formalabEnable();
    formalabFakeApi([formalabClassPayload(1)]);
    formalabRecentSync();
    formalabCourseAttributes();

    $result = formalabHealth();

    expect($result['status'])->toBe(HealthStatusEnum::Ok)
        ->and(collect($result['details'])->pluck('key')->all())->toBe(['configuration', 'connection', 'last_sync', 'recent_failures', 'course_fields'])
        ->and(formalabHealthDetail($result, 'course_fields')['value'])->toBe('12/12')
        ->and(formalabHealthDetail($result, 'configuration')['value'])->toBe('formalab.test')
        ->and(formalabHealthDetail($result, 'connection')['value'])->toMatch('/^\d+ ms$/')
        ->and(FormalabCallLog::query()->where('trigger', 'health')->count())->toBe(2);
});

it('is degraded when a course field code was renamed or deleted and lists the missing ones', function () {
    formalabEnable();
    formalabFakeApi([formalabClassPayload(1)]);
    formalabRecentSync();
    $attributes = formalabCourseAttributes();
    $attributes['gol_data_esame']->update(['code' => 'gol_exam_date_renamed']);
    $attributes['gol_stato_classe']->delete();

    $result = formalabHealth();
    $detail = formalabHealthDetail($result, 'course_fields');

    expect($result['status'])->toBe(HealthStatusEnum::Degraded)
        ->and($detail['status'])->toBe(HealthStatusEnum::Degraded)
        ->and($detail['value'])->toBe('gol_stato_classe, gol_data_esame')
        ->and($detail['message'])->not->toBeNull();
});

it('is down when the sync never ran', function () {
    formalabEnable();
    formalabFakeApi([]);

    $result = formalabHealth();

    expect($result['status'])->toBe(HealthStatusEnum::Down)
        ->and(formalabHealthDetail($result, 'last_sync')['status'])->toBe(HealthStatusEnum::Down);
});

it('is degraded when the last sync is older than three intervals or failed', function () {
    formalabEnable(['sync_interval_minutes' => 10]);
    formalabFakeApi([]);
    formalabRecentSync('success', 45);

    expect(formalabHealthDetail(formalabHealth(), 'last_sync')['status'])->toBe(HealthStatusEnum::Degraded);

    formalabRecentSync('failure', 1);

    expect(formalabHealthDetail(formalabHealth(), 'last_sync')['status'])->toBe(HealthStatusEnum::Degraded);
});

it('is degraded by failed calls in the last 24 hours and ignores older ones', function () {
    formalabEnable();
    formalabFakeApi([]);
    formalabRecentSync();
    formalabCourseAttributes(); // REQUIREMENT CHANGED: the course fields are part of an all-ok baseline
    formalabCallLog(['outcome' => 'failure', 'status_code' => 500], now()->subDays(2));

    expect(formalabHealth()['status'])->toBe(HealthStatusEnum::Ok);

    formalabCallLog(['outcome' => 'failure', 'status_code' => 500]);
    $result = formalabHealth();

    expect($result['status'])->toBe(HealthStatusEnum::Degraded)
        ->and(formalabHealthDetail($result, 'recent_failures')['value'])->toBe('1');
});

it('is down when Formalab does not answer the probe', function () {
    formalabEnable();
    Http::preventStrayRequests();
    Http::fake(['*/api/login' => Http::response(['accessToken' => 't']), '*/api/classi*' => Http::response('x', 500)]);
    formalabRecentSync();

    $result = formalabHealth();

    expect($result['status'])->toBe(HealthStatusEnum::Down)->and($result['latency_ms'])->toBeNull()
        ->and(formalabHealthDetail($result, 'connection')['status'])->toBe(HealthStatusEnum::Down);
});

it('includes in SystemHealthService every check tagged system-health.checks and isolates its failures', function () {
    $extra = new class implements HealthCheck
    {
        public function key(): string
        {
            return 'extra';
        }

        public function run(): array
        {
            return ['status' => HealthStatusEnum::Degraded, 'latency_ms' => null, 'message' => 'meh', 'details' => []];
        }
    };
    $broken = new class implements HealthCheck
    {
        public function key(): string
        {
            return 'broken';
        }

        public function run(): array
        {
            throw new RuntimeException('boom');
        }
    };
    app()->instance('test.extra-check', $extra);
    app()->instance('test.broken-check', $broken);
    app()->tag(['test.extra-check', 'test.broken-check'], 'system-health.checks');

    $report = app(SystemHealthService::class)->handle();
    $checks = collect($report['checks'])->keyBy('key');

    expect($checks->keys()->all())->toBe(['database', 'email', 'queue', 'security', 'extra', 'broken'])
        ->and($checks['extra']['status'])->toBe('degraded')
        ->and($checks['broken']['status'])->toBe('down')
        ->and($report['overall'])->toBe('down');
});

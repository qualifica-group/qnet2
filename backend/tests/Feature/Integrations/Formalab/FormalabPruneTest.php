<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Integrations\Formalab\Models\FormalabCallLog;
use Integrations\Formalab\Models\FormalabSyncRun;

uses(RefreshDatabase::class);

// AC-012
it('prunes logs and sync runs older than the retention window', function () {
    formalabEnable(['log_retention_days' => 30]);
    formalabCallLog(at: now()->subDays(31));
    formalabCallLog(at: now()->subDays(29));
    $run = ['trigger' => 'schedule', 'outcome' => 'success'];
    FormalabSyncRun::query()->create($run + ['started_at' => now()->subDays(31)]);
    FormalabSyncRun::query()->create($run + ['started_at' => now()->subDays(1)]);

    $this->artisan('model:prune', ['--model' => [FormalabCallLog::class, FormalabSyncRun::class]])->assertSuccessful();

    expect(FormalabCallLog::query()->count())->toBe(1)->and(FormalabSyncRun::query()->count())->toBe(1);
});

it('registers the two console commands only when enabled', function () {
    expect(array_key_exists('formalab:sync-classes', Artisan::all()))->toBeFalse();

    formalabEnable();
    formalabFakeApi([formalabClassPayload(1)]);

    $this->artisan('formalab:sync-classes')->assertSuccessful();
    $this->artisan('formalab:link-work-orders')->assertSuccessful();
});

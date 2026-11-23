<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Integrations\Formalab\Jobs\SyncFormalabClasses;
use Integrations\Formalab\Models\FormalabClass;
use Integrations\Formalab\Models\FormalabSyncRun;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/** A catalogue run still open, started the given seconds ago. */
function formalabOpenRun(int $secondsAgo = 0, string $trigger = 'schedule'): FormalabSyncRun
{
    return FormalabSyncRun::query()->create(['trigger' => $trigger, 'outcome' => 'running', 'started_at' => now()->subSeconds($secondsAgo)]);
}

/** Past the sync timeout plus the launcher's margin: the worker that held the run is gone. */
function formalabAbandonedAge(): int
{
    return (int) config('formalab.sync_timeout_seconds') + 301;
}

// User request 2026-11-23: own queue, never two syncs together
it('queues the sync on the Formalab queue, never retried and with a timeout', function () {
    formalabEnable();
    Queue::fake();
    Sanctum::actingAs(formalabSuperAdmin());

    $this->postJson('/api/integrations/formalab/sync')->assertStatus(202);

    $run = FormalabSyncRun::query()->sole();
    expect($run->trigger)->toBe('manual')->and($run->outcome)->toBe('running');
    Queue::assertPushedOn('formalab', SyncFormalabClasses::class, fn (SyncFormalabClasses $job) => $job->runId === $run->id
        && $job->tries === 1 && $job->timeout === (int) config('formalab.sync_timeout_seconds'));
});

it('refuses a second sync while one is queued or running, and says so in the status', function () {
    formalabEnable();
    Queue::fake();
    Sanctum::actingAs(formalabSuperAdmin());

    $this->postJson('/api/integrations/formalab/sync')->assertStatus(202);
    $this->getJson('/api/integrations/formalab/status')->assertJsonPath('data.sync_in_progress', true);

    $this->postJson('/api/integrations/formalab/sync')->assertStatus(409)
        ->assertJsonPath('message', __('formalab::messages.sync_already_running'));

    Queue::assertPushed(SyncFormalabClasses::class, 1);
    expect(FormalabSyncRun::query()->count())->toBe(1);
});

it('skips the scheduled turn while a sync is running, and queues it otherwise', function () {
    formalabEnable();
    Queue::fake();
    $event = collect(app(Schedule::class)->events())->firstWhere('description', 'formalab:sync-classes');

    formalabOpenRun(60, 'manual');
    $event->run(app());
    Queue::assertNothingPushed();

    FormalabSyncRun::query()->update(['outcome' => 'success', 'finished_at' => now()]);
    $event->run(app());
    Queue::assertPushedOn('formalab', SyncFormalabClasses::class);
    expect(FormalabSyncRun::query()->where('trigger', 'schedule')->value('outcome'))->toBe('running');
});

it('closes as interrupted a run its worker abandoned, and starts a new one', function () {
    formalabEnable();
    Queue::fake();
    $abandoned = formalabOpenRun(formalabAbandonedAge());
    Sanctum::actingAs(formalabSuperAdmin());

    $this->getJson('/api/integrations/formalab/status')->assertJsonPath('data.sync_in_progress', false);
    $this->postJson('/api/integrations/formalab/sync')->assertStatus(202);

    expect($abandoned->refresh()->outcome)->toBe('failure')
        ->and($abandoned->finished_at)->not->toBeNull()
        ->and($abandoned->error_message)->toBe(__('formalab::messages.sync_interrupted'))
        ->and(FormalabSyncRun::query()->where('outcome', 'running')->count())->toBe(1);
});

it('does not count the single-edition refreshes as a running catalogue sync', function () {
    formalabEnable();
    Queue::fake();
    formalabOpenRun(0, 'webhook');
    Sanctum::actingAs(formalabSuperAdmin());

    $this->postJson('/api/integrations/formalab/sync')->assertStatus(202);
});

it('closes its run as interrupted when the job fails or times out', function () {
    formalabEnable();
    $run = formalabOpenRun();

    (new SyncFormalabClasses($run->id))->failed(new RuntimeException('timed out'));

    expect($run->refresh()->outcome)->toBe('failure')->and($run->error_message)->toBe(__('formalab::messages.sync_interrupted'));
});

it('does not run a job whose run was closed while it waited in the queue', function () {
    formalabEnable();
    Http::preventStrayRequests();
    Http::fake();
    $run = formalabOpenRun();
    $run->update(['outcome' => 'failure', 'finished_at' => now()]);

    dispatch_sync(new SyncFormalabClasses($run->id));

    Http::assertNothingSent();
    expect($run->refresh()->outcome)->toBe('failure')->and(FormalabClass::query()->count())->toBe(0);
});

it('runs the queued sync to the end and frees the next one', function () {
    formalabEnable();
    formalabFakeApi([formalabClassPayload(1)]);
    Sanctum::actingAs(formalabSuperAdmin());

    $this->postJson('/api/integrations/formalab/sync')->assertStatus(202);

    expect(FormalabSyncRun::query()->sole()->outcome)->toBe('success')->and(FormalabClass::query()->count())->toBe(1);
    $this->getJson('/api/integrations/formalab/status')->assertJsonPath('data.sync_in_progress', false);
    $this->postJson('/api/integrations/formalab/sync')->assertStatus(202);
});

it('refuses the console sync while another is running', function () {
    formalabEnable();
    formalabOpenRun();

    $this->artisan('formalab:sync-classes')->expectsOutput(__('formalab::messages.sync_already_running'))->assertFailed();
    expect(FormalabSyncRun::query()->count())->toBe(1);
});

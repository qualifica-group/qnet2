<?php

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Attachment;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('AC-017: prunes only conversations idle for more than retention_days, with their files', function () {
    Storage::fake('local');
    config()->set('ai-assistant.retention_days', 30);
    $user = aiapi_user();

    $stale = aiapi_conversation($user, ['last_message_at' => now()->subDays(31)]);
    $fresh = aiapi_conversation($user, ['last_message_at' => now()->subDays(29)]);
    $neverAnswered = aiapi_conversation($user);
    $neverAnswered->forceFill(['created_at' => now()->subDays(40)])->saveQuietly();

    $staleFile = AiMessage::factory()->for($stale, 'conversation')->create()->attach(aiapi_pdf());
    $freshFile = AiMessage::factory()->for($fresh, 'conversation')->create()->attach(aiapi_pdf());

    $this->artisan('ai-assistant:prune')->assertSuccessful();

    expect(AiConversation::query()->pluck('id')->all())->toBe([$fresh->id])
        ->and(AiMessage::query()->count())->toBe(1)
        ->and(Attachment::query()->pluck('id')->all())->toBe([$freshFile->id]);
    Storage::disk('local')->assertMissing($staleFile->path);
    Storage::disk('local')->assertExists($freshFile->path);
});

it('AC-017: is registered in the daily scheduler', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains((string) $event->command, 'ai-assistant:prune'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('30 3 * * *')
        ->and($event->withoutOverlapping)->toBeTrue()
        ->and($event->onOneServer)->toBeTrue();
});

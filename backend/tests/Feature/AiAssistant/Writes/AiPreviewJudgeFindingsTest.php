<?php

use App\Enums\AiTurnErrorCode;
use App\Enums\AiTurnStatus;
use App\Jobs\AiAssistant\ExpireAiPreview;
use App\Services\AiAssistant\Agent\AiTurnRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\PersonalAccessToken;

/*
| Judge findings: conversations are private even to the super-admin (Gate::before must not reach them),
| and a write whose outcome may exist is never reported as "nothing was written".
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    aiapi_configure();
    Queue::fake([ExpireAiPreview::class]);
});

/** @return list<string> */
function judge_confirm_requests(): array
{
    return Http::recorded()->map(fn (array $pair): string => $pair[0]->url())
        ->filter(fn (string $url): bool => str_ends_with($url, '/assistant/confirm'))->values()->all();
}

it('a super-admin who does not own the conversation is refused on every route and the connector is not called', function () {
    [$turn, , $conversation] = aiapi_waiting_turn();
    $admin = aiapi_super_admin();

    aiapi_decide($turn, 'confirm', $admin)->assertForbidden();
    aiapi_decide($turn, 'cancel', $admin)->assertForbidden();
    aiapi_turn_state($turn, $admin)->assertForbidden();

    app('auth')->forgetGuards();
    $this->withToken($admin->createToken('x')->plainTextToken);
    $this->getJson("/api/ai-assistant/conversations/{$conversation->id}")->assertForbidden();
    $this->deleteJson("/api/ai-assistant/conversations/{$conversation->id}")->assertForbidden();
    aiapi_send($admin, $conversation)->assertForbidden();

    app('auth')->forgetGuards();
    $this->withToken($admin->createToken('y')->plainTextToken)->getJson('/api/ai-assistant/conversations')->assertOk()->assertJsonCount(0, 'items');

    expect(judge_confirm_requests())->toBe([])
        ->and($turn->fresh()->status)->toBe(AiTurnStatus::AwaitingConfirmation)
        ->and($conversation->fresh())->not->toBeNull();
});

it('a cancel after a confirmation was left in flight is uncertain, never "nothing was written"', function () {
    [$turn] = aiapi_waiting_turn();
    $turn->update(['resume_state' => [...$turn->fresh()->resume_state, 'confirming' => true]]);

    Queue::fake();
    aiapi_decide($turn, 'cancel')->assertStatus(202)->assertJsonPath('data.preview.outcome', 'uncertain');

    $pending = $turn->fresh()->resume_state['pending'];
    expect($pending['outcome'])->toBe('uncertain')
        ->and($pending['result'])->toContain('Do not repeat the change')
        ->and($pending['result'])->not->toContain('Nothing was written')
        ->and(judge_confirm_requests())->toBe([]);
});

it('a link that falls while a confirmation was in flight still ends with the uncertain message', function () {
    [$turn] = aiapi_waiting_turn();
    $turn->update(['resume_state' => [...$turn->fresh()->resume_state, 'confirming' => true]]);
    PersonalAccessToken::query()->findOrFail($turn->personal_access_token_id)->delete();

    aiapi_turn_state($turn)->assertOk();
    app(AiTurnRunner::class)->run($turn->id);

    $closed = $turn->fresh();
    expect($closed->preview['outcome'])->toBe('uncertain')
        ->and($closed->error_code)->toBe(AiTurnErrorCode::LinkLost)
        ->and($closed->assistant_text)->toContain('outcome of the change is not known')
        ->and($closed->assistant_message_id)->not->toBeNull()
        ->and(judge_confirm_requests())->toBe([]);
});

it('the stale-turn command reports the outcome of a confirmed write whose turn never resumed', function () {
    [$turn, $user, $conversation] = aiapi_waiting_turn();
    Queue::fake();
    aiapi_decide($turn, 'confirm')->assertStatus(202)->assertJsonPath('data.status', 'queued');

    $this->travel(3)->days();
    Artisan::call('ai-assistant:close-stale-turns');

    $closed = $turn->fresh();
    expect($closed->status)->toBe(AiTurnStatus::Failed)
        ->and($closed->error_code)->toBe(AiTurnErrorCode::TurnTimeout)
        ->and($closed->resume_state)->toBeNull()
        ->and($closed->assistant_text)->toContain('was confirmed and written');

    app('auth')->forgetGuards();
    $this->withToken($user->createToken('r')->plainTextToken)->getJson("/api/ai-assistant/conversations/{$conversation->id}")
        ->assertOk()->assertJsonFragment(['content' => $closed->assistant_text]);
});

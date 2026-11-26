<?php

use App\Enums\AiTurnStatus;
use App\Models\AiMessage;
use App\Models\AiTurn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

const ACTIVE_TURNS_TEXT = 'You have too many assistant requests running at once. Wait for one to finish.';

beforeEach(function () {
    aiapi_configure();
    Queue::fake();
});

// Spec 0433: a person has at most `max_active_turns_per_user` turns not final, over all their conversations.
it('answers 409 with a fixed text when the person already has the maximum of active turns, and writes nothing', function () {
    $user = aiapi_user();
    foreach (range(1, 3) as $ignored) {
        aiapi_send($user, aiapi_conversation($user))->assertStatus(202);
    }

    aiapi_send($user, aiapi_conversation($user), ['content' => 'one too many'])->assertStatus(409)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', ACTIVE_TURNS_TEXT);

    expect(AiTurn::query()->count())->toBe(3)->and(AiMessage::query()->where('content', 'one too many')->exists())->toBeFalse();
});

it('counts a turn that waits for a confirmation, not a final one', function () {
    $user = aiapi_user();
    foreach (range(1, 3) as $ignored) {
        aiapi_send($user, aiapi_conversation($user))->assertStatus(202);
    }
    $turns = AiTurn::query()->orderBy('id')->get();
    $turns[0]->update(['status' => AiTurnStatus::AwaitingConfirmation]);
    $turns[1]->update(['status' => AiTurnStatus::Completed]);
    $turns[2]->update(['status' => AiTurnStatus::Failed]);

    aiapi_send($user, aiapi_conversation($user))->assertStatus(202);
    aiapi_send($user, aiapi_conversation($user))->assertStatus(202);
    aiapi_send($user, aiapi_conversation($user))->assertStatus(409);
});

it('leaves the other people alone and takes the maximum from the configuration', function () {
    config()->set('ai-assistant.agent.max_active_turns_per_user', 1);
    $user = aiapi_user();
    $other = aiapi_user();
    aiapi_send($user, aiapi_conversation($user))->assertStatus(202);

    aiapi_send($user, aiapi_conversation($user))->assertStatus(409);

    aiapi_send($other, aiapi_conversation($other))->assertStatus(202);
});

it('says it in Italian too', function () {
    app()->setLocale('it');

    expect(__(ACTIVE_TURNS_TEXT))->toBe('Hai troppe richieste in corso con l\'assistente. Attendi che ne finisca una.');
});

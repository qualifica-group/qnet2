<?php

use App\Enums\AiTurnErrorCode;
use App\Enums\AiTurnStatus;
use App\Models\AiCostEntry;
use App\Models\AiCostReservation;
use App\Models\AiMessage;
use App\Models\AiSpendCounter;
use App\Models\AiTurn;
use App\Models\BusinessFunction;
use App\Services\AiAssistant\Settings\AiAssistantSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    aiapi_configure();
});

function costs_cap(string $field, string $value): void
{
    app(AiAssistantSettingsService::class)->current()->update([$field => $value]);
}

it('D-7: every model call is reserved, priced with the ceiling, settled with usage.cost and written in the ledger', function () {
    aiapi_fake_agent([aiapi_tool_body('list_records', ['{}']), aiapi_text_body(['ok'])]);
    $user = aiapi_user();

    aiapi_send($user, aiapi_conversation($user))->assertStatus(202);

    $payloads = aiapi_sent('chat/completions');
    $entries = AiCostEntry::query()->orderBy('id')->get();
    expect($payloads)->toHaveCount(2)
        ->and($payloads[0]['provider']['max_price'])->toBe(['prompt' => '1', 'completion' => '2', 'request' => '0'])
        ->and($payloads[0]['max_tokens'])->toBe(4096)
        ->and($entries)->toHaveCount(2)
        ->and($entries->pluck('cost_usd')->map(fn ($c) => rtrim($c, '0'))->all())->toBe(['0.0005', '0.0012'])
        ->and($entries[1]->user_id)->toBe($user->id)
        ->and($entries[1]->model_id)->toBe('model-x')
        ->and((int) $entries[1]->cached_tokens)->toBe(40)
        ->and($entries[0]->shares()->count())->toBe(1)
        ->and(AiCostReservation::query()->count())->toBe(0)
        ->and(AiSpendCounter::query()->where('user_id', $user->id)->value('spent_usd'))->toStartWith('0.0017');
});

it('D-7: the cost shares follow the functions photographed on the message of the turn', function () {
    aiapi_fake_agent([aiapi_text_body()]);
    $user = aiapi_user();
    $first = BusinessFunction::factory()->create();
    $second = BusinessFunction::factory()->create();
    $user->businessFunctions()->attach([$first->id, $second->id]);

    aiapi_send($user, aiapi_conversation($user))->assertStatus(202);
    $user->businessFunctions()->detach();

    $entry = AiCostEntry::query()->sole();
    expect($entry->shares()->pluck('business_function_id')->sort()->values()->all())->toBe([$first->id, $second->id])
        ->and(AiMessage::query()->where('role', 'user')->sole()->business_function_ids)->toBe([$first->id, $second->id]);
});

it('AC-010: a monthly cap too small for the next call closes the turn limit_reached with no call to the model', function (string $field, string $code, string $text) {
    aiapi_fake_agent([aiapi_text_body()]);
    costs_cap($field, '0.01');
    $user = aiapi_user();

    aiapi_send($user, aiapi_conversation($user), ['content' => 'keep me'])->assertStatus(202);

    $turn = AiTurn::query()->sole();
    expect($turn->status)->toBe(AiTurnStatus::LimitReached)
        ->and($turn->error_code->value)->toBe($code)
        ->and($turn->error_code->message('en'))->toBe($text)
        ->and(aiapi_sent('chat/completions'))->toBe([])
        ->and(AiCostEntry::query()->count())->toBe(0)
        ->and(AiCostReservation::query()->count())->toBe(0)
        ->and(AiMessage::query()->where('content', 'keep me')->exists())->toBeTrue();
})->with([
    'person' => ['spend_cap_person_usd', 'limit_person', 'You have reached your monthly assistant spending limit.'],
    'total' => ['spend_cap_total_usd', 'limit_total', 'The company monthly assistant spending limit has been reached.'],
]);

it('AC-010: a call whose real cost passes the cap closes the turn limit_reached before any tool runs', function () {
    aiapi_fake_agent([
        str_replace('"cost":0.0005', '"cost":2.5', aiapi_tool_body('list_records', ['{}'])),
        aiapi_text_body(),
    ]);
    costs_cap('spend_cap_person_usd', '1.00');
    $user = aiapi_user();

    aiapi_send($user, aiapi_conversation($user))->assertStatus(202);

    $turn = AiTurn::query()->sole();
    expect($turn->status)->toBe(AiTurnStatus::LimitReached)
        ->and($turn->error_code)->toBe(AiTurnErrorCode::LimitPerson)
        ->and(AiCostEntry::query()->sole()->cost_usd)->toStartWith('2.5')
        ->and(collect(aiapi_sent('connector.test'))->pluck('method')->all())->toBe(['tools/list'])
        ->and(aiapi_sent('chat/completions'))->toHaveCount(1)
        ->and(AiCostReservation::query()->count())->toBe(0);
});

it('a model call that fails without a cost frees its reservation and writes no ledger row', function (int $status) {
    aiapi_fake_agent(['boom'], modelStatus: $status);
    $user = aiapi_user();

    aiapi_send($user, aiapi_conversation($user))->assertStatus(202);

    expect(AiTurn::query()->sole()->status)->toBe(AiTurnStatus::Failed)
        ->and(AiCostReservation::query()->count())->toBe(0)
        ->and(AiCostEntry::query()->count())->toBe(0)
        ->and(AiSpendCounter::query()->sum('spent_usd'))->toEqual(0);
})->with([500, 429]);

it('an error in the middle of the stream frees the reservation too', function () {
    aiapi_fake_agent([aiapi_sse([['error' => ['code' => 500], 'choices' => [['delta' => [], 'finish_reason' => 'error']]]])]);
    $user = aiapi_user();

    aiapi_send($user, aiapi_conversation($user))->assertStatus(202);

    expect(AiTurn::query()->sole()->error_code)->toBe(AiTurnErrorCode::StreamError)
        ->and(AiCostReservation::query()->count())->toBe(0);
});

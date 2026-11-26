<?php

use App\Enums\AiMessageRole;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Services\AiAssistant\Memory\AiConversationMemory;
use App\Services\AiAssistant\Memory\AiMemoryEntry;
use App\Services\AiAssistant\Memory\AiMemoryInput;
use App\Services\AiAssistant\Memory\AiMemoryPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** @return list<AiMemoryEntry> three turns of about 400 bytes each */
function aim_three_turns(): array
{
    $text = str_repeat('x', 400);

    return [
        new AiMemoryEntry('user', "u1 {$text}", id: 1), new AiMemoryEntry('assistant', "a1 {$text}", id: 2),
        new AiMemoryEntry('user', "u2 {$text}", id: 3), new AiMemoryEntry('assistant', "a2 {$text}", id: 4),
        new AiMemoryEntry('user', "u3 {$text}", id: 5),
    ];
}

it('asks to summarise the old part only when the estimate passes the threshold', function () {
    $memory = app(AiConversationMemory::class);
    config(['ai-assistant.memory.keep_recent_turns' => 2]);

    // ~1.2 KB of history at 2 bytes/token ~ 650 tokens: far below 70% of 100000
    $calm = $memory->plan(aim_input(aim_three_turns(), window: 100000));
    // ... and above 70% of a 800 token window
    $tight = $memory->plan(aim_input(aim_three_turns(), window: 800));

    expect($calm->needsSummary)->toBeFalse()
        ->and($tight->thresholdTokens)->toBe(560)
        ->and($tight->estimatedTokens)->toBeGreaterThan(560)
        ->and($tight->needsSummary)->toBeTrue()
        // the last two turns stay whole: only the first turn is old
        ->and(array_map(fn ($e) => $e->id, $tight->entriesToSummarize))->toBe([1, 2]);
});

it('never asks to summarise when everything is inside the kept turns', function () {
    $plan = app(AiConversationMemory::class)->plan(aim_input([new AiMemoryEntry('user', str_repeat('x', 5000), id: 1)], window: 100));

    expect($plan->needsSummary)->toBeFalse()->and($plan->entriesToSummarize)->toBe([]);
});

it('estimates with a prudent upper bound: bytes divided by the configured divisor plus a framing overhead', function () {
    config(['ai-assistant.memory.bytes_per_token' => 2.0]);
    $plan = app(AiConversationMemory::class)->plan(new AiMemoryInput('', '', [], [new AiMemoryEntry('user', str_repeat('à', 50))], null, 0, 1000));

    // '' and '' (4 each), '{"tools":[]}' = 12 bytes (6+4), 50 chars of 2 bytes = 100 bytes (50+4): 72
    expect($plan->estimatedTokens)->toBe(72);
});

it('proposes a new conversation after the configured number of summaries', function () {
    config(['ai-assistant.memory.max_summaries' => 3]);
    $memory = app(AiConversationMemory::class);

    expect($memory->plan(aim_input([new AiMemoryEntry('user', 'hi')], summaryCount: 2))->proposeNewConversation)->toBeFalse()
        ->and($memory->plan(aim_input([new AiMemoryEntry('user', 'hi')], summaryCount: 3))->proposeNewConversation)->toBeTrue();
});

it('builds a summary request with the rules and without the old tool rows', function () {
    $entries = [
        new AiMemoryEntry('user', 'show order 7', id: 1),
        new AiMemoryEntry('tool', '[Earlier tool result omitted: tool=run_code operation=GET /orders. Read the data again with the tool if it is needed.]', 'run_code', 'GET /orders', 2),
    ];

    [$system, $user] = app(AiConversationMemory::class)->buildSummaryRequest($entries, 'PREVIOUS');

    expect($system->role)->toBe('system')
        ->and($system->text)->toContain('decisions')->toContain('type and identifier')->toContain('operations performed')
        ->toContain('Never invent')->toContain('masked')
        ->and($user->text)->toContain('PREVIOUS')->toContain('[user] show order 7')->toContain('GET /orders');
});

it('saves the summary on the conversation, counts it and moves the cut forward', function () {
    $conversation = AiConversation::factory()->create();
    $ids = collect(range(1, 5))->map(fn ($i) => AiMessage::factory()->create([
        'ai_conversation_id' => $conversation->id,
        'role' => $i % 2 ? AiMessageRole::User : AiMessageRole::Assistant,
        'content' => str_repeat("m{$i} ", 100),
    ])->id)->all();
    config(['ai-assistant.memory.keep_recent_turns' => 1]);
    $memory = app(AiConversationMemory::class);

    $plan = $memory->plan(aim_input($memory->entriesOf($conversation), window: 300));
    expect($plan->needsSummary)->toBeTrue();
    $seen = null;
    $summary = $memory->summarize($conversation, $plan, aim_executor('  THE SUMMARY ', $seen));

    $conversation->refresh();
    expect($summary)->toBe('THE SUMMARY')
        ->and($conversation->summary)->toBe('THE SUMMARY')
        ->and($conversation->summary_count)->toBe(1)
        ->and($conversation->summary_through_message_id)->toBe($ids[3])
        ->and($seen[1]->text)->toContain('m1 ');
    // Next plan: only the message after the cut is sent whole, the summary goes in the prefix.
    $next = $memory->plan(aim_input($memory->entriesOf($conversation), summary: $conversation->summary));
    expect(array_values(array_map(fn ($e) => $e->id, array_filter($next->messages, fn ($e) => $e->id !== null))))->toBe([$ids[4]]);
});

it('sends nothing from another conversation', function () {
    $mine = AiConversation::factory()->create();
    $other = AiConversation::factory()->create(['summary' => 'OTHER SUMMARY']);
    AiMessage::factory()->create(['ai_conversation_id' => $mine->id, 'content' => 'my words']);
    AiMessage::factory()->create(['ai_conversation_id' => $other->id, 'content' => 'OTHER SECRET']);
    $memory = app(AiConversationMemory::class);

    $plan = $memory->plan(aim_input($memory->entriesOf($mine), summary: $mine->summary));

    $all = implode("\n", array_map(fn ($e) => $e->text, $plan->messages));
    expect($all)->toContain('my words')->not->toContain('OTHER SECRET')->not->toContain('OTHER SUMMARY');
});

it('refuses an empty summary and leaves the conversation untouched', function () {
    $conversation = AiConversation::factory()->create();
    $plan = new AiMemoryPlan([], 0, 0, true, [new AiMemoryEntry('user', 'x', id: 9)], false);

    expect(fn () => app(AiConversationMemory::class)->summarize($conversation, $plan, aim_executor('   ')))->toThrow(RuntimeException::class);
    expect($conversation->refresh()->summary)->toBeNull()->and($conversation->summary_count)->toBe(0);
});

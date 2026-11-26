<?php

use App\Services\AiAssistant\Memory\AiConversationMemory;
use App\Services\AiAssistant\Memory\AiMemoryEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('puts core, instructions, tools in fixed order, then summary, then messages', function () {
    $plan = app(AiConversationMemory::class)->plan(aim_input([
        new AiMemoryEntry('user', 'hello'),
    ], summary: 'SUMMARY TEXT'));

    expect(array_map(fn ($e) => $e->role, $plan->messages))->toBe(['system', 'system', 'system', 'system', 'user']);
    expect($plan->messages[0]->text)->toBe('CORE TEXT');
    expect($plan->messages[1]->text)->toBe('EXTRA TEXT');
    // Tools sorted by name, keys sorted at every level, so the bytes do not depend on caller order.
    expect($plan->messages[2]->text)->toBe('{"tools":[{"description":"Confirm","name":"confirm","parameters":[]},{"description":"Run","name":"run_code","parameters":{"a":2,"b":1}}]}');
    expect($plan->messages[3]->text)->toContain('SUMMARY TEXT');
    expect($plan->messages[4]->text)->toBe('hello');
});

it('keeps the prefix byte-identical between two calls of a growing conversation', function () {
    $memory = app(AiConversationMemory::class);
    $first = [new AiMemoryEntry('user', 'q1'), new AiMemoryEntry('assistant', 'a1'), new AiMemoryEntry('user', 'q2')];
    $second = [...$first, new AiMemoryEntry('assistant', 'a2'), new AiMemoryEntry('user', 'q3')];

    $one = $memory->plan(aim_input($first, summary: 'S'));
    $two = $memory->plan(aim_input($second, summary: 'S'));

    expect(aim_json(array_slice($two->messages, 0, count($one->messages))))->toBe(aim_json($one->messages));
});

it('replaces tool results of earlier turns with a line naming tool and operation, and keeps the current turn whole', function () {
    $plan = app(AiConversationMemory::class)->plan(aim_input([
        new AiMemoryEntry('user', 'old question'),
        new AiMemoryEntry('tool_call', '{"id":1}', 'run_code', 'GET /orders'),
        new AiMemoryEntry('tool', 'SECRET ROWS 111', 'run_code', 'GET /orders'),
        new AiMemoryEntry('assistant', 'old answer'),
        new AiMemoryEntry('user', 'new question'),
        new AiMemoryEntry('tool_call', '{"id":2}', 'run_code', 'GET /clients'),
        new AiMemoryEntry('tool', 'CURRENT ROWS 222', 'run_code', 'GET /clients'),
    ]));

    $all = implode("\n", array_map(fn ($e) => $e->text, $plan->messages));
    expect($all)->not->toContain('SECRET ROWS 111')
        ->and($all)->toContain('CURRENT ROWS 222')
        ->and($all)->toContain('run_code')->toContain('GET /orders');
    // Earlier tool_call dropped, earlier result became one line; current turn call and result untouched.
    expect(array_map(fn ($e) => $e->role, array_slice($plan->messages, 3)))
        ->toBe(['user', 'tool', 'assistant', 'user', 'tool_call', 'tool']);
});

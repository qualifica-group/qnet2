<?php

use App\Models\WorkOrderPage;
use App\Models\WorkOrderPagePoll;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

require_once __DIR__.'/../../../Helpers/WorkOrderChatHelpers.php';
require_once __DIR__.'/../../../Helpers/WorkOrderPageHelpers.php';

uses(RefreshDatabase::class);

it('AC-012: 1, 11 or duplicated options are a 422; 2..10 create the poll and the page', function (): void {
    $workOrder = chatWorkOrder();
    Sanctum::actingAs(chatMember($workOrder));
    $url = pageUrl($workOrder, 'polls');

    $this->postJson($url, ['question' => 'Q', 'options' => ['a']])->assertUnprocessable()->assertJsonValidationErrors('options');
    $this->postJson($url, ['question' => 'Q', 'options' => array_map('strval', range(1, 11))])->assertUnprocessable()->assertJsonValidationErrors('options');
    $this->postJson($url, ['question' => 'Q', 'options' => ['a', 'A']])->assertUnprocessable()->assertJsonValidationErrors('options.1');
    $this->postJson($url, ['question' => '', 'options' => ['a', 'b']])->assertUnprocessable()->assertJsonValidationErrors('question');
    $this->postJson($url, ['question' => str_repeat('q', 256), 'options' => ['a', 'b']])->assertUnprocessable();
    $this->postJson($url, ['question' => 'Q', 'options' => ['a', str_repeat('b', 121)]])->assertUnprocessable();
    expect(WorkOrderPagePoll::count())->toBe(0);

    $this->postJson($url, ['question' => 'Lunch?', 'options' => ['Pizza', 'Sushi']])
        ->assertCreated()
        ->assertJsonPath('data.question', 'Lunch?')
        ->assertJsonPath('data.options.0.key', 'o1')
        ->assertJsonPath('data.options.1.label', 'Sushi')
        ->assertJsonPath('data.my_vote', null)
        ->assertJsonPath('data.total_votes', 0);
    $this->postJson($url, ['question' => 'Ten', 'options' => array_map('strval', range(1, 10))])->assertCreated();

    expect(WorkOrderPage::count())->toBe(1)->and(WorkOrderPage::first()->version)->toBe(0);
    $this->getJson(pageUrl($workOrder))->assertJsonCount(2, 'data.polls');
});

it('AC-013: one vote per user, changeable and withdrawable, nominative', function (): void {
    $workOrder = chatWorkOrder();
    $anna = chatMember($workOrder);
    $bruno = chatMember($workOrder, 'participants');
    Sanctum::actingAs($anna);
    $pollId = $this->postJson(pageUrl($workOrder, 'polls'), ['question' => 'Q', 'options' => ['a', 'b']])->json('data.id');
    $vote = pageUrl($workOrder, "polls/{$pollId}/vote");

    $this->putJson($vote, ['option_key' => 'o1'])->assertOk()
        ->assertJsonPath('data.my_vote', 'o1')->assertJsonPath('data.total_votes', 1)
        ->assertJsonPath('data.options.0.votes', 1)->assertJsonPath('data.options.0.voters.0.id', $anna->id);

    $this->putJson($vote, ['option_key' => 'o2'])->assertOk()
        ->assertJsonPath('data.my_vote', 'o2')->assertJsonPath('data.total_votes', 1)
        ->assertJsonPath('data.options.0.votes', 0)->assertJsonPath('data.options.1.votes', 1);

    Sanctum::actingAs($bruno);
    $this->putJson($vote, ['option_key' => 'o2'])->assertOk()
        ->assertJsonPath('data.total_votes', 2)->assertJsonPath('data.my_vote', 'o2')->assertJsonCount(2, 'data.options.1.voters');

    $this->deleteJson($vote)->assertOk()->assertJsonPath('data.my_vote', null)->assertJsonPath('data.total_votes', 1);
    $this->putJson($vote, ['option_key' => 'zzz'])->assertUnprocessable()->assertJsonValidationErrors('option_key');
    $this->putJson($vote, [])->assertUnprocessable();

    // Page payload carries the same poll shape.
    $this->getJson(pageUrl($workOrder))->assertJsonPath("data.polls.{$pollId}.total_votes", 1)
        ->assertJsonPath("data.polls.{$pollId}.my_vote", null);
});

it('AC-013: a poll of another work order is a 404 (scopeBindings)', function (): void {
    $mine = chatWorkOrder();
    $other = chatWorkOrder();
    $foreignPoll = WorkOrderPagePoll::factory()->create(['work_order_page_id' => pageFor($other)->id]);
    Sanctum::actingAs(chatMember($mine));

    $this->putJson(pageUrl($mine, "polls/{$foreignPoll->id}/vote"), ['option_key' => 'o1'])->assertNotFound();
    $this->deleteJson(pageUrl($mine, "polls/{$foreignPoll->id}/vote"))->assertNotFound();
    $this->assertDatabaseCount('work_order_page_poll_votes', 0);
});

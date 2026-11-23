<?php

use App\Models\Attachment;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\WorkOrderPage;
use App\Models\WorkOrderPagePoll;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

require_once __DIR__.'/../../../Helpers/WorkOrderChatHelpers.php';
require_once __DIR__.'/../../../Helpers/WorkOrderPageHelpers.php';

uses(RefreshDatabase::class);

it('AC-004: PUT with the current version saves, increments the version and records the author', function (): void {
    $workOrder = chatWorkOrder();
    $member = chatMember($workOrder);
    Sanctum::actingAs($member);

    $this->putJson(pageUrl($workOrder), ['content' => '<p>First</p>', 'version' => 0])
        ->assertOk()
        ->assertJsonPath('data.version', 1)
        ->assertJsonPath('data.content', '<p>First</p>')
        ->assertJsonPath('data.updated_by.id', $member->id);

    $this->putJson(pageUrl($workOrder), ['content' => '<p>Second</p>', 'version' => 1])->assertOk()->assertJsonPath('data.version', 2);

    $page = WorkOrderPage::firstOrFail();
    expect($page->version)->toBe(2)->and($page->content)->toBe('<p>Second</p>')->and($page->updated_by_id)->toBe($member->id);
    $this->getJson(pageUrl($workOrder))->assertOk()->assertJsonPath('data.content', '<p>Second</p>');
});

it('AC-005: a stale version answers 409 page_version_conflict with the current page and writes nothing', function (): void {
    $workOrder = chatWorkOrder();
    $member = chatMember($workOrder);
    pageFor($workOrder, ['content' => '<p>Theirs</p>', 'version' => 5]);
    Sanctum::actingAs($member);

    $this->putJson(pageUrl($workOrder), ['content' => '<p>Mine</p>', 'version' => 4])
        ->assertStatus(409)
        ->assertJsonPath('success', false)
        ->assertJsonPath('code', 'page_version_conflict')
        ->assertJsonPath('data.version', 5)
        ->assertJsonPath('data.content', '<p>Theirs</p>');

    expect(WorkOrderPage::firstOrFail()->content)->toBe('<p>Theirs</p>');
    $this->putJson(pageUrl($workOrder), ['content' => '<p>Mine</p>', 'version' => 3])->assertStatus(409);
});

it('validates the payload: version required and integer, content must be present', function (): void {
    $workOrder = chatWorkOrder();
    Sanctum::actingAs(chatMember($workOrder));

    $this->putJson(pageUrl($workOrder), ['content' => '<p>x</p>'])->assertUnprocessable()->assertJsonValidationErrors('version');
    $this->putJson(pageUrl($workOrder), ['content' => '<p>x</p>', 'version' => -1])->assertUnprocessable()->assertJsonValidationErrors('version');
    $this->putJson(pageUrl($workOrder), ['version' => 0])->assertUnprocessable()->assertJsonValidationErrors('content');
});

it('accepts null and blank content (emptied page)', function (): void {
    $workOrder = chatWorkOrder();
    Sanctum::actingAs(chatMember($workOrder));

    $this->putJson(pageUrl($workOrder), ['content' => '<p>x</p>', 'version' => 0])->assertOk();
    $this->putJson(pageUrl($workOrder), ['content' => null, 'version' => 1])->assertOk()->assertJsonPath('data.content', null)->assertJsonPath('data.version', 2);
    $this->putJson(pageUrl($workOrder), ['content' => '   ', 'version' => 2])->assertOk()->assertJsonPath('data.content', null);
});

it('AC-007: the page profile is applied on save: unsafe markup is dropped, page blocks survive', function (): void {
    $workOrder = chatWorkOrder();
    Sanctum::actingAs(chatMember($workOrder));
    $html = '<p onclick="x()" style="color:red" class="c">Hi<script>alert(1)</script></p>'
        .'<div data-type="callout" data-variant="info"><p>Note</p></div>'
        .'<div data-type="callout" data-variant="evil"><p>Bad</p></div>'
        .'<a href="javascript:alert(1)">link</a><mark data-color="yellow">m</mark><mark data-color="red">m</mark>';

    $content = $this->putJson(pageUrl($workOrder), ['content' => $html, 'version' => 0])->assertOk()->json('data.content');

    expect($content)->toContain('<div data-type="callout" data-variant="info"><p>Note</p></div>')
        ->toContain('<mark data-color="yellow">m</mark>')
        ->not->toContain('script')->not->toContain('onclick')->not->toContain('style=')->not->toContain('class=')
        ->not->toContain('javascript:')->not->toContain('evil')->not->toContain('data-color="red"');
});

it('AC-009: an inline image becomes an attachment, is removed when no longer referenced and is readable only by who reads the page', function (): void {
    Storage::fake('local');
    $workOrder = chatWorkOrder();
    $member = chatMember($workOrder);
    $outsider = chatOutsider();
    Sanctum::actingAs($member);

    $content = $this->putJson(pageUrl($workOrder), ['content' => '<p><img src="'.pagePngDataUri().'" alt=""></p>', 'version' => 0])
        ->assertOk()->json('data.content');

    expect($content)->toContain('data-attachment-id')->not->toContain('src=')->not->toContain('data:');
    $attachment = Attachment::firstOrFail();
    expect($attachment->collection)->toBe('rich_text')
        ->and($member->can('view', $attachment))->toBeTrue()
        ->and($outsider->can('view', $attachment))->toBeFalse();

    $this->putJson(pageUrl($workOrder), ['content' => '<p>No image</p>', 'version' => 1])->assertOk();
    expect(Attachment::count())->toBe(0);
});

it('AC-010: cited tasks are returned with their data when visible, otherwise unavailable', function (): void {
    $workOrder = chatWorkOrder();
    $member = chatMember($workOrder);
    $status = TaskStatus::factory()->create(['name' => 'Open', 'color' => '#112233']);
    $visible = Task::factory()->inStatus($status)->create(['work_order_id' => $workOrder->id, 'title' => 'Visible task', 'end_date' => '2026-12-31']);
    $visible->assignees()->attach($member->id);
    $private = Task::factory()->create(['work_order_id' => $workOrder->id, 'title' => 'Secret', 'is_private' => true]);
    $foreign = Task::factory()->create(['work_order_id' => chatWorkOrder()->id, 'title' => 'Elsewhere']);

    $refs = fn (array $tasks): string => implode('', array_map(fn (Task $t) => '<div data-type="task-ref" data-task-id="'.$t->id.'"></div>', $tasks));
    Sanctum::actingAs($member);

    $data = $this->putJson(pageUrl($workOrder), ['content' => $refs([$visible, $private, $foreign]).'<div data-type="task-ref" data-task-id="999999"></div>', 'version' => 0])
        ->assertOk()->json('data');

    expect($data['tasks'][(string) $visible->id])->toMatchArray([
        'id' => $visible->id, 'available' => true, 'title' => 'Visible task', 'end_date' => '2026-12-31', 'completed' => false,
        'status' => ['id' => $status->id, 'name' => 'Open', 'color' => '#112233'],
        'assignees' => [['id' => $member->id, 'name' => $member->name]],
    ]);
    expect($data['tasks'][(string) $private->id])->toMatchArray(['available' => false, 'title' => null, 'status' => null, 'end_date' => null, 'completed' => false, 'assignees' => []])
        ->and($data['tasks'])->not->toHaveKey('999999');

    // A task of another work order has its reference scrubbed at save time (the node stays).
    expect($data['content'])->toContain('data-task-id="'.$visible->id.'"')->not->toContain('data-task-id="'.$foreign->id.'"')->toContain('data-type="task-ref"')->not->toContain('999999');
    expect($data['tasks'])->not->toHaveKey((string) $foreign->id);

    $visible->forceFill(['completion_date' => '2026-11-01'])->save();
    $this->getJson(pageUrl($workOrder))->assertJsonPath('data.tasks.'.$visible->id.'.completed', true);
});

it('AC-014: saving content that no longer cites a poll deletes it and its votes', function (): void {
    $workOrder = chatWorkOrder();
    $member = chatMember($workOrder);
    Sanctum::actingAs($member);
    $kept = $this->postJson(pageUrl($workOrder, 'polls'), ['question' => 'Keep?', 'options' => ['a', 'b']])->json('data.id');
    $dropped = $this->postJson(pageUrl($workOrder, 'polls'), ['question' => 'Drop?', 'options' => ['a', 'b']])->json('data.id');
    $this->putJson(pageUrl($workOrder, "polls/{$dropped}/vote"), ['option_key' => 'o1'])->assertOk();

    $cite = fn (int $id) => '<div data-type="poll" data-poll-id="'.$id.'"></div>';
    $this->putJson(pageUrl($workOrder), ['content' => $cite($kept).$cite($dropped), 'version' => 0])->assertOk();
    expect(WorkOrderPagePoll::count())->toBe(2);

    $this->putJson(pageUrl($workOrder), ['content' => $cite($kept), 'version' => 1])->assertOk()
        ->assertJsonPath('data.polls.'.$kept.'.question', 'Keep?');

    expect(WorkOrderPagePoll::pluck('id')->all())->toBe([$kept])
        ->and(DB::table('work_order_page_poll_votes')->count())->toBe(0);
});

it('AC-015: more than 1 MB of HTML or more than 100000 visible characters is a 422', function (): void {
    $workOrder = chatWorkOrder();
    Sanctum::actingAs(chatMember($workOrder));

    $this->putJson(pageUrl($workOrder), ['content' => '<p>'.str_repeat('a', 100001).'</p>', 'version' => 0])
        ->assertUnprocessable()->assertJsonValidationErrors('content');

    $paragraphs = str_repeat('<p>'.str_repeat('a', 900).'</p>', 1200);
    config(['rich_text.page_text_max' => 10_000_000]);
    $this->putJson(pageUrl($workOrder), ['content' => $paragraphs, 'version' => 0])
        ->assertUnprocessable()->assertJsonValidationErrors('content');

    config(['rich_text.page_text_max' => 100000]);
    $this->putJson(pageUrl($workOrder), ['content' => '<p>'.str_repeat('a', 100000).'</p>', 'version' => 0])->assertOk();
    expect(WorkOrderPage::firstOrFail()->version)->toBe(1);
});

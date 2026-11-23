<?php

use App\Models\Attachment;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkOrder;
use App\Notifications\NoteMentionNotification;
use Database\Seeders\QualificaCatalog\DepartmentRoleCatalogue as Department;
use Database\Seeders\QualificaDepartmentRoleSeeder as Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

/**
 * Spec 0408 D-16 and spec 0353 D-2 rev. (user directive 2026-11-23): a
 * department role that reads the notes of a commessa or task mentions anyone
 * in them, and the mention reaches the colleague with a link that opens the record.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    Role::create(['name' => 'super-admin']);
    $this->seed(Seeder::class);
});

function departmentMember(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function departmentMentionSpan(User $user): string
{
    return sprintf('<span data-type="mention" data-id="%d" data-label="%s">@%s</span>', $user->id, $user->name, $user->name);
}

/** @param  array<string, mixed>  $extra */
function departmentNote(object $test, string $type, int $id, User $mentioned, array $extra = []): void
{
    $test->postJson('/api/notes', [
        'entity_type' => $type,
        'entity_id' => $id,
        'body' => 'Vedi '.departmentMentionSpan($mentioned),
        'mentions' => [$mentioned->id],
        ...$extra,
    ])->assertCreated();
}

it('shows Sicurezza only the commesse it is involved in', function () {
    foreach ([Department::SAFETY, Department::senior(Department::SAFETY)] as $role) {
        expect(Role::findByName($role)->hasPermissionTo('work-orders.viewCompetence'))->toBeFalse($role)
            ->and(Role::findByName($role)->hasPermissionTo('work-orders.view'))->toBeTrue($role);
    }

    $technician = departmentMember(Department::SAFETY);
    $mine = WorkOrder::factory()->create();
    $mine->participants()->attach($technician, ['position' => 1]);
    $others = WorkOrder::factory()->create();
    Sanctum::actingAs($technician);

    $this->getJson("/api/work-orders/{$mine->id}")->assertOk();
    $this->getJson("/api/work-orders/{$others->id}")->assertForbidden();
});

it('lets a Sicurezza reader of a commessa read its documents and mention anyone, who gets a working link', function () {
    Notification::fake();
    $technician = departmentMember(Department::SAFETY);
    $colleague = departmentMember(Department::COMMERCIAL);
    $workOrder = WorkOrder::factory()->create();
    $workOrder->participants()->attach($technician, ['position' => 1]);
    $document = Attachment::factory()->for($workOrder, 'attachable')->create(['collection' => 'documents']);
    Sanctum::actingAs($technician);

    $this->getJson("/api/work-orders/{$workOrder->id}")->assertOk()->assertJsonPath('permissions.actions.view_documents', true);
    $this->getJson('/api/attachments?attachable_type=work_order&attachable_id='.$workOrder->id.'&collection=documents')
        ->assertOk()->assertJsonPath('data.0.id', $document->id);
    $this->getJson("/api/notes?entity_type=work-orders&entity_id={$workOrder->id}")
        ->assertOk()->assertJsonPath('meta.mention_outside.allowed', true);

    departmentNote($this, 'work-orders', $workOrder->id, $colleague);

    expect(DB::table('work_order_participant')->where('work_order_id', $workOrder->id)->where('user_id', $colleague->id)->exists())->toBeTrue();
    Notification::assertSentTo($colleague, NoteMentionNotification::class, fn ($n) => $n->toArray($colleague)['action_url'] === "/work-orders/{$workOrder->id}?tab=notes");
});

it('lets a Marketing watcher, who cannot edit the task, mention anyone on it, who gets a working link', function () {
    Notification::fake();
    $watcher = departmentMember(Department::MARKETING);
    $colleague = departmentMember(Department::ISO);
    $task = Task::factory()->forCreator(departmentMember(Department::ISO))->create(['is_private' => true]);
    $task->watchers()->attach($watcher);
    Sanctum::actingAs($watcher);

    expect($watcher->can('update', $task))->toBeFalse();

    departmentNote($this, 'tasks', $task->id, $colleague);

    expect($task->watchers()->pluck('users.id')->all())->toContain($colleague->id);
    Notification::assertSentTo($colleague, NoteMentionNotification::class, fn ($n) => $n->toArray($colleague)['action_url'] === "/tasks/{$task->id}");
});

it('links a commerciale mentioned on an offer of their team, with the offer and opportunity documents', function () {
    Notification::fake();
    $author = departmentMember(Department::COMMERCIAL);
    $colleague = departmentMember(Department::COMMERCIAL);
    $opportunity = Opportunity::factory()->create();
    $quote = Quote::factory()->create(['opportunity_id' => $opportunity->id]);
    $quote->managers()->attach([$author->id => ['position' => 1], $colleague->id => ['position' => 2]]);
    Attachment::factory()->for($opportunity, 'attachable')->create(['collection' => 'documents']);
    Sanctum::actingAs($author);

    departmentNote($this, 'request-management', $opportunity->id, $colleague, ['quote_id' => $quote->id]);

    Notification::assertSentTo($colleague, NoteMentionNotification::class, fn ($n) => $n->toArray($colleague)['action_url'] === "/request-management/{$quote->id}");

    Sanctum::actingAs($colleague);
    $this->getJson('/api/attachments?attachable_type=opportunity&attachable_id='.$opportunity->id.'&collection=documents')->assertOk();
    $this->getJson('/api/attachments?attachable_type=quote&attachable_id='.$quote->id.'&collection=documents')->assertOk();
});

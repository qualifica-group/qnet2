<?php

declare(strict_types=1);

use App\Models\Attribute;
use App\Models\EmailTemplate;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkOrder;
use App\Notifications\TableRowNotification;
use App\Services\Notifications\AssignmentNotifier;
use App\Services\WorkOrders\WorkOrderAttributeValueWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0390 AC-004/005/006: a table attribute row event on a Commessa.
uses(RefreshDatabase::class);

/**
 * A work order whose table attribute notifies, with the actor as Responsabile
 * plus one more Responsabile and one Partecipante.
 *
 * @param  array<string, mixed>  $notify
 * @return array{actor: User, workOrder: WorkOrder, supervisor: User, participant: User}
 */
function notifyingWorkOrder(array $notify = []): array
{
    foreach (['viewAny', 'view', 'create', 'update', 'viewAll'] as $ability) {
        Permission::findOrCreate("work-orders.{$ability}");
    }

    $actor = User::factory()->create();
    $actor->givePermissionTo(['work-orders.create', 'work-orders.update', 'work-orders.view', 'work-orders.viewAll']);
    Sanctum::actingAs($actor);

    $attribute = Attribute::factory()->ofType('table')->create([
        'code' => 'inspections', 'name' => 'Verifiche', 'config' => notifyingInspectionConfig($notify),
    ]);
    $category = ProductCategory::factory()->create();
    $category->attributes()->attach($attribute->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'work_order']);
    $quote = Quote::factory()->create();
    $line = QuoteLine::factory()->create(['quote_id' => $quote->id, 'product_id' => Product::factory()->create(['category_id' => $category->id])->id]);

    $id = test()->postJson('/api/work-orders', [
        ...workOrderRequiredFields(),
        'quote_id' => $quote->id, 'title' => 'Commessa ISO', 'type' => 'processing', 'quote_line_ids' => [$line->id],
    ])->assertCreated()->json('data.id');

    $workOrder = WorkOrder::query()->findOrFail($id);
    $supervisor = User::factory()->create();
    $participant = User::factory()->create();
    $workOrder->supervisors()->sync([$actor->id, $supervisor->id]);
    $workOrder->participants()->sync([$participant->id => ['position' => 1]]);

    return compact('actor', 'workOrder', 'supervisor', 'participant');
}

/**
 * @param  array<int, array<string, mixed>>  $rows
 */
function patchInspections(WorkOrder $workOrder, array $rows): array
{
    return test()->patchJson("/api/work-orders/{$workOrder->id}", ['attribute_values' => ['inspections' => ['rows' => $rows]]])
        ->assertOk()
        ->json('data.attribute_values.inspections');
}

it('AC-004: switching the trigger on tells the record team, not the author, and only once', function (): void {
    ['actor' => $actor, 'workOrder' => $workOrder, 'supervisor' => $supervisor, 'participant' => $participant] = notifyingWorkOrder();
    Notification::fake();

    $stored = patchInspections($workOrder, [['inspection_date' => '2026-12-01', 'alert_sent' => true]]);
    patchInspections($workOrder, $stored['rows']);

    Notification::assertSentToTimes($supervisor, TableRowNotification::class, 1);
    Notification::assertSentToTimes($participant, TableRowNotification::class, 1);
    Notification::assertNotSentTo($actor, TableRowNotification::class);
});

it('AC-004: a row added with the trigger off tells nobody, switching it on later does', function (): void {
    ['workOrder' => $workOrder, 'supervisor' => $supervisor] = notifyingWorkOrder();
    Notification::fake();

    $stored = patchInspections($workOrder, [['inspection_date' => '2026-12-01', 'alert_sent' => false]]);
    Notification::assertNothingSent();

    patchInspections($workOrder, [[...$stored['rows'][0], 'alert_sent' => true]]);
    Notification::assertSentToTimes($supervisor, TableRowNotification::class, 1);
});

it('AC-005: chosen users, active holders of chosen roles and free addresses are told', function (): void {
    $chosen = User::factory()->create();
    $inactive = User::factory()->create(['is_active' => false]);
    $roleHolder = User::factory()->create();
    $role = Role::findOrCreate('accounting');
    $roleHolder->assignRole($role);
    ['workOrder' => $workOrder, 'supervisor' => $supervisor] = notifyingWorkOrder([
        'record_team' => false,
        'user_ids' => [$chosen->id, $inactive->id],
        'role_ids' => [$role->id],
        'emails' => ['audit@example.com', strtoupper($chosen->email)],
    ]);
    Notification::fake();

    patchInspections($workOrder, [['inspection_date' => '2026-12-01', 'alert_sent' => true]]);

    Notification::assertSentTo($chosen, TableRowNotification::class);
    Notification::assertSentTo($roleHolder, TableRowNotification::class);
    Notification::assertNotSentTo([$inactive, $supervisor], TableRowNotification::class);
    Notification::assertSentOnDemand(
        TableRowNotification::class,
        fn (TableRowNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === 'audit@example.com' && $channels === ['mail'],
    );
    Notification::assertSentOnDemandTimes(TableRowNotification::class, 1);
});

it('AC-006: a muted write or a write with nobody authenticated tells nobody', function (): void {
    ['workOrder' => $workOrder] = notifyingWorkOrder();
    Notification::fake();

    AssignmentNotifier::muted(fn () => patchInspections($workOrder, [['inspection_date' => '2026-12-01', 'alert_sent' => true]]));
    Notification::assertNothingSent();

    $values = $workOrder->fresh()->attribute_values;
    $values['inspections']['rows'][] = ['inspection_date' => '2026-12-02', 'alert_sent' => true];
    $changed = [];
    $old = [];
    Auth::forgetGuards();
    app(WorkOrderAttributeValueWriter::class)->apply($workOrder, $values, $changed, $old);

    expect($changed)->toHaveKey('attribute_values');
    Notification::assertNothingSent();
});

it('AC-009: the chosen email template is rendered against the work order', function (): void {
    $template = EmailTemplate::factory()->create([
        'subject' => 'Verifica programmata {work_order.code}',
        'body' => '<p>Commessa {work_order.title}</p>',
    ]);
    ['workOrder' => $workOrder, 'supervisor' => $supervisor] = notifyingWorkOrder(['email_template_id' => $template->id]);
    Notification::fake();

    patchInspections($workOrder, [['inspection_date' => '2026-12-01', 'inspector' => 'Rossi', 'alert_sent' => true]]);

    Notification::assertSentTo($supervisor, TableRowNotification::class, function (TableRowNotification $notification) use ($supervisor, $workOrder): bool {
        $mail = $notification->toMail($supervisor);
        $html = (string) $mail->render();

        return $mail->subject === "Verifica programmata {$workOrder->code}"
            && str_contains($html, 'Commessa Commessa ISO')
            && str_contains($html, '01/12/2026')
            && str_contains($html, 'Rossi');
    });
});

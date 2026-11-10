<?php

use App\DataObjects\WorkOrders\UpdateWorkOrderData;
use App\Enums\AttributeContext;
use App\Migrations\Support\LegacyRecordTouchGuard;
use App\Models\Attribute;
use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Quotes\QuoteManagerWriter;
use App\Services\WorkOrders\WorkOrderNotable;
use App\Services\WorkOrderService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Spec 0372, D-7 / AC-023: people and attribute_values leave an activity entry
// ---------------------------------------------------------------------------

const PEOPLE_DESCRIPTION = 'People and request fields change';

/**
 * @param  array<int, string>  $modules  e.g. ['work-orders']
 */
function peopleActor(array $modules): User
{
    $user = User::factory()->create();

    foreach ($modules as $module) {
        foreach (['viewAny', 'view', 'create', 'update', 'viewAll', 'viewActivity'] as $ability) {
            $user->givePermissionTo(Permission::findOrCreate("{$module}.{$ability}", 'web'));
        }
    }

    return $user;
}

/**
 * @return Collection<int, Activity>
 */
function peopleEntries(Model $subject)
{
    return Activity::query()
        ->where('subject_type', $subject->getMorphClass())
        ->where('subject_id', $subject->getKey())
        ->where('description', PEOPLE_DESCRIPTION)
        ->get();
}

/**
 * A commessa with one line whose category carries a work_order attribute `field_code`.
 */
function peopleWorkOrder(): WorkOrder
{
    $category = ProductCategory::factory()->create(['business_function_id' => BusinessFunction::factory()->create()->id]);
    $attribute = Attribute::factory()->create(['code' => 'field_code', 'type' => 'text']);
    $category->attributes()->attach($attribute->id, ['is_required' => false, 'sort_order' => 0, 'context' => AttributeContext::WorkOrder->value]);
    $product = Product::factory()->create(['category_id' => $category->id]);
    $quote = Quote::factory()->create();
    $line = QuoteLine::factory()->create(['quote_id' => $quote->id, 'product_id' => $product->id]);
    $workOrder = WorkOrder::factory()->create(['quote_id' => $quote->id]);
    $workOrder->quoteLines()->attach($line->id);

    return $workOrder;
}

it('logs one entry for a work order update that changes supervisors, participants and flexible fields', function () {
    $workOrder = peopleWorkOrder();
    [$supervisor, $participant] = User::factory()->count(2)->create();
    Sanctum::actingAs($actor = peopleActor(['work-orders']));

    $this->patchJson("/api/work-orders/{$workOrder->id}", [
        'supervisor_ids' => [$supervisor->id],
        'participant_slots' => [null, $participant->id],
        'attribute_values' => ['field_code' => 'valore'],
    ])->assertOk();

    $entries = peopleEntries($workOrder);

    expect($entries)->toHaveCount(1)
        ->and($entries->first()->event)->toBe('updated')
        ->and($entries->first()->causer_id)->toBe($actor->id)
        ->and($entries->first()->properties->get('attributes'))->toBe([
            'supervisors' => [$supervisor->id],
            'participants' => [null, $participant->id],
            'attribute_values' => ['field_code' => 'valore'],
        ])
        ->and($entries->first()->properties->get('old'))->toBe(['supervisors' => [], 'participants' => [], 'attribute_values' => []]);

    // The same payload again changes nothing: no second entry.
    $this->patchJson("/api/work-orders/{$workOrder->id}", [
        'supervisor_ids' => [$supervisor->id],
        'participant_slots' => [null, $participant->id],
        'attribute_values' => ['field_code' => 'valore'],
    ])->assertOk();

    expect(peopleEntries($workOrder))->toHaveCount(1);
});

it('logs only the keys that changed', function () {
    $workOrder = peopleWorkOrder();
    [$supervisor, $other] = User::factory()->count(2)->create();
    Sanctum::actingAs(peopleActor(['work-orders']));
    $this->patchJson("/api/work-orders/{$workOrder->id}", ['supervisor_ids' => [$supervisor->id]])->assertOk();

    $this->patchJson("/api/work-orders/{$workOrder->id}", ['supervisor_ids' => [$supervisor->id], 'participant_slots' => [$other->id]])->assertOk();

    $last = peopleEntries($workOrder)->sortByDesc('id')->first();

    expect(peopleEntries($workOrder))->toHaveCount(2)
        ->and(array_keys($last->properties->get('attributes')))->toBe(['participants']);
});

it('is recognised by the legacy touch guard', function () {
    $workOrder = activity()->withoutLogs(fn (): WorkOrder => peopleWorkOrder());
    $supervisor = User::factory()->create();
    $guard = app(LegacyRecordTouchGuard::class);

    expect($guard->isWorkOrderTouched($workOrder))->toBeFalse();

    Sanctum::actingAs(peopleActor(['work-orders']));
    $this->patchJson("/api/work-orders/{$workOrder->id}", ['supervisor_ids' => [$supervisor->id]])->assertOk();

    expect($guard->isWorkOrderTouched($workOrder))->toBeTrue();
});

it('logs a participant enlisted through a note mention', function () {
    $workOrder = WorkOrder::factory()->create();
    $newcomer = User::factory()->create();

    (new WorkOrderNotable)->enlist($workOrder, [$newcomer->id]);

    expect(peopleEntries($workOrder))->toHaveCount(1)
        ->and(peopleEntries($workOrder)->first()->properties->get('attributes'))->toBe(['participants' => [$newcomer->id]]);
});

it('logs a quote update that changes the Gestori, on the quote and on the promoted opportunity', function () {
    $quote = Quote::factory()->create();
    $newcomer = User::factory()->create();
    Sanctum::actingAs(peopleActor(['quotes']));

    $this->patchJson("/api/quotes/{$quote->id}", ['manager_slots' => [$newcomer->id], 'promote_managers_to_opportunity' => true])->assertOk();

    $opportunity = Opportunity::query()->findOrFail($quote->opportunity_id);

    expect(peopleEntries($quote)->first()->properties->get('attributes'))->toBe(['manager_slots' => [$newcomer->id]])
        ->and(peopleEntries($opportunity)->first()->properties->get('attributes'))->toBe(['manager_slots' => [$newcomer->id]])
        ->and(app(LegacyRecordTouchGuard::class)->isQuoteTouched($quote))->toBeTrue();
});

it('logs a quote update that changes the flexible fields', function () {
    $category = ProductCategory::factory()->create(['business_function_id' => BusinessFunction::factory()->create()->id]);
    $attribute = Attribute::factory()->create(['code' => 'quote_field', 'type' => 'text']);
    $category->attributes()->attach($attribute->id, ['is_required' => false, 'sort_order' => 0, 'context' => AttributeContext::Quote->value]);
    $product = Product::factory()->create(['category_id' => $category->id]);
    $quote = Quote::factory()->create();
    QuoteLine::factory()->create(['quote_id' => $quote->id, 'product_id' => $product->id]);
    Sanctum::actingAs(peopleActor(['quotes']));

    $this->patchJson("/api/quotes/{$quote->id}", ['attribute_values' => ['quote_field' => 'x']])->assertOk();

    expect(peopleEntries($quote)->first()->properties->get('attributes'))->toBe(['attribute_values' => ['quote_field' => 'x']]);
});

it('logs an opportunity update that changes the Gestori', function () {
    $opportunity = Opportunity::factory()->create();
    $manager = User::factory()->create();
    Sanctum::actingAs(peopleActor(['opportunities']));

    $this->patchJson("/api/opportunities/{$opportunity->id}", ['manager_slots' => [$manager->id]])->assertOk();

    expect(peopleEntries($opportunity)->first()->properties->get('attributes'))->toBe(['manager_slots' => [$manager->id]]);
});

it('writes nothing while the activity log is off, as during an import', function () {
    $workOrder = WorkOrder::factory()->create();
    $quote = Quote::factory()->create();
    $manager = User::factory()->create();
    $before = DB::table('activity_log')->where('description', PEOPLE_DESCRIPTION)->count();

    activity()->withoutLogs(function () use ($workOrder, $quote, $manager): void {
        app(WorkOrderService::class)->update($workOrder, new UpdateWorkOrderData(supervisorIds: [$manager->id], participantSlots: [$manager->id]));
        app(QuoteManagerWriter::class)->sync($quote, [$manager->id], true);
    });

    expect($workOrder->supervisors()->count())->toBe(1)
        ->and($quote->managers()->count())->toBe(1)
        ->and(DB::table('activity_log')->where('description', PEOPLE_DESCRIPTION)->count())->toBe($before);
});

it('shows the entry in the activity history with user names, not raw ids', function () {
    $workOrder = WorkOrder::factory()->create();
    $supervisor = User::factory()->create(['name' => 'Mario Rossi']);
    Sanctum::actingAs(peopleActor(['work-orders']));
    $this->patchJson("/api/work-orders/{$workOrder->id}", ['supervisor_ids' => [$supervisor->id]])->assertOk();

    $response = $this->getJson("/api/activity-log/work-orders/{$workOrder->id}")->assertOk();
    $change = collect($response->json('data.items'))->flatMap(fn (array $entry) => $entry['changes'])->firstWhere('field', 'supervisors');

    expect($change['new_display'])->toBe('Mario Rossi')
        ->and($change['old_display'])->toBe('—');
});

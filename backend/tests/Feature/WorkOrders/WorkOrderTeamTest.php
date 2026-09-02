<?php

use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\User;
use App\Models\WorkOrder;
use Database\Seeders\DemoWorkOrderSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0096 — start date, Responsabili (`supervisors`) and Partecipanti
 * (`participants`) on the Commessa.
 *
 * Covers AC-001/004/005 (schema), AC-020..027 (write endpoints), AC-030/031
 * (read shape and eager loading) and AC-040/041 (field permissions). The
 * Contract "Programma" dialog's own AC-010..013 live in
 * tests/Feature/Contracts/ContractWorkOrderGenerationTest.php, beside the
 * rest of that endpoint's coverage.
 */
uses(RefreshDatabase::class);

if (! function_exists('workOrderTeamActor')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function workOrderTeamActor(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'viewAll'] as $ability) {
            Permission::findOrCreate("work-orders.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("work-orders.{$ability}");
        }

        // `viewAll` on top: this suite is about start date / Responsabili /
        // Partecipanti, not about the membership scoping (user directive
        // 2026-09-02, covered by WorkOrderVisibilityTest), so the actor keeps
        // seeing every commessa as it did before.
        $user->givePermissionTo('work-orders.viewAll');

        return $user;
    }
}

// ---------------------------------------------------------------------------
// schema — AC-001, AC-005
// ---------------------------------------------------------------------------

it('AC-001: start_date is NOT NULL on work_orders', function () {
    expect(Schema::hasColumn('work_orders', 'start_date'))->toBeTrue()
        // The Responsabili are a pivot, deliberately not a column any more.
        ->and(Schema::hasColumn('work_orders', 'supervisor_id'))->toBeFalse();

    $quote = Quote::factory()->create();

    expect(fn () => DB::table('work_orders')->insert([
        'code' => 'COM-8001', 'quote_id' => $quote->id, 'title' => 'No start date', 'type' => 'processing',
        'is_force_closed' => false, 'created_at' => now(), 'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('AC-005: work_order_supervisor rejects the same responsabile twice on one commessa', function () {
    $workOrder = WorkOrder::factory()->create();
    $supervisor = User::factory()->create();

    $workOrder->supervisors()->attach($supervisor->id);

    expect(fn () => $workOrder->supervisors()->attach($supervisor->id))->toThrow(QueryException::class);
});

it('AC-005: work_order_participant rejects the same user twice, and two members on the same position', function () {
    $workOrder = WorkOrder::factory()->create();
    $member = User::factory()->create();
    $other = User::factory()->create();

    $workOrder->participants()->attach($member->id, ['position' => 1]);

    expect(fn () => $workOrder->participants()->attach($member->id, ['position' => 2]))->toThrow(QueryException::class);
    expect(fn () => $workOrder->participants()->attach($other->id, ['position' => 1]))->toThrow(QueryException::class);
});

it('AC-005: deleting the work order cascades both user pivots away', function () {
    $workOrder = WorkOrder::factory()->create();
    $workOrder->supervisors()->attach(User::factory()->create()->id);
    $workOrder->participants()->attach(User::factory()->create()->id, ['position' => 1]);

    $workOrder->delete();

    expect(DB::table('work_order_supervisor')->where('work_order_id', $workOrder->id)->count())->toBe(0)
        ->and(DB::table('work_order_participant')->where('work_order_id', $workOrder->id)->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// create — AC-020..023, AC-027
// ---------------------------------------------------------------------------

it('AC-020: POST /api/work-orders without start_date or without a responsabile is 422', function () {
    $actor = workOrderTeamActor(['create']);
    $quote = Quote::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/work-orders', ['quote_id' => $quote->id, 'title' => 'No dates', 'type' => 'processing'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['start_date', 'supervisor_ids']);

    // An EMPTY array is not "at least one responsabile" either.
    $this->postJson('/api/work-orders', [
        'quote_id' => $quote->id, 'title' => 'Empty supervisors', 'type' => 'processing',
        'start_date' => '2026-09-10', 'supervisor_ids' => [],
    ])->assertStatus(422)->assertJsonValidationErrors('supervisor_ids');

    $this->postJson('/api/work-orders', [
        'quote_id' => $quote->id, 'title' => 'Unknown supervisor', 'type' => 'processing',
        'start_date' => '2026-09-10', 'supervisor_ids' => [999999],
    ])->assertStatus(422)->assertJsonValidationErrors('supervisor_ids.0');

    expect(WorkOrder::count())->toBe(0);
});

it('AC-020: a commessa can carry SEVERAL responsabili, returned ordered by name', function () {
    $actor = workOrderTeamActor(['create', 'view']);
    $quote = Quote::factory()->create();
    $zoe = User::factory()->create(['name' => 'Zoe Zanetti']);
    $ada = User::factory()->create(['name' => 'Ada Alberti']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/work-orders', [
        'quote_id' => $quote->id, 'title' => 'Due responsabili', 'type' => 'processing',
        'start_date' => '2026-09-10', 'supervisor_ids' => [$zoe->id, $ada->id],
    ])->assertCreated();

    expect($response->json('data.supervisors'))->toBe([
        ['id' => $ada->id, 'name' => 'Ada Alberti'],
        ['id' => $zoe->id, 'name' => 'Zoe Zanetti'],
    ]);
});

it('AC-021: participant_slots [7, null, 9] persists positions 1 and 3, preserving the gap', function () {
    $actor = workOrderTeamActor(['create', 'view']);
    $quote = Quote::factory()->create();
    $first = User::factory()->create();
    $third = User::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/work-orders', [
        ...workOrderRequiredFields(),
        'quote_id' => $quote->id, 'title' => 'Con partecipanti', 'type' => 'processing',
        'participant_slots' => [$first->id, null, $third->id],
    ])->assertCreated();

    expect($response->json('data.participants'))->toBe([
        ['id' => $first->id, 'name' => $first->name, 'position' => 1],
        ['id' => $third->id, 'name' => $third->name, 'position' => 3],
    ]);
});

it('AC-022: the same user in two slots is 422 and creates nothing', function () {
    $actor = workOrderTeamActor(['create']);
    $quote = Quote::factory()->create();
    $member = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/work-orders', [
        ...workOrderRequiredFields(),
        'quote_id' => $quote->id, 'title' => 'Doppio slot', 'type' => 'processing',
        'participant_slots' => [$member->id, $member->id],
    ])->assertStatus(422)->assertJsonValidationErrors('participant_slots');

    expect(WorkOrder::count())->toBe(0);
});

it('AC-023: more than 12 filled slots is 422', function () {
    $actor = workOrderTeamActor(['create']);
    $quote = Quote::factory()->create();
    $members = User::factory()->count(13)->create()->pluck('id')->all();
    Sanctum::actingAs($actor);

    $this->postJson('/api/work-orders', [
        ...workOrderRequiredFields(),
        'quote_id' => $quote->id, 'title' => 'Troppi', 'type' => 'processing',
        'participant_slots' => $members,
    ])->assertStatus(422)->assertJsonValidationErrors('participant_slots');
});

it('AC-027: a rejected create leaves no work order and no user pivot rows behind', function () {
    $actor = workOrderTeamActor(['create']);
    $quote = Quote::factory()->create();
    $otherQuoteLine = QuoteLine::factory()->create();
    Sanctum::actingAs($actor);

    // The line belongs to ANOTHER offer: WorkOrderLineWriter rejects it after
    // the insert, inside the transaction — the team sync must roll back too.
    $this->postJson('/api/work-orders', [
        ...workOrderRequiredFields(),
        'quote_id' => $quote->id, 'title' => 'Rollback', 'type' => 'processing',
        'quote_line_ids' => [$otherQuoteLine->id],
        'participant_slots' => [User::factory()->create()->id],
    ])->assertStatus(422);

    expect(WorkOrder::count())->toBe(0)
        ->and(DB::table('work_order_supervisor')->count())->toBe(0)
        ->and(DB::table('work_order_participant')->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// update — AC-024..026
// ---------------------------------------------------------------------------

it('AC-024: PATCH full-replaces each user relation only when its own key is submitted', function () {
    $actor = workOrderTeamActor(['update', 'view']);
    $workOrder = WorkOrder::factory()->create()->fresh();
    $supervisor = User::factory()->create();
    $before = User::factory()->create();
    $after = User::factory()->create();
    $workOrder->supervisors()->attach($supervisor->id);
    $workOrder->participants()->attach($before->id, ['position' => 1]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/work-orders/{$workOrder->id}", ['participant_slots' => [$after->id]])
        ->assertOk()
        ->assertJsonPath('data.participants.0.id', $after->id)
        ->assertJsonCount(1, 'data.participants')
        // The responsabili key was NOT submitted: that pivot stays untouched.
        ->assertJsonPath('data.supervisors.0.id', $supervisor->id);

    $this->patchJson("/api/work-orders/{$workOrder->id}", ['title' => 'Solo titolo'])->assertOk();

    expect($workOrder->fresh()->participants->pluck('id')->all())->toBe([$after->id])
        ->and($workOrder->fresh()->supervisors->pluck('id')->all())->toBe([$supervisor->id]);
});

it('AC-025: PATCH cannot null the start date nor empty the responsabili', function () {
    $actor = workOrderTeamActor(['update']);
    $workOrder = WorkOrder::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/work-orders/{$workOrder->id}", ['start_date' => null])
        ->assertStatus(422)->assertJsonValidationErrors('start_date');

    $this->patchJson("/api/work-orders/{$workOrder->id}", ['supervisor_ids' => []])
        ->assertStatus(422)->assertJsonValidationErrors('supervisor_ids');
});

it('AC-026: start_date and responsabili are editable after create, unlike code/quote_id', function () {
    $actor = workOrderTeamActor(['update', 'view']);
    $workOrder = WorkOrder::factory()->create(['start_date' => '2026-01-01']);
    $newSupervisor = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/work-orders/{$workOrder->id}", [
        'start_date' => '2026-12-24',
        'supervisor_ids' => [$newSupervisor->id],
    ])->assertOk()
        ->assertJsonPath('data.start_date', '2026-12-24')
        ->assertJsonPath('data.supervisors.0.id', $newSupervisor->id);

    $this->patchJson("/api/work-orders/{$workOrder->id}", ['code' => 'COM-0099'])
        ->assertStatus(422)->assertJsonValidationErrors('code');
});

// ---------------------------------------------------------------------------
// read shape — AC-030, AC-031
// ---------------------------------------------------------------------------

it('AC-030/031: the detail exposes start_date, responsabili and the ordered partecipanti without lazy loading', function () {
    $actor = workOrderTeamActor(['view']);
    $workOrder = WorkOrder::factory()->create(['start_date' => '2026-03-04']);
    $supervisor = User::factory()->create();
    $second = User::factory()->create();
    $first = User::factory()->create();
    $workOrder->supervisors()->attach($supervisor->id);
    $workOrder->participants()->attach($second->id, ['position' => 5]);
    $workOrder->participants()->attach($first->id, ['position' => 2]);
    Sanctum::actingAs($actor);

    // preventLazyLoading is enabled outside production (AppServiceProvider),
    // so an un-eager-loaded relation would fail this request outright.
    $this->getJson("/api/work-orders/{$workOrder->id}")
        ->assertOk()
        ->assertJsonPath('data.start_date', '2026-03-04')
        ->assertJsonPath('data.supervisors.0.id', $supervisor->id)
        ->assertJsonPath('data.participants.0.id', $first->id)
        ->assertJsonPath('data.participants.0.position', 2)
        ->assertJsonPath('data.participants.1.id', $second->id)
        ->assertJsonPath('data.participants.1.position', 5);
});

// ---------------------------------------------------------------------------
// field permissions — AC-040, AC-041
// ---------------------------------------------------------------------------

it('AC-040: the three new fields are required/editable for a writer and readonly for a reader', function () {
    $writer = workOrderTeamActor(['viewAny', 'create']);
    Sanctum::actingAs($writer);

    $fields = $this->getJson('/api/meta/work-orders')->assertOk()->json('permissions.fields');

    expect($fields['start_date']['editable'])->toBeTrue()
        ->and($fields['start_date']['required'])->toBeTrue()
        ->and($fields['supervisor_ids']['editable'])->toBeTrue()
        ->and($fields['supervisor_ids']['required'])->toBeTrue()
        ->and($fields['participant_slots']['editable'])->toBeTrue()
        ->and($fields['participant_slots']['required'])->toBeFalse();

    $reader = workOrderTeamActor(['viewAny']);
    Sanctum::actingAs($reader);

    $readerFields = $this->getJson('/api/meta/work-orders')->assertOk()->json('permissions.fields');

    expect($readerFields['start_date']['visible'])->toBeTrue()
        ->and($readerFields['start_date']['editable'])->toBeFalse()
        ->and($readerFields['supervisor_ids']['editable'])->toBeFalse()
        ->and($readerFields['participant_slots']['editable'])->toBeFalse();
});

it('AC-041: a reader submitting participant_slots is rejected by the field-permission gate', function () {
    $reader = workOrderTeamActor(['viewAny', 'update']);
    $workOrder = WorkOrder::factory()->create();
    Sanctum::actingAs($reader);

    // `update` alone makes the actor a writer, so revoke it to get a
    // read-only ceiling while keeping the route reachable.
    $reader->revokePermissionTo('work-orders.update');

    $this->patchJson("/api/work-orders/{$workOrder->id}", ['participant_slots' => [User::factory()->create()->id]])
        ->assertForbidden();
});

// ---------------------------------------------------------------------------
// date serialization — regression, see WorkOrderResource::formatDate()
// ---------------------------------------------------------------------------

it('both date fields serialize as Y-m-d, the shape an <input type="date"> accepts', function () {
    $actor = workOrderTeamActor(['view']);
    $workOrder = WorkOrder::factory()->create([
        'start_date' => '2026-03-04',
        'callback_date' => '2026-05-06',
    ]);
    Sanctum::actingAs($actor);

    // `callback_date` shipped in spec 0093 as a full ISO-8601 timestamp, which
    // a date input silently renders as EMPTY — so opening an existing commessa
    // showed no "Data richiamo" and saving would have cleared it.
    $this->getJson("/api/work-orders/{$workOrder->id}")
        ->assertOk()
        ->assertJsonPath('data.start_date', '2026-03-04')
        ->assertJsonPath('data.callback_date', '2026-05-06');
});

// ---------------------------------------------------------------------------
// demo seeder — it goes through the same service, so it must satisfy the same
// invariants (backend.md §3.1: demo data lives on the DemoDataSeeder path)
// ---------------------------------------------------------------------------

it('DemoWorkOrderSeeder produces commesse with a responsabile and partecipanti', function () {
    User::factory()->count(4)->create();
    $quote = Quote::factory()->create();
    QuoteLine::factory()->create(['quote_id' => $quote->id]);

    app(DemoWorkOrderSeeder::class)->run();

    $workOrders = WorkOrder::with(['supervisors', 'participants'])->get();

    expect($workOrders)->not->toBeEmpty();
    $workOrders->each(function (WorkOrder $workOrder): void {
        expect($workOrder->start_date)->not->toBeNull()
            ->and($workOrder->supervisors)->not->toBeEmpty()
            ->and($workOrder->participants)->not->toBeEmpty();
    });
});

// ---------------------------------------------------------------------------
// grid columns — AC-050, AC-051, AC-052
// ---------------------------------------------------------------------------

it('AC-050: rows carry start_date and the responsabili avatar-stack payload', function () {
    $actor = workOrderTeamActor(['viewAny']);
    $workOrder = WorkOrder::factory()->create(['start_date' => '2026-07-08']);
    $supervisor = User::factory()->create(['name' => 'Ada Alberti']);
    $workOrder->supervisors()->attach($supervisor->id);
    Sanctum::actingAs($actor);

    $row = collect(
        $this->postJson('/api/tables/work-orders/rows', ['startRow' => 0, 'endRow' => 25])
            ->assertOk()->json('items')
    )->firstWhere('id', $workOrder->id);

    expect($row['start_date'])->toStartWith('2026-07-08')
        ->and($row['supervisors'])->toHaveCount(1)
        ->and($row['supervisors'][0]['name'])->toBe('Ada Alberti')
        // AC-052: the same {id,name,avatar_url} shape UserStackCell renders.
        ->and($row['supervisors'][0])->toHaveKey('avatar_url');
});

it('AC-050: the start_date column sorts and filters by date', function () {
    $actor = workOrderTeamActor(['viewAny']);
    WorkOrder::factory()->create(['title' => 'Tardi', 'start_date' => '2026-08-01']);
    WorkOrder::factory()->create(['title' => 'Presto', 'start_date' => '2026-02-01']);
    Sanctum::actingAs($actor);

    $sorted = $this->postJson('/api/tables/work-orders/rows', [
        'startRow' => 0, 'endRow' => 25,
        'sortModel' => [['colId' => 'start_date', 'sort' => 'asc']],
    ])->assertOk()->json('items');

    expect(collect($sorted)->pluck('title')->all())->toBe(['Presto', 'Tardi']);

    $filtered = $this->postJson('/api/tables/work-orders/rows', [
        'startRow' => 0, 'endRow' => 25,
        'filterModel' => ['start_date' => ['filterType' => 'date', 'type' => 'greaterThan', 'dateFrom' => '2026-05-01']],
    ])->assertOk()->json('items');

    expect(collect($filtered)->pluck('title')->all())->toBe(['Tardi']);
});

it('AC-051: the supervisors set filter matches a commessa by ANY of its responsabili', function () {
    $actor = workOrderTeamActor(['viewAny']);
    $wanted = WorkOrder::factory()->create(['title' => 'Con Ada']);
    $other = WorkOrder::factory()->create(['title' => 'Senza Ada']);
    $ada = User::factory()->create(['name' => 'Ada Alberti']);
    $wanted->supervisors()->attach([$ada->id, User::factory()->create()->id]);
    $other->supervisors()->attach(User::factory()->create(['name' => 'Zoe Zanetti'])->id);
    Sanctum::actingAs($actor);

    $items = $this->postJson('/api/tables/work-orders/rows', [
        'startRow' => 0, 'endRow' => 25,
        'filterModel' => ['supervisors' => ['filterType' => 'set', 'values' => ['Ada Alberti']]],
    ])->assertOk()->json('items');

    expect(collect($items)->pluck('title')->all())->toBe(['Con Ada']);
});

it('AC-051: distinct values for supervisors list only names actually in charge, deduplicated', function () {
    $actor = workOrderTeamActor(['viewAny']);
    $ada = User::factory()->create(['name' => 'Ada Alberti']);
    User::factory()->create(['name' => 'Mai Assegnato']);
    WorkOrder::factory()->create()->supervisors()->attach($ada->id);
    WorkOrder::factory()->create()->supervisors()->attach($ada->id);
    Sanctum::actingAs($actor);

    $values = $this->postJson('/api/tables/work-orders/values', ['columnId' => 'supervisors', 'limit' => 25])
        ->assertOk()->json('data.values');

    expect($values)->toBe(['Ada Alberti']);
});

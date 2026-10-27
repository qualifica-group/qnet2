<?php

use App\Models\Attachment;
use App\Models\Contract;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\Registry;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0316, D-9 / AC-040: the attachments of an opportunity, work order or
 * contract need `view` on the owning record on every endpoint. Other owner
 * types keep the plain `attachments.*` check.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
});

/**
 * @return array{0: User, 1: array<string, Closure>}
 */
function ownerScopedFixture(): array
{
    foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
        Permission::findOrCreate("attachments.{$ability}");
    }

    foreach (['opportunities', 'work-orders', 'contracts'] as $resource) {
        foreach (['viewAny', 'view', 'viewAll'] as $ability) {
            Permission::findOrCreate("{$resource}.{$ability}");
        }
    }

    $actor = User::factory()->create();
    $actor->givePermissionTo([
        'attachments.viewAny', 'attachments.view', 'attachments.create', 'attachments.delete',
        'opportunities.view', 'work-orders.view', 'contracts.view',
    ]);

    // Per type: [visible owner, hidden owner] for this actor.
    $owners = [
        'opportunity' => [
            fn () => Opportunity::factory()->create(['supervisor_id' => $actor->id]),
            fn () => Opportunity::factory()->create(),
        ],
        'work_order' => [
            function () use ($actor) {
                $workOrder = WorkOrder::factory()->create();
                $workOrder->supervisors()->attach($actor->id);

                return $workOrder;
            },
            fn () => WorkOrder::factory()->create(),
        ],
        'contract' => [
            fn () => Contract::factory()->create(['quote_id' => Quote::factory()->create(['supervisor_id' => $actor->id])->id]),
            fn () => Contract::factory()->create(),
        ],
    ];

    return [$actor, $owners];
}

function ownerScopedAttachment(string $alias, Model $owner): Attachment
{
    return Attachment::factory()->create([
        'attachable_type' => $alias,
        'attachable_id' => $owner->getKey(),
        'disk' => 'local',
    ]);
}

foreach (['opportunity', 'work_order', 'contract'] as $alias) {
    it("{$alias}: index, show, download, store and delete are 403 when the owner is not visible", function () use ($alias) {
        [$actor, $owners] = ownerScopedFixture();
        Sanctum::actingAs($actor);
        $hidden = $owners[$alias][1]();
        $attachment = ownerScopedAttachment($alias, $hidden);

        $this->getJson("/api/attachments?attachable_type={$alias}&attachable_id={$hidden->id}")->assertForbidden();
        $this->getJson("/api/attachments/{$attachment->id}")->assertForbidden();
        $this->getJson("/api/attachments/{$attachment->id}/download")->assertForbidden();
        $this->postJson('/api/attachments', [
            'file' => UploadedFile::fake()->create('a.pdf', 8, 'application/pdf'),
            'attachable_type' => $alias,
            'attachable_id' => $hidden->id,
        ])->assertForbidden();
        $this->deleteJson("/api/attachments/{$attachment->id}")->assertForbidden();

        expect(Attachment::query()->whereKey($attachment->id)->exists())->toBeTrue();
    });

    it("{$alias}: every endpoint succeeds when the owner is visible", function () use ($alias) {
        [$actor, $owners] = ownerScopedFixture();
        Sanctum::actingAs($actor);
        $visible = $owners[$alias][0]();
        $attachment = ownerScopedAttachment($alias, $visible);
        Storage::disk('local')->put($attachment->path, 'x');

        $this->getJson("/api/attachments?attachable_type={$alias}&attachable_id={$visible->id}")
            ->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/attachments/{$attachment->id}")->assertOk();
        $this->get("/api/attachments/{$attachment->id}/download")->assertOk();
        $this->postJson('/api/attachments', [
            'file' => UploadedFile::fake()->create('a.pdf', 8, 'application/pdf'),
            'attachable_type' => $alias,
            'attachable_id' => $visible->id,
        ])->assertCreated();
        $this->deleteJson("/api/attachments/{$attachment->id}")->assertNoContent();
    });

    it("{$alias}: viewAll on the owner module lifts the scope", function () use ($alias) {
        [$actor, $owners] = ownerScopedFixture();
        $actor->givePermissionTo(['opportunities.viewAll', 'work-orders.viewAll', 'contracts.viewAll']);
        Sanctum::actingAs($actor);
        $hidden = $owners[$alias][1]();

        $this->getJson("/api/attachments?attachable_type={$alias}&attachable_id={$hidden->id}")->assertOk();
    });
}

it('registry (non owner-scoped) attachments are unchanged: the attachments permission is enough', function () {
    [$actor] = ownerScopedFixture();
    Sanctum::actingAs($actor);
    $registry = Registry::factory()->create();
    $attachment = ownerScopedAttachment('registry', $registry);

    $this->getJson("/api/attachments?attachable_type=registry&attachable_id={$registry->id}")->assertOk();
    $this->getJson("/api/attachments/{$attachment->id}")->assertOk();
    $this->deleteJson("/api/attachments/{$attachment->id}")->assertNoContent();
});

it('opportunity: an operator of one of its offers reaches the documents without opportunities.view (request management)', function () {
    [$actor] = ownerScopedFixture();
    foreach (['request-management.viewAny', 'request-management.view', 'request-management.viewAll'] as $name) {
        Permission::findOrCreate($name);
    }
    $actor->revokePermissionTo('opportunities.view');
    $actor->givePermissionTo('request-management.view');
    $opportunity = Opportunity::factory()->create();
    Quote::factory()->create(['opportunity_id' => $opportunity->id, 'operator_id' => $actor->id]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/attachments?attachable_type=opportunity&attachable_id={$opportunity->id}")->assertOk();
    $this->postJson('/api/attachments', [
        'file' => UploadedFile::fake()->create('a.pdf', 8, 'application/pdf'),
        'attachable_type' => 'opportunity',
        'attachable_id' => $opportunity->id,
    ])->assertCreated();
});

it('opportunity: request management without scope on its offers is refused', function () {
    [$actor] = ownerScopedFixture();
    Permission::findOrCreate('request-management.view');
    $actor->revokePermissionTo('opportunities.view');
    $actor->givePermissionTo('request-management.view');
    $opportunity = Opportunity::factory()->create();
    Quote::factory()->create(['opportunity_id' => $opportunity->id, 'operator_id' => User::factory()->create()->id]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/attachments?attachable_type=opportunity&attachable_id={$opportunity->id}")->assertForbidden();
});

it('opportunity: a viewer of a contract on its offer reaches the documents, another contract viewer does not', function () {
    [$actor] = ownerScopedFixture();
    $actor->revokePermissionTo('opportunities.view');
    $opportunity = Opportunity::factory()->create();
    $mine = Quote::factory()->create(['opportunity_id' => $opportunity->id, 'supervisor_id' => $actor->id]);
    Contract::factory()->create(['quote_id' => $mine->id]);
    $otherOpportunity = Opportunity::factory()->create();
    Contract::factory()->create(['quote_id' => Quote::factory()->create(['opportunity_id' => $otherOpportunity->id])->id]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/attachments?attachable_type=opportunity&attachable_id={$opportunity->id}")->assertOk();
    $this->getJson("/api/attachments?attachable_type=opportunity&attachable_id={$otherOpportunity->id}")->assertForbidden();
});

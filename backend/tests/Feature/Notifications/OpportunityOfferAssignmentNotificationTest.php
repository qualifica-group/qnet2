<?php

use App\Enums\CategoryManagementMode;
use App\Models\BusinessFunction;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\Registry;
use App\Models\User;
use App\Notifications\RecordAssignmentNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Rev. 2026-10-01 (decisione utente): who sits on the Opportunity but on none
// of its Offerte gets the Opportunity notification; who is inserted in an
// Offerta gets the Offerta notification only, never the Opportunity one.

uses(RefreshDatabase::class);

if (! function_exists('offerAssignmentUserWith')) {
    /**
     * @param  array<int, string>  $permissions
     */
    function offerAssignmentUserWith(array $permissions): User
    {
        $user = User::factory()->create();

        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission));
        }

        return $user;
    }
}

if (! function_exists('offerAssignmentRecipient')) {
    // Sees both modules, so the link tells the two notifications apart.
    function offerAssignmentRecipient(): User
    {
        return offerAssignmentUserWith(['opportunities.view', 'quotes.view']);
    }
}

if (! function_exists('offerAssignmentPaths')) {
    /**
     * @return array<int, string|null> the action_url of every assignment notification $recipient got
     */
    function offerAssignmentPaths(User $recipient): array
    {
        return Notification::sent($recipient, RecordAssignmentNotification::class)
            ->map(fn (RecordAssignmentNotification $notification): ?string => $notification->toArray($recipient)['action_url'])
            ->values()
            ->all();
    }
}

if (! function_exists('offerAssignmentSynchronizedOpportunity')) {
    function offerAssignmentSynchronizedOpportunity(): Opportunity
    {
        $category = ProductCategory::factory()->create([
            'business_function_id' => BusinessFunction::factory()->create()->id,
            'single_quote_per_opportunity' => true,
            'management_mode' => CategoryManagementMode::Single,
        ]);

        $opportunity = Opportunity::factory()->create();
        $opportunity->productLines()->create([
            'business_function_id' => $category->business_function_id,
            'product_category_id' => $category->id,
        ]);

        return $opportunity;
    }
}

it('notifies a manager inserted in the Opportunity only with the Opportunity notification', function () {
    Notification::fake();

    $recipient = offerAssignmentRecipient();
    $opportunity = Opportunity::factory()->create();
    Quote::factory()->for($opportunity)->create();
    Sanctum::actingAs(offerAssignmentUserWith(['opportunities.update']));

    $this->patchJson("/api/opportunities/{$opportunity->id}", ['manager_slots' => [$recipient->id]])->assertOk();

    expect(offerAssignmentPaths($recipient))->toBe(["/opportunities/{$opportunity->id}"]);
});

it('sends only the Offerta notification when a synchronized Opportunity copies the manager onto its Offerta', function () {
    Notification::fake();

    $recipient = offerAssignmentRecipient();
    $opportunity = offerAssignmentSynchronizedOpportunity();
    $quote = Quote::factory()->for($opportunity)->create();
    Sanctum::actingAs(offerAssignmentUserWith(['opportunities.update']));

    $this->patchJson("/api/opportunities/{$opportunity->id}", ['manager_slots' => [$recipient->id]])->assertOk();

    expect(offerAssignmentPaths($recipient))->toBe(["/quotes/{$quote->id}"]);
});

it('sends only the Offerta notification to the managers of an Opportunity converted from a lead', function () {
    Notification::fake();

    $recipient = offerAssignmentRecipient();
    $actor = offerAssignmentUserWith(['opportunities.create', 'leads.view']);
    $businessFunction = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $businessFunction->id]);
    $lead = Lead::factory()->create([
        'campaign_id' => Campaign::factory()->create()->id,
        'registry_id' => Registry::factory()->create()->id,
    ]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/opportunities', [
        'lead_id' => $lead->id,
        'product_lines' => [['business_function_id' => $businessFunction->id, 'product_category_id' => $category->id]],
        'manager_slots' => [$recipient->id],
    ])->assertCreated();

    $quote = Quote::query()->where('opportunity_id', $response->json('data.id'))->sole();

    expect(offerAssignmentPaths($recipient))->toBe(["/quotes/{$quote->id}"]);
});

it('does not send the Opportunity notification to a supervisor who already manages one of its Offerte', function () {
    Notification::fake();

    $recipient = offerAssignmentRecipient();
    $opportunity = Opportunity::factory()->create();
    $opportunity->managers()->sync([$recipient->id => ['position' => 1]]);
    Quote::factory()->for($opportunity)->create()->managers()->sync([$recipient->id => ['position' => 1]]);
    Sanctum::actingAs(offerAssignmentUserWith(['opportunities.update']));

    $this->patchJson("/api/opportunities/{$opportunity->id}", ['supervisor_id' => $recipient->id])->assertOk();

    expect(offerAssignmentPaths($recipient))->toBe([]);
});

<?php

use App\Actions\Leads\ConvertLeadToOpportunity;
use App\Models\BusinessFunction;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Registry;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * "Note generali" (`opportunities.general_notes`, user directive 2026-07-27):
 * a plain free-text field, prefilled from the originating Lead's own `notes`
 * at conversion but NEVER BR-2-locked (freely editable/clearable afterwards).
 */
uses(RefreshDatabase::class);

if (! function_exists('generalNotesOpportunityUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function generalNotesOpportunityUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("opportunities.{$ability}");
            Permission::findOrCreate("leads.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo($ability);
        }

        return $user;
    }
}

if (! function_exists('generalNotesMandatoryOpportunityFks')) {
    /**
     * @return array{registry_id: int, supervisor_id: int, product_lines: array<int, array{business_function_id: int, product_category_id: int}>, products_of_interest: array<int, int>}
     */
    function generalNotesMandatoryOpportunityFks(): array
    {
        $businessFunction = BusinessFunction::factory()->create();
        $category = ProductCategory::factory()->create(['business_function_id' => $businessFunction->id]);

        return [
            'registry_id' => Registry::factory()->create()->id,
            'supervisor_id' => User::factory()->create()->id,
            'product_lines' => [
                ['business_function_id' => $businessFunction->id, 'product_category_id' => $category->id],
            ],
            'products_of_interest' => [Product::factory()->create(['category_id' => $category->id])->id],
        ];
    }
}

if (! function_exists('leadWithNotes')) {
    /**
     * A lead complete enough for the defaults endpoint AND the contextual
     * conversion (a campaign carrying both a business function and a product
     * category), carrying free-text notes.
     */
    function leadWithNotes(?string $notes): Lead
    {
        $businessFunction = BusinessFunction::factory()->create();
        $productCategory = ProductCategory::factory()->create(['business_function_id' => $businessFunction->id]);
        $campaign = Campaign::factory()->create([
            'business_function_id' => $businessFunction->id,
            'product_category_id' => $productCategory->id,
        ]);

        return Lead::factory()->create([
            'campaign_id' => $campaign->id,
            'registry_id' => Registry::factory()->create()->id,
            'source_id' => Source::factory()->create()->id,
            'operational_site_id' => OperationalSite::factory()->withAddress()->create()->id,
            'notes' => $notes,
        ]);
    }
}

it('create: general_notes is persisted and read back', function () {
    $actor = generalNotesOpportunityUserWith(['opportunities.create']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/opportunities', array_merge(generalNotesMandatoryOpportunityFks(), [
        'general_notes' => 'Il cliente richiama a settembre.',
    ]))->assertCreated();

    $this->assertDatabaseHas('opportunities', [
        'id' => $response->json('data.id'),
        'general_notes' => 'Il cliente richiama a settembre.',
    ]);
    expect($response->json('data.general_notes'))->toBe('Il cliente richiama a settembre.');
});

it('create: without general_notes the field stays null', function () {
    $actor = generalNotesOpportunityUserWith(['opportunities.create']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/opportunities', generalNotesMandatoryOpportunityFks())->assertCreated();

    expect($response->json('data.general_notes'))->toBeNull();
});

it('create: general_notes longer than 5000 chars -> 422', function () {
    $actor = generalNotesOpportunityUserWith(['opportunities.create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/opportunities', array_merge(generalNotesMandatoryOpportunityFks(), [
        'general_notes' => str_repeat('a', 5001),
    ]))->assertStatus(422)->assertJsonValidationErrors('general_notes');
});

it('update: PATCH general_notes rewrites the field', function () {
    $actor = generalNotesOpportunityUserWith(['opportunities.update']);
    $opportunity = Opportunity::factory()->create(['general_notes' => 'Prima nota']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/opportunities/{$opportunity->id}", ['general_notes' => 'Nota aggiornata'])
        ->assertOk()
        ->assertJsonPath('data.general_notes', 'Nota aggiornata');

    expect($opportunity->fresh()->general_notes)->toBe('Nota aggiornata');
});

it('update: PATCH general_notes null clears the field', function () {
    $actor = generalNotesOpportunityUserWith(['opportunities.update']);
    $opportunity = Opportunity::factory()->create(['general_notes' => 'Da cancellare']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/opportunities/{$opportunity->id}", ['general_notes' => null])
        ->assertOk()
        ->assertJsonPath('data.general_notes', null);

    expect($opportunity->fresh()->general_notes)->toBeNull();
});

it('opportunity-defaults: the lead notes surface as general_notes, never locked', function () {
    $actor = generalNotesOpportunityUserWith(['opportunities.create', 'leads.view']);
    $lead = leadWithNotes('Nota del lead');
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/leads/{$lead->id}/opportunity-defaults")->assertOk();

    expect($response->json('data.values.general_notes'))->toBe('Nota del lead');
    expect($response->json('data.locked_fields'))->not->toContain('general_notes');
});

it('opportunity-defaults: a lead without notes derives a null general_notes', function () {
    $actor = generalNotesOpportunityUserWith(['opportunities.create', 'leads.view']);
    $lead = leadWithNotes(null);
    Sanctum::actingAs($actor);

    $this->getJson("/api/leads/{$lead->id}/opportunity-defaults")
        ->assertOk()
        ->assertJsonPath('data.values.general_notes', null);
});

it('create from a lead: general_notes is freely overridable (never prohibited)', function () {
    $actor = generalNotesOpportunityUserWith(['opportunities.create', 'leads.view']);
    $lead = leadWithNotes('Nota del lead');
    Sanctum::actingAs($actor);

    $payload = array_merge(generalNotesMandatoryOpportunityFks(), [
        'lead_id' => $lead->id,
        'general_notes' => 'Nota riscritta in fase di creazione',
    ]);
    // registry_id/source_id are BR-2-derived from the lead: sending them is
    // `prohibited`, so they are dropped from this payload.
    unset($payload['registry_id']);

    $response = $this->postJson('/api/opportunities', $payload)->assertCreated();

    expect($response->json('data.general_notes'))->toBe('Nota riscritta in fase di creazione');
});

it('contextual conversion: the generated opportunity inherits the lead notes', function () {
    $actor = generalNotesOpportunityUserWith(['opportunities.create', 'leads.create']);
    $lead = leadWithNotes('Nota da ereditare');
    Sanctum::actingAs($actor);

    $opportunity = app(ConvertLeadToOpportunity::class)->handle($lead);

    expect($opportunity->general_notes)->toBe('Nota da ereditare');
    $this->assertDatabaseHas('opportunities', [
        'id' => $opportunity->id,
        'general_notes' => 'Nota da ereditare',
    ]);
});

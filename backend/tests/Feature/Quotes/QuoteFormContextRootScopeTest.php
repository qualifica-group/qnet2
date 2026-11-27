<?php

declare(strict_types=1);

use App\Enums\AttributeContext;
use App\Models\Attribute;
use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// User report 2026-11-27 (offer 31044): a request whose lines span two roots
// has one offer per root (spec 0411). Editing the offer of one root, the form
// preview must resolve only that root's opportunity lines, as the save does;
// otherwise it renders the other offer's fields and the save refuses them as
// "not part of the applicable attribute set".

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $function = BusinessFunction::factory()->create();
    $this->opportunity = Opportunity::factory()->create();

    foreach (['tender' => 'tender_field', 'safety' => 'safety_field'] as $key => $code) {
        $root = ProductCategory::factory()->create(['business_function_id' => $function->id]);
        $child = ProductCategory::factory()->childOf($root)->create(['business_function_id' => $function->id]);
        $root->attributes()->attach(Attribute::factory()->create(['code' => $code, 'type' => 'text'])->id, [
            'is_required' => false, 'sort_order' => 0, 'context' => AttributeContext::Quote->value,
        ]);
        OpportunityProductLine::factory()->create([
            'opportunity_id' => $this->opportunity->id,
            'business_function_id' => $function->id,
            'product_category_id' => $child->id,
        ]);
        $this->{$key.'Root'} = $root;
    }
});

function formContextRootActor(array $abilities): User
{
    $user = User::factory()->create();

    foreach ($abilities as $ability) {
        $user->givePermissionTo(Permission::findOrCreate("quotes.{$ability}"));
    }

    return $user;
}

function boundQuoteOn(Opportunity $opportunity, ProductCategory $root): Quote
{
    $quote = Quote::factory()->create(['opportunity_id' => $opportunity->id]);
    $quote->forceFill(['root_product_category_id' => $root->id])->save();

    return $quote;
}

it('form-context with quote_id resolves only the opportunity lines under the bound offer root', function () {
    Sanctum::actingAs(formContextRootActor(['create', 'view', 'viewAll']));
    $quote = boundQuoteOn($this->opportunity, $this->tenderRoot);

    $response = $this->postJson('/api/quotes/form-context', [
        'opportunity_id' => $this->opportunity->id,
        'quote_id' => $quote->id,
    ])->assertOk();

    expect(collect($response->json('data.applicable_attributes'))->pluck('code')->all())->toBe(['tender_field']);
});

it('form-context without quote_id keeps reading every opportunity line', function () {
    Sanctum::actingAs(formContextRootActor(['create']));

    $response = $this->postJson('/api/quotes/form-context', ['opportunity_id' => $this->opportunity->id])->assertOk();

    expect(collect($response->json('data.applicable_attributes'))->pluck('code')->sort()->values()->all())
        ->toBe(['safety_field', 'tender_field']);
});

it('form-context: a quote_id the actor cannot view -> 403', function () {
    Sanctum::actingAs(formContextRootActor(['create']));
    $quote = boundQuoteOn($this->opportunity, $this->tenderRoot);

    $this->postJson('/api/quotes/form-context', [
        'opportunity_id' => $this->opportunity->id,
        'quote_id' => $quote->id,
    ])->assertForbidden();
});

it('form-context: an unknown quote_id -> 422', function () {
    Sanctum::actingAs(formContextRootActor(['create']));

    $this->postJson('/api/quotes/form-context', ['quote_id' => 999999])
        ->assertStatus(422)
        ->assertJsonValidationErrors('quote_id');
});

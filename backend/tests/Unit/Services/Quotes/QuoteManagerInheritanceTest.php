<?php

use App\Models\Opportunity;
use App\Models\User;
use App\Services\Quotes\QuoteManagerInheritance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Spec 0087, D-5: the ordered, gap-aware `manager_slots` array a brand-new
// Offerta is PREFILLED from, when the client submitted none — QuoteService's
// dedicated collaborator (extracted for the file-size hard limit, R-4).

uses(TestCase::class, RefreshDatabase::class);

it('[] when the opportunity has no manager', function () {
    $opportunity = Opportunity::factory()->create();

    expect(app(QuoteManagerInheritance::class)->fromOpportunity($opportunity))->toBe([]);
});

it('mirrors the opportunity\'s managers at their SAME positions, gap preserved', function () {
    $opportunity = Opportunity::factory()->create();
    $ga1 = User::factory()->create();
    $ga3 = User::factory()->create();
    $opportunity->managers()->sync([$ga1->id => ['position' => 1], $ga3->id => ['position' => 3]]);

    expect(app(QuoteManagerInheritance::class)->fromOpportunity($opportunity))->toBe([$ga1->id, null, $ga3->id]);
});

it('reads an already-loaded managers relation without an extra query, never lazy-loading', function () {
    $opportunity = Opportunity::factory()->create();
    $manager = User::factory()->create();
    $opportunity->managers()->sync([$manager->id => ['position' => 2]]);
    $opportunity->load('managers');

    expect($opportunity->relationLoaded('managers'))->toBeTrue()
        ->and(app(QuoteManagerInheritance::class)->fromOpportunity($opportunity))->toBe([null, $manager->id]);
});

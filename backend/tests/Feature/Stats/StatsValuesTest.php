<?php

use App\Models\BusinessFunction;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\OperationalSite;
use App\Models\PipelineStatus;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Project;
use App\Models\Registry;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// AC-005 — widget values on a known dataset
// ---------------------------------------------------------------------------

it('leads: total, with_source, with_site, source split and trend (AC-005)', function () {
    $source = Source::factory()->create(['name' => 'Web']);
    $site = OperationalSite::factory()->create();
    Lead::factory()->count(3)->create(['source_id' => $source->id, 'operational_site_id' => $site->id]);
    Lead::factory()->create(['source_id' => null, 'operational_site_id' => null]);

    $widgets = statsWidgets('leads');

    expect(statsWidget($widgets, 'total'))->toMatchArray([
        'type' => 'stat', 'label' => 'leads.stats.total', 'value' => 4, 'format' => 'number',
    ]);
    expect(statsWidget($widgets, 'with_source')['value'])->toBe(3);
    expect(statsWidget($widgets, 'with_site')['value'])->toBe(3);

    $bySource = statsWidget($widgets, 'by_source');
    expect($bySource)->toMatchArray(['type' => 'distribution', 'label' => 'leads.stats.bySource', 'total' => 4])
        ->and($bySource['items'])->toBe([
            ['key' => (string) $source->id, 'label' => 'Web', 'value' => 3, 'color' => null],
        ]);

    $trend = statsWidget($widgets, 'trend');
    expect($trend['type'])->toBe('trend')
        ->and($trend['points'])->toHaveCount(12)
        ->and(end($trend['points']))->toBe(['label' => now()->format('Y-m'), 'value' => 4])
        ->and($trend['points'][0]['value'])->toBe(0);
});

it('leads: with_source and with_site are 0 on an empty module (AC-005)', function () {
    $widgets = statsWidgets('leads');

    expect(statsWidget($widgets, 'total')['value'])->toBe(0)
        ->and(statsWidget($widgets, 'with_source')['value'])->toBe(0)
        ->and(statsWidget($widgets, 'with_site')['value'])->toBe(0)
        ->and(statsWidget($widgets, 'by_source')['items'])->toBe([])
        ->and(statsWidget($widgets, 'by_source')['total'])->toBe(0);
});

it('companies: a percent with a zero denominator is null, not 0 (AC-005)', function () {
    $widgets = statsWidgets('companies');

    expect(statsWidget($widgets, 'with_vat_number')['value'])->toBeNull();
});

it('registries: supplier percentages carry the absolute count in the subtitle (AC-005)', function () {
    Registry::factory()->count(2)->create(['is_supplier' => true, 'is_qualified_supplier' => true]);
    Registry::factory()->count(2)->create(['is_supplier' => false]);

    $widgets = statsWidgets('registries');

    expect(statsWidget($widgets, 'total')['value'])->toBe(4);

    $suppliers = statsWidget($widgets, 'suppliers');
    expect($suppliers)->toMatchArray(['value' => 50, 'format' => 'percent'])
        ->and($suppliers['subtitle'])->toBe(['key' => 'registries.stats.suppliersSubtitle', 'count' => 2]);

    expect(statsWidget($widgets, 'qualified_suppliers')['value'])->toBe(50);
});

it('registries: enum distributions label and color each case from the enum (AC-005)', function () {
    Registry::factory()->count(2)->create(['agreement_status' => 'agreed', 'size_class' => 'micro']);
    Registry::factory()->create(['agreement_status' => 'rejected']);

    $widgets = statsWidgets('registries');

    $byAgreement = statsWidget($widgets, 'by_agreement_status');
    expect($byAgreement['total'])->toBe(3)
        ->and($byAgreement['items'])->toBe([
            ['key' => 'agreed', 'label' => 'Agreed', 'value' => 2, 'color' => null],
            ['key' => 'rejected', 'label' => 'Rejected', 'value' => 1, 'color' => null],
        ]);

    // The registry with no size_class is absent from the items but still part
    // of the denominator.
    $bySize = statsWidget($widgets, 'by_size_class');
    expect($bySize['total'])->toBe(3)
        ->and($bySize['items'])->toHaveCount(1)
        ->and($bySize['items'][0]['value'])->toBe(2);
});

it('products: average price and margin are computed in SQL (AC-005)', function () {
    $category = ProductCategory::factory()->create(['name' => 'Hardware']);
    Product::factory()->create(['price' => 100, 'cost' => 40, 'category_id' => $category->id]);
    Product::factory()->create(['price' => 200, 'cost' => 60, 'category_id' => $category->id]);

    $widgets = statsWidgets('products');

    expect(statsWidget($widgets, 'total')['value'])->toBe(2)
        ->and(statsWidget($widgets, 'average_price'))->toMatchArray(['value' => 150.0, 'format' => 'currency'])
        ->and(statsWidget($widgets, 'average_margin'))->toMatchArray(['value' => 100.0, 'format' => 'currency']);

    $byCategory = statsWidget($widgets, 'by_category');
    expect($byCategory['total'])->toBe(2)
        ->and($byCategory['items'])->toBe([
            ['key' => (string) $category->id, 'label' => 'Hardware', 'value' => 2, 'color' => null],
        ]);

    expect(statsWidget($widgets, 'by_type')['items'][0])
        ->toMatchArray(['key' => 'SERVICE', 'value' => 2]);
});

it('products: the currency averages are null on an empty catalogue (AC-005)', function () {
    $widgets = statsWidgets('products');

    expect(statsWidget($widgets, 'average_price')['value'])->toBeNull()
        ->and(statsWidget($widgets, 'average_margin')['value'])->toBeNull();
});

it('campaigns: budget, generated leads and the effective project status split (AC-005)', function () {
    $running = PipelineStatus::factory()->create(['name' => 'Running', 'color' => '#22c55e']);
    $project = Project::factory()->create(['pipeline_status_id' => $running->id]);
    $linked = Campaign::factory()->forProject($project)->create(['total_budget' => 500]);

    $draft = PipelineStatus::factory()->create(['name' => 'Draft', 'color' => null]);
    Campaign::factory()->create(['pipeline_status_id' => $draft->id, 'total_budget' => 1000]);

    Lead::factory()->count(2)->create(['campaign_id' => $linked->id]);

    $widgets = statsWidgets('campaigns');

    expect(statsWidget($widgets, 'total')['value'])->toBe(2)
        ->and(statsWidget($widgets, 'linked_to_project')['value'])->toBe(1)
        ->and(statsWidget($widgets, 'total_budget'))->toMatchArray(['value' => 1500.0, 'format' => 'currency'])
        ->and(statsWidget($widgets, 'generated_leads')['value'])->toBe(2);

    // The project-linked campaign derives its status from the project (its own
    // column is null), so BOTH statuses must appear.
    $byStatus = collect(statsWidget($widgets, 'by_pipeline_status')['items'])->keyBy('label')->all();
    expect($byStatus)->toHaveCount(2)
        ->and($byStatus['Running'])->toMatchArray(['value' => 1, 'color' => '#22c55e'])
        ->and($byStatus['Draft'])->toMatchArray(['value' => 1, 'color' => null]);
});

/**
 * REQUIREMENT CHANGE: the `total_budget` stat is gone from the module (every
 * module exposes exactly 4 stats now), so this case no longer asserts it — the
 * remaining KPIs keep the same semantics.
 */
it('projects: campaigns, project-reachable leads, allocated budget and status split (AC-005)', function () {
    $running = PipelineStatus::factory()->create(['name' => 'Running', 'color' => '#22c55e']);
    $draft = PipelineStatus::factory()->create(['name' => 'Draft', 'color' => null]);

    $project = Project::factory()->create(['pipeline_status_id' => $running->id]);
    Project::factory()->create(['pipeline_status_id' => $draft->id]);

    $linked = Campaign::factory()->forProject($project)->create(['total_budget' => 500]);
    Lead::factory()->count(3)->create(['campaign_id' => $linked->id]);

    // A standalone campaign (project_id null): neither it, its budget, nor its
    // lead is reachable through a project, so all stay out of every project
    // KPI — the exact semantics of ProjectService::summary().
    $standalone = Campaign::factory()->create(['total_budget' => 1000]);
    Lead::factory()->create(['campaign_id' => $standalone->id]);

    $widgets = statsWidgets('projects');

    expect(statsWidget($widgets, 'total'))->toMatchArray([
        'type' => 'stat', 'label' => 'projects.stats.total', 'value' => 2, 'format' => 'number',
    ]);
    expect(statsWidget($widgets, 'campaigns')['value'])->toBe(1)
        ->and(statsWidget($widgets, 'leads')['value'])->toBe(3);

    expect(statsWidget($widgets, 'allocated_budget'))->toMatchArray(['value' => 500.0, 'format' => 'currency']);

    $byStatus = statsWidget($widgets, 'by_status');
    expect($byStatus)->toMatchArray(['type' => 'distribution', 'label' => 'projects.stats.byStatus', 'total' => 2]);
    expect(collect($byStatus['items'])->keyBy('label')->all())->toHaveCount(2)
        ->and(collect($byStatus['items'])->firstWhere('label', 'Running'))
        ->toMatchArray(['key' => (string) $running->id, 'value' => 1, 'color' => '#22c55e'])
        ->and(collect($byStatus['items'])->firstWhere('label', 'Draft'))
        ->toMatchArray(['value' => 1, 'color' => null]);

    $trend = statsWidget($widgets, 'trend');
    expect($trend['points'])->toHaveCount(12)
        ->and(end($trend['points']))->toBe(['label' => now()->format('Y-m'), 'value' => 2])
        ->and($trend['points'][0]['value'])->toBe(0);
});

it('projects: allocated_budget is 0 (not null) with no project-linked campaign (AC-005)', function () {
    Project::factory()->create();

    $widgets = statsWidgets('projects');

    expect(statsWidget($widgets, 'total')['value'])->toBe(1)
        ->and(statsWidget($widgets, 'campaigns')['value'])->toBe(0)
        ->and(statsWidget($widgets, 'leads')['value'])->toBe(0)
        ->and(statsWidget($widgets, 'allocated_budget'))->toMatchArray(['value' => 0.0, 'format' => 'currency'])
        ->and(statsWidget($widgets, 'by_status')['items'])->toHaveCount(1);
});

it('users: headcount split and the role distribution (AC-005)', function () {
    User::factory()->create(['is_active' => false]);
    User::factory()->create(['is_active' => true]);

    $widgets = statsWidgets('users');

    // The acting user (created by statsUserWith) is part of the headcount.
    expect(statsWidget($widgets, 'total')['value'])->toBe(3)
        ->and(statsWidget($widgets, 'active')['value'])->toBe(2)
        ->and(statsWidget($widgets, 'inactive')['value'])->toBe(1)
        ->and(statsWidget($widgets, 'by_role')['items'])->toBe([])
        ->and(statsWidget($widgets, 'by_role')['total'])->toBe(0)
        ->and(statsWidget($widgets, 'trend')['points'])->toHaveCount(12);
});

it('business-functions: unit/service counts and the membership distribution (AC-005)', function () {
    // The factory randomizes the (mutually exclusive) type, so both flags are
    // pinned explicitly here.
    $unit = BusinessFunction::factory()->create([
        'name' => 'Sales', 'is_business_unit' => true, 'is_business_service' => false,
    ]);
    BusinessFunction::factory()->create([
        'name' => 'Support', 'is_business_unit' => false, 'is_business_service' => true,
    ]);
    $unit->users()->attach(User::factory()->count(2)->create()->pluck('id'));

    $widgets = statsWidgets('business-functions');

    expect(statsWidget($widgets, 'total')['value'])->toBe(2)
        ->and(statsWidget($widgets, 'business_units')['value'])->toBe(1)
        ->and(statsWidget($widgets, 'business_services')['value'])->toBe(1);

    $byUsers = statsWidget($widgets, 'by_users');
    expect($byUsers['total'])->toBe(2)
        ->and($byUsers['items'])->toBe([
            ['key' => (string) $unit->id, 'label' => 'Sales', 'value' => 2, 'color' => null],
        ]);
});

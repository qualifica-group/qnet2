<?php

use App\Enums\WorkflowStatusGroup;
use App\Models\QuoteWorkflowStatus;
use App\Services\QuoteWorkflowService;
use Database\Seeders\QualificaCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

// The production seed adds "Non risponde" to the GLOBAL default set as a
// closed loss (user directive 2026-09-28), next to whatever the configurator
// already put there.
uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Keeps the catalogue step from offering the q-crm import.
    config(['migrations.base_url' => null]);
});

it('adds "Non risponde" to the default set as a closed loss, keeping its custom rows, idempotently', function (): void {
    // Step 1: a custom row the configurator already added to the default set.
    $service = app(QuoteWorkflowService::class);
    $service->syncDefaultStatuses([[
        'id' => null,
        'name' => 'In lavorazione',
        'description' => 'Lavorazione avviata.',
        'color' => 'blue',
        'group' => WorkflowStatusGroup::Pending->value,
        'requires_note' => true,
    ]]);

    // Step 2: seed, then seed again.
    test()->seed(QualificaCatalogSeeder::class);
    test()->seed(QualificaCatalogSeeder::class);

    $defaultStatuses = $service->defaultStatuses();

    // The pinned rows anchor the set; the existing custom row survives
    // untouched and the new one follows it, once.
    expect($defaultStatuses->pluck('system_key')->all())->toBe(['open', null, null, 'closed_won', 'closed_lost'])
        ->and($defaultStatuses->pluck('name')->slice(1, 2)->values()->all())->toBe(['In lavorazione', 'Non risponde']);

    $kept = $defaultStatuses->firstWhere('name', 'In lavorazione');

    expect($kept->group)->toBe(WorkflowStatusGroup::Pending)
        ->and($kept->requires_note)->toBeTrue();

    $noAnswer = $defaultStatuses->firstWhere('name', 'Non risponde');

    expect($noAnswer->group)->toBe(WorkflowStatusGroup::ClosedLost)
        ->and($noAnswer->color)->toBe('red')
        ->and($noAnswer->system_key)->toBeNull();

    // Only the default set gained it: no workflow row leaked to it.
    expect(QuoteWorkflowStatus::query()->whereNull('quote_workflow_id')->where('name', 'Non risponde')->count())->toBe(1);
});

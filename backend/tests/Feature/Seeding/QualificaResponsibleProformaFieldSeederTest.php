<?php

declare(strict_types=1);

use App\Models\CustomFieldDefinition;
use Database\Seeders\QualificaResponsibleProformaFieldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('adds the proforma responsible as a users select after the existing sede fields, once', function (): void {
    $other = CustomFieldDefinition::factory()->create(['entity_type' => 'company-sites', 'key' => 'color', 'label' => 'Colore', 'sort_order' => 7]);

    $this->seed(QualificaResponsibleProformaFieldSeeder::class);
    $this->seed(QualificaResponsibleProformaFieldSeeder::class);

    $definitions = CustomFieldDefinition::query()->where('entity_type', 'company-sites')->where('key', 'responsible_proforma')->get();

    expect($definitions)->toHaveCount(1)
        ->and($definitions->first()->only(['type', 'label', 'sort_order', 'is_active', 'is_indexed', 'relation_target']))->toEqual([
            'type' => 'relation',
            'label' => 'Responsabile emissione proforma',
            'sort_order' => 8,
            'is_active' => true,
            'is_indexed' => false,
            'relation_target' => ['entity_type' => 'users', 'cardinality' => 'one', 'for_select_resource' => 'users'],
        ])
        ->and($other->fresh()->only(['label', 'sort_order']))->toEqual(['label' => 'Colore', 'sort_order' => 7]);
});

it('leaves an existing definition as it is', function (): void {
    $existing = CustomFieldDefinition::factory()->create([
        'entity_type' => 'company-sites',
        'key' => 'responsible_proforma',
        'type' => 'relation',
        'label' => 'Responsabile proforma (rinominato)',
        'sort_order' => 3,
        'is_active' => false,
        'relation_target' => ['entity_type' => 'users', 'cardinality' => 'one', 'for_select_resource' => 'users'],
    ]);

    $this->seed(QualificaResponsibleProformaFieldSeeder::class);

    expect(CustomFieldDefinition::query()->where('key', 'responsible_proforma')->count())->toBe(1)
        ->and($existing->fresh()->only(['label', 'sort_order', 'is_active']))->toEqual([
            'label' => 'Responsabile proforma (rinominato)',
            'sort_order' => 3,
            'is_active' => false,
        ]);
});

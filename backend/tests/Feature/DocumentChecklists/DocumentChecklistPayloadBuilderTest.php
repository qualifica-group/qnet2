<?php

use App\Models\DocumentChecklist;
use App\Models\DocumentChecklistItem;
use App\Services\ArchivioSmart\DocumentChecklistPayloadBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('builds the contract body with items ordered by position and typed values', function () {
    $checklist = DocumentChecklist::factory()->create([
        'name' => 'Apertura', 'code' => 'APERTURA', 'description' => '', 'is_active' => false,
    ]);
    $second = DocumentChecklistItem::factory()->for($checklist, 'checklist')->create([
        'position' => 1, 'name' => 'Visura', 'description' => 'Recente', 'section' => 'Societa',
        'is_required' => false, 'allowed_file_types' => ['pdf', 'jpg'], 'max_file_size_kb' => 2048,
        'min_files' => 1, 'max_files' => 3, 'validation_prompt' => 'Controlla la data', 'max_age_days' => 90,
    ]);
    $first = DocumentChecklistItem::factory()->for($checklist, 'checklist')->create([
        'position' => 0, 'name' => 'Documento', 'description' => '', 'section' => null,
        'validation_prompt' => '', 'max_age_days' => null,
    ]);

    $payload = app(DocumentChecklistPayloadBuilder::class)->build($checklist->fresh('items'));

    expect($payload)->toBe([
        'name' => 'Apertura',
        'code' => 'APERTURA',
        'description' => null,
        'is_active' => false,
        'items' => [
            [
                'external_id' => (string) $first->id,
                'name' => 'Documento',
                'description' => null,
                'section' => null,
                'is_required' => true,
                'allowed_file_types' => ['pdf'],
                'max_file_size_kb' => 10240,
                'min_files' => 1,
                'max_files' => 1,
                'validation_prompt' => null,
                'validation_rules' => null,
            ],
            [
                'external_id' => (string) $second->id,
                'name' => 'Visura',
                'description' => 'Recente',
                'section' => 'Societa',
                'is_required' => false,
                'allowed_file_types' => ['pdf', 'jpg'],
                'max_file_size_kb' => 2048,
                'min_files' => 1,
                'max_files' => 3,
                'validation_prompt' => 'Controlla la data',
                'validation_rules' => ['max_age_days' => 90, 'date_field' => 'issue_date'],
            ],
        ],
    ]);
});

it('builds an empty items array for a checklist without items', function () {
    $checklist = DocumentChecklist::factory()->create();

    expect(app(DocumentChecklistPayloadBuilder::class)->build($checklist->fresh('items'))['items'])->toBe([]);
});

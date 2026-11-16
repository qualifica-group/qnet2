<?php

declare(strict_types=1);

use App\Models\Attribute;
use Database\Seeders\InspectionDateGridColumnSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('labels the summary of both inspection tables and leaves the rest of the config untouched', function (): void {
    $notify = ['trigger' => 'alert_sent', 'email_template_id' => null, 'record_team' => true, 'user_ids' => [], 'role_ids' => [], 'emails' => []];
    $legacy = Attribute::factory()->create(['code' => 'orderiso_verificas', 'type' => 'table', 'config' => inspectionTableConfig(['notify' => $notify])]);
    $catalogue = Attribute::factory()->create(['code' => 'iso_inspections', 'type' => 'table', 'config' => inspectionTableConfig()]);
    $other = Attribute::factory()->create(['code' => 'iso_certificates', 'type' => 'table', 'config' => inspectionTableConfig()]);

    $this->seed(InspectionDateGridColumnSeeder::class);
    $this->seed(InspectionDateGridColumnSeeder::class);

    $labelled = inspectionTableConfig(['summary' => ['column' => 'inspection_date', 'strategy' => 'selected', 'grid_label' => 'Data verifica ispettiva']]);

    expect($legacy->fresh()->config)->toEqual([...$labelled, 'notify' => $notify])
        ->and($catalogue->fresh()->config)->toEqual($labelled)
        ->and($other->fresh()->config)->toEqual(inspectionTableConfig());
});

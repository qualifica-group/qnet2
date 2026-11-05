<?php

use App\Models\ChartAccount;
use App\Models\RevenueType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('reads the activity history of a chart account and a revenue type with viewActivity + view', function (string $resource, string $model) {
    Sanctum::actingAs(chartUserWith(["{$resource}.view", "{$resource}.viewActivity"]));
    $record = $model::factory()->create();
    $record->update(['name' => 'Renamed entry']);

    $this->getJson("/api/activity-log/{$resource}/{$record->id}")->assertOk();
})->with([
    'chart-accounts' => ['chart-accounts', ChartAccount::class],
    'revenue-types' => ['revenue-types', RevenueType::class],
]);

it('refuses the activity history without viewActivity', function (string $resource, string $model) {
    Sanctum::actingAs(chartUserWith(["{$resource}.view"]));
    $record = $model::factory()->create();

    $this->getJson("/api/activity-log/{$resource}/{$record->id}")->assertForbidden();
})->with([
    'chart-accounts' => ['chart-accounts', ChartAccount::class],
    'revenue-types' => ['revenue-types', RevenueType::class],
]);

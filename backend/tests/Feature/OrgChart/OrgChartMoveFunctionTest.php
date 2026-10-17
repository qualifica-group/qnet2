<?php

use App\Models\BusinessFunction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

it('0273 AC-005: moves a function under another one and logs it', function () {
    $parent = BusinessFunction::factory()->create(['name' => 'Parent']);
    $function = BusinessFunction::factory()->create(['name' => 'Leaf']);
    Sanctum::actingAs(orgChartActor(['business-functions.update']));

    $this->postJson("/api/org-chart/functions/{$function->id}/parent", ['parent_id' => $parent->id])
        ->assertOk()->assertJsonPath('data', ['id' => $function->id, 'parent_id' => $parent->id]);

    $log = Activity::query()->where('log_name', 'org-chart')->sole();
    expect($function->refresh()->parent_id)->toBe($parent->id)
        ->and($log->event)->toBe('moved')
        ->and($log->properties->toArray())->toBe(['kind' => 'function_parent', 'from' => null, 'to' => ['id' => $parent->id, 'name' => 'Parent']]);

    $this->postJson("/api/org-chart/functions/{$function->id}/parent", ['parent_id' => null])
        ->assertOk()->assertJsonPath('data.parent_id', null);
});

it('0273 AC-005: refuses a function under itself or one of its descendants', function () {
    $root = BusinessFunction::factory()->create();
    $child = BusinessFunction::factory()->create(['parent_id' => $root->id]);
    $grandchild = BusinessFunction::factory()->create(['parent_id' => $child->id]);
    Sanctum::actingAs(orgChartActor(['business-functions.update']));

    $this->postJson("/api/org-chart/functions/{$root->id}/parent", ['parent_id' => $root->id])->assertUnprocessable();
    $this->postJson("/api/org-chart/functions/{$root->id}/parent", ['parent_id' => $grandchild->id])->assertUnprocessable();

    expect($root->refresh()->parent_id)->toBeNull()
        ->and(Activity::query()->where('log_name', 'org-chart')->count())->toBe(0);
});

it('0273 AC-007: refuses the function move without business-functions.update', function () {
    $parent = BusinessFunction::factory()->create();
    $function = BusinessFunction::factory()->create();
    Sanctum::actingAs(orgChartActor(['org-chart.view', 'business-functions.view']));

    $this->postJson("/api/org-chart/functions/{$function->id}/parent", ['parent_id' => $parent->id])->assertForbidden();

    expect($function->refresh()->parent_id)->toBeNull();
});

it('0273 AC-005: 404 for a missing function and 422 for a missing key', function () {
    $function = BusinessFunction::factory()->create();
    Sanctum::actingAs(orgChartActor(['business-functions.update']));

    $this->postJson('/api/org-chart/functions/999999/parent', ['parent_id' => null])->assertNotFound();
    $this->postJson("/api/org-chart/functions/{$function->id}/parent", [])->assertUnprocessable();
});

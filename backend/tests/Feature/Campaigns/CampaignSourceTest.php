<?php

use App\Models\Campaign;
use App\Models\Country;
use App\Models\Source;
use App\Models\User;
use App\Services\SourceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

// Spec 0176 (AC-001): the campaign's optional Fonte, inherited by its leads.

if (! function_exists('campaignSourceUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function campaignSourceUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("campaigns.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("campaigns.{$ability}");
        }

        return $user;
    }
}

it('update: persists source_id and exposes the source, then clears it', function () {
    Sanctum::actingAs(campaignSourceUserWith(['update']));
    $campaign = Campaign::factory()->create();
    $source = Source::factory()->create(['name' => 'Web']);

    $this->patchJson("/api/campaigns/{$campaign->id}", ['source_id' => $source->id])
        ->assertOk()
        ->assertJsonPath('data.source_id', $source->id)
        ->assertJsonPath('data.source', ['id' => $source->id, 'name' => 'Web']);

    $this->patchJson("/api/campaigns/{$campaign->id}", ['source_id' => null])
        ->assertOk()
        ->assertJsonPath('data.source', null);
});

it('update: a non-existent source_id -> 422', function () {
    Sanctum::actingAs(campaignSourceUserWith(['update']));
    $campaign = Campaign::factory()->create();

    $this->patchJson("/api/campaigns/{$campaign->id}", ['source_id' => 999999])
        ->assertStatus(422)->assertJsonValidationErrors('source_id');
});

it('create: persists source_id', function () {
    Sanctum::actingAs(campaignSourceUserWith(['create']));
    $source = Source::factory()->create();

    $this->postJson('/api/campaigns', [
        'name' => 'Spring Push',
        'source_id' => $source->id,
        'country_id' => Country::factory()->create()->id,
        ...standaloneClassificationFields(),
        ...campaignStoreDates(),
    ])->assertCreated()->assertJsonPath('data.source.id', $source->id);
});

it('for-select: meta carries the campaign source', function () {
    Sanctum::actingAs(campaignSourceUserWith(['viewAny']));
    $source = Source::factory()->create(['name' => 'Fiera']);
    $campaign = Campaign::factory()->for($source)->create();

    $item = collect($this->getJson('/api/campaigns/for-select')->assertOk()->json('items'))
        ->firstWhere('id', $campaign->id);

    expect($item['meta']['source'])->toBe(['id' => $source->id, 'name' => 'Fiera']);
});

it('a source named by a campaign cannot be deleted', function () {
    $source = Source::factory()->create();
    Campaign::factory()->for($source)->create();

    expect(fn () => app(SourceService::class)->delete($source))->toThrow(HttpException::class);
    expect(Source::query()->whereKey($source->id)->exists())->toBeTrue();
});

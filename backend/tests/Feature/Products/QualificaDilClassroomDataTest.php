<?php

use App\Enums\AttributeContext;
use App\Enums\FormMode;
use App\Enums\LayoutFormScope;
use App\Models\Attribute;
use App\Models\ProductCategory;
use App\Services\ProductCategories\AttributeLayoutService;
use Database\Seeders\QualificaCatalog\ClassroomAttributeCatalogue;
use Database\Seeders\QualificaCatalog\ContactProcessingAttributeCatalogue;
use Database\Seeders\QualificaCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

// "DIL" gets "Dati Aula" back on its own node (task 427536, user decision
// 2026-11-10), and an installation seeded before converges on a re-run of
// QualificaCatalogSeeder — the command to run in production.

uses(RefreshDatabase::class);

it('converges an installation seeded before "Dati Aula" joined "DIL"', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    // Rewind: no classroom assignment on DIL, and its offer layout exactly as
    // the previous revision composed it — today's, without the section.
    $dil = ProductCategory::query()->where('name', ContactProcessingAttributeCatalogue::DIL_CATEGORY)->firstOrFail();
    $service = app(AttributeLayoutService::class);
    DB::table('attribute_category')
        ->where('category_id', $dil->id)
        ->where('context', AttributeContext::Quote->value)
        ->whereIn('attribute_id', Attribute::query()->whereIn('code', ClassroomAttributeCatalogue::codes())->pluck('id'))
        ->delete();
    $blob = $service->resolveWithFallback($dil, AttributeContext::Quote, FormMode::Create);
    $blob['sections'] = array_values(array_filter($blob['sections'], static fn (array $section): bool => $section['id'] !== 'classroom-data'));
    $service->upsert($dil, AttributeContext::Quote, LayoutFormScope::All, $blob);

    test()->seed(QualificaCatalogSeeder::class);

    $sections = collect($service->resolveWithFallback($dil->fresh(), AttributeContext::Quote, FormMode::Create)['sections']);
    $classroom = $sections->firstWhere('id', 'classroom-data');

    expect($sections->pluck('id')->all())->toBe(['contact-processing', 'classroom-data'])
        ->and(array_merge(...array_map(static fn (array $row): array => array_column($row['items'], 'attribute_code'), $classroom['rows'])))
        ->toBe(ClassroomAttributeCatalogue::codes());
});

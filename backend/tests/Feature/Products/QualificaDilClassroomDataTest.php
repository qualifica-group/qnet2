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
use Database\Seeders\QualificaDilClassroomDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

// "DIL" gets "Dati Aula" back on its own node (task 427536, user decision
// 2026-11-10), and an installation seeded before converges on a re-run of
// QualificaCatalogSeeder — the command to run in production.

uses(RefreshDatabase::class);

it('converges an installation seeded before "Dati Aula" joined "DIL"', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    // Rewind: no classroom assignment on DIL, and its offer layout exactly as
    // the previous revision composed it — today's, without the section. Also
    // without "Data associazione" (task 427520, REQUIREMENT CHANGED): it joined
    // after "Dati Aula", so the previous revision composed without it.
    $dil = ProductCategory::query()->where('name', ContactProcessingAttributeCatalogue::DIL_CATEGORY)->firstOrFail();
    rewindDilBeforeAssociationDate($dil);
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

it('the one-shot seeder adds only the classroom fields and section to "DIL", keeping hand edits, idempotently', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    // An installation seeded before: DIL without classroom fields, and its
    // layout retouched by hand (contact-processing section renamed).
    $dil = ProductCategory::query()->where('name', ContactProcessingAttributeCatalogue::DIL_CATEGORY)->firstOrFail();
    $service = app(AttributeLayoutService::class);
    $classroomIds = Attribute::query()->whereIn('code', ClassroomAttributeCatalogue::codes())->pluck('id');
    DB::table('attribute_category')
        ->where('category_id', $dil->id)
        ->where('context', AttributeContext::Quote->value)
        ->whereIn('attribute_id', $classroomIds)
        ->delete();
    $blob = $service->resolveExact($dil, AttributeContext::Quote, LayoutFormScope::All);
    $blob['sections'] = array_values(array_filter($blob['sections'], static fn (array $section): bool => $section['id'] !== 'classroom-data'));
    $blob['sections'][0]['title'] = 'Lavorazione (rinominata a mano)';
    $service->upsert($dil, AttributeContext::Quote, LayoutFormScope::All, $blob);
    $otherAssignments = DB::table('attribute_category')->whereNotIn('attribute_id', $classroomIds)->count();

    test()->seed(QualificaDilClassroomDataSeeder::class);
    test()->seed(QualificaDilClassroomDataSeeder::class);

    $sections = $service->resolveExact($dil, AttributeContext::Quote, LayoutFormScope::All)['sections'];
    $classroom = collect($sections)->firstWhere('id', 'classroom-data');

    expect(array_column($sections, 'id'))->toBe(['contact-processing', 'classroom-data'])
        ->and($sections[0]['title'])->toBe('Lavorazione (rinominata a mano)')
        ->and(array_merge(...array_map(static fn (array $row): array => array_column($row['items'], 'attribute_code'), $classroom['rows'])))
        ->toBe(ClassroomAttributeCatalogue::codes())
        ->and(DB::table('attribute_category')->where('category_id', $dil->id)->where('context', AttributeContext::Quote->value)->whereIn('attribute_id', $classroomIds)->count())
        ->toBe(count(ClassroomAttributeCatalogue::codes()))
        ->and(DB::table('attribute_category')->whereNotIn('attribute_id', $classroomIds)->count())->toBe($otherAssignments);
});

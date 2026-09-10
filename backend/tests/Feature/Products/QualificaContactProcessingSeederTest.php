<?php

use App\Enums\AttributeContext;
use App\Enums\FormMode;
use App\Enums\LayoutFormScope;
use App\Models\Attribute;
use App\Models\AttributeLayout;
use App\Models\ProductCategory;
use App\Services\ProductCategories\AttributeLayoutService;
use App\Services\ProductCategories\CategoryHierarchy;
use Database\Seeders\QualificaCatalog\ContactProcessingAttributeCatalogue;
use Database\Seeders\QualificaCatalogSeeder;
use Database\Seeders\QualificaContactProcessingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

// The client's "Dati Lavorazione Contatto" set: scoped per category, adopting
// the q-crm rows where the import already created them, and provisioned in the
// two contexts that read it — the Offerta and the Commessa. The Offerta FORM
// is composed by QualificaQuoteLayoutSeeder (this catalogue's section sits
// there next to "Dati corso" and "Dati Aula"); this seeder writes the Commessa
// one.
uses(RefreshDatabase::class);

// Guarded: QualificaCourseSiteAttributeTest, split out of this file, declares
// the same two helpers — whichever Pest loads first wins.
if (! function_exists('contactSectionOf')) {
    /**
     * This catalogue's section inside a layout blob, wherever the composition
     * put it: first on the Commessa, last on the Offerta.
     */
    function contactSectionOf(array $blob): array
    {
        return collect($blob['sections'])->firstWhere('id', 'contact-processing');
    }
}

if (! function_exists('effectiveCodes')) {
    /**
     * @return list<string>
     */
    function effectiveCodes(string $categoryName, AttributeContext $context): array
    {
        $category = ProductCategory::query()->where('name', $categoryName)->firstOrFail();

        return app(CategoryHierarchy::class)
            ->effectiveAttributes($category, $context)
            ->pluck('code')
            ->all();
    }
}

/**
 * The training codes "DIL" declares AGAIN on its own row: it is cut off the
 * root in the Offerta context, so an inherited assignment would not reach it.
 * Derived, never listed, so the two catalogue entries cannot drift apart.
 *
 * @return list<string>
 */
function trainingCodesSharedWithDil(): array
{
    return array_values(array_intersect(
        array_column(ContactProcessingAttributeCatalogue::ATTRIBUTES[ContactProcessingAttributeCatalogue::TRAINING_CATEGORY], 'code'),
        array_column(ContactProcessingAttributeCatalogue::ATTRIBUTES[ContactProcessingAttributeCatalogue::DIL_CATEGORY], 'code'),
    ));
}

it('assigns the training set to the Formazione root, idempotently', function (): void {
    test()->seed(QualificaCatalogSeeder::class);
    test()->seed(QualificaContactProcessingSeeder::class); // re-run: no duplicate attribute nor pivot row.

    $specs = ContactProcessingAttributeCatalogue::ATTRIBUTES[ContactProcessingAttributeCatalogue::TRAINING_CATEGORY];
    $codes = array_column($specs, 'code');

    $formazione = ProductCategory::query()->where('name', 'Formazione')->whereNull('parent_id')->firstOrFail();
    $attributeIds = Attribute::query()->whereIn('code', $codes)->pluck('id');

    expect($attributeIds)->toHaveCount(count($codes));

    $pivot = DB::table('attribute_category')
        ->whereIn('attribute_id', $attributeIds)
        ->where('context', AttributeContext::Quote->value)
        ->get();

    // The whole set on the root, plus the three codes "DIL" re-declares on
    // itself: below the barrier an inherited assignment would never reach it.
    $dil = ProductCategory::query()->where('name', ContactProcessingAttributeCatalogue::DIL_CATEGORY)->firstOrFail();

    expect($pivot)->toHaveCount(count($codes) + count(trainingCodesSharedWithDil()))
        ->and($pivot->where('category_id', $formazione->id))->toHaveCount(count($codes))
        ->and($pivot->where('category_id', $dil->id))->toHaveCount(count(trainingCodesSharedWithDil()));

    // Inherited down the Formazione branch, and nowhere outside it.
    expect(effectiveCodes('GOL - Molise', AttributeContext::Quote))->toContain(...$codes)
        ->and(effectiveCodes('Trattative in Corso', AttributeContext::Quote))->not->toContain('cpi');
});

it('keeps "DIL" on its own six offer fields, cut off the Formazione set', function (): void {
    test()->seed(QualificaCatalogSeeder::class);
    test()->seed(QualificaContactProcessingSeeder::class); // re-run: no duplicate pivot row.

    $expected = array_column(
        ContactProcessingAttributeCatalogue::ATTRIBUTES[ContactProcessingAttributeCatalogue::DIL_CATEGORY],
        'code',
    );
    sort($expected);

    // EXACTLY the six the client dictated (user directive 2026-09-10) — the
    // barrier keeps out "Dati corso", "Dati Aula" and the rest of the training
    // set every sibling inherits from the root.
    $effective = effectiveCodes(ContactProcessingAttributeCatalogue::DIL_CATEGORY, AttributeContext::Quote);
    sort($effective);

    expect($effective)->toBe($expected);

    // The Commessa is NOT cut: the directive is about the offer form, and the
    // two inheritance flags are independent columns.
    expect(effectiveCodes(ContactProcessingAttributeCatalogue::DIL_CATEGORY, AttributeContext::WorkOrder))
        ->toContain('cpi', 'profilo_cpi', 'chosen_course');
});

it('keeps the self-funded and consulting sets on their own categories', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    // Autofinanziato resolves the branch set AND its own two fields.
    expect(effectiveCodes('Autofinanziato', AttributeContext::Quote))
        ->toContain('cpi')
        ->toContain('course_time_preference')
        ->toContain('price');

    // Its sibling sees neither of the two.
    expect(effectiveCodes('DIL', AttributeContext::Quote))
        ->not->toContain('course_time_preference')
        ->not->toContain('price');

    // REQUIREMENT CHANGED (user directive 2026-09-10): the two Consulenza
    // leaves are EMPTY — the company-appointment set is retired, and they
    // inherit nothing from their root either.
    foreach (ContactProcessingAttributeCatalogue::CONSULTING_CATEGORIES as $name) {
        expect(effectiveCodes($name, AttributeContext::Quote))->toBe([], $name);
    }

    expect(effectiveCodes('Consulenza', AttributeContext::Quote))->toBe([]);
});

it('hands the CPI appointment time to every GOL region, the APL one to three', function (): void {
    test()->seed(QualificaCatalogSeeder::class);
    test()->seed(QualificaContactProcessingSeeder::class); // re-run: no duplicate attribute nor pivot row.

    // One assignment PER CONTEXT, both on the container the whole branch
    // inherits from — the same attribute row, never a duplicated one.
    $gol = ProductCategory::query()->where('name', ContactProcessingAttributeCatalogue::GOL_CATEGORY)->firstOrFail();
    $cpiTime = Attribute::query()->where('code', 'ora_app_cpi')->firstOrFail();
    $assignments = DB::table('attribute_category')->where('attribute_id', $cpiTime->id)->get();

    expect(Attribute::query()->where('code', 'ora_app_cpi')->count())->toBe(1)
        ->and($cpiTime->type)->toBe('text')
        ->and($assignments->pluck('category_id')->unique()->values()->all())->toBe([$gol->id])
        ->and($assignments->pluck('context')->sort()->values()->all())
        ->toBe([AttributeContext::Quote->value, AttributeContext::WorkOrder->value]);

    $regions = ProductCategory::query()->where('parent_id', $gol->id)->pluck('name');
    expect($regions)->toHaveCount(10);

    foreach ($regions as $region) {
        expect(effectiveCodes($region, AttributeContext::Quote))->toContain('ora_app_cpi');
    }

    // The APL time is the client's exception: three regions, nowhere else.
    foreach (['GOL - Lombardia', 'GOL - Lazio', 'GOL - Sicilia'] as $region) {
        expect(effectiveCodes($region, AttributeContext::Quote))->toContain('ora_app_apl');
    }

    expect(effectiveCodes('GOL - Molise', AttributeContext::Quote))->not->toContain('ora_app_apl');

    // Neither time leaks outside the GOL branch.
    expect(effectiveCodes('Formazione', AttributeContext::Quote))->not->toContain('ora_app_cpi')
        ->and(effectiveCodes('Autofinanziato', AttributeContext::Quote))->not->toContain('ora_app_cpi');
});

it('places each appointment time right under its own date', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $service = app(AttributeLayoutService::class);
    $rowsOf = function (string $categoryName) use ($service): array {
        $category = ProductCategory::query()->where('name', $categoryName)->firstOrFail();
        $blob = $service->resolveWithFallback($category, AttributeContext::Quote, FormMode::Create);

        return array_map(
            static fn (array $row): array => array_column($row['items'], 'attribute_code'),
            contactSectionOf($blob)['rows'],
        );
    };

    // A region with both appointments: dates row, then times row, aligned.
    expect(array_slice($rowsOf('GOL - Lazio'), 0, 2))->toBe([
        ['data_scelta_cpi', 'data_app_apl'],
        ['ora_app_cpi', 'ora_app_apl'],
    ]);

    // A region with only the CPI one keeps it alone, still in the first column.
    expect(array_slice($rowsOf('GOL - Molise'), 0, 2))->toBe([
        ['data_scelta_cpi', 'data_app_apl'],
        ['ora_app_cpi'],
    ]);

    // Outside the GOL branch the times row drops entirely.
    expect($rowsOf('Autofinanziato'))->not->toContain(['ora_app_cpi']);
});

it('retires "Corso di interesse" from every category without deleting the imported row', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $corso = Attribute::query()->where('code', 'corso')->first();

    // The q-crm import is not simulated here: on a clean database the code the
    // catalogue stopped declaring is simply never created.
    expect($corso)->toBeNull();

    // Re-created as the import would leave it, and put back on the category an
    // earlier revision of the catalogue assigned it to.
    $corso = Attribute::query()->create(['code' => 'corso', 'name' => 'Corso scelto', 'type' => 'text']);
    $formazione = ProductCategory::query()->where('name', 'Formazione')->whereNull('parent_id')->firstOrFail();
    $formazione->attributes()->attach($corso->id, [
        'context' => AttributeContext::Quote->value,
        'is_required' => false,
        'sort_order' => 0,
    ]);

    $service = app(AttributeLayoutService::class);
    $molise = ProductCategory::query()->where('name', 'GOL - Molise')->firstOrFail();
    $service->upsert($molise, AttributeContext::Quote, LayoutFormScope::All, [
        'sections' => [[
            'id' => 'legacy',
            'title' => 'Dati Lavorazione Contatto',
            'description' => null,
            'variant' => 'default',
            'collapsible' => false,
            'default_collapsed' => false,
            'columns' => 2,
            'sort_order' => 0,
            'rows' => [
                ['id' => 'legacy-0', 'items' => [['attribute_code' => 'corso', 'width' => 'half']]],
                ['id' => 'legacy-1', 'items' => [['attribute_code' => 'cpi', 'width' => 'half']]],
            ],
        ]],
    ]);

    test()->seed(QualificaContactProcessingSeeder::class);

    // The row survives (it belongs to the import), the assignments do not.
    expect(Attribute::query()->where('code', 'corso')->exists())->toBeTrue()
        ->and(DB::table('attribute_category')->where('attribute_id', $corso->id)->count())->toBe(0)
        ->and(effectiveCodes('GOL - Molise', AttributeContext::Quote))->not->toContain('corso');

    // The stale layout item goes with it, or the next save would 422.
    $blob = $service->resolveWithFallback($molise, AttributeContext::Quote, FormMode::Create);
    expect($blob['sections'][0]['rows'])->toHaveCount(1)
        ->and(array_column($blob['sections'][0]['rows'][0]['items'], 'attribute_code'))->toBe(['cpi']);
});

it('retires "Sede" from the categories an earlier revision assigned it to', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    // The catalogue stopped declaring it (user directive 2026-09-10): a clean
    // database never creates the row at all.
    expect(Attribute::query()->where('code', 'training_site')->exists())->toBeFalse();

    // Re-created and assigned exactly as the earlier revision left it, in both
    // contexts it was seeded in.
    $site = Attribute::query()->create(['code' => 'training_site', 'name' => 'Sede', 'type' => 'text']);
    $formazione = ProductCategory::query()->where('name', 'Formazione')->whereNull('parent_id')->firstOrFail();

    foreach ([AttributeContext::Quote, AttributeContext::WorkOrder] as $context) {
        $formazione->attributes()->attach($site->id, [
            'context' => $context->value,
            'is_required' => false,
            'sort_order' => 0,
        ]);
    }

    test()->seed(QualificaContactProcessingSeeder::class);

    // The row survives (an offer may already carry a value), the assignments do
    // not, so no work panel renders the field any more.
    expect(Attribute::query()->where('code', 'training_site')->exists())->toBeTrue()
        ->and(DB::table('attribute_category')->where('attribute_id', $site->id)->count())->toBe(0)
        ->and(effectiveCodes('GOL - Molise', AttributeContext::Quote))->not->toContain('training_site')
        ->and(effectiveCodes('GOL - Molise', AttributeContext::WorkOrder))->not->toContain('training_site');
});

it('adopts the q-crm row instead of minting a parallel one', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    // The reused codes stay ONE attribute each, with the label the legacy
    // system gave them (user decision: keep code and label).
    foreach (['cpi' => 'CPI', 'data_app_apl' => 'OK app. APL', 'id_corso' => 'ID Corso'] as $code => $name) {
        $rows = Attribute::query()->where('code', $code)->get();

        expect($rows)->toHaveCount(1, $code)
            ->and($rows->first()->name)->toBe($name);
    }

    // A pre-existing row is never relabelled by the seeder.
    Attribute::query()->where('code', 'cpi')->update(['name' => 'Rinominato a mano']);
    test()->seed(QualificaContactProcessingSeeder::class);

    expect(Attribute::query()->where('code', 'cpi')->value('name'))->toBe('Rinominato a mano');
});

it('promotes the imported "Titolo di Studio" to a pick list while it carries no value', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $degree = Attribute::query()->where('code', ContactProcessingAttributeCatalogue::DEGREE_ATTRIBUTE)->firstOrFail();

    expect($degree->type)->toBe('enum')
        ->and($degree->options()->pluck('label')->all())->toBe([
            'Assolvimento obbligo scolastico', 'Licenza Elementare',
            'Licenza Media', 'Diploma', 'Laurea',
        ]);
});

it('seeds one "Dati Lavorazione Contatto" section per contributing category', function (): void {
    test()->seed(QualificaCatalogSeeder::class);
    test()->seed(QualificaContactProcessingSeeder::class); // re-run: the configured layout is left alone.

    $service = app(AttributeLayoutService::class);

    // Spec 0115: a row only where the composition DIFFERS from the ancestor's
    // — the eight Formazione categories that add a field of their own, or sit
    // behind a barrier. The two Consulenza leaves carry no attribute at all
    // since the 2026-09-10 directive, so they compose to nothing.
    expect(AttributeLayout::query()->where('context', AttributeContext::Quote->value)->count())->toBe(8);

    $molise = ProductCategory::query()->where('name', 'GOL - Molise')->firstOrFail();
    $layout = $service->resolveWithFallback($molise, AttributeContext::Quote, FormMode::Create);
    $section = contactSectionOf($layout);

    expect($section['title'])->toBe('Dati Lavorazione Contatto')
        ->and($section['columns'])->toBe(2)
        ->and(array_column($section['rows'][0]['items'], 'attribute_code'))
        ->toBe(['data_scelta_cpi', 'data_app_apl']);

    // Each category places exactly what it resolves: "DIL" has none of the
    // training rows below its barrier, Autofinanziato adds its own pair.
    $placed = fn (array $blob): array => collect(contactSectionOf($blob)['rows'])
        ->flatMap(fn (array $row): array => array_column($row['items'], 'attribute_code'))
        ->all();

    $dil = ProductCategory::query()->where('name', 'DIL')->firstOrFail();
    expect($placed($service->resolveWithFallback($dil, AttributeContext::Quote, FormMode::Create)))->toBe([
        'chosen_course', 'data_scelta_cpi', 'data_app_apl',
        'dote_activation_date', 'dote_expiry_date', 'subsidy_type',
    ]);

    $autofinanziato = ProductCategory::query()->where('name', 'Autofinanziato')->firstOrFail();
    expect($placed($service->resolveWithFallback($autofinanziato, AttributeContext::Quote, FormMode::Create)))
        ->toContain('course_time_preference', 'price', 'cpi');
});

it('mirrors the whole set into the Commessa context, on the same categories', function (): void {
    test()->seed(QualificaCatalogSeeder::class);
    test()->seed(QualificaContactProcessingSeeder::class); // re-run: no duplicate pivot row.

    $specs = ContactProcessingAttributeCatalogue::ATTRIBUTES[ContactProcessingAttributeCatalogue::TRAINING_CATEGORY];
    $codes = array_column($specs, 'code');

    $formazione = ProductCategory::query()->where('name', 'Formazione')->whereNull('parent_id')->firstOrFail();
    $attributeIds = Attribute::query()->whereIn('code', $codes)->pluck('id');

    // The SAME attribute rows carry both contexts: one pivot row each, never a
    // parallel catalogue.
    expect($attributeIds)->toHaveCount(count($codes));

    $pivot = DB::table('attribute_category')
        ->whereIn('attribute_id', $attributeIds)
        ->where('context', AttributeContext::WorkOrder->value)
        ->get();

    // Same split as the Offerta side: the set on the root, "DIL"'s three on
    // itself — the assignment is context-agnostic, the barrier is not.
    $dil = ProductCategory::query()->where('name', ContactProcessingAttributeCatalogue::DIL_CATEGORY)->firstOrFail();

    expect($pivot)->toHaveCount(count($codes) + count(trainingCodesSharedWithDil()))
        ->and($pivot->where('category_id', $formazione->id))->toHaveCount(count($codes))
        ->and($pivot->where('category_id', $dil->id))->toHaveCount(count(trainingCodesSharedWithDil()));

    // Inherited down the branch through `inherits_work_order_attributes`, and
    // scoped exactly as on the Offerta side.
    expect(effectiveCodes('GOL - Molise', AttributeContext::WorkOrder))
        ->toContain(...$codes)
        ->toContain('ora_app_cpi')
        ->not->toContain('ora_app_apl');

    expect(effectiveCodes('GOL - Lazio', AttributeContext::WorkOrder))->toContain('ora_app_apl');

    expect(effectiveCodes('Autofinanziato', AttributeContext::WorkOrder))
        ->toContain('cpi', 'course_time_preference', 'price');

    // Empty in the Commessa too: the retirement is context-wide.
    foreach (ContactProcessingAttributeCatalogue::CONSULTING_CATEGORIES as $name) {
        expect(effectiveCodes($name, AttributeContext::WorkOrder))->toBe([], $name);
    }
});

it('seeds the Commessa section per contributing category, alone in its own form', function (): void {
    test()->seed(QualificaCatalogSeeder::class);
    test()->seed(QualificaContactProcessingSeeder::class); // re-run: the layout is left alone.

    $service = app(AttributeLayoutService::class);

    // Spec 0115: a row only where the composition differs from the ancestor's.
    // One fewer than the Offerta's eight — "Autoimpiego" adds its
    // self-employment flag in the `quote` context alone, so on the Commessa it
    // has nothing its parent does not already say.
    expect(AttributeLayout::query()->where('context', AttributeContext::WorkOrder->value)->count())->toBe(7);

    $molise = ProductCategory::query()->where('name', 'GOL - Molise')->firstOrFail();
    $blob = $service->resolveWithFallback($molise, AttributeContext::WorkOrder, FormMode::Create);

    // Nothing else contributes to the Commessa form: this catalogue's section
    // is the whole of it, unlike the Offerta where it comes third.
    expect(array_column($blob['sections'], 'id'))->toBe(['contact-processing'])
        ->and($blob['sections'][0]['title'])->toBe('Dati Lavorazione Contatto')
        ->and(array_column($blob['sections'][0]['rows'][0]['items'], 'attribute_code'))
        ->toBe(['data_scelta_cpi', 'data_app_apl']);

    // Same catalogue, same effective set in both contexts: the section comes
    // out identical, so a divergence here means one context resolved something
    // the other did not. Only its position differs — the Offerta stacks it
    // after the two training sections, when the category has them.
    foreach (['GOL - Lazio', 'Autofinanziato', 'GOL - Molise'] as $name) {
        $category = ProductCategory::query()->where('name', $name)->firstOrFail();

        $commessa = contactSectionOf($service->resolveWithFallback($category, AttributeContext::WorkOrder, FormMode::Create));
        $offerta = contactSectionOf($service->resolveWithFallback($category, AttributeContext::Quote, FormMode::Create));

        expect(array_diff_key($commessa, ['sort_order' => null]))
            ->toBe(array_diff_key($offerta, ['sort_order' => null]), $name);
    }
});

it('never overwrites a Commessa layout configured by hand, nor the Offerta one for it', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $service = app(AttributeLayoutService::class);
    $molise = ProductCategory::query()->where('name', 'GOL - Molise')->firstOrFail();

    $offerta = $service->resolveWithFallback($molise, AttributeContext::Quote, FormMode::Create);

    $configured = $service->upsert($molise, AttributeContext::WorkOrder, LayoutFormScope::All, [
        'sections' => [[
            'id' => 'by-hand',
            'title' => 'Configurata a mano',
            'description' => null,
            'variant' => 'highlighted',
            'collapsible' => true,
            'default_collapsed' => true,
            'columns' => 1,
            'sort_order' => 0,
            'rows' => [['id' => 'by-hand-0', 'items' => [['attribute_code' => 'cpi', 'width' => 'full']]]],
        ]],
    ]);

    test()->seed(QualificaContactProcessingSeeder::class);

    // The two contexts are independent rows: touching one leaves the other as
    // the seeder wrote it.
    expect($service->resolveExact($molise, AttributeContext::WorkOrder, LayoutFormScope::All))->toBe($configured)
        ->and($service->resolveWithFallback($molise, AttributeContext::Quote, FormMode::Create))->toBe($offerta);
});

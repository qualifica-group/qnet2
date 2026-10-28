<?php

use App\Enums\DocumentLayoutModule;
use App\Models\DocumentLayout;
use App\Models\Quote;
use App\Models\User;
use App\Services\DocumentLayouts\DocumentLayoutConfigValidator;
use App\Services\DocumentLayouts\DocumentLayoutVariableCatalog;
use App\Services\DocumentLayouts\Rendering\DocumentGenerator;
use App\Services\DocumentLayouts\Rendering\QuoteRenderSubject;
use App\Services\RoleAssignmentGuard;
use Database\Seeders\QualificaCatalog\QuoteDocumentLayoutCatalogue;
use Database\Seeders\QualificaDocumentLayoutSeeder;
use Database\Seeders\QualificaQuoteDocumentLayoutSeeder;
use Database\Seeders\QualificaTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

// The client's legacy quote layouts, converted from their `.docx` templates.
// What is pinned: every converted config passes the frozen contract, names
// only variables of the catalogue and images the layout owns, renders to a
// real `.docx`; the standard layout keeps the `quotes` default; and a re-run
// never overwrites what an operator changed in the configurator.
uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake(config('attachments.disk'));
});

/**
 * @return Collection<int, DocumentLayout>
 */
function seededLegacyQuoteLayouts(): Collection
{
    return DocumentLayout::query()
        ->where('code', 'like', QuoteDocumentLayoutCatalogue::CODE_PREFIX.'%')
        ->orderBy('code')
        ->get();
}

it('seeds every catalogue layout, active and not default, with a config the validator accepts', function (): void {
    test()->seed(QualificaQuoteDocumentLayoutSeeder::class);

    $layouts = seededLegacyQuoteLayouts();
    $validator = app(DocumentLayoutConfigValidator::class);
    $actor = User::factory()->create();

    expect($layouts)->toHaveCount(count(QuoteDocumentLayoutCatalogue::LAYOUTS));

    foreach ($layouts as $layout) {
        expect($layout->module)->toBe(DocumentLayoutModule::Quotes)
            ->and($layout->is_active)->toBeTrue()
            ->and($layout->is_default)->toBeFalse()
            ->and($layout->description)->toStartWith('Importato dal QNet legacy: ')
            ->and($validator->validate($layout->config, $layout, $layout->module, $actor))->toBe([], $layout->code);
    }
});

it('points every image block at an attachment the layout owns, with its binary stored', function (): void {
    test()->seed(QualificaQuoteDocumentLayoutSeeder::class);

    foreach (seededLegacyQuoteLayouts() as $layout) {
        $owned = $layout->images()->get()->keyBy('id');
        $referenced = collect(['header', 'body', 'footer'])
            ->flatMap(fn (string $zone): array => $layout->config[$zone]['blocks'])
            ->where('type', 'image')
            ->pluck('attachment_id');

        expect($referenced->unique()->sort()->values()->all())->toBe($owned->keys()->sort()->values()->all(), $layout->code);

        foreach ($owned as $attachment) {
            expect(Storage::disk($attachment->disk)->exists($attachment->path))->toBeTrue();
        }
    }
});

it('names no legacy placeholder and only variables of the quotes catalogue', function (): void {
    test()->seed(QualificaQuoteDocumentLayoutSeeder::class);

    Role::findOrCreate(RoleAssignmentGuard::PRIVILEGED_ROLE);
    $admin = User::factory()->create();
    $admin->assignRole(RoleAssignmentGuard::PRIVILEGED_ROLE);

    $known = collect(app(DocumentLayoutVariableCatalog::class)->categoriesFor(DocumentLayoutModule::Quotes, $admin))
        ->flatMap(fn (array $category): array => array_column($category['variables'], 'variable'))
        ->all();

    foreach (seededLegacyQuoteLayouts() as $layout) {
        $json = json_encode($layout->config, JSON_UNESCAPED_UNICODE);
        preg_match_all('/\{[a-z_]+\.[a-z_]+\}/', $json, $matches);

        expect($json)->not->toContain('${', $layout->code)
            ->and(array_diff(array_unique($matches[0]), $known))->toBe([], $layout->code);
    }
});

it('renders every layout into a docx for a quote', function (): void {
    test()->seed(QualificaQuoteDocumentLayoutSeeder::class);

    $actor = User::factory()->create();
    $generator = app(DocumentGenerator::class);

    foreach (seededLegacyQuoteLayouts() as $layout) {
        $quote = Quote::factory()->create(['layout_id' => $layout->id]);
        $binary = $generator->generate(QuoteRenderSubject::for($quote->fresh()), $layout, $actor);

        $path = tempnam(sys_get_temp_dir(), 'legacy-layout').'.docx';
        file_put_contents($path, $binary);
        $zip = new ZipArchive;
        expect($zip->open($path))->toBeTrue($layout->code);
        $document = (string) $zip->getFromName('word/document.xml');
        $zip->close();
        unlink($path);

        // Every reference resolved: none of the converted tokens is left verbatim.
        expect($document)->toContain('<w:body>')
            ->and(preg_match('/\{[a-z_]+\.[a-z_]+\}/', $document))->toBe(0, $layout->code);
    }
});

it('leaves the quotes default on the standard layout', function (): void {
    test()->seed(QualificaTemplateSeeder::class);

    expect(DocumentLayout::query()->where('module', DocumentLayoutModule::Quotes)->where('is_default', true)->pluck('code')->all())
        ->toBe([QualificaDocumentLayoutSeeder::LAYOUT_CODE])
        ->and(seededLegacyQuoteLayouts())->toHaveCount(count(QuoteDocumentLayoutCatalogue::LAYOUTS));
});

it('is create-only: a re-run duplicates nothing and keeps an operator edit', function (): void {
    test()->seed(QualificaQuoteDocumentLayoutSeeder::class);

    $layout = DocumentLayout::query()->where('code', QuoteDocumentLayoutCatalogue::CODE_PREFIX.'iso')->sole();
    $edited = [...$layout->config, 'body' => ['blocks' => []]];
    $layout->update(['name' => 'Preventivo ISO rivisto', 'config' => $edited]);
    $imageCount = $layout->images()->count();

    test()->seed(QualificaQuoteDocumentLayoutSeeder::class);

    $layout->refresh();

    expect(seededLegacyQuoteLayouts())->toHaveCount(count(QuoteDocumentLayoutCatalogue::LAYOUTS))
        ->and($layout->name)->toBe('Preventivo ISO rivisto')
        ->and($layout->config['body']['blocks'])->toBe([])
        ->and($layout->images()->count())->toBe($imageCount);
});

it('skips a layout whose name another layout of the module already holds', function (): void {
    [$name] = QuoteDocumentLayoutCatalogue::LAYOUTS['mepa'];
    DocumentLayout::factory()->create(['module' => DocumentLayoutModule::Quotes, 'name' => $name, 'code' => 'operator_mepa']);

    test()->seed(QualificaQuoteDocumentLayoutSeeder::class);

    expect(DocumentLayout::query()->where('code', QuoteDocumentLayoutCatalogue::CODE_PREFIX.'mepa')->exists())->toBeFalse()
        ->and(seededLegacyQuoteLayouts())->toHaveCount(count(QuoteDocumentLayoutCatalogue::LAYOUTS) - 1);
});

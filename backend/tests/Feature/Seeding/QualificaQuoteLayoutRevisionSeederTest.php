<?php

use App\Models\DocumentLayout;
use App\Models\User;
use App\Services\DocumentLayouts\DocumentLayoutConfigValidator;
use Database\Seeders\QualificaCatalog\QuoteDocumentLayoutCatalogue;
use Database\Seeders\QualificaQuoteDocumentLayoutSeeder;
use Database\Seeders\QualificaQuoteLayoutRevisionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

// Task 427544: the November revisions of "Layout servizi" and "Preventivo ISO"
// reach an installation already seeded. What is pinned: the one-shot overwrites
// config and description (hand edits included), keeps name and flags, reuses
// the letterhead the layout owns, never recreates a missing layout, and a
// re-run changes nothing.
uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake(config('attachments.disk'));
});

function revisedQuoteLayout(string $key): DocumentLayout
{
    return DocumentLayout::query()->where('code', QuoteDocumentLayoutCatalogue::CODE_PREFIX.$key)->sole();
}

/**
 * @return array<int, string>
 */
function revisedLayoutTexts(DocumentLayout $layout): array
{
    return collect($layout->config['body']['blocks'])
        ->where('type', 'text')
        ->map(fn (array $block): string => implode('', array_column($block['runs'], 'text')))
        ->values()
        ->all();
}

it('replaces config and description of the revised layouts, hand edits included', function (): void {
    test()->seed(QualificaQuoteDocumentLayoutSeeder::class);

    foreach (QualificaQuoteLayoutRevisionSeeder::REVISED_LAYOUTS as $key) {
        revisedQuoteLayout($key)->update([
            'name' => "Rinominato {$key}",
            'description' => 'Ritoccato a mano',
            'is_active' => false,
            'config' => [...revisedQuoteLayout($key)->config, 'body' => ['blocks' => []]],
        ]);
    }

    test()->seed(QualificaQuoteLayoutRevisionSeeder::class);

    $validator = app(DocumentLayoutConfigValidator::class);
    $actor = User::factory()->create();

    foreach (QualificaQuoteLayoutRevisionSeeder::REVISED_LAYOUTS as $key) {
        $layout = revisedQuoteLayout($key);

        expect($layout->name)->toBe("Rinominato {$key}")
            ->and($layout->is_active)->toBeFalse()
            ->and($layout->description)->toBe(QuoteDocumentLayoutCatalogue::DESCRIPTION_PREFIX.QuoteDocumentLayoutCatalogue::LAYOUTS[$key][1])
            ->and(revisedLayoutTexts($layout))->toContain('INFORMATIVA SUL TRATTAMENTO DEI DATI PERSONALI DEI CLIENTI')
            ->and(collect($layout->config['body']['blocks'])->where('type', 'table'))->toHaveCount(3)
            ->and($validator->validate($layout->config, $layout, $layout->module, $actor))->toBe([], $key);
    }
});

it('reuses the letterhead the layout owns and points the image block at it', function (): void {
    test()->seed(QualificaQuoteDocumentLayoutSeeder::class);
    $owned = revisedQuoteLayout('iso')->images()->pluck('id')->all();

    test()->seed(QualificaQuoteLayoutRevisionSeeder::class);

    $layout = revisedQuoteLayout('iso');

    expect($layout->images()->pluck('id')->all())->toBe($owned)
        ->and($layout->config['header']['blocks'][0]['attachment_id'])->toBe($owned[0]);
});

it('skips a revised layout that is not present instead of recreating it', function (): void {
    test()->seed(QualificaQuoteDocumentLayoutSeeder::class);
    revisedQuoteLayout('services')->delete();

    test()->seed(QualificaQuoteLayoutRevisionSeeder::class);

    expect(DocumentLayout::query()->where('code', QuoteDocumentLayoutCatalogue::CODE_PREFIX.'services')->exists())->toBeFalse()
        ->and(revisedLayoutTexts(revisedQuoteLayout('iso')))->toContain('PRIVACY E PROTEZIONE DEI DATI PERSONALI:');
});

it('changes nothing when re-run', function (): void {
    test()->seed(QualificaQuoteDocumentLayoutSeeder::class);
    test()->seed(QualificaQuoteLayoutRevisionSeeder::class);
    $first = revisedQuoteLayout('services');
    $imageCount = $first->images()->count();

    test()->seed(QualificaQuoteLayoutRevisionSeeder::class);

    $second = revisedQuoteLayout('services');

    expect($second->config)->toBe($first->config)
        ->and($second->images()->count())->toBe($imageCount);
});

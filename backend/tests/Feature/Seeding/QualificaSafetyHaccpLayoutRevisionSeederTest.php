<?php

use App\Models\DocumentLayout;
use App\Models\User;
use App\Services\DocumentLayouts\DocumentLayoutConfigValidator;
use Database\Seeders\QualificaCatalog\QuoteDocumentLayoutCatalogue;
use Database\Seeders\QualificaQuoteDocumentLayoutSeeder;
use Database\Seeders\QualificaSafetyHaccpLayoutRevisionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

// The November revision of "PREVENTIVO SIC_HACCP" reaches an installation
// already seeded. What is pinned: the one-shot overwrites config and
// description of that layout only (hand edits included), keeps name and
// flags, reuses the letterhead it owns, and a re-run changes nothing.
uses(RefreshDatabase::class);

const SAFETY_HACCP_CODE = QuoteDocumentLayoutCatalogue::CODE_PREFIX.'safety_haccp';

beforeEach(function (): void {
    Storage::fake(config('attachments.disk'));
    test()->seed(QualificaQuoteDocumentLayoutSeeder::class);
});

it('replaces config and description of the SIC_HACCP layout, hand edits included', function (): void {
    $layout = DocumentLayout::query()->where('code', SAFETY_HACCP_CODE)->sole();
    $ownedImages = $layout->images()->pluck('id')->all();
    $layout->update([
        'name' => 'Rinominato',
        'description' => 'Ritoccato a mano',
        'is_active' => false,
        'config' => [...$layout->config, 'body' => ['blocks' => []]],
    ]);

    test()->seed(QualificaSafetyHaccpLayoutRevisionSeeder::class);

    $layout->refresh();
    $texts = collect($layout->config['body']['blocks'])
        ->where('type', 'text')
        ->map(fn (array $block): string => implode('', array_column($block['runs'], 'text')));

    expect($layout->name)->toBe('Rinominato')
        ->and($layout->is_active)->toBeFalse()
        ->and($layout->description)->toBe(QuoteDocumentLayoutCatalogue::DESCRIPTION_PREFIX.'Template preventivo _SIC_HACCP Rev. 19.11.2026.docx')
        ->and($texts)->toContain('INFORMATIVA SUL TRATTAMENTO DEI DATI PERSONALI DEI CLIENTI')
        ->and($texts->contains(fn (string $text): bool => str_contains($text, 'art. 1341')))->toBeFalse()
        ->and(collect($layout->config['body']['blocks'])->where('type', 'table'))->toHaveCount(3)
        ->and($layout->images()->pluck('id')->all())->toBe($ownedImages)
        ->and($layout->config['header']['blocks'][0]['attachment_id'])->toBe($ownedImages[0])
        ->and(app(DocumentLayoutConfigValidator::class)->validate($layout->config, $layout, $layout->module, User::factory()->create()))->toBe([]);
});

it('leaves the other quote layouts untouched', function (): void {
    $others = DocumentLayout::query()->where('code', '!=', SAFETY_HACCP_CODE)->pluck('config', 'code');
    DocumentLayout::query()->where('code', '!=', SAFETY_HACCP_CODE)->update(['description' => 'Ritoccato a mano']);

    test()->seed(QualificaSafetyHaccpLayoutRevisionSeeder::class);

    expect(DocumentLayout::query()->where('code', '!=', SAFETY_HACCP_CODE)->pluck('config', 'code')->all())->toBe($others->all())
        ->and(DocumentLayout::query()->where('code', '!=', SAFETY_HACCP_CODE)->pluck('description')->unique()->all())->toBe(['Ritoccato a mano']);
});

it('skips the layout when it is not present instead of recreating it', function (): void {
    DocumentLayout::query()->where('code', SAFETY_HACCP_CODE)->delete();

    test()->seed(QualificaSafetyHaccpLayoutRevisionSeeder::class);

    expect(DocumentLayout::query()->where('code', SAFETY_HACCP_CODE)->exists())->toBeFalse();
});

it('changes nothing when re-run', function (): void {
    test()->seed(QualificaSafetyHaccpLayoutRevisionSeeder::class);
    $first = DocumentLayout::query()->where('code', SAFETY_HACCP_CODE)->sole();

    test()->seed(QualificaSafetyHaccpLayoutRevisionSeeder::class);

    $second = DocumentLayout::query()->where('code', SAFETY_HACCP_CODE)->sole();

    expect($second->config)->toBe($first->config)
        ->and($second->images()->count())->toBe($first->images()->count());
});

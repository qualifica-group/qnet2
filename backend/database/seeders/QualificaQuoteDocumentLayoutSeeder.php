<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\DocumentLayoutModule;
use App\Models\DocumentLayout;
use Database\Seeders\QualificaCatalog\QuoteDocumentLayoutCatalogue;
use Database\Seeders\Support\QuoteLayoutAssets;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The client's other quote layouts (QuoteDocumentLayoutCatalogue), next to
 * the standard one QualificaDocumentLayoutSeeder owns. Not fake data, hence a
 * `Qualifica*` seeder (backend.md §3.1); runs right after the standard layout
 * in QualificaTemplateSeeder, and alone with
 * `php artisan db:seed --class=QualificaQuoteDocumentLayoutSeeder`.
 *
 * Every layout is active and NONE is the default: the `quotes` default stays
 * the standard layout, the operator picks one of these per quote.
 *
 * CREATE-ONLY, unlike the standard layout: these are the layouts the operators
 * will retouch in the configurator, and a production re-seed must not wipe
 * what they changed. A code already present is left alone, and so is a name
 * an operator already gave another layout of the module (unique per module).
 * A new revision of a template reaches an installation already seeded through
 * the QualificaQuoteLayoutRevisionSeeder one-shot, not through this seeder.
 *
 * The row, its images and the final config land in ONE transaction for the
 * same reason as the standard layout: an image block must reference an
 * attachment the layout already owns, and the layout must exist to own one.
 */
class QualificaQuoteDocumentLayoutSeeder extends Seeder
{
    public function __construct(private readonly QuoteLayoutAssets $assets) {}

    public function run(): void
    {
        foreach (QuoteDocumentLayoutCatalogue::LAYOUTS as $key => [$name, $sourceFile]) {
            $code = QuoteDocumentLayoutCatalogue::CODE_PREFIX.$key;

            if ($this->alreadyPresent($code, $name)) {
                continue;
            }

            DB::transaction(fn () => $this->seedLayout($code, $name, $sourceFile, $this->assets->config($key)));
        }
    }

    private function alreadyPresent(string $code, string $name): bool
    {
        if (DocumentLayout::query()->where('code', $code)->exists()) {
            return true;
        }

        $nameTaken = DocumentLayout::query()
            ->where('module', DocumentLayoutModule::Quotes)
            ->where('name', $name)
            ->exists();

        if ($nameTaken) {
            $this->command?->warn("Layout \"{$name}\" non importato: il nome e' gia' usato da un altro layout dei preventivi.");
        }

        return $nameTaken;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function seedLayout(string $code, string $name, string $sourceFile, array $config): void
    {
        // Step 1: the row, with the page but no block — its images have no
        // id to reference yet.
        $layout = DocumentLayout::query()->create([
            'code' => $code,
            'name' => $name,
            'description' => QuoteDocumentLayoutCatalogue::DESCRIPTION_PREFIX.$sourceFile,
            'module' => DocumentLayoutModule::Quotes,
            'is_active' => true,
            'is_default' => false,
            'config' => [...$config, 'header' => ['blocks' => []], 'body' => ['blocks' => []], 'footer' => ['blocks' => []]],
        ]);

        // Step 2: the images the blocks name, uploaded once per layout, and
        // the converted config with each image block pointing at its attachment.
        $layout->update(['config' => $this->assets->withImages($layout, $config)]);
    }
}

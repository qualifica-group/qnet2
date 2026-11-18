<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\DocumentLayout;
use Database\Seeders\QualificaCatalog\QuoteDocumentLayoutCatalogue;
use Database\Seeders\Support\QuoteLayoutAssets;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * One-shot for an installation seeded before the November revisions of the
 * "Layout servizi" and "Preventivo ISO" templates (task 427544):
 * `php artisan db:seed --class=QualificaQuoteLayoutRevisionSeeder --force`.
 * A fresh installation gets the same through the create-only
 * QualificaQuoteDocumentLayoutSeeder, which reads the same assets.
 *
 * REPLACES, on purpose: the config and the description of each revised layout
 * are overwritten with the new conversion, hand edits made in the configurator
 * included. Name, active and default flags are left as the operators set them.
 * A layout no longer present (deleted, or never seeded) is reported and
 * skipped, never recreated. Re-running it rewrites the same content and
 * uploads no image twice.
 */
class QualificaQuoteLayoutRevisionSeeder extends Seeder
{
    /** @var array<int, string> catalogue keys whose asset holds a new revision */
    public const array REVISED_LAYOUTS = ['services', 'iso'];

    public function __construct(private readonly QuoteLayoutAssets $assets) {}

    public function run(): void
    {
        foreach (self::REVISED_LAYOUTS as $key) {
            $code = QuoteDocumentLayoutCatalogue::CODE_PREFIX.$key;
            $layout = DocumentLayout::query()->where('code', $code)->first();

            if ($layout === null) {
                $this->command?->warn("Layout {$code} non presente: nessuna sostituzione.");

                continue;
            }

            // The images first, the config pointing at them after: one
            // transaction, so a failed upload leaves the previous revision whole.
            DB::transaction(fn () => $layout->update([
                'description' => QuoteDocumentLayoutCatalogue::DESCRIPTION_PREFIX.QuoteDocumentLayoutCatalogue::LAYOUTS[$key][1],
                'config' => $this->assets->withImages($layout, $this->assets->config($key)),
            ]));

            $this->command?->info("Layout \"{$layout->name}\" (id {$layout->id}) sostituito.");
        }
    }
}

<?php

namespace Database\Seeders;

use App\Enums\DocumentLayoutModule;
use App\Models\DocumentLayout;
use Database\Factories\DocumentLayoutFactory;
use Illuminate\Database\Seeder;

/**
 * Seed a couple of demo `quotes` layouts (spec 0069). Standalone anagraphic:
 * no dependency on any other seeder, no consumer module references a layout
 * yet (`quotes.layout_id` is spec 0070, out of scope here). Idempotent:
 * `updateOrCreate` keyed by `code` (immutable identity), so re-running never
 * duplicates rows. The first row seeded is flagged `is_default` — mirrors
 * D-7a (the invariant itself lives in DocumentLayoutDefaultManager, spec
 * 0070's write path; this seeder writes directly to stay dependency-free of
 * that service, same as every other Demo*Seeder bypassing its own domain
 * service).
 */
class DemoDocumentLayoutSeeder extends Seeder
{
    /**
     * @var array<int, array{name: string, code: string, description: string, is_default: bool}>
     */
    private const array LAYOUTS = [
        ['name' => 'Layout Standard', 'code' => 'standard', 'description' => 'Layout preventivo standard, carta bianca.', 'is_default' => true],
        ['name' => 'Layout Accordo Sindacale', 'code' => 'accordo_sindacale', 'description' => 'Layout con carta intestata a piena pagina.', 'is_default' => false],
    ];

    public function run(): void
    {
        foreach (self::LAYOUTS as $layout) {
            DocumentLayout::query()->updateOrCreate(
                ['code' => $layout['code']],
                [
                    'name' => $layout['name'],
                    'description' => $layout['description'],
                    'module' => DocumentLayoutModule::Quotes,
                    'is_active' => true,
                    'is_default' => $layout['is_default'],
                    'config' => DocumentLayoutFactory::minimalConfig(),
                ],
            );
        }
    }
}

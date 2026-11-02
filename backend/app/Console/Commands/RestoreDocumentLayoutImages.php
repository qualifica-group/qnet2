<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Attachment;
use App\Models\DocumentLayout;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * One-off repair after a database moved between machines without its
 * attachments folder: the `layout_image` rows survive, their binaries do
 * not, so every layout renders without letterhead or logo (the image block
 * renderer skips a missing file silently).
 *
 * The seeded layouts' images are versioned under `database/seeders/assets`:
 * a missing binary is written back at the row's own disk and path from the
 * asset with the same original name AND the same byte size (a different
 * size is a different image, never restored). Rows and existing files are
 * never touched; an image uploaded by hand has no versioned source and is
 * only reported. Idempotent: a second run restores nothing.
 */
class RestoreDocumentLayoutImages extends Command
{
    /** Directories, under database_path(), holding the seeded layouts' images. */
    private const array ASSET_DIRECTORIES = ['seeders/assets', 'seeders/assets/quote-layouts/images'];

    protected $signature = 'document-layouts:restore-images {--dry-run : Report what would be restored without writing}';

    protected $description = 'Restore the missing files of the seeded document layout images from database/seeders/assets';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $assets = $this->assetsByName();
        $restored = 0;
        $unrestorable = 0;

        // Step 1: only the rows whose binary is gone.
        foreach ($this->missingImages() as $attachment) {
            $label = "#{$attachment->id} (layout {$attachment->attachable_id}) {$attachment->original_name}";

            // Step 2: the versioned asset with the same name and size.
            $source = $this->sourceFor($attachment, $assets);

            if ($source === null) {
                $unrestorable++;
                $this->warn("  not restorable: {$label}");

                continue;
            }

            // Step 3: written back at the row's own disk and path.
            if (! $dryRun && ! Storage::disk($attachment->disk)->put($attachment->path, (string) file_get_contents($source))) {
                $this->error("  write failed: {$label} -> {$attachment->path}");

                return self::FAILURE;
            }

            $restored++;
            $this->line("  {$label} -> {$attachment->path}");
        }

        $this->info(sprintf('%d image(s) %s, %d not restorable.', $restored, $dryRun ? 'would be restored' : 'restored', $unrestorable));

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, Attachment>
     */
    private function missingImages(): Collection
    {
        return Attachment::query()
            ->where('collection', DocumentLayout::IMAGE_COLLECTION)
            ->orderBy('id')
            ->get()
            ->reject(fn (Attachment $attachment): bool => Storage::disk($attachment->disk)->exists($attachment->path))
            ->values();
    }

    /**
     * @return array<string, string> file name => absolute path
     */
    private function assetsByName(): array
    {
        $assets = [];

        foreach (self::ASSET_DIRECTORIES as $directory) {
            foreach (glob(database_path($directory).'/*') ?: [] as $path) {
                if (is_file($path)) {
                    $assets[basename($path)] = $path;
                }
            }
        }

        return $assets;
    }

    /**
     * @param  array<string, string>  $assets
     */
    private function sourceFor(Attachment $attachment, array $assets): ?string
    {
        $path = $assets[$attachment->original_name] ?? null;

        return $path !== null && filesize($path) === $attachment->size ? $path : null;
    }
}

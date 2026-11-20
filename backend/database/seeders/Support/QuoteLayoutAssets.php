<?php

declare(strict_types=1);

namespace Database\Seeders\Support;

use App\Models\DocumentLayout;
use App\Services\DocumentLayouts\DocumentLayoutImageService;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * The converted quote layouts on disk (QuoteDocumentLayoutCatalogue): one
 * `assets/quote-layouts/{key}.json` per layout, whose image blocks name a file
 * of `assets/quote-layouts/images/` in `attachment_id` instead of an id.
 * Shared by the create-only QualificaQuoteDocumentLayoutSeeder and the
 * QualificaQuoteLayoutRevisionSeeder one-shot.
 */
final class QuoteLayoutAssets
{
    private const string ASSETS_DIRECTORY = 'seeders/assets/quote-layouts';

    private const string IMAGES_DIRECTORY = 'images';

    /** @var array<int, string> */
    private const array ZONES = ['header', 'body', 'footer'];

    /** @var array<string, string> */
    private const array IMAGE_MIME_TYPES = ['png' => 'image/png', 'jpg' => 'image/jpeg'];

    public function __construct(private readonly DocumentLayoutImageService $images) {}

    /**
     * @return array<string, mixed>
     */
    public function config(string $key): array
    {
        $path = database_path(self::ASSETS_DIRECTORY."/{$key}.json");

        if (! is_file($path)) {
            throw new RuntimeException("A quote layout config is missing: {$path}");
        }

        return json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * $config with every image block pointing at an attachment $layout owns.
     * An image the layout already owns under the same file name is reused, so
     * a layout re-seeded from its asset never piles up copies of its letterhead
     * (and never hits the per-layout image limit).
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function withImages(DocumentLayout $layout, array $config): array
    {
        $attachmentIds = [];

        foreach ($this->imageFiles($config) as $file) {
            $attachmentIds[$file] = $layout->images()->where('original_name', $file)->value('id')
                ?? $this->upload($layout, $file);
        }

        foreach (self::ZONES as $zone) {
            $config[$zone]['blocks'] = array_map(
                static fn (array $block): array => $block['type'] === 'image'
                    ? [...$block, 'attachment_id' => $attachmentIds[$block['attachment_id']]]
                    : $block,
                $config[$zone]['blocks'],
            );
        }

        return $config;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<int, string>
     */
    private function imageFiles(array $config): array
    {
        $files = [];

        foreach (self::ZONES as $zone) {
            foreach ($config[$zone]['blocks'] as $block) {
                if ($block['type'] === 'image') {
                    $files[$block['attachment_id']] = true;
                }
            }
        }

        return array_keys($files);
    }

    private function upload(DocumentLayout $layout, string $file): int
    {
        $path = database_path(self::ASSETS_DIRECTORY.'/'.self::IMAGES_DIRECTORY.'/'.$file);

        if (! is_file($path)) {
            throw new RuntimeException("A quote layout image is missing: {$path}");
        }

        $mimeType = self::IMAGE_MIME_TYPES[pathinfo($file, PATHINFO_EXTENSION)];

        // Marked as a test upload: the file comes from the repository, not
        // from a PHP upload, so the is_uploaded_file() check must be skipped.
        return $this->images->store($layout, new UploadedFile($path, $file, $mimeType, null, true))->id;
    }
}

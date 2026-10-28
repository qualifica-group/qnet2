<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\DocumentLayoutModule;
use App\Models\DocumentLayout;
use App\Services\DocumentLayouts\DocumentLayoutImageService;
use Database\Seeders\QualificaCatalog\QuoteDocumentLayoutCatalogue;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;

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
 *
 * The row, its images and the final config land in ONE transaction for the
 * same reason as the standard layout: an image block must reference an
 * attachment the layout already owns, and the layout must exist to own one.
 */
class QualificaQuoteDocumentLayoutSeeder extends Seeder
{
    private const string ASSETS_DIRECTORY = 'seeders/assets/quote-layouts';

    private const string IMAGES_DIRECTORY = 'images';

    private const string DESCRIPTION_PREFIX = 'Importato dal QNet legacy: ';

    /** @var array<string, string> */
    private const array IMAGE_MIME_TYPES = ['png' => 'image/png', 'jpg' => 'image/jpeg'];

    public function __construct(private readonly DocumentLayoutImageService $images) {}

    public function run(): void
    {
        foreach (QuoteDocumentLayoutCatalogue::LAYOUTS as $key => [$name, $sourceFile]) {
            $code = QuoteDocumentLayoutCatalogue::CODE_PREFIX.$key;

            if ($this->alreadyPresent($code, $name)) {
                continue;
            }

            DB::transaction(fn () => $this->seedLayout($code, $name, $sourceFile, $this->readConfig($key)));
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
            'description' => self::DESCRIPTION_PREFIX.$sourceFile,
            'module' => DocumentLayoutModule::Quotes,
            'is_active' => true,
            'is_default' => false,
            'config' => [...$config, 'header' => ['blocks' => []], 'body' => ['blocks' => []], 'footer' => ['blocks' => []]],
        ]);

        // Step 2: the images the blocks name, uploaded once per layout.
        $attachmentIds = $this->uploadImages($layout, $config);

        // Step 3: the converted config, each image block pointing at its
        // attachment.
        foreach (['header', 'body', 'footer'] as $zone) {
            $config[$zone]['blocks'] = array_map(
                static fn (array $block): array => $block['type'] === 'image'
                    ? [...$block, 'attachment_id' => $attachmentIds[$block['attachment_id']]]
                    : $block,
                $config[$zone]['blocks'],
            );
        }

        $layout->update(['config' => $config]);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, int> image file name => attachment id
     */
    private function uploadImages(DocumentLayout $layout, array $config): array
    {
        $files = [];

        foreach (['header', 'body', 'footer'] as $zone) {
            foreach ($config[$zone]['blocks'] as $block) {
                if ($block['type'] === 'image') {
                    $files[$block['attachment_id']] = true;
                }
            }
        }

        $attachmentIds = [];

        foreach (array_keys($files) as $file) {
            $path = database_path(self::ASSETS_DIRECTORY.'/'.self::IMAGES_DIRECTORY.'/'.$file);

            if (! is_file($path)) {
                throw new RuntimeException("A quote layout image is missing: {$path}");
            }

            $mimeType = self::IMAGE_MIME_TYPES[pathinfo($file, PATHINFO_EXTENSION)];

            // Marked as a test upload: the file comes from the repository, not
            // from a PHP upload, so the is_uploaded_file() check must be skipped.
            $attachmentIds[$file] = $this->images->store($layout, new UploadedFile($path, $file, $mimeType, null, true))->id;
        }

        return $attachmentIds;
    }

    /**
     * @return array<string, mixed>
     */
    private function readConfig(string $key): array
    {
        $path = database_path(self::ASSETS_DIRECTORY."/{$key}.json");

        if (! is_file($path)) {
            throw new RuntimeException("A quote layout config is missing: {$path}");
        }

        return json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    }
}

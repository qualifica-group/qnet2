<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\DocumentLayoutModule;
use App\Models\Attachment;
use App\Models\DocumentLayout;
use App\Services\DocumentLayouts\DocumentLayoutDefaultManager;
use App\Services\DocumentLayouts\DocumentLayoutImageService;
use Database\Seeders\QualificaCatalog\StandardQuoteLayout;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The client's standard quote layout (spec 0069): ONE `document_layouts` row,
 * the generic layout every quote falls back to, transcribed from their
 * reference `.docx` (see StandardQuoteLayout). Not fake data — hence a
 * `Qualifica*` seeder, not a `Demo*` one (backend.md §3.1);
 * DemoDocumentLayoutSeeder's two placeholder rows stay what they are,
 * fixtures.
 *
 * Runs as the last step of QualificaTemplateSeeder — the layout is part of
 * the installation's shape, like the custom field definitions there — but as
 * its OWN class rather than inlined into it: it writes a binary to a
 * filesystem disk and owns the D-7 default invariant, so it needs its two
 * injected services and its own suite. It is also runnable alone:
 * `php artisan db:seed --class=QualificaDocumentLayoutSeeder`.
 *
 * The row and its letterhead are created in the SAME transaction because the
 * two depend on each other: an `image` block references an `attachment_id`
 * that must already belong to the layout (DocumentLayoutBlockValidator
 * enforces exactly that), and an attachment needs the layout to exist first.
 * The row is therefore inserted with StandardQuoteLayout::emptyConfig()
 * and updated with the real tree once the upload has an id — no half-built
 * config is ever observable outside the transaction.
 *
 * Idempotent: keyed on the immutable `code`, a re-run refreshes the
 * transcription (name, description, config) and never re-uploads the
 * letterhead. It also RE-CLAIMS the `quotes` default on every run — this row
 * is the module's fallback by definition, so a database where the demo
 * fixtures (or an operator) had promoted another layout converges back here.
 * Claiming it goes through DocumentLayoutDefaultManager, never through a bare
 * update, so the D-7 invariant ("0 or 1 default per module", plus "a default
 * is active") holds throughout.
 */
class QualificaDocumentLayoutSeeder extends Seeder
{
    /** Globally unique and immutable after create (spec 0069, D-2): the natural key of the upsert. */
    public const string LAYOUT_CODE = 'qualifica_standard';

    /** The letterhead extracted from the reference `.docx` (`word/media/image1.png`). */
    public const string LETTERHEAD_FILE = 'quote-letterhead.png';

    private const string LAYOUT_NAME = 'Preventivo standard';

    private const string LAYOUT_DESCRIPTION = 'Offerta economica su carta intestata Qualifica Group Training. Layout predefinito dei preventivi.';

    private const string LETTERHEAD_MIME_TYPE = 'image/png';

    public function __construct(
        private readonly DocumentLayoutImageService $images,
        private readonly DocumentLayoutDefaultManager $defaults,
    ) {}

    public function run(): void
    {
        DB::transaction(function (): void {
            // Step 1: the row, with a config scaffold — its letterhead has no
            // id to reference yet.
            $layout = $this->upsertLayout();

            // Step 2: the letterhead, uploaded once and reused on every re-run.
            $letterhead = $this->letterhead($layout);

            // Step 3: the transcription itself, now that the image block can
            // point at an attachment the layout owns.
            $layout->update(['config' => StandardQuoteLayout::config($letterhead->id)]);
        });
    }

    private function upsertLayout(): DocumentLayout
    {
        $attributes = [
            'name' => self::LAYOUT_NAME,
            'description' => self::LAYOUT_DESCRIPTION,
            // D-7c: a default layout must be active, so the two flags are
            // written together and never drift apart.
            'is_active' => true,
            'is_default' => true,
        ];

        $layout = DocumentLayout::query()->where('code', self::LAYOUT_CODE)->first();

        if ($layout !== null) {
            $layout->update($attributes);
        } else {
            $layout = DocumentLayout::query()->create([
                ...$attributes,
                'code' => self::LAYOUT_CODE,
                'module' => DocumentLayoutModule::Quotes,
                'config' => StandardQuoteLayout::emptyConfig(),
            ]);
        }

        // D-7b: exactly one default per module. Runs after this row already
        // carries the flag, in the same transaction as the write above.
        $this->defaults->clearOtherDefaults($layout);

        return $layout;
    }

    private function letterhead(DocumentLayout $layout): Attachment
    {
        /** @var Attachment|null $existing */
        $existing = $layout->images()->where('original_name', self::LETTERHEAD_FILE)->first();

        if ($existing !== null) {
            return $existing;
        }

        $path = database_path('seeders/assets/'.self::LETTERHEAD_FILE);

        if (! is_file($path)) {
            throw new RuntimeException("The layout letterhead is missing: {$path}");
        }

        // Marked as a test upload: the file comes from the repository, not
        // from a PHP upload, so the is_uploaded_file() check must be skipped.
        return $this->images->store($layout, new UploadedFile($path, self::LETTERHEAD_FILE, self::LETTERHEAD_MIME_TYPE, null, true));
    }
}

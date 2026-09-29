<?php

namespace App\Migrations\Sources;

use App\DataObjects\DocumentBundles\CreateDocumentBundleData;
use App\Migrations\AbstractMigrationSource;
use App\Migrations\MigrationImportContext;
use App\Migrations\MigrationRowOutcome;
use App\Migrations\Support\ExternalApiClient;
use App\Models\Attachment;
use App\Models\DocumentBundle;
use App\Services\DocumentBundleService;
use finfo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * `document-bundles` migration source (spec 0175, D-13): the legacy
 * `modello_documentis` + `modello_documenti_allegatis` tables become an
 * `App\Models\DocumentBundle` (`name = title`) with its files copied in as
 * `documents`-collection `Attachment`s. Phase 1 anchor (no dependency on any
 * other source).
 *
 * The legacy endpoint inlines every file's content as base64 (its
 * ExternalApiClient speaks JSON only) — DECODED here, checked against the
 * SAME `config('attachments.max_size')`/`allowed_mime_types` allow-list the
 * live upload endpoint enforces (MIME sniffed from the decoded bytes, never
 * the legacy filename), and written directly to the attachments disk the
 * same way `App\RichText\RichTextImageProcessor` persists a decoded image
 * (AttachmentService itself only accepts an `UploadedFile`, which a
 * migration source never has). A file already reported `missing: true` by
 * the legacy endpoint, or one that fails a check, is SKIPPED with a warning
 * — never fails the whole bundle row. `importBatchSize()` is overridden much
 * lower than the default: every row's payload carries every file's full
 * content.
 *
 * `name` (unique, no module scoping unlike email-templates) is truncated
 * with a warning; a collision (a row already present with a DIFFERENT
 * `old_id`) is resolved by a numeric suffix, also warned — never a row
 * error. Idempotent on `old_id` (skipped, no file re-copy on re-run).
 */
class DocumentBundlesSource extends AbstractMigrationSource
{
    private const int NAME_MAX = 191;

    /**
     * Base64 inflates the response by ~1/3: keep the per-page byte budget
     * comparable to a plain source's default 100-row page of small JSON rows.
     */
    private const int IMPORT_BATCH_SIZE = 5;

    public function __construct(
        ExternalApiClient $client,
        private readonly DocumentBundleService $service,
    ) {
        parent::__construct($client);
    }

    public function key(): string
    {
        return 'document-bundles';
    }

    public function label(): string
    {
        return 'Document bundles';
    }

    public function endpoint(): string
    {
        return 'document-bundles';
    }

    protected function importBatchSize(): int
    {
        return self::IMPORT_BATCH_SIZE;
    }

    /**
     * @return array<int, array{id: string, label: string, type: string}>
     */
    protected function nativeColumns(): array
    {
        return [
            ['id' => 'id', 'label' => 'ID', 'type' => 'number'],
            ['id' => 'title', 'label' => 'Title', 'type' => 'string'],
            // The preview's generic column catalogue has no "nested array"
            // type: the real `files` payload (id/name/title/mime_type/size/
            // content_base64/missing per file) is consumed directly in
            // processRow(), never through this scalar preview projection.
            ['id' => 'files_count', 'label' => 'Files', 'type' => 'number'],
        ];
    }

    protected function externalId(array $record): int|string|null
    {
        return $record['id'] ?? null;
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, string|int|bool|null>
     */
    protected function mapNativeRow(array $record): array
    {
        $files = $record['files'] ?? null;

        return [
            'id' => $record['id'] ?? null,
            'title' => $record['title'] ?? null,
            'files_count' => is_array($files) ? count($files) : 0,
        ];
    }

    protected function processRow(MigrationImportContext $context, array $record): MigrationRowOutcome
    {
        $externalId = $this->externalId($record);

        if ($externalId === null) {
            throw new RuntimeException('External id is required.');
        }

        if ($this->existsByOldId(DocumentBundle::class, $externalId)) {
            return MigrationRowOutcome::skipped();
        }

        $title = trim((string) ($record['title'] ?? ''));

        if ($title === '') {
            throw new RuntimeException('title is required.');
        }

        $warnings = [];
        $name = $this->uniqueName($this->truncated($title, self::NAME_MAX, 'name', $warnings), $warnings);

        $bundle = $this->service->create(new CreateDocumentBundleData(
            name: $name,
            description: null,
            isActive: true,
        ));

        $bundle->old_id = (int) $externalId;
        $bundle->save();

        foreach ((array) ($record['files'] ?? []) as $file) {
            $this->attachFile($bundle, (array) $file, $context, $warnings);
        }

        return MigrationRowOutcome::created($warnings, $bundle);
    }

    /**
     * @param  array<string, mixed>  $file
     * @param  array<int, string>  $warnings
     */
    private function attachFile(DocumentBundle $bundle, array $file, MigrationImportContext $context, array &$warnings): void
    {
        $label = trim((string) ($file['name'] ?? '')) !== '' ? (string) $file['name'] : (string) ($file['id'] ?? '?');

        if (($file['missing'] ?? false) === true || ! is_string($file['content_base64'] ?? null)) {
            $warnings[] = "file '{$label}' skipped: missing from legacy storage.";

            return;
        }

        $bytes = base64_decode((string) $file['content_base64'], true);

        if ($bytes === false || $bytes === '') {
            $warnings[] = "file '{$label}' skipped: invalid content.";

            return;
        }

        $maxBytes = (int) config('attachments.max_size') * 1024;

        if (strlen($bytes) > $maxBytes) {
            $warnings[] = "file '{$label}' skipped: exceeds the ".config('attachments.max_size').' KB limit.';

            return;
        }

        $mimeType = (string) (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        $allowedMimeTypes = (array) config('attachments.allowed_mime_types');

        if ($allowedMimeTypes !== [] && ! in_array($mimeType, $allowedMimeTypes, true)) {
            $warnings[] = "file '{$label}' skipped: MIME type '{$mimeType}' not allowed.";

            return;
        }

        $this->storeAttachment($bundle, $bytes, $label, $mimeType, $context);
    }

    private function storeAttachment(DocumentBundle $bundle, string $bytes, string $originalName, string $mimeType, MigrationImportContext $context): void
    {
        $disk = (string) config('attachments.disk');
        $directory = trim((string) config('attachments.directory'), '/');
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $storedName = (string) Str::uuid().($extension !== '' ? '.'.$extension : '');
        $path = $directory !== '' ? $directory.'/'.$storedName : $storedName;

        Storage::disk($disk)->put($path, $bytes);

        try {
            $attachment = new Attachment([
                'collection' => 'documents',
                'disk' => $disk,
                'path' => $path,
                'original_name' => $originalName,
                'mime_type' => $mimeType,
                'extension' => $extension !== '' ? $extension : null,
                'size' => strlen($bytes),
                'uploaded_by' => $context->actor->id,
            ]);
            $attachment->attachable()->associate($bundle);
            $attachment->save();
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($path);

            throw $exception;
        }
    }

    /**
     * @param  array<int, string>  $warnings
     */
    private function truncated(string $value, int $max, string $field, array &$warnings): string
    {
        if (mb_strlen($value) <= $max) {
            return $value;
        }

        $warnings[] = "{$field} truncated to {$max} characters (was ".mb_strlen($value).').';

        return mb_substr($value, 0, $max);
    }

    /**
     * A `name` collision (a row already present, any `old_id`) is resolved
     * by appending " (2)", " (3)", ... rather than failing the row (D-13:
     * "scegli la regola piu' sicura").
     *
     * @param  array<int, string>  $warnings
     */
    private function uniqueName(string $name, array &$warnings): string
    {
        if (! DocumentBundle::query()->where('name', $name)->exists()) {
            return $name;
        }

        $original = $name;
        $suffix = 2;

        do {
            $suffixText = " ({$suffix})";
            $budget = self::NAME_MAX - mb_strlen($suffixText);
            $candidate = (mb_strlen($original) > $budget ? mb_substr($original, 0, $budget) : $original).$suffixText;
            $suffix++;
        } while (DocumentBundle::query()->where('name', $candidate)->exists());

        $warnings[] = "name '{$original}' already used; renamed to '{$candidate}'.";

        return $candidate;
    }
}

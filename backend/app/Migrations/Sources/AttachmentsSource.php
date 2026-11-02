<?php

declare(strict_types=1);

namespace App\Migrations\Sources;

use App\Migrations\AbstractMigrationSource;
use App\Migrations\MigrationDependency;
use App\Migrations\MigrationImportContext;
use App\Migrations\MigrationRowOutcome;
use App\Migrations\Sources\Concerns\MapsLegacyOperationalRecord;
use App\Migrations\Sources\Concerns\RequiresLegacyAnchors;
use App\Models\Attachment;
use App\Models\Opportunity;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestLine;
use App\Models\Registry;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\LegacyFileUrl;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * `attachments` migration source (spec 0295): the documents of every legacy
 * module in one pass, imported as link-only rows (the file stays on the
 * legacy and is streamed by LegacyAttachmentFetcher). Nothing is written to
 * disk and nothing to the activity log. The legacy sends name, MIME, size and
 * absolute URL already composed (D-15); here the `old_id` lookups and the
 * security checks (SSRF boundary D-11, MIME allow-list D-12).
 */
class AttachmentsSource extends AbstractMigrationSource
{
    use MapsLegacyOperationalRecord, RequiresLegacyAnchors;

    /**
     * Contract attachable types (D-5). Any other type fails the row.
     *
     * @var array<string, class-string<Model>>
     */
    private const array ATTACHABLE_TYPES = [
        'work_order' => WorkOrder::class,
        'opportunity' => Opportunity::class,
        'task' => Task::class,
        'registry' => Registry::class,
        'purchase_request' => PurchaseRequest::class,
        'purchase_request_line' => PurchaseRequestLine::class,
        'purchase_order' => PurchaseOrder::class,
    ];

    private const string DOCUMENTS_COLLECTION = 'documents';

    private const string FALLBACK_MIME_TYPE = 'application/octet-stream';

    private const int NAME_MAX = 255;

    public function key(): string
    {
        return 'attachments';
    }

    public function label(): string
    {
        return 'Documents';
    }

    public function endpoint(): string
    {
        return 'attachments';
    }

    /**
     * {@inheritDoc}
     */
    public function dependencies(): array
    {
        return [
            MigrationDependency::required('work-orders'),
            MigrationDependency::optional('users'),
            MigrationDependency::optional('opportunities'),
            MigrationDependency::optional('tasks'),
            MigrationDependency::optional('registries'),
            MigrationDependency::optional('purchase-requests'),
            MigrationDependency::optional('purchase-orders'),
        ];
    }

    /**
     * Without the legacy files base URL no document could be opened (D-11).
     */
    public function isAvailable(): bool
    {
        return parent::isAvailable() && (string) config('migrations.legacy_files_base_url') !== '';
    }

    /**
     * @return array<int, array{id: string, label: string, type: string}>
     */
    protected function nativeColumns(): array
    {
        return [
            ['id' => 'id', 'label' => 'ID', 'type' => 'string'],
            ['id' => 'source', 'label' => 'Source', 'type' => 'string'],
            ['id' => 'attachable_type', 'label' => 'Attachable type', 'type' => 'string'],
            ['id' => 'attachable_id', 'label' => 'Attachable (external id)', 'type' => 'number'],
            ['id' => 'original_name', 'label' => 'Name', 'type' => 'string'],
            ['id' => 'mime_type', 'label' => 'MIME type', 'type' => 'string'],
            ['id' => 'size', 'label' => 'Size', 'type' => 'number'],
            ['id' => 'url', 'label' => 'URL', 'type' => 'string'],
            ['id' => 'uploaded_by_user_id', 'label' => 'Uploaded by (external id)', 'type' => 'number'],
            ['id' => 'created_at', 'label' => 'Created at', 'type' => 'date'],
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
        return $this->legacyPreviewCells($record);
    }

    protected function processRow(MigrationImportContext $context, array $record): MigrationRowOutcome
    {
        $source = $this->blankToNull($record['source'] ?? null);
        $sourceId = $record['source_id'] ?? null;

        if ($source === null || $sourceId === null || $sourceId === '') {
            throw new RuntimeException('Source and source id are required.');
        }

        if ($this->legacyLinkExists($source, $sourceId)) {
            return MigrationRowOutcome::skipped();
        }

        return activity()->withoutLogs(fn (): MigrationRowOutcome => $this->importRecord($source, (int) $sourceId, $record));
    }

    /**
     * Idempotence is on the PAIR: legacy table ids overlap across tables (D-10).
     */
    private function legacyLinkExists(string $source, int|string $sourceId): bool
    {
        return Attachment::query()->where('old_source', $source)->where('old_id', $sourceId)->exists();
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function importRecord(string $source, int $sourceId, array $record): MigrationRowOutcome
    {
        $warnings = array_map('strval', (array) ($record['warnings'] ?? []));

        // Step 1: the parent record, the link and the size must be sound.
        $parent = $this->attachableParent($record);
        $url = $this->allowedUrl($record['url'] ?? null);
        $size = (int) ($record['size'] ?? 0);

        if ($size <= 0) {
            throw new RuntimeException('Size must be positive; the record is not imported.');
        }

        // Step 2: name, MIME type (D-12) and uploader.
        $name = $this->cappedText($this->requiredText($record['original_name'] ?? null, 'Name'), self::NAME_MAX, 'Name', $warnings);
        $mimeType = $this->safeMimeType($record['mime_type'] ?? null, $warnings);
        $uploadedBy = $this->remapLegacyId(User::class, $record['uploaded_by_user_id'] ?? null, 'uploaded_by_user_id', $warnings);

        // Step 3: the link-only row, with the legacy anchors and dates.
        $attachment = new Attachment([
            'collection' => self::DOCUMENTS_COLLECTION,
            'disk' => Attachment::LEGACY_DISK,
            'path' => "{$source}/{$sourceId}",
            'original_name' => $name,
            'mime_type' => $mimeType,
            'extension' => $this->blankToNull($record['extension'] ?? null),
            'size' => $size,
            'uploaded_by' => $uploadedBy,
        ]);
        $attachment->attachable()->associate($parent);
        $attachment->forceFill(['external_url' => $url, 'old_source' => $source]);
        $this->finalizeLegacyRecord($attachment, $sourceId, $record);

        return MigrationRowOutcome::created($warnings, $attachment);
    }

    /**
     * @param  array<string, mixed>  $record
     *
     * @throws RuntimeException when the type is unsupported or the parent is not migrated
     */
    private function attachableParent(array $record): Model
    {
        $type = (string) ($record['attachable_type'] ?? '');
        $class = self::ATTACHABLE_TYPES[$type]
            ?? throw new RuntimeException("Documents on {$type} are not supported; the record is not imported.");

        return $class::query()->findOrFail(
            $this->requiredOldId($class, $record['attachable_id'] ?? null, "Attachable {$type}")
        );
    }

    /**
     * @throws RuntimeException when the URL is outside the legacy files base URL
     */
    private function allowedUrl(mixed $url): string
    {
        $url = $this->blankToNull($url);

        if (! LegacyFileUrl::isAllowed($url)) {
            throw new RuntimeException('URL is not under the legacy files base URL; the record is not imported.');
        }

        return (string) $url;
    }

    /**
     * A legacy MIME outside the allow-list is stored as opaque bytes: it is
     * downloaded, never rendered (D-12).
     *
     * @param  array<int, string>  $warnings
     */
    private function safeMimeType(mixed $mimeType, array &$warnings): string
    {
        $mimeType = strtolower((string) $this->blankToNull($mimeType));

        if (in_array($mimeType, (array) config('attachments.allowed_mime_types'), true)) {
            return $mimeType;
        }

        $warnings[] = 'MIME type "'.$mimeType.'" is not allowed; stored as '.self::FALLBACK_MIME_TYPE.'.';

        return self::FALLBACK_MIME_TYPE;
    }
}

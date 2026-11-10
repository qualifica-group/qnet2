<?php

declare(strict_types=1);

namespace App\Services\Attachments;

use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestLine;

/**
 * The legacy QNet page of a migrated documents owner (spec 0343): shown in the
 * Documents section as "can't find a document? check the legacy QNet". Null
 * when the owner was not migrated (no `old_id`), its alias has no legacy page
 * (`migrations.legacy_record_paths`) or the legacy host is not configured.
 */
final class LegacyRecordLink
{
    private const string ID_PLACEHOLDER = '{id}';

    public function forOwner(string $attachableType, int $attachableId): ?string
    {
        // Step 1: a line's documents point to the page of its purchase request.
        [$alias, $oldId] = $attachableType === 'purchase_request_line'
            ? ['purchase_request', $this->lineRequestOldId($attachableId)]
            : [$attachableType, $this->ownerOldId($attachableType, $attachableId)];

        // Step 2: only a migrated owner with a legacy page gets a link.
        return $this->forRecord($alias, $oldId);
    }

    /**
     * The legacy page of a record already in hand, by its alias in
     * `migrations.legacy_record_paths` (e.g. the contacts of an anagrafica, spec 0378).
     */
    public function forRecord(string $alias, ?int $oldId): ?string
    {
        $base = rtrim((string) config('migrations.legacy_files_base_url'), '/');
        $paths = (array) config('migrations.legacy_record_paths');

        if ($base === '' || $oldId === null || ! isset($paths[$alias])) {
            return null;
        }

        return $base.'/'.str_replace(self::ID_PLACEHOLDER, (string) $oldId, (string) $paths[$alias]);
    }

    private function ownerOldId(string $attachableType, int $attachableId): ?int
    {
        $modelClass = config("attachments.attachable_types.{$attachableType}");

        if (! is_string($modelClass) || ! isset(config('migrations.legacy_record_paths')[$attachableType])) {
            return null;
        }

        $oldId = $modelClass::query()->whereKey($attachableId)->value('old_id');

        return $oldId === null ? null : (int) $oldId;
    }

    private function lineRequestOldId(int $lineId): ?int
    {
        $requestId = PurchaseRequestLine::query()->whereKey($lineId)->value('purchase_request_id');
        $oldId = $requestId === null ? null : PurchaseRequest::query()->whereKey($requestId)->value('old_id');

        return $oldId === null ? null : (int) $oldId;
    }
}

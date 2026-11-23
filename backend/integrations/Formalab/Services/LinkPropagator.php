<?php

declare(strict_types=1);

namespace Integrations\Formalab\Services;

use App\Models\WorkOrder;
use Illuminate\Validation\ValidationException;
use Integrations\Formalab\Models\FormalabWorkOrderLink;
use Throwable;

/**
 * Pushes catalogue changes to the linked work orders: only the links whose
 * class fingerprint differs from the one they last synced are rewritten. A
 * failure on one work order is recorded on its link and never stops the rest.
 */
final class LinkPropagator
{
    private const int CHUNK = 100;

    public function __construct(private readonly CourseFieldWriter $courseWriter) {}

    /**
     * @param  list<int>|null  $editionIds  restrict to these editions; null = every pending link
     * @return int work orders whose fields actually changed
     */
    public function propagate(?array $editionIds = null): int
    {
        $updated = 0;

        foreach (array_chunk($this->pendingLinkIds($editionIds), self::CHUNK) as $ids) {
            $links = FormalabWorkOrderLink::query()->with('formalabClass')->whereIn('id', $ids)->get();

            foreach ($links as $link) {
                $updated += $this->syncLink($link) ? 1 : 0;
            }
        }

        return $updated;
    }

    /**
     * @param  list<int>|null  $editionIds
     * @return list<int>
     */
    private function pendingLinkIds(?array $editionIds): array
    {
        return FormalabWorkOrderLink::query()
            ->join('formalab_classes', 'formalab_classes.edition_id', '=', 'formalab_work_order_links.edition_id')
            ->where(static fn ($query) => $query
                ->whereNull('formalab_work_order_links.synced_hash')
                ->orWhereColumn('formalab_work_order_links.synced_hash', '<>', 'formalab_classes.payload_hash'))
            ->when($editionIds !== null, static fn ($query) => $query->whereIn('formalab_work_order_links.edition_id', $editionIds))
            ->pluck('formalab_work_order_links.id')
            ->map(intval(...))
            ->all();
    }

    /**
     * Writes the link's class onto its work order and records the outcome on
     * the link: the fingerprint on success, the reason on failure.
     *
     * @return bool whether any value of the work order actually changed
     */
    public function syncLink(FormalabWorkOrderLink $link): bool
    {
        $workOrder = WorkOrder::query()->find($link->work_order_id);
        $class = $link->formalabClass;

        if ($workOrder === null || $class === null) {
            return false;
        }

        try {
            $changed = $this->courseWriter->apply($workOrder, $class);
        } catch (Throwable $exception) {
            $link->update(['sync_error' => mb_substr($this->messageOf($exception), 0, 1000)]);

            return false;
        }

        $link->update(['synced_hash' => $class->payload_hash, 'synced_at' => now(), 'sync_error' => null]);

        return $changed;
    }

    private function messageOf(Throwable $exception): string
    {
        return $exception instanceof ValidationException
            ? (string) collect($exception->errors())->flatten()->first()
            : $exception->getMessage();
    }
}

<?php

declare(strict_types=1);

namespace App\Services\DocumentRequests;

use App\Enums\DocumentRequestStatus;
use App\Models\DocumentRequest;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Copies an ArchivioSmart request representation (spec 0428, D-3) into the local
 * row: remote state fields plus the snapshot, replaced as a whole. The editable
 * fields (due date, recipient, message) stay as the user wrote them.
 */
class DocumentRequestRemoteWriter
{
    /**
     * @param  array<string, mixed>  $data  the `data` object of the remote response
     */
    public function apply(DocumentRequest $request, array $data): void
    {
        $sections = is_array($data['sections'] ?? null) ? array_values($data['sections']) : [];
        $items = is_array($data['items'] ?? null) ? array_values($data['items']) : [];

        activity()->withoutLogs(fn () => $request->forceFill([
            'status' => DocumentRequestStatus::tryFrom((string) ($data['status'] ?? '')) ?? $request->status,
            'title' => is_string($data['title'] ?? null) ? $data['title'] : null,
            'items_count' => (int) ($data['items_count'] ?? count($items)),
            'items_uploaded_count' => (int) ($data['items_uploaded_count'] ?? 0),
            'items_satisfied_count' => (int) ($data['items_satisfied_count'] ?? 0),
            'snapshot' => ['sections' => $sections, 'items' => $items],
            'sent_at' => $this->date($data['sent_at'] ?? null),
            'last_reminded_at' => $this->date($data['last_reminded_at'] ?? null),
            'completed_at' => $this->date($data['completed_at'] ?? null),
            'cancelled_at' => $this->date($data['cancelled_at'] ?? null),
            'expired_at' => $this->date($data['expired_at'] ?? null),
            'link_expires_at' => $this->date($data['link_expires_at'] ?? null),
            'remote_updated_at' => $this->date($data['updated_at'] ?? null) ?? now(),
        ])->save());
    }

    public function date(mixed $value): ?CarbonInterface
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}

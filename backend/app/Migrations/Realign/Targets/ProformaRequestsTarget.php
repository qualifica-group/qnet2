<?php

declare(strict_types=1);

namespace App\Migrations\Realign\Targets;

use App\Enums\ProformaRequestStatus;
use App\Migrations\Realign\LegacyRealignNotes;
use App\Migrations\Realign\Support\PurchaseDocumentTarget;
use App\Models\ProformaRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Proforma requests (spec 0375 D-11): status, issue date, note and people.
 */
final class ProformaRequestsTarget extends PurchaseDocumentTarget
{
    public function source(): string
    {
        return 'proforma-requests';
    }

    protected function modelClass(): string
    {
        return ProformaRequest::class;
    }

    public function label(Model $record): string
    {
        return 'Richiesta #'.$record->getKey();
    }

    public function snapshot(Model $record): array
    {
        /** @var ProformaRequest $record */
        return [
            ...$this->columns($record, ['status', 'issued_at', 'note']),
            'assigned_to' => $this->userName($record->assigned_to),
            'assigned_by' => $this->userName($record->assigned_by),
        ];
    }

    public function realign(Model $record, array $legacy, LegacyRealignNotes $notes): void
    {
        /** @var ProformaRequest $record */
        $status = ProformaRequestStatus::tryFrom((string) ($legacy['status'] ?? ''))
            ?? throw new RuntimeException("Status '".($legacy['status'] ?? '')."' is not valid.");

        // Step 1: people and note; a person not migrated fails the row.
        $record->forceFill([
            'note' => $this->references->text($legacy['note'] ?? null) ?? throw new RuntimeException('note is required.'),
            'assigned_to' => $this->references->required(User::class, $legacy['assigned_to_user_id'] ?? null, 'Assignee'),
            'assigned_by' => $this->references->required(User::class, $legacy['assigned_by_user_id'] ?? null, 'Requester'),
            ...$this->statusAttributes($record, $status, $legacy),
        ]);

        // Step 2: one quiet save.
        $this->finish($record, false, $legacy, [], $notes);
    }

    /**
     * A request holding a document stays issued (spec 0194 D-7). An already issued one
     * keeps its date: the legacy sends the request's moving `updated_at` as issue date.
     *
     * @param  array<string, mixed>  $legacy
     * @return array<string, mixed>
     */
    private function statusAttributes(ProformaRequest $record, ProformaRequestStatus $status, array $legacy): array
    {
        if ($status === ProformaRequestStatus::Pending) {
            return $record->invoice()->exists() ? [] : ['status' => $status, 'issued_at' => null];
        }

        if ($record->status === ProformaRequestStatus::Issued) {
            return [];
        }

        return ['status' => $status, 'issued_at' => $this->references->timestamp($legacy['issued_at'] ?? null)];
    }
}

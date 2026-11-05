<?php

declare(strict_types=1);

namespace App\Services\Invoices;

use App\Enums\ResidualMode;
use App\Enums\WriteOffReason;
use Illuminate\Validation\ValidationException;

/**
 * Write-off of an installment residual (spec 0361), shared by the active
 * collection and the passive payment: the installment keeps its amount, the
 * difference is stored as write-off computed by the server.
 */
final class InstallmentWriteOff
{
    /** Columns reset when the collection/payment is cleared (D-8). */
    public const array CLEARED = ['write_off_amount' => null, 'write_off_reason' => null, 'write_off_note' => null];

    /**
     * @param  array<string, mixed>  $payload  write_off_reason, write_off_note?
     * @return array{write_off_amount: string, write_off_reason: WriteOffReason, write_off_note: string|null}
     */
    public static function attributes(string $amount, string $settled, array $payload): array
    {
        $reason = WriteOffReason::tryFrom((string) ($payload['write_off_reason'] ?? ''))
            ?? throw ValidationException::withMessages(['write_off_reason' => ['The write-off reason is required.']]);
        $note = trim((string) ($payload['write_off_note'] ?? ''));

        return [
            'write_off_amount' => bcsub($amount, $settled, 2),
            'write_off_reason' => $reason,
            'write_off_note' => $note === '' ? null : $note,
        ];
    }

    /** A zero amount settles nothing: only a write-off may close the installment with it. */
    public static function assertZeroAllowed(string $settled, ResidualMode $mode): void
    {
        if (bccomp($settled, '0', 2) === 0 && $mode !== ResidualMode::WriteOff) {
            throw ValidationException::withMessages(['residual_mode' => ['A zero amount is allowed only with a write-off.']]);
        }
    }
}

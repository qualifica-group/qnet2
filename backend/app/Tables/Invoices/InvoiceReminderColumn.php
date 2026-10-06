<?php

declare(strict_types=1);

namespace App\Tables\Invoices;

use App\Enums\OutboundEmailPurpose;
use App\Enums\OutboundEmailStatus;
use App\Models\Invoice;
use App\Models\OutboundEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The `last_reminder_at` column of the invoices grid (spec 0195, D-11): the
 * latest `sent_at` of a sent reminder email of the invoice, as a correlated
 * subquery so the grid never runs per-row queries. The filter and the sort
 * compare/order by that same subquery; every date is validated and bound.
 */
final class InvoiceReminderColumn
{
    public const string ID = 'last_reminder_at';

    /**
     * @return Builder<OutboundEmail>
     */
    public static function subquery(): Builder
    {
        return OutboundEmail::query()
            ->select(DB::raw('max(outbound_emails.sent_at)'))
            ->where('outbound_emails.emailable_type', (new Invoice)->getMorphClass())
            ->whereColumn('outbound_emails.emailable_id', 'invoices.id')
            ->where('outbound_emails.purpose', OutboundEmailPurpose::Reminder->value)
            ->where('outbound_emails.status', OutboundEmailStatus::Sent->value);
    }

    /**
     * @param  Builder<Invoice>  $query
     * @param  array<string, mixed>  $filter
     */
    public static function applyFilter(Builder $query, string $columnId, array $filter): bool
    {
        if ($columnId !== self::ID) {
            return false;
        }

        $type = is_string($filter['type'] ?? null) ? $filter['type'] : 'equals';
        $from = self::day($filter['dateFrom'] ?? null);
        $to = self::day($filter['dateTo'] ?? null);

        if ($from === null) {
            return true;
        }

        $start = $from.' 00:00:00';
        $end = $from.' 23:59:59';

        match (true) {
            $type === 'inRange' && $to !== null => $query->where(self::subquery(), '>=', $start)->where(self::subquery(), '<=', $to.' 23:59:59'),
            $type === 'greaterThan' => $query->where(self::subquery(), '>', $end),
            $type === 'lessThan' => $query->where(self::subquery(), '<', $start),
            $type === 'notEqual' => $query->where(static fn (Builder $group) => $group->where(self::subquery(), '<', $start)->orWhere(self::subquery(), '>', $end)),
            default => $query->where(self::subquery(), '>=', $start)->where(self::subquery(), '<=', $end),
        };

        return true;
    }

    /**
     * @param  Builder<Invoice>  $query
     */
    public static function applySort(Builder $query, string $columnId, string $direction): bool
    {
        if ($columnId !== self::ID) {
            return false;
        }

        $query->orderBy(self::subquery(), $direction);

        return true;
    }

    private static function day(mixed $value): ?string
    {
        if (! is_string($value) || strlen($value) < 10) {
            return null;
        }

        $date = Carbon::createFromFormat('!Y-m-d', substr($value, 0, 10));

        return $date === false ? null : $date->toDateString();
    }
}

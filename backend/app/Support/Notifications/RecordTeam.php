<?php

declare(strict_types=1);

namespace App\Support\Notifications;

use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\Registry;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Model;

/**
 * The people in charge of a record (spec 0390, D-3): who a "tell the record's
 * team" notification reaches. A Commessa's Responsabili and Partecipanti, an
 * Offerta's Supervisore, Gestori Account and Operatore, an Opportunita' or an
 * Anagrafica's Supervisore and Gestori Account. Any other record has no team.
 */
final class RecordTeam
{
    /**
     * @return array<int, int> distinct user ids
     */
    public static function userIds(Model $record): array
    {
        $ids = match (true) {
            $record instanceof WorkOrder => [
                ...$record->supervisors()->pluck('users.id'),
                ...$record->participants()->pluck('users.id'),
            ],
            $record instanceof Quote => [
                $record->supervisor_id,
                $record->operator_id,
                ...$record->managers()->pluck('users.id'),
            ],
            $record instanceof Opportunity, $record instanceof Registry => [
                $record->supervisor_id,
                ...$record->managers()->pluck('users.id'),
            ],
            default => [],
        };

        return array_values(array_unique(array_map('intval', array_filter($ids))));
    }
}

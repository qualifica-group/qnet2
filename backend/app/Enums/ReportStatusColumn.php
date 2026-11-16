<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The status-based columns of "Statistiche Gestione Richieste" whose counted
 * working statuses are configured (spec 0391 D-2). The value is the column
 * key without a period (or the only one); twin() is its "(nel periodo
 * selezionato)" column, which counts the same statuses within the range.
 */
enum ReportStatusColumn: string
{
    case NewContacts = 'unhandled_new_contacts';
    case Potentials = 'current_potentials';
    case Associated = 'associati';
    case ConcludedDeals = 'trattative_concluse';
    case IntakeSent = 'invio_presa_in_carico';

    public function twin(): ?string
    {
        return match ($this) {
            self::NewContacts => 'nuovi_contatti',
            self::Potentials => 'potenziali',
            default => null,
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

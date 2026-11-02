<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Referent;

/**
 * A referent projected for the records that show it as "Referente"
 * (anagrafica cards, Opportunita', Offerta, Attivita'): {id, name} plus its
 * Tipo referente, so the reader sees the person's role ("Amministratore",
 * "Titolare") beside the name. Relies on the caller having eager-loaded
 * `referentType` — this never queries.
 */
final class ReferentRef
{
    /**
     * @return array{id: int, name: string, referent_type: array{id: int, name: string}|null}|null
     */
    public static function summarize(?Referent $referent): ?array
    {
        if ($referent === null) {
            return null;
        }

        return [
            'id' => $referent->id,
            'name' => $referent->name,
            'referent_type' => self::typeOf($referent),
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    public static function typeOf(Referent $referent): ?array
    {
        $type = $referent->referentType;

        return $type !== null ? ['id' => $type->id, 'name' => $type->name] : null;
    }
}

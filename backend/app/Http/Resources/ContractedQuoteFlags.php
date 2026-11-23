<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Quote;
use App\Models\User;
use App\Services\Quotes\ContractedQuoteGuard;

/**
 * The two flags the Offerte detail and the Gestione Richieste/Iscritti panel
 * read to freeze an offer under contract (spec 0404 part E): whether it is
 * under contract, and whether the actor may still edit it after a
 * confirmation. ContractedQuoteGuard enforces the rule server-side.
 */
final class ContractedQuoteFlags
{
    /**
     * @return array{is_contracted: bool, can_update_contracted: bool}
     */
    public static function of(Quote $quote, ?User $actor): array
    {
        return [
            'is_contracted' => $quote->relationLoaded('contract') ? $quote->contract !== null : $quote->contract()->exists(),
            'can_update_contracted' => $actor !== null && app(ContractedQuoteGuard::class)->mayEdit($actor),
        ];
    }
}

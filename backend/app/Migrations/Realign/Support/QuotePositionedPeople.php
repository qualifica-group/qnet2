<?php

declare(strict_types=1);

namespace App\Migrations\Realign\Support;

use App\Models\User;
use App\Support\ManagerPositions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Gestori and partecipanti in the report: the same person moved to another position
 * is a real change, so the position stays next to the name.
 */
final class QuotePositionedPeople
{
    /**
     * @param  BelongsToMany<User, Model>  $people
     */
    public static function describe(BelongsToMany $people): string
    {
        $users = $people->get();
        $positions = ManagerPositions::positionsOf($users);
        ksort($positions);

        return collect($positions)
            ->map(static fn (int $position, int $userId): string => $position.': '.$users->firstWhere('id', $userId)?->name)
            ->sort()
            ->values()
            ->implode(', ');
    }
}

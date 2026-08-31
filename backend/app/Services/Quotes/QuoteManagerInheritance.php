<?php

declare(strict_types=1);

namespace App\Services\Quotes;

use App\Models\Opportunity;
use App\Models\User;

/**
 * Resolves the ordered, gap-aware `manager_slots` array (spec 0087, D-5) an
 * Offerta's own Gestori Account are PREFILLED from at create time, when the
 * client submitted none: $opportunity's CURRENT managers, at their SAME
 * pivot positions.
 *
 * A dedicated class rather than a `QuoteService` private method: that
 * service sits at the file-size hard limit (D-4/R-4 context) with no room
 * left for a second responsibility.
 *
 * The shape mirrors exactly what `QuoteManagerWriter::sync()` already
 * consumes (index+1 = position, a null entry an empty slot), so the caller
 * hands the result straight over without any further transformation.
 */
final class QuoteManagerInheritance
{
    /**
     * @return array<int, int|null>
     */
    public function fromOpportunity(Opportunity $opportunity): array
    {
        $managers = $opportunity->relationLoaded('managers')
            ? $opportunity->managers
            : $opportunity->managers()->get();

        if ($managers->isEmpty()) {
            return [];
        }

        $highestPosition = (int) $managers->max(static fn (User $manager): int => (int) $manager->pivot->position);
        $slots = array_fill(0, $highestPosition, null);

        foreach ($managers as $manager) {
            $slots[(int) $manager->pivot->position - 1] = $manager->id;
        }

        return $slots;
    }
}

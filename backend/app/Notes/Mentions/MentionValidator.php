<?php

namespace App\Notes\Mentions;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Enforces both mention invariants server-side (spec 0052): the D-12
 * token/mentions coherence (the client can neither notify without
 * mentioning in the text nor mention without notifying) and the D-10
 * mentionable-set boundary (a mention outside the set is rejected even if
 * the body contains a matching token, unless spec 0353 admits it) —
 * AC-051/AC-052. Used identically by create and update: mentions are always re-validated against the CURRENT
 * body and the CURRENT mentionable set.
 */
final class MentionValidator
{
    public function __construct(private readonly MentionEnlistment $enlistment) {}

    /**
     * Returns the mentioned ids that sit outside the record's perimeter and
     * were admitted (spec 0353, D-5): the caller enlists them on save.
     *
     * @param  array<int, int>  $mentionIds
     * @return array<int, int>
     *
     * @throws ValidationException
     */
    public function validate(string $entityType, Model $record, string $body, array $mentionIds, User $author): array
    {
        // Step 1: the D-12 coherence between body tokens and mentions.
        $tokenIds = MentionParser::extractIds($body);
        $mentionIds = array_values(array_unique($mentionIds));

        $sortedTokenIds = $tokenIds;
        $sortedMentionIds = $mentionIds;
        sort($sortedTokenIds);
        sort($sortedMentionIds);

        if ($sortedTokenIds !== $sortedMentionIds) {
            throw ValidationException::withMessages([
                'mentions' => ['The mentions must match the @mentions in the note body exactly.'],
            ]);
        }

        // Step 2: split off the outsiders and admit them or refuse the lot.
        $outsideIds = $this->enlistment->outsideIds($entityType, $record, $mentionIds);

        if ($outsideIds !== []) {
            $this->enlistment->assertEnlistable($author, $entityType, $record, $outsideIds);
        }

        return $outsideIds;
    }
}

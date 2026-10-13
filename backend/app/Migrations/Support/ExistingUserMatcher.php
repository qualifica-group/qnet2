<?php

namespace App\Migrations\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;

/**
 * Finds the qnet user a legacy `users` record already corresponds to, so the
 * import adopts it (sets its `old_id`) instead of creating a duplicate: the
 * accounts seeded by hand (QualificaOperatorSeeder) carry no `old_id`, and
 * every later source links users through it (user decision 2026-10-12).
 *
 * Email first, case-insensitive; then first + last name among the users not
 * linked to a legacy id yet. A conflict never guesses: it throws, so the row
 * fails with a readable reason the super-admin can fix and re-run.
 */
final class ExistingUserMatcher
{
    /**
     * @throws RuntimeException the email belongs to a user already linked to
     *                          another legacy id, or the name matches several users
     */
    public function match(string $email, string $firstName, string $lastName): ?User
    {
        // Step 1: the email identifies the account.
        $byEmail = User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])->first();

        if ($byEmail !== null) {
            return $this->assertNotLinked($byEmail, $email);
        }

        // Step 2: otherwise the person's name, among the users not linked yet.
        return $this->uniqueByName($firstName, $lastName);
    }

    private function assertNotLinked(User $user, string $email): User
    {
        if ($user->old_id !== null) {
            throw new RuntimeException("Email {$email} already belongs to the user linked to legacy id {$user->old_id}.");
        }

        return $user;
    }

    private function uniqueByName(string $firstName, string $lastName): ?User
    {
        /** @var Collection<int, User> $candidates */
        $candidates = User::query()
            ->whereNull('old_id')
            ->whereHas('personalData', fn (Builder $card): Builder => $card
                ->whereRaw('LOWER(first_name) = ?', [mb_strtolower($firstName)])
                ->whereRaw('LOWER(last_name) = ?', [mb_strtolower($lastName)]))
            ->get();

        if ($candidates->count() > 1) {
            $emails = $candidates->pluck('email')->implode(', ');

            throw new RuntimeException("Several users are named {$firstName} {$lastName} ({$emails}): give the right one the legacy email, then re-run.");
        }

        return $candidates->first();
    }
}

<?php

declare(strict_types=1);

namespace App\Services\System;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reads "who is online" from personal_access_tokens (spec 0187): the Sanctum
 * guard touches last_used_at on every authenticated request, and the frontend
 * heartbeat keeps it fresh. The online person is COALESCE(impersonated_by,
 * tokenable_id): an impersonation token is issued on the target but the real
 * person using it is the actor. Two queries in total (tokens, then users).
 */
final class OnlineUsersService
{
    /**
     * @return array{count:int, window_minutes:int, users:array<int, array{id:int, name:string, email:string, last_seen_at:string, impersonating:?array{id:int, name:string}}>}
     */
    public function handle(): array
    {
        // Step 1: tokens used within the window, grouped per real person.
        $window = max(1, (int) config('system-health.online_window_minutes'));
        $people = $this->groupByPerson($this->recentTokens($window));

        // Step 2: resolve the active people and the impersonated targets.
        $names = $this->loadUsers(array_unique([
            ...array_keys($people),
            ...array_filter(array_column($people, 'target_id')),
        ]));

        // Step 3: build the sorted payload, skipping inactive/missing people.
        $users = [];
        foreach ($people as $personId => $person) {
            $user = $names[$personId] ?? null;
            if ($user === null || ! $user->is_active) {
                continue;
            }

            $users[] = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'last_seen_at' => $person['last_seen_at']->toIso8601String(),
                'impersonating' => $this->impersonating($person['target_id'], $names),
            ];
        }

        usort($users, static fn (array $a, array $b): int => strcmp($b['last_seen_at'], $a['last_seen_at']));

        return ['count' => count($users), 'window_minutes' => $window, 'users' => $users];
    }

    /**
     * @return Collection<int, object>
     */
    private function recentTokens(int $window)
    {
        return DB::table('personal_access_tokens')
            ->where('tokenable_type', (new User)->getMorphClass())
            ->where('last_used_at', '>=', now()->subMinutes($window))
            ->get(['tokenable_id', 'impersonated_by', 'last_used_at']);
    }

    /**
     * @param  Collection<int, object>  $tokens
     * @return array<int, array{last_seen_at:CarbonImmutable, target_id:?int}>
     */
    private function groupByPerson($tokens): array
    {
        $people = [];

        foreach ($tokens as $token) {
            $personId = (int) ($token->impersonated_by ?? $token->tokenable_id);
            $seenAt = CarbonImmutable::parse($token->last_used_at);

            if (! isset($people[$personId]) || $seenAt->greaterThan($people[$personId]['last_seen_at'])) {
                $people[$personId] = [
                    'last_seen_at' => $seenAt,
                    'target_id' => $token->impersonated_by !== null ? (int) $token->tokenable_id : null,
                ];
            }
        }

        return $people;
    }

    /**
     * @param  array<int, int>  $ids
     * @return Collection<int, User>
     */
    private function loadUsers(array $ids)
    {
        return User::query()->whereIn('id', $ids)->get(['id', 'name', 'email', 'is_active'])->keyBy('id');
    }

    /**
     * @param  Collection<int, User>  $users
     * @return array{id:int, name:string}|null
     */
    private function impersonating(?int $targetId, $users): ?array
    {
        $target = $targetId !== null ? $users[$targetId] ?? null : null;

        return $target === null ? null : ['id' => $target->id, 'name' => $target->name];
    }
}

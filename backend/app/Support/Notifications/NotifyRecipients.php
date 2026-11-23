<?php

declare(strict_types=1);

namespace App\Support\Notifications;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;

/**
 * Who an administrator-configured notification reaches: the record's team
 * (`record_team`), chosen users (`user_ids`), the holders of chosen roles
 * (`role_ids`) and free addresses (`emails`). Shared by the table row events
 * (spec 0390 D-3) and the working status emails of the offer (spec 0419 D-3):
 * active users only, the author excluded, a free address already reached as
 * a user never mailed twice.
 */
final class NotifyRecipients
{
    /**
     * @param  array<string, mixed>  $notify
     * @return EloquentCollection<int, User>
     */
    public static function users(Model $record, array $notify, User $actor): EloquentCollection
    {
        $ids = [
            ...(($notify['record_team'] ?? false) === true ? RecordTeam::userIds($record) : []),
            ...($notify['user_ids'] ?? []),
        ];
        $roleIds = $notify['role_ids'] ?? [];

        return User::query()
            ->where('is_active', true)
            ->whereKeyNot($actor->getKey())
            ->where(function ($query) use ($ids, $roleIds): void {
                $query->whereKey($ids);

                if ($roleIds !== []) {
                    $query->orWhereHas('roles', fn ($roles) => $roles->whereKey($roleIds));
                }
            })
            ->get();
    }

    /**
     * The free addresses, minus those of a user already notified (or of the author).
     *
     * @param  array<string, mixed>  $notify
     * @param  EloquentCollection<int, User>  $users
     * @return array<int, string>
     */
    public static function freeAddresses(array $notify, EloquentCollection $users, User $actor): array
    {
        $known = $users->pluck('email')->push($actor->email)->map(fn (mixed $email): string => mb_strtolower((string) $email))->all();

        return array_values(array_filter(
            $notify['emails'] ?? [],
            fn (mixed $email): bool => is_string($email) && ! in_array(mb_strtolower($email), $known, true),
        ));
    }
}

<?php

declare(strict_types=1);

namespace App\Support\Notifications;

use App\Enums\AssignmentTargetEnum;
use App\Models\User;
use App\RequestManagement\RequestModule;

/**
 * Which channels an assignment notification uses for ONE recipient (spec
 * 0420): on an Opportunita' or an Offerta the recipient's roles decide, with
 * a bell permission and an email permission per module; the email also
 * needs the personal switch (D-3). Registries and work orders keep the
 * pre-0420 rule: bell always, email per the personal switch (D-5).
 */
final class AssignmentNotificationChannels
{
    public const string BELL_ABILITY = 'notifyAssignment';

    public const string MAIL_ABILITY = 'notifyAssignmentEmail';

    /** The modules an Opportunita' assignment reads the permissions from. */
    private const array OPPORTUNITY_RESOURCES = ['opportunities'];

    /**
     * @return array<int, string>
     */
    public static function for(User $user, AssignmentTargetEnum $target): array
    {
        $resources = self::resourcesFor($target);

        if ($resources === null) {
            return $user->wantsEmailNotifications() ? ['database', 'mail'] : ['database'];
        }

        $channels = [];

        if (self::grants($user, $resources, self::BELL_ABILITY)) {
            $channels[] = 'database';
        }

        if ($user->wantsEmailNotifications() && self::grants($user, $resources, self::MAIL_ABILITY)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Every permission of this spec, `<resource>.<ability>`, on every module
     * carrying the two abilities (D-1).
     *
     * @return array<int, string>
     */
    public static function permissions(): array
    {
        return collect([...self::OPPORTUNITY_RESOURCES, ...self::quoteResources()])
            ->flatMap(static fn (string $resource): array => ["{$resource}.".self::BELL_ABILITY, "{$resource}.".self::MAIL_ABILITY])
            ->all();
    }

    /**
     * Null for the targets outside this spec (D-5).
     *
     * @return array<int, string>|null
     */
    private static function resourcesFor(AssignmentTargetEnum $target): ?array
    {
        return match ($target) {
            AssignmentTargetEnum::Opportunity => self::OPPORTUNITY_RESOURCES,
            AssignmentTargetEnum::Quote => self::quoteResources(),
            AssignmentTargetEnum::Registry, AssignmentTargetEnum::WorkOrder => null,
        };
    }

    /**
     * An Offerta is also a Gestione Richieste / Iscritti row (D-2).
     *
     * @return array<int, string>
     */
    private static function quoteResources(): array
    {
        return ['quotes', ...array_map(static fn (RequestModule $module): string => $module->value, RequestModule::cases())];
    }

    /**
     * @param  array<int, string>  $resources
     */
    private static function grants(User $user, array $resources, string $ability): bool
    {
        foreach ($resources as $resource) {
            if ($user->can("{$resource}.{$ability}")) {
                return true;
            }
        }

        return false;
    }
}

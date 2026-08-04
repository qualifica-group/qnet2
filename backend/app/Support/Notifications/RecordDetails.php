<?php

declare(strict_types=1);

namespace App\Support\Notifications;

use App\Enums\AssignmentTargetEnum;
use App\Models\Opportunity;
use App\Models\Registry;
use App\Models\User;
use App\Support\OperationalSiteLabel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Builds the "scheda dettagli" a notification email carries about the record
 * it is talking about (direttiva utente 2026-08-04).
 *
 * Returns an ORDERED map `i18n label key => value`, with keys from the
 * `notifications` lang file and NEVER a rendered label. Nothing is
 * translated here on purpose: `__()` must run at render time, inside the
 * per-recipient locale Laravel switches to via HasLocalePreference, or the
 * locale of whoever performed the write would be frozen onto every
 * recipient.
 *
 * A field with no value is OMITTED, never rendered as an empty row: a table
 * half full of dashes reads as broken data.
 *
 * Called from the SERVICE layer before dispatch, never from inside a
 * Notification: a Notification presents already-known facts and does not
 * query (the discipline RequestTransferredNotification documents).
 */
final class RecordDetails
{
    /**
     * Relations each builder reads, loaded in one batch by loadMissing() so a
     * batch write never degenerates into an N+1.
     *
     * @var array<int, string>
     */
    private const array REGISTRY_RELATIONS = ['source', 'supervisor', 'managers'];

    /**
     * @var array<int, string>
     */
    private const array OPPORTUNITY_RELATIONS = [
        'registry', 'source', 'supervisor', 'managers',
        'opportunityStatus', 'workflowStatus',
        'operationalSite.addresses.city',
    ];

    /**
     * @return array<string, string>
     */
    public static function for(Model $record): array
    {
        return match (true) {
            $record instanceof Registry => self::forRegistry($record),
            $record instanceof Opportunity => self::forOpportunity($record),
            default => [],
        };
    }

    public static function targetFor(Model $record): AssignmentTargetEnum
    {
        return $record instanceof Registry
            ? AssignmentTargetEnum::Registry
            : AssignmentTargetEnum::Opportunity;
    }

    /**
     * @return array<string, string>
     */
    private static function forRegistry(Registry $registry): array
    {
        $registry->loadMissing(self::REGISTRY_RELATIONS);

        return self::compact([
            'notifications.fields.name' => $registry->name,
            // The i18n KEY, not a rendered word: DetailsTable translates it in
            // the recipient's locale (see its value() method).
            'notifications.fields.type' => $registry->is_supplier ? 'notifications.values.supplier' : 'notifications.values.client',
            'notifications.fields.source' => $registry->source?->name,
            'notifications.fields.supervisor' => $registry->supervisor?->name,
            'notifications.fields.account_managers' => self::managerList($registry->managers),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private static function forOpportunity(Opportunity $opportunity): array
    {
        $opportunity->loadMissing(self::OPPORTUNITY_RELATIONS);

        $site = $opportunity->operationalSite;

        return self::compact([
            'notifications.fields.title' => $opportunity->name,
            'notifications.fields.client' => $opportunity->registry?->name,
            'notifications.fields.operational_site' => $site === null ? null : OperationalSiteLabel::compose($site->primaryAddress),
            'notifications.fields.status' => $opportunity->opportunityStatus?->name,
            'notifications.fields.working_status' => $opportunity->workflowStatus?->name,
            'notifications.fields.source' => $opportunity->source?->name,
            'notifications.fields.supervisor' => $opportunity->supervisor?->name,
            'notifications.fields.operator' => $opportunity->operatorManager()?->name,
        ]);
    }

    /**
     * The managers as "1. Ada Lovelace, 2. Alan Turing" — the pivot position
     * is the "G.A. n" slot and carries the order of importance, so it is part
     * of the information, not decoration.
     *
     * @param  Collection<int, User>  $managers
     */
    private static function managerList(iterable $managers): ?string
    {
        $parts = [];

        foreach ($managers as $manager) {
            $parts[] = $manager->pivot->position.'. '.$manager->name;
        }

        return $parts === [] ? null : implode(', ', $parts);
    }

    /**
     * Drop every empty field and normalize what survives to a string.
     *
     * @param  array<string, string|null>  $fields
     * @return array<string, string>
     */
    private static function compact(array $fields): array
    {
        $details = [];

        foreach ($fields as $label => $value) {
            if ($value !== null && trim($value) !== '') {
                $details[$label] = trim($value);
            }
        }

        return $details;
    }
}

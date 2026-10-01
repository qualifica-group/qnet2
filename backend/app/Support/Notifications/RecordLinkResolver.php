<?php

declare(strict_types=1);

namespace App\Support\Notifications;

use App\Enums\AssignmentTargetEnum;
use App\Models\User;
use App\RequestManagement\RequestModule;

/**
 * Resolves the SPA path a notification should point THIS recipient to
 * (spec 0081, decisione utente 2026-08-04). Per-recipient rather than
 * per-record because the same notification reaches people with different
 * permission sets: an Offerta opens from `/quotes` or `/request-management`
 * (spec 0186), a transfer from `/opportunities` or the request module.
 *
 * Returns a PATH or null, never an absolute URL — the contract every
 * notification of this repo already honours (see
 * RequestTransferredNotification::dataActionUrl()): `config('app.frontend_url')`
 * is prepended in the mail CTA and nowhere else, so a stored `action_url`
 * stays valid if the frontend ever moves.
 *
 * Null means "this recipient may not open the record from any module": the
 * caller then omits the mail button and appends the request-access sentence,
 * rather than handing out a link that lands on a 403. The bell already
 * renders a null `action_url` as a non-clickable row (notification-item.tsx),
 * so no frontend change is needed for that case.
 *
 * Static because it is pure logic over the recipient's abilities with no
 * state and no dependencies — the role `OperationalSiteLabel` and
 * `MentionParser` already play in this codebase.
 */
final class RecordLinkResolver
{
    /**
     * The link of an ASSIGNMENT notification: each record type points to its
     * own module only (rev. 2026-10-01, decisione utente). An Opportunity no
     * longer falls back to request management: that module opens Offerte,
     * which send their own assignment notification (spec 0186).
     *
     * @param  int  $recordId  the Registry, Opportunity or Quote id
     */
    public static function pathFor(User $notifiable, AssignmentTargetEnum $target, int $recordId): ?string
    {
        return match ($target) {
            AssignmentTargetEnum::Registry => $notifiable->can('registries.view')
                ? "/registries/{$recordId}"
                : null,
            AssignmentTargetEnum::Opportunity => $notifiable->can('opportunities.view')
                ? "/opportunities/{$recordId}"
                : null,
            AssignmentTargetEnum::Quote => self::quotePath($notifiable, $recordId),
        };
    }

    /**
     * The link of a TRANSFER notification (spec 0081, 0086 MT-04b, 0130):
     * the opportunities module wins when the recipient may see it, the
     * actor's request module is the fallback, opened on the Offerta — whose
     * id DIVERGES from the Opportunity's since spec 0086.
     */
    public static function transferPath(User $notifiable, int $opportunityId, int $quoteId, RequestModule $module): ?string
    {
        if ($notifiable->can('opportunities.view')) {
            return "/opportunities/{$opportunityId}";
        }

        if ($notifiable->can($module->permission('view'))) {
            return "{$module->recordPath()}/{$quoteId}";
        }

        return null;
    }

    /**
     * Spec 0186, D-1: the SAME order the SPA's own Offerta links follow
     * (`resolveViewableDomain('quotes')`): the Offerte module first, then
     * Gestione Richieste, whose rows are Offerte under the same id.
     */
    private static function quotePath(User $notifiable, int $quoteId): ?string
    {
        if ($notifiable->can('quotes.view')) {
            return "/quotes/{$quoteId}";
        }

        $requests = RequestModule::Requests;

        if ($notifiable->can($requests->permission('view'))) {
            return "{$requests->recordPath()}/{$quoteId}";
        }

        return null;
    }
}

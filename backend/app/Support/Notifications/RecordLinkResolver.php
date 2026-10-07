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
 * (spec 0186), a transfer the same way, under the actor's request module.
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
     * Each record type points to its own module only (rev. 2026-10-01,
     * decisione utente): an Opportunity never falls back to request
     * management, which opens Offerte, never Opportunities.
     *
     * @param  int  $recordId  the Registry, Opportunity, Quote or WorkOrder id
     * @param  RequestModule  $module  the request module an Offerta falls
     *                                 back to (spec 0130): the transfer passes
     *                                 the actor's, every assignment the default
     */
    public static function pathFor(User $notifiable, AssignmentTargetEnum $target, int $recordId, RequestModule $module = RequestModule::Requests): ?string
    {
        return match ($target) {
            AssignmentTargetEnum::Registry => $notifiable->can('registries.view')
                ? "/registries/{$recordId}"
                : null,
            AssignmentTargetEnum::Opportunity => $notifiable->can('opportunities.view')
                ? "/opportunities/{$recordId}"
                : null,
            AssignmentTargetEnum::Quote => self::quotePath($notifiable, $recordId, $module),
            AssignmentTargetEnum::WorkOrder => $notifiable->can('work-orders.view')
                ? "/work-orders/{$recordId}"
                : null,
        };
    }

    /**
     * Spec 0186, D-1: the SAME order the SPA's own Offerta links follow
     * (`resolveViewableDomain('quotes')`): the Offerte module first, then the
     * request module, whose rows are Offerte under the same id.
     */
    private static function quotePath(User $notifiable, int $quoteId, RequestModule $module): ?string
    {
        if ($notifiable->can('quotes.view')) {
            return "/quotes/{$quoteId}";
        }

        if ($notifiable->can($module->permission('view'))) {
            return "{$module->recordPath()}/{$quoteId}";
        }

        return null;
    }
}

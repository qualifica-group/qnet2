<?php

declare(strict_types=1);

namespace App\Services\ProformaRequests;

use App\DataObjects\Tasks\CreateTaskData;
use App\Models\CompanySite;
use App\Models\ProformaRequest;
use App\Models\Quote;
use App\Models\TaskCategory;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\TaskService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Opens the Accounting Task of a proforma request (spec 0421): one Task per
 * proforma manager of the offer's company site (D-1), assigned to them and
 * notified through the ordinary Task assignment notification (D-6). A site
 * with no manager, or an inactive one, opens nothing (D-2). Runs inside the
 * caller's transaction, so a failure here rolls the requests back too.
 */
final class ProformaRequestTaskDispatcher
{
    /** The company-sites custom field holding the proforma manager (D-2). */
    public const string RESPONSIBLE_FIELD = 'responsible_proforma';

    /** Length of `tasks.title`. */
    private const int TITLE_MAX = 191;

    public function __construct(private readonly TaskService $tasks) {}

    /**
     * @param  Collection<int, ProformaRequest>  $requests
     */
    public function dispatch(WorkOrder $workOrder, Collection $requests, User $actor, string $note): void
    {
        // Step 1: group the requests by the manager of their offer's site
        $quotes = $this->quotesOf($requests);
        $byResponsible = $this->groupByResponsible($requests, $quotes);

        if ($byResponsible === []) {
            return;
        }

        // Step 2: the settings every Task shares (D-3, D-4, D-7)
        $categoryId = $this->categoryId();
        $endDate = now()->addDays((int) config('proforma-requests.task.due_days'))->toDateString();

        // Step 3: one Task per manager, type/priority/importance left to the defaults
        foreach ($byResponsible as $responsibleId => $group) {
            $this->tasks->create(new CreateTaskData(
                title: Str::limit(__('proforma_requests.task.title', ['code' => $workOrder->code]), self::TITLE_MAX, ''),
                requesterId: $actor->id,
                endDate: $endDate,
                assigneeIds: [$responsibleId],
                description: $this->descriptionHtml($note, $group, $quotes),
                taskCategoryId: $categoryId,
                workOrderId: $workOrder->id,
                notifyAssignedUsers: true,
            ), $actor);
        }
    }

    /**
     * The offers of the requests with their site's custom-field row, in one go.
     *
     * @param  Collection<int, ProformaRequest>  $requests
     * @return Collection<int, Quote>
     */
    private function quotesOf(Collection $requests): Collection
    {
        return Quote::query()
            ->whereIn('id', $requests->pluck('quote_id')->unique()->all())
            ->with('companySite:id', 'companySite.customFieldValueRow')
            ->get(['id', 'code', 'company_site_id'])
            ->keyBy('id');
    }

    /**
     * @param  Collection<int, ProformaRequest>  $requests
     * @param  Collection<int, Quote>  $quotes
     * @return array<int, array<int, ProformaRequest>>
     */
    private function groupByResponsible(Collection $requests, Collection $quotes): array
    {
        $responsibleOf = $requests->mapWithKeys(fn (ProformaRequest $request): array => [
            $request->id => $this->responsibleOf($quotes->get($request->quote_id)?->companySite),
        ]);

        $activeIds = User::query()
            ->excludingServiceAccounts()
            ->where('is_active', true)
            ->whereIn('id', $responsibleOf->filter()->unique()->all())
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $groups = [];

        foreach ($requests as $request) {
            $responsibleId = $responsibleOf[$request->id];

            if ($responsibleId !== null && in_array($responsibleId, $activeIds, true)) {
                $groups[$responsibleId][] = $request;
            }
        }

        return $groups;
    }

    private function responsibleOf(?CompanySite $site): ?int
    {
        $value = $site?->custom_fields[self::RESPONSIBLE_FIELD] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * D-3: matched by name; a renamed, missing or inactive category leaves the Task without one.
     */
    private function categoryId(): ?int
    {
        $id = TaskCategory::query()
            ->where('name', config('proforma-requests.task.category_name'))
            ->where('is_active', true)
            ->value('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * The requester's note (plain text, escaped) followed by the requests of this manager.
     *
     * @param  array<int, ProformaRequest>  $group
     * @param  Collection<int, Quote>  $quotes
     */
    private function descriptionHtml(string $note, array $group, Collection $quotes): string
    {
        $items = collect($group)->map(fn (ProformaRequest $request): string => '<li>'.e(
            __('proforma_requests.task.kinds.'.$request->kind->value).' - '.$quotes->get($request->quote_id)?->code,
        ).'</li>')->implode('');

        $text = str_replace(["\r\n", "\r"], "\n", $note);

        return '<p>'.nl2br(e($text), false).'</p><p>'.e(__('proforma_requests.task.requests')).'</p><ul>'.$items.'</ul>';
    }
}

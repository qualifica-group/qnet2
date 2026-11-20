<?php

declare(strict_types=1);

namespace App\Services\WorkOrders\Page;

use App\Exceptions\WorkOrderPageConflictException;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderPage;
use App\RichText\RichTextImageProcessor;
use App\RichText\RichTextPlainText;
use App\RichText\RichTextProfile;
use App\Services\Tasks\TaskVisibilityScope;
use App\Services\WorkOrders\Chat\WorkOrderChatAccess;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reads and saves the notes page of a project work order (spec 0420). The callers
 * (controller) have already run WorkOrderChatAccess::assertCanRead/assertOpen;
 * this class owns the optimistic concurrency (D-3) and the content pipeline.
 */
final class WorkOrderPageService
{
    private const string FIELD = 'content';

    public function __construct(
        private readonly WorkOrderChatAccess $access,
        private readonly RichTextImageProcessor $images,
        private readonly WorkOrderPageReferences $references,
    ) {}

    public function view(WorkOrder $workOrder, User $user): WorkOrderPageView
    {
        $page = WorkOrderPage::query()
            ->where('work_order_id', $workOrder->id)
            ->with(['updatedBy', 'polls.votes.user'])
            ->first();

        return new WorkOrderPageView(
            $page,
            $this->visibleTasks($workOrder, $user, $page?->content),
            $page?->polls ?? new Collection,
            $this->access->canWrite($user, $workOrder),
        );
    }

    /**
     * @throws WorkOrderPageConflictException the stored version is not the one the client read
     * @throws ValidationException size limits
     */
    public function save(WorkOrder $workOrder, User $user, ?string $content, int $version): WorkOrderPageView
    {
        $keepAttachmentIds = [];
        $page = null;

        DB::transaction(function () use ($workOrder, $user, $content, $version, &$keepAttachmentIds, &$page): void {
            // Step 1: lock the page row (created lazily)
            $page = $this->lockedPage($workOrder);

            // Step 2: optimistic concurrency, nothing is overwritten on a mismatch
            if ($page->version !== $version) {
                throw new WorkOrderPageConflictException;
            }

            // Step 3: sanitize, extract images, scrub foreign references, enforce limits
            [$html, $keepAttachmentIds] = $this->prepareContent($workOrder, $page, $user, $content);

            // Step 4: persist and drop the polls the content no longer cites
            $page->forceFill([
                'content' => $html,
                'version' => $page->version + 1,
                'updated_by_id' => $user->id,
            ])->save();
            $page->polls()->whereNotIn('id', $this->references->pollIds($html))->delete();
        });

        // Step 5: after commit, drop the images the content no longer references
        $this->images->deleteUnreferenced($page, $keepAttachmentIds);

        return $this->view($workOrder, $user);
    }

    private function lockedPage(WorkOrder $workOrder): WorkOrderPage
    {
        WorkOrderPage::query()->createOrFirst(['work_order_id' => $workOrder->id]);

        return WorkOrderPage::query()->where('work_order_id', $workOrder->id)->lockForUpdate()->firstOrFail();
    }

    /**
     * @return array{0: ?string, 1: array<int, int>} the html to store and the attachment ids it references
     */
    private function prepareContent(WorkOrder $workOrder, WorkOrderPage $page, User $user, ?string $content): array
    {
        if ($content === null || trim($content) === '') {
            return [null, []];
        }

        $result = $this->images->process($content, $page, $user, false, self::FIELD, RichTextProfile::Page);

        $html = $this->references->scrub(
            $result->html,
            Task::query()->where('work_order_id', $workOrder->id)->whereIn('id', $this->references->taskIds($result->html))->pluck('id')->all(),
            $page->polls()->pluck('id')->all(),
        );

        $this->assertWithinLimits($html);

        return [$html, $result->referencedAttachmentIds];
    }

    private function assertWithinLimits(string $html): void
    {
        if (strlen($html) > (int) config('rich_text.page_html_max_bytes')) {
            throw ValidationException::withMessages([self::FIELD => ['The page is too large.']]);
        }

        if (mb_strlen(RichTextPlainText::toPlainText($html)) > (int) config('rich_text.page_text_max')) {
            throw ValidationException::withMessages([self::FIELD => ['The page text is too long.']]);
        }
    }

    /**
     * The tasks the content cites, as seen by $user: a task that is not of this
     * work order, deleted or not visible maps to null.
     *
     * @return array<int, Task|null>
     */
    private function visibleTasks(WorkOrder $workOrder, User $user, ?string $content): array
    {
        $ids = $this->references->taskIds($content);

        if ($ids === []) {
            return [];
        }

        $visible = TaskVisibilityScope::scopeToActor(
            Task::query()->with(['taskStatus', 'assignees'])->where('work_order_id', $workOrder->id)->whereIn('id', $ids),
            $user,
        )->get()->keyBy('id');

        return array_combine($ids, array_map(fn (int $id): ?Task => $visible->get($id), $ids));
    }
}

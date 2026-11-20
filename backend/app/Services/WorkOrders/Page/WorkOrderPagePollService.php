<?php

declare(strict_types=1);

namespace App\Services\WorkOrders\Page;

use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderPage;
use App\Models\WorkOrderPagePoll;

/**
 * Polls embedded in the notes page (spec 0420, D-7): one vote per user, changeable
 * or withdrawable. Callers have run the access checks and the validation.
 */
final class WorkOrderPagePollService
{
    private const string KEY_PREFIX = 'o';

    /**
     * Creates the page when missing (version stays 0 until its content is saved).
     *
     * @param  list<string>  $labels
     */
    public function create(WorkOrder $workOrder, User $user, string $question, array $labels): WorkOrderPagePoll
    {
        $page = WorkOrderPage::query()->createOrFirst(['work_order_id' => $workOrder->id]);

        $options = [];

        foreach (array_values($labels) as $index => $label) {
            $options[] = ['key' => self::KEY_PREFIX.($index + 1), 'label' => $label];
        }

        $poll = $page->polls()->create([
            'question' => $question,
            'options' => $options,
            'created_by_id' => $user->id,
        ]);

        return $poll->load('votes.user');
    }

    public function vote(WorkOrderPagePoll $poll, User $user, string $optionKey): WorkOrderPagePoll
    {
        $poll->votes()->updateOrCreate(['user_id' => $user->id], ['option_key' => $optionKey]);

        return $poll->load('votes.user');
    }

    public function withdraw(WorkOrderPagePoll $poll, User $user): WorkOrderPagePoll
    {
        $poll->votes()->where('user_id', $user->id)->delete();

        return $poll->load('votes.user');
    }
}

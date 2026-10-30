<?php

declare(strict_types=1);

namespace App\Services;

use App\DataObjects\SupportTopics\SupportTopicData;
use App\Models\SupportTopic;
use Illuminate\Support\Facades\DB;

/**
 * Business logic for the `support-topics` configuration (spec 0334). Tasks
 * already created from a topic are not touched on delete: no FK points from
 * `tasks` to a topic.
 */
class SupportTopicService
{
    public function create(SupportTopicData $data): SupportTopic
    {
        return DB::transaction(function () use ($data): SupportTopic {
            $topic = SupportTopic::create($data->attributes);

            $this->syncUsers($topic, $data);

            return $this->loadDetail($topic);
        });
    }

    public function update(SupportTopic $topic, SupportTopicData $data): SupportTopic
    {
        return DB::transaction(function () use ($topic, $data): SupportTopic {
            // Unconditional save: also fires the saved event for a pivot-only edit.
            $topic->fill($data->attributes)->save();

            $this->syncUsers($topic, $data);

            return $this->loadDetail($topic->refresh());
        });
    }

    public function delete(SupportTopic $topic): void
    {
        $topic->delete();
    }

    public function loadDetail(SupportTopic $topic): SupportTopic
    {
        return $topic->load([
            'taskType:id,name',
            'taskCategory:id,name',
            'taskPriority:id,name',
            'urgentTaskPriority:id,name',
            'taskImportance:id,name',
            'assignees:id,name',
            'watchers:id,name',
        ]);
    }

    private function syncUsers(SupportTopic $topic, SupportTopicData $data): void
    {
        if ($data->assigneeIds !== null) {
            $topic->assignees()->sync($data->assigneeIds);
        }

        if ($data->watcherIds !== null) {
            $topic->watchers()->sync($data->watcherIds);
        }
    }
}

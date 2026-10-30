<?php

declare(strict_types=1);

namespace App\Http\Requests\SupportTopics;

use App\Models\SupportTopic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Validates PATCH /api/support-topics/{supportTopic} (spec 0334): same keys
 * as the store, all `sometimes`.
 */
class UpdateSupportTopicRequest extends SupportTopicRequest
{
    protected function presence(): array
    {
        return ['sometimes', 'required'];
    }

    protected function nameUniqueRule(): mixed
    {
        return Rule::unique('support_topics', 'name')->ignore($this->topic());
    }

    protected function authorizationModel(): ?Model
    {
        return $this->topic();
    }

    private function topic(): SupportTopic
    {
        /** @var SupportTopic $topic */
        $topic = $this->route('supportTopic');

        return $topic;
    }
}

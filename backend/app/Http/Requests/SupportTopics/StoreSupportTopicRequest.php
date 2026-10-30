<?php

declare(strict_types=1);

namespace App\Http\Requests\SupportTopics;

use Illuminate\Database\Eloquent\Model;

/**
 * Validates POST /api/support-topics (spec 0334).
 */
class StoreSupportTopicRequest extends SupportTopicRequest
{
    protected function presence(): array
    {
        return ['required'];
    }

    protected function nameUniqueRule(): mixed
    {
        return 'unique:support_topics,name';
    }

    protected function authorizationModel(): ?Model
    {
        return null;
    }
}

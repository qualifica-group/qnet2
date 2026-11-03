<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AiConversation;
use App\Models\User;

/**
 * AI assistant conversations (spec 0347): everything requires the
 * `ai-assistant.use` permission, and per-record access is owner-only.
 * Deliberately not a BasePolicy: the single permission comes from the
 * navigation config, not from the standard `{resource}.{ability}` catalogue.
 */
class AiConversationPolicy
{
    public const string PERMISSION = 'ai-assistant.use';

    public function viewAny(User $user): bool
    {
        return $user->can(self::PERMISSION);
    }

    public function create(User $user): bool
    {
        return $user->can(self::PERMISSION);
    }

    public function view(User $user, AiConversation $conversation): bool
    {
        return $this->owns($user, $conversation);
    }

    public function update(User $user, AiConversation $conversation): bool
    {
        return $this->owns($user, $conversation);
    }

    public function delete(User $user, AiConversation $conversation): bool
    {
        return $this->owns($user, $conversation);
    }

    private function owns(User $user, AiConversation $conversation): bool
    {
        return $conversation->user_id === $user->id && $user->can(self::PERMISSION);
    }
}

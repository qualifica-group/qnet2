<?php

declare(strict_types=1);

namespace App\Services\AiAssistant\Mentions;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * Turns a mention {module, id} into what the chat stores: the record is
 * authorized with the module policy's `view`, then only its label (for the
 * person) is kept. No record field is ever read for the provider (spec 0433):
 * the model reads the content through the connector, with its own masking.
 */
final class AiMentionResolver
{
    public function __construct(private readonly AiMentionRegistry $registry) {}

    /**
     * @throws AiMentionNotFoundException unknown module or missing record
     * @throws AuthorizationException policy `view` denied
     */
    public function resolve(User $user, string $module, int $id): ResolvedAiMention
    {
        // Step 1: the module must be mentionable
        $definition = $this->registry->definition($module) ?? throw new AiMentionNotFoundException;

        // Step 2: load the record with the relations its label reads
        $record = $definition->modelClass::query()->with($definition->with)->find($id)
            ?? throw new AiMentionNotFoundException;

        // Step 3: per-record authorization, as the module itself would
        Gate::forUser($user)->authorize('view', $record);

        // Step 4: keep the label for the person; nothing else
        return new ResolvedAiMention($module, $id, ($definition->label)($record));
    }
}

<?php

declare(strict_types=1);

namespace App\Services\AiAssistant\Mentions;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;

/**
 * Turns a mention {module, id} into the record's compact projection for the
 * prompt (spec 0347, D-4, AC-009..AC-011). The record is authorized with the
 * module policy's `view` BEFORE anything is read from it, and only the
 * definition's allow-listed columns are projected: never password, tokens or
 * any other column the definition does not name.
 */
final class AiMentionResolver
{
    /** Closes a capped context so the model knows the JSON is incomplete. */
    public const string TRUNCATION_MARKER = "\n[...truncated]";

    public function __construct(private readonly AiMentionRegistry $registry) {}

    /**
     * @throws AiMentionNotFoundException unknown module or missing record
     * @throws AuthorizationException policy `view` denied
     */
    public function resolve(User $user, string $module, int $id): ResolvedAiMention
    {
        // Step 1: the module must be mentionable
        $definition = $this->registry->definition($module) ?? throw new AiMentionNotFoundException;

        // Step 2: load the record with every relation the projection reads
        $record = $definition->modelClass::query()->with(array_keys($definition->relations))->find($id)
            ?? throw new AiMentionNotFoundException;

        // Step 3: per-record authorization, as the module itself would
        Gate::forUser($user)->authorize('view', $record);

        // Step 4: project the allow-listed data and cap it
        return new ResolvedAiMention(
            module: $module,
            id: $id,
            label: ($definition->label)($record),
            context: $this->truncate($this->encode($this->project($definition, $record))),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function project(AiMentionDefinition $definition, Model $record): array
    {
        // attributesToArray() applies casts and honours $hidden; the allow-list narrows further.
        $projection = ['id' => $record->getKey(), ...Arr::only($record->attributesToArray(), $definition->fields)];

        foreach ($definition->relations as $relation => $columns) {
            $related = $record->getRelation($relation);

            $projection[$relation] = $related instanceof Collection
                ? $related->map(fn (Model $row) => $this->projectRelated($row, $columns))->values()->all()
                : ($related === null ? null : $this->projectRelated($related, $columns));
        }

        return array_filter($projection, static fn (mixed $value): bool => $value !== null && $value !== []);
    }

    /**
     * @param  string|array<int, string>  $columns
     */
    private function projectRelated(Model $row, string|array $columns): mixed
    {
        return is_string($columns)
            ? $row->getAttribute($columns)
            : Arr::only($row->attributesToArray(), $columns);
    }

    /**
     * @param  array<string, mixed>  $projection
     */
    private function encode(array $projection): string
    {
        return (string) json_encode($projection, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    private function truncate(string $json): string
    {
        $max = (int) config('ai-assistant.mention_max_chars', 8000);

        if (mb_strlen($json) <= $max) {
            return $json;
        }

        // The marker counts inside the limit.
        return mb_substr($json, 0, max(0, $max - mb_strlen(self::TRUNCATION_MARKER))).self::TRUNCATION_MARKER;
    }
}

<?php

declare(strict_types=1);

namespace App\Migrations\Realign;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Defaults shared by the targets: a record found by its `old_id`, a report
 * label from its code, and the people of a relation listed by name so the
 * report can be read without looking ids up.
 */
abstract class AbstractLegacyRealignTarget implements LegacyRealignTarget
{
    /** @return class-string<Model> */
    abstract protected function modelClass(): string;

    public function endpoint(): string
    {
        return $this->source();
    }

    public function legacyKey(array $legacy): string
    {
        return (string) ($legacy['id'] ?? '');
    }

    public function findMany(array $legacyKeys): array
    {
        $found = [];

        foreach ($this->modelClass()::query()->whereIn('old_id', $legacyKeys)->get() as $record) {
            $found[(string) $record->getAttribute('old_id')] = $record;
        }

        return $found;
    }

    public function reload(Model $record): Model
    {
        return $this->modelClass()::query()->findOrFail($record->getKey());
    }

    public function label(Model $record): string
    {
        return (string) ($record->getAttribute('code') ?? $record->getAttribute('title') ?? $record->getKey());
    }

    public function touchSubjects(Model $record): array
    {
        return [[$this->modelClass(), $record->getKey()]];
    }

    public function isAmountField(string $field): bool
    {
        return false;
    }

    /**
     * @param  Relation<User, Model, mixed>  $people
     */
    protected function peopleNames(Relation $people): string
    {
        return $people->get()->map(fn (User $user): string => $this->personName($user))->sort()->values()->implode(', ');
    }

    protected function personName(?User $user): ?string
    {
        return $user?->name;
    }

    /**
     * Scalar columns as stored, so two reads of the same row compare equal.
     *
     * @param  list<string>  $columns
     * @return array<string, string|int|float|bool|null>
     */
    protected function columns(Model $record, array $columns): array
    {
        $values = [];

        foreach ($columns as $column) {
            $values[$column] = $record->getRawOriginal($column);
        }

        return $values;
    }

    /**
     * A structured value in a stable text form.
     *
     * @param  array<int|string, mixed>|null  $value
     */
    protected function json(?array $value): ?string
    {
        return $value === null ? null : (string) json_encode($value, JSON_UNESCAPED_UNICODE);
    }
}

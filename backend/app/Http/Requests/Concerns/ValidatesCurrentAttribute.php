<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Models\Attribute;
use App\Services\Attributes\AttributeLinkRules;
use Illuminate\Contracts\Validation\Validator;

/**
 * The "Campo attuale" link of an attribute (spec 0429, D-1/D-2): never to
 * itself, never chained, only between types that share a grid column. A
 * type change of a field already linked, in either direction, is held to
 * the same rule. Used by the Store and Update attribute requests.
 */
trait ValidatesCurrentAttribute
{
    /** The attribute being edited, null on create. */
    abstract protected function linkedAttribute(): ?Attribute;

    protected function validateCurrentAttribute(Validator $validator): void
    {
        if ($validator->errors()->hasAny(['type', 'current_attribute_id'])) {
            return;
        }

        $self = $this->linkedAttribute();
        $type = (string) $this->input('type', $self?->type);

        // Step 1: the link this request sets
        if ($this->filled('current_attribute_id')) {
            $message = $this->currentAttributeError($self, (int) $this->input('current_attribute_id'), $type);

            if ($message !== null) {
                $validator->errors()->add('current_attribute_id', $message);
            }

            return;
        }

        // Step 2: a type change keeps the links it already has compatible
        if ($self !== null && $this->has('type') && ! $this->typeKeepsLinks($self, $type)) {
            $validator->errors()->add('type', __('attributes.current_attribute_incompatible'));
        }
    }

    private function currentAttributeError(?Attribute $self, int $currentId, string $type): ?string
    {
        if ($self !== null && $self->id === $currentId) {
            return __('attributes.current_attribute_self');
        }

        $current = Attribute::query()->find($currentId);

        if ($current === null) {
            return null;
        }

        $isTarget = $self !== null && Attribute::query()->where('current_attribute_id', $self->id)->exists();

        if ($current->current_attribute_id !== null || $isTarget) {
            return __('attributes.current_attribute_chained');
        }

        return AttributeLinkRules::compatible($type, $current->type) ? null : __('attributes.current_attribute_incompatible');
    }

    private function typeKeepsLinks(Attribute $self, string $type): bool
    {
        $linkedTypes = Attribute::query()->where('current_attribute_id', $self->id)->pluck('type');

        if (! $this->has('current_attribute_id') && $self->current_attribute_id !== null) {
            $linkedTypes->push(Attribute::query()->whereKey($self->current_attribute_id)->value('type'));
        }

        return $linkedTypes->every(static fn (?string $linkedType): bool => $linkedType !== null && AttributeLinkRules::compatible($type, $linkedType));
    }
}

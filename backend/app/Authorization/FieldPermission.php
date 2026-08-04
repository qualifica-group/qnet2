<?php

declare(strict_types=1);

namespace App\Authorization;

/**
 * Value object carrying the six authorization flags a form field always
 * emits (spec 0004). Only `visible`, `editable`, `required` and `disabled`
 * are primary; `hidden` and `readonly` are always DERIVED, never set
 * directly, so the two can never drift apart from their source flags:
 *
 *  - hidden   = !visible
 *  - readonly = visible && !editable && !disabled
 *
 * Concrete `ResourceAuthorization` implementations build instances only
 * through the named constructors below, so every emitted permission is one
 * of a small set of coherent states.
 */
final class FieldPermission
{
    public readonly bool $hidden;

    public readonly bool $readonly;

    private function __construct(
        public readonly bool $visible,
        public readonly bool $editable,
        public readonly bool $required,
        public readonly bool $disabled,
    ) {
        $this->hidden = ! $visible;
        $this->readonly = $visible && ! $editable && ! $disabled;
    }

    /**
     * A visible field the actor may change and submit.
     */
    public static function visibleEditable(bool $required = false): self
    {
        return new self(visible: true, editable: true, required: $required, disabled: false);
    }

    /**
     * A visible field shown but not changeable (contextual lock, e.g. a
     * super-admin-protected value). `required` defaults to false to keep
     * every pre-existing call site unchanged; pass `true` when the field
     * being restricted was already mandatory (spec 0078, AC-006) so the
     * restriction narrows to readonly without also dropping `required`.
     */
    public static function visibleReadonly(bool $required = false): self
    {
        return new self(visible: true, editable: false, required: $required, disabled: false);
    }

    /**
     * A field not rendered at all.
     */
    public static function hidden(): self
    {
        return new self(visible: false, editable: false, required: false, disabled: false);
    }

    /**
     * A visible field hard-disabled (stronger than readonly — e.g. depends on
     * another field's value not yet set).
     */
    public static function disabled(): self
    {
        return new self(visible: true, editable: false, required: false, disabled: true);
    }

    /**
     * @return array{visible: bool, hidden: bool, editable: bool, readonly: bool, required: bool, disabled: bool}
     */
    public function toArray(): array
    {
        return [
            'visible' => $this->visible,
            'hidden' => $this->hidden,
            'editable' => $this->editable,
            'readonly' => $this->readonly,
            'required' => $this->required,
            'disabled' => $this->disabled,
        ];
    }
}

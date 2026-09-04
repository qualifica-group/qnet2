import type { Control, FieldPath, FieldPathValue, FieldValues } from 'react-hook-form'
import { FormControl } from '@/components/ui/form'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { MetaField } from '@/features/authorization/MetaField'
import { useQuickCreateAction } from '@/components/form/use-quick-create-action'
import type { ForSelectItem } from '@/features/for-select/types'

/** A hydrated `{id, name}` relation projection — the shape every module's single-relation ref shares. */
export interface RelationFieldRef {
  id: number
  name: string
}

/** Field paths of `TFieldValues` whose value is a nullable relation id — the only shape this field supports. */
type RelationFieldPath<TFieldValues extends FieldValues> = {
  [K in FieldPath<TFieldValues>]: FieldPathValue<TFieldValues, K> extends number | null ? K : never
}[FieldPath<TFieldValues>]

interface RelationSelectFieldProps<
  TFieldValues extends FieldValues,
  TName extends RelationFieldPath<TFieldValues>,
> {
  control: Control<TFieldValues>
  name: TName
  /** Authorization metadata key for this field (may differ from the RHF path). */
  metaKey: string
  label: string
  /** Optional explanatory tooltip rendered next to the label via `FieldHint`. */
  hint?: string
  /** Resource segment of the for-select endpoint, e.g. `campaigns` -> `/campaigns/for-select`. */
  resource: string
  searchPlaceholder: string
  /** The loaded detail's hydrated `{id, name}` projection for this relation (edit mode), or a just-picked ref (create-mode prefill). */
  selected: RelationFieldRef | null
  /**
   * Keeps the record's PERSISTED value selectable even when the for-select
   * endpoint never returns it — the case for any picker fed by a
   * visibility-scoped endpoint (see `AsyncPaginatedSelect.pinnedItem`).
   * Without it, a user who changes the selection can never pick the original
   * back. Pass only the ref the caller already holds from its own detail
   * payload; omitted, the picker behaves exactly as before.
   */
  pinned?: RelationFieldRef | null
  /** Forces the field read-only regardless of field permissions (e.g. a derived/linked value). */
  forceDisabled?: boolean
  /** Overrides the required marker when requiredness is form-state-dependent; forwarded to `MetaField.required`. */
  required?: boolean
  /**
   * Fired after the field value changes (user pick or quick-create), with the
   * new value — lets a caller react to it, e.g. reset a dependent field when
   * this one drives a scoped picker (business function -> product category).
   */
  onValueChange?: (next: FieldPathValue<TFieldValues, TName>) => void
  /**
   * Fired alongside `onValueChange` (pick/clear) with the full selected
   * `ForSelectItem`, including `meta` — for a caller that needs more than the
   * id (e.g. auto-filling a dependent field from a presentation bag). See
   * `AsyncPaginatedSelect.onItemChange`.
   */
  onItemChange?: (item: ForSelectItem | null) => void
  /**
   * Extra, resource-specific query parameters forwarded to the for-select
   * request (spec 0032 `dependency.param`, e.g. `{ registry_id }` to scope a
   * referent picker, spec 0040 BR-4). Omitted for a plain, unscoped picker.
   * An array value is serialized as repeated `key[]=` params (Laravel
   * convention), e.g. `{ status_groups: ['open', 'pending'] }`.
   */
  params?: Record<string, string | number | string[] | number[]>
  placeholder: string
  emptyLabel: string
  errorLabel: string
  clearLabel: string
  retryLabel: string
  /** Renders an avatar in the trigger and every option (see `AsyncPaginatedSelect`). */
  showAvatar?: boolean
}

/** Renders a `{id, name}` relation ref as the `ForSelectItem` shape `AsyncPaginatedSelect` hydrates from. */
function toForSelectItem(ref: RelationFieldRef | null): ForSelectItem | null {
  return ref ? { id: ref.id, label: ref.name } : null
}

/**
 * Domain-agnostic single-relation picker: an `AsyncPaginatedSelect` inside
 * `MetaField`, hydrated from the caller's `{id, name}` projection. Shared by
 * every module with a "pick one related record" field (spec 0024 M7 —
 * extracted out of `campaign-relation-field.tsx`, the first module to need
 * it) so the picker shape is defined exactly once. Callers own their own
 * i18n strings: this component takes labels as props rather than reading a
 * fixed translation namespace.
 */
export function RelationSelectField<
  TFieldValues extends FieldValues,
  TName extends RelationFieldPath<TFieldValues>,
>({
  control,
  name,
  metaKey,
  label,
  hint,
  resource,
  searchPlaceholder,
  selected,
  pinned = null,
  forceDisabled = false,
  required,
  onValueChange,
  onItemChange,
  params,
  placeholder,
  emptyLabel,
  errorLabel,
  clearLabel,
  retryLabel,
  showAvatar = false,
}: RelationSelectFieldProps<TFieldValues, TName>) {
  const { quickCreated, renderAction } = useQuickCreateAction(resource)

  return (
    <MetaField
      control={control}
      name={name}
      metaKey={metaKey}
      label={label}
      hint={hint}
      required={required}
    >
      {({ field, disabled }) => {
        const isDisabled = disabled || forceDisabled
        // The just-created ref wins over the caller's `selected` prop so the
        // field shows the new record even before it lands on an options page
        // (AC-006); once `field.value` moves away from it, this falls back.
        const quickCreatedMatch = quickCreated.find((ref) => ref.id === field.value) ?? null

        return (
          <FormControl>
            <AsyncPaginatedSelect
              resource={resource}
              value={field.value}
              onChange={(next) => {
                const value = next as FieldPathValue<TFieldValues, TName>
                field.onChange(value)
                onValueChange?.(value)
              }}
              onItemChange={onItemChange}
              selectedItem={toForSelectItem(quickCreatedMatch ?? selected)}
              pinnedItem={toForSelectItem(pinned)}
              showAvatar={showAvatar}
              disabled={isDisabled}
              params={params}
              labels={{
                placeholder,
                searchPlaceholder,
                empty: emptyLabel,
                error: errorLabel,
                clearLabel,
                triggerLabel: label,
                retry: retryLabel,
              }}
              action={renderAction((ref) => {
                const value = ref.id as FieldPathValue<TFieldValues, TName>
                field.onChange(value)
                onValueChange?.(value)
              }, isDisabled)}
            />
          </FormControl>
        )
      }}
    </MetaField>
  )
}

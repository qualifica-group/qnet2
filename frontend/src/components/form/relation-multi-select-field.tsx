import type { Control, FieldPath, FieldPathValue, FieldValues } from 'react-hook-form'
import { FormControl } from '@/components/ui/form'
import { AsyncPaginatedMultiSelect } from '@/components/ui/async-paginated-multi-select'
import { MetaField } from '@/features/authorization/MetaField'
import { useQuickCreateAction } from '@/components/form/use-quick-create-action'
import type { RelationFieldRef } from '@/components/form/relation-select-field'
import type { ForSelectItem } from '@/features/for-select/types'

/** No hydration known: stable module-level reference so callers can omit `selected` without creating a new array every render. */
const EMPTY_SELECTED: RelationFieldRef[] = []

/** Field paths of `TFieldValues` whose value is a relation id array — the only shape this field supports. */
type RelationMultiFieldPath<TFieldValues extends FieldValues> = {
  [K in FieldPath<TFieldValues>]: FieldPathValue<TFieldValues, K> extends number[] ? K : never
}[FieldPath<TFieldValues>]

interface RelationMultiSelectFieldProps<
  TFieldValues extends FieldValues,
  TName extends RelationMultiFieldPath<TFieldValues>,
> {
  control: Control<TFieldValues>
  name: TName
  /** Authorization metadata key for this field (may differ from the RHF path). */
  metaKey: string
  label: string
  /** Resource segment of the for-select endpoint, e.g. `sectors` -> `/sectors/for-select`. */
  resource: string
  searchPlaceholder: string
  /** The loaded detail's hydrated `{id, name}` projections for this relation (edit mode). */
  selected?: RelationFieldRef[]
  /** Forces the field read-only regardless of field permissions (e.g. a derived/linked value). */
  forceDisabled?: boolean
  placeholder: string
  emptyLabel: string
  errorLabel: string
  removeLabel: string
  retryLabel: string
  /** Renders an avatar in every badge and option (see `AsyncPaginatedMultiSelect`). */
  showAvatar?: boolean
  /** Ids the option list drops client-side (spec 0118 AC-035); see `AsyncPaginatedMultiSelect.excludeIds`. */
  excludeIds?: number[]
}

/** Renders `{id, name}` relation refs as the `ForSelectItem` shape `AsyncPaginatedMultiSelect` hydrates from. */
function toForSelectItems(refs: RelationFieldRef[]): ForSelectItem[] {
  return refs.map((ref) => ({ id: ref.id, label: ref.name }))
}

/**
 * Domain-agnostic multi-relation picker: an `AsyncPaginatedMultiSelect`
 * inside `MetaField`, hydrated from the caller's `{id, name}` projections.
 * Cardinality-many sibling of `RelationSelectField` (spec 0028): a picked
 * quick-created record is ADDED to the current selection rather than
 * replacing it (AC-010).
 */
export function RelationMultiSelectField<
  TFieldValues extends FieldValues,
  TName extends RelationMultiFieldPath<TFieldValues>,
>({
  control,
  name,
  metaKey,
  label,
  resource,
  searchPlaceholder,
  selected = EMPTY_SELECTED,
  forceDisabled = false,
  placeholder,
  emptyLabel,
  errorLabel,
  removeLabel,
  retryLabel,
  showAvatar = false,
  excludeIds,
}: RelationMultiSelectFieldProps<TFieldValues, TName>) {
  const { quickCreated, renderAction } = useQuickCreateAction(resource)

  return (
    <MetaField control={control} name={name} metaKey={metaKey} label={label}>
      {({ field, disabled }) => {
        const isDisabled = disabled || forceDisabled
        const value: number[] = field.value
        // Quick-created refs still selected but not (yet) in the caller's
        // hydration prop, so their badge shows a label instead of `#id`
        // until the invalidated options page catches up (AC-006/AC-010).
        const extraSelected = quickCreated.filter(
          (ref) => value.includes(ref.id) && !selected.some((item) => item.id === ref.id),
        )

        return (
          <FormControl>
            <AsyncPaginatedMultiSelect
              resource={resource}
              value={value}
              onChange={(next) => field.onChange(next as FieldPathValue<TFieldValues, TName>)}
              selectedItems={toForSelectItems([...selected, ...extraSelected])}
              showAvatar={showAvatar}
              disabled={isDisabled}
              excludeIds={excludeIds}
              labels={{
                placeholder,
                searchPlaceholder,
                empty: emptyLabel,
                error: errorLabel,
                removeLabel,
                triggerLabel: label,
                retry: retryLabel,
              }}
              action={renderAction((ref) => {
                if (value.includes(ref.id)) {
                  return
                }
                field.onChange([...value, ref.id] as FieldPathValue<TFieldValues, TName>)
              }, isDisabled)}
            />
          </FormControl>
        )
      }}
    </MetaField>
  )
}

import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import {
  AsyncPaginatedMultiSelect,
  type AsyncPaginatedMultiSelectLabels,
} from '@/components/ui/async-paginated-multi-select'
import {
  AsyncPaginatedSelect,
  type AsyncPaginatedSelectLabels,
} from '@/components/ui/async-paginated-select'
import { toOptionValueArray } from '@/features/table/advanced-filters/option-utils'
import type { AdvancedFilterFieldProps } from '@/features/table/advanced-filters/advanced-filter-field-props'
import type { AdvancedFilterDescriptor } from '@/features/table/advanced-filters/types'

type SelectParams = Record<string, string | number>

/**
 * The for-select query params of a relation field (spec 0208): the descriptor's
 * static `source.params` merged with the dependency params, the dependency
 * winning on the same key. Booleans travel as 1/0 (Laravel `boolean` rule).
 * Memoised so the select does not see a new object on every render.
 */
function useSourceParams(
  descriptor: AdvancedFilterDescriptor,
  dependencyParams: SelectParams | undefined,
): SelectParams | undefined {
  const staticParams = descriptor.source?.params

  return useMemo(() => {
    if (staticParams === undefined) {
      return dependencyParams
    }
    const normalized = Object.fromEntries(
      Object.entries(staticParams).map(([key, entry]) => [key, typeof entry === 'boolean' ? Number(entry) : entry]),
    )
    return { ...normalized, ...dependencyParams }
  }, [staticParams, dependencyParams])
}

/**
 * Single-select for-select field, shared by `autocomplete`, `async_search`
 * and `relation` (cardinality `one`). `descriptor.source.resource` is
 * resolved at RUNTIME from the backend catalog (spec 0032 AC-011); a
 * `dependency.param` forwards the parent value as an extra for-select query
 * parameter, scoping the child's options.
 */
function SingleRelationField({
  descriptor,
  value,
  onChange,
  disabled,
  id,
  describedBy,
  invalid,
  dependencyParams,
}: AdvancedFilterFieldProps) {
  const { t } = useTranslation()
  const params = useSourceParams(descriptor, dependencyParams)
  const labels: AsyncPaginatedSelectLabels = {
    placeholder: descriptor.placeholder
      ? t(descriptor.placeholder)
      : t('table.advancedFilters.selectPlaceholder'),
    searchPlaceholder: t('table.advancedFilters.searchPlaceholder'),
    empty: t('table.advancedFilters.empty'),
    error: t('table.advancedFilters.loadError'),
    clearLabel: t('table.advancedFilters.clearLabel'),
    triggerLabel: t(descriptor.label),
    retry: t('common.retry'),
  }

  return (
    <AsyncPaginatedSelect
      resource={descriptor.source?.resource ?? ''}
      value={typeof value === 'number' ? value : null}
      onChange={onChange}
      disabled={disabled}
      id={id}
      aria-describedby={describedBy}
      aria-invalid={invalid}
      params={params}
      labels={labels}
    />
  )
}

/** Multi-select for-select field, shared by `autocomplete_multi` and `relation` (cardinality `many`). */
function MultiRelationField({
  descriptor,
  value,
  onChange,
  disabled,
  id,
  describedBy,
  invalid,
  dependencyParams,
}: AdvancedFilterFieldProps) {
  const { t } = useTranslation()
  const params = useSourceParams(descriptor, dependencyParams)
  const labels: AsyncPaginatedMultiSelectLabels = {
    placeholder: descriptor.placeholder
      ? t(descriptor.placeholder)
      : t('table.advancedFilters.selectPlaceholder'),
    searchPlaceholder: t('table.advancedFilters.searchPlaceholder'),
    empty: t('table.advancedFilters.empty'),
    error: t('table.advancedFilters.loadError'),
    removeLabel: t('table.advancedFilters.removeLabel'),
    triggerLabel: t(descriptor.label),
    retry: t('common.retry'),
  }

  return (
    <AsyncPaginatedMultiSelect
      resource={descriptor.source?.resource ?? ''}
      value={toOptionValueArray(value).filter(
        (entry): entry is number => typeof entry === 'number',
      )}
      onChange={onChange}
      disabled={disabled}
      id={id}
      aria-describedby={describedBy}
      aria-invalid={invalid}
      params={params}
      labels={labels}
    />
  )
}

/** `type: 'autocomplete'` -> always single-valued. */
export function AutocompleteAdvancedFilterField(props: AdvancedFilterFieldProps) {
  return <SingleRelationField {...props} />
}

/** `type: 'autocomplete_multi'` -> always multi-valued. */
export function AutocompleteMultiAdvancedFilterField(props: AdvancedFilterFieldProps) {
  return <MultiRelationField {...props} />
}

/**
 * `type: 'async_search'` -> always single-valued, functionally identical to
 * `autocomplete` (spec 0032 AC-011 groups them under the same component).
 */
export function AsyncSearchAdvancedFilterField(props: AdvancedFilterFieldProps) {
  return <SingleRelationField {...props} />
}

/** `type: 'relation'` -> cardinality driven by `descriptor.multiple`. */
export function RelationAdvancedFilterField(props: AdvancedFilterFieldProps) {
  return props.descriptor.multiple ? (
    <MultiRelationField {...props} />
  ) : (
    <SingleRelationField {...props} />
  )
}

import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { formatAdvancedFilterChipValue } from '@/features/table/custom-filters/format-advanced-filter-chip'
import { formatColumnFilterChipValue } from '@/features/table/custom-filters/format-column-filter-chip'
import type { CustomFilterState } from '@/features/table/custom-filters/use-custom-filter-state'
import type { UseAdvancedFiltersResult } from '@/features/table/advanced-filters/use-advanced-filters'
import type { AdvancedFilterDescriptor } from '@/features/table/advanced-filters/types'
import type { TableColumn } from '@/features/table/types'

/** One removable chip below the toolbar (spec 0158 D-5). */
export interface FilterChip {
  id: string
  label: string
  onRemove: () => void
}

interface UseActiveFilterChipsArgs {
  columns: TableColumn[]
  /** The grid's live column filterModel (spec 0158: tracked as state by `useTableLayoutPersistence`). */
  filterModel: Record<string, unknown>
  onRemoveColumnFilter: (columnId: string) => void
  advancedDescriptors: AdvancedFilterDescriptor[]
  advancedFilters: Pick<UseAdvancedFiltersResult, 'activeValues' | 'clearField'>
  search: string
  onClearSearch: () => void
  customFilter: CustomFilterState | null
  onRemoveCustomFilter: () => void
  onClearAll: () => void
}

export interface UseActiveFilterChipsResult {
  chips: FilterChip[]
  onClearAll: () => void
}

/**
 * Builds the "one chip per active filter" row (D-5): column filters, applied
 * advanced filters, the global search term and the active custom filter, each
 * removable independently; an advanced filter's `required` field is cleared
 * to its default instead of removed outright (`clearField` already does
 * that — see `useAdvancedFilters`).
 */
export function useActiveFilterChips({
  columns,
  filterModel,
  onRemoveColumnFilter,
  advancedDescriptors,
  advancedFilters,
  search,
  onClearSearch,
  customFilter,
  onRemoveCustomFilter,
  onClearAll,
}: UseActiveFilterChipsArgs): UseActiveFilterChipsResult {
  const { t } = useTranslation()

  const chips = useMemo<FilterChip[]>(() => {
    const result: FilterChip[] = []

    for (const columnId of Object.keys(filterModel)) {
      const column = columns.find((candidate) => candidate.id === columnId)
      const value = formatColumnFilterChipValue(filterModel[columnId], column, t)
      const columnLabel = column ? t(column.label) : columnId
      result.push({
        id: `column:${columnId}`,
        label: value ? `${columnLabel}: ${value}` : columnLabel,
        onRemove: () => onRemoveColumnFilter(columnId),
      })
    }

    for (const descriptor of advancedDescriptors) {
      const value = advancedFilters.activeValues[descriptor.name]
      if (value === undefined) {
        continue
      }
      const formatted = formatAdvancedFilterChipValue(descriptor, value, t)
      result.push({
        id: `advanced:${descriptor.name}`,
        label: formatted ? `${t(descriptor.label)}: ${formatted}` : t(descriptor.label),
        onRemove: () => advancedFilters.clearField(descriptor.name),
      })
    }

    if (search.trim() !== '') {
      result.push({
        id: 'search',
        label: `${t('table.search').replace('…', '')}: ${search.trim()}`,
        onRemove: onClearSearch,
      })
    }

    if (customFilter) {
      result.push({
        id: 'custom',
        label: customFilter.name ?? t('table.customFilters.unnamed'),
        onRemove: onRemoveCustomFilter,
      })
    }

    return result
  }, [
    columns,
    filterModel,
    onRemoveColumnFilter,
    advancedDescriptors,
    advancedFilters,
    search,
    onClearSearch,
    customFilter,
    onRemoveCustomFilter,
    t,
  ])

  return { chips, onClearAll }
}

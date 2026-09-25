/**
 * Renders one applied advanced filter (spec 0032) as the "valori" half of its
 * chip label (spec 0158 D-5): "enum localizzato; relazione = conteggio se le
 * etichette non sono in memoria" — the frontend never caches a for-select's
 * resolved labels outside the field itself, so a relation/autocomplete value
 * shows as a count rather than a guessed label.
 */
import { enumLabelOf } from '@/features/config/enum-label'
import type { AdvancedFilterDescriptor, AdvancedFilterValue } from '@/features/table/advanced-filters/types'

const LABELED_TYPES: ReadonlySet<AdvancedFilterDescriptor['type']> = new Set(['select', 'radio', 'enum'])

function isRange(value: object): value is { from?: unknown; to?: unknown } {
  return !Array.isArray(value)
}

function labelFor(descriptor: AdvancedFilterDescriptor, raw: string | number): string {
  if (descriptor.enumKey) {
    return enumLabelOf(descriptor.enumKey, String(raw))
  }
  const option = descriptor.options?.find((candidate) => String(candidate.value) === String(raw))
  return option?.label ?? String(raw)
}

export function formatAdvancedFilterChipValue(
  descriptor: AdvancedFilterDescriptor,
  value: AdvancedFilterValue,
  translate: (key: string, options?: Record<string, unknown>) => string,
): string {
  if (value === null || value === undefined) {
    return ''
  }
  if (typeof value === 'boolean') {
    return translate(value ? 'common.yes' : 'common.no')
  }
  if (Array.isArray(value)) {
    if (LABELED_TYPES.has(descriptor.type) && value.length <= 3) {
      return value.map((entry) => labelFor(descriptor, entry)).join(', ')
    }
    return translate('table.customFilters.selectedCount', { count: value.length })
  }
  if (typeof value === 'object' && isRange(value)) {
    const from = value.from != null ? String(value.from) : ''
    const to = value.to != null ? String(value.to) : ''
    return [from, to].filter(Boolean).join(' – ')
  }
  if (LABELED_TYPES.has(descriptor.type)) {
    return labelFor(descriptor, value as string | number)
  }
  if (descriptor.type === 'relation' || descriptor.type === 'autocomplete' || descriptor.type === 'async_search') {
    // No cached label for a for-select-backed single value: the count degrades to "1".
    return translate('table.customFilters.selectedCount', { count: 1 })
  }
  return String(value)
}

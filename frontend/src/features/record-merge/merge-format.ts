import type { TFunction } from 'i18next'
import { isEmptyValue } from '@/features/record-merge/merge-selection'
import type { MergeField, MergeFieldValue } from '@/features/record-merge/types'

/** Enum fields -> the existing `enums.*` group translating their raw values. */
const ENUM_GROUPS: Readonly<Record<string, string>> = {
  gender: 'enums.gender',
  agreement_status: 'enums.agreement_status',
  size_class: 'enums.size_class',
  contact_scope: 'enums.referent_contact_scope',
  type: 'recordMerge.values',
}

const DATE_ONLY_LENGTH = 10

export function formatDate(value: string, language: string): string {
  // A bare `YYYY-MM-DD` is parsed as local time so the day never shifts with the timezone.
  const date = new Date(value.length === DATE_ONLY_LENGTH ? `${value}T00:00:00` : value)
  return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString(language)
}

/** Human-readable value of one record's cell: the FE formats by field `type`. */
export function formatFieldValue(field: MergeField, entry: MergeFieldValue | undefined, t: TFunction, language: string): string {
  const value = entry?.value ?? null
  if (isEmptyValue(value)) {
    return t('recordMerge.values.empty')
  }
  switch (field.type) {
    case 'boolean':
      return value === true || value === 1 || value === '1' ? t('recordMerge.values.yes') : t('recordMerge.values.no')
    case 'date':
      return formatDate(String(value), language)
    case 'enum': {
      const group = ENUM_GROUPS[field.key]
      return group ? t(`${group}.${String(value)}`, { defaultValue: String(value) }) : String(value)
    }
    case 'relation':
      return entry?.display ?? String(value)
    default:
      return String(value)
  }
}

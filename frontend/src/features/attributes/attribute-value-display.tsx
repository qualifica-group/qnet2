import type { TFunction } from 'i18next'
import { useTranslation } from 'react-i18next'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { ReadonlyTableValue } from '@/features/custom-fields/components/readonly-table-value'
import type { TableFieldConfig, TableFieldValue } from '@/features/custom-fields/types'
import { formatDateTime } from '@/features/table/cell-renderers'

/**
 * The fields a read-only detail reads off an applicable Attribute. Structural,
 * so the Offerta, Commessa and Prodotto summaries (kept local to each module
 * by contract) all satisfy it without an import between modules.
 */
export interface DisplayableAttribute {
  id: number
  code: string
  name: string
  type: string
  config: Record<string, unknown> | null
  sort_order: number
  options: { value: string; label: string }[]
}

/** Formats a single (non-array) attribute value, dispatching on `type`; `null` for an unset value. */
function formatAttributeScalar(attribute: DisplayableAttribute, rawValue: unknown, t: TFunction): string | null {
  if (rawValue === null || rawValue === undefined || rawValue === '') {
    return null
  }
  switch (attribute.type) {
    case 'boolean':
      return rawValue ? t('common.yes') : t('common.no')
    case 'enum': {
      const option = attribute.options.find((candidate) => candidate.value === String(rawValue))
      return option?.label ?? String(rawValue)
    }
    case 'table':
      return null
    case 'datetime':
      return formatDateTime(rawValue) || String(rawValue)
    case 'relation': {
      if (rawValue && typeof rawValue === 'object') {
        const relation = rawValue as { label?: unknown; name?: unknown; id?: unknown }
        if (typeof relation.label === 'string') return relation.label
        if (typeof relation.name === 'string') return relation.name
        if (relation.id !== undefined) return String(relation.id)
      }
      return String(rawValue)
    }
    default:
      return String(rawValue)
  }
}

/** Array values (multiselect / many-relation) join their formatted members. */
function formatAttributeValue(attribute: DisplayableAttribute, rawValue: unknown, t: TFunction): string | null {
  if (Array.isArray(rawValue)) {
    const items = rawValue
      .map((item) => formatAttributeScalar(attribute, item, t))
      .filter((item): item is string => item !== null)
    return items.length > 0 ? items.join(', ') : null
  }
  return formatAttributeScalar(attribute, rawValue, t)
}

/** A `table` attribute with a usable definition and value, else null (falls back to the generic formatter). */
function readTableAttribute(
  attribute: DisplayableAttribute,
  rawValue: unknown,
): { config: TableFieldConfig; value: TableFieldValue } | null {
  const config = attribute.config as Partial<TableFieldConfig> | null
  if (attribute.type !== 'table' || !config || !Array.isArray(config.columns)) {
    return null
  }
  const value = rawValue as Partial<TableFieldValue> | null
  if (!value || typeof value !== 'object' || !Array.isArray(value.rows)) {
    return null
  }
  return { config: config as TableFieldConfig, value: value as TableFieldValue }
}

/**
 * One collected attribute value, read-only, per the Attribute's shared type
 * vocabulary — the same denomination the form uses, so what the operator
 * typed reads back identically. A `table` value renders as a mini table; an
 * unset value falls back to the kit's empty placeholder.
 */
export function AttributeValueDisplay({ attribute, value }: { attribute: DisplayableAttribute; value: unknown }) {
  const { t } = useTranslation()
  const table = readTableAttribute(attribute, value)

  if (table) {
    return <ReadonlyTableValue config={table.config} value={table.value} />
  }

  return formatAttributeValue(attribute, value, t) ?? <DetailEmpty />
}

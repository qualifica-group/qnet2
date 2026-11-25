import type { TFunction } from 'i18next'
import { useTranslation } from 'react-i18next'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { ReadonlyTableValue } from '@/features/custom-fields/components/readonly-table-value'
import type { TableFieldConfig, TableFieldValue } from '@/features/custom-fields/types'
import { useForSelectLabels } from '@/features/for-select/use-for-select'
import { formatDate, formatDateTime } from '@/lib/formatting/date-display'

/** Shown while a relation label resolves: never the bare id. */
const PENDING_LABEL = '…'

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
  /** Present on a `relation` attribute: where its ids get their labels. */
  relation_target?: Record<string, unknown> | null
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
    case 'date':
      return formatDate(rawValue) || String(rawValue)
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
  const resource = relationResource(attribute)
  const ids = relationIds(value)

  if (resource !== null && ids.length > 0) {
    return <RelationAttributeValue resource={resource} ids={ids} />
  }

  return <PlainAttributeValue attribute={attribute} value={value} />
}

/** The for-select resource of a `relation` attribute, null for any other type or a target without one. */
function relationResource(attribute: DisplayableAttribute): string | null {
  const resource = attribute.type === 'relation' ? attribute.relation_target?.for_select_resource : null
  return typeof resource === 'string' && resource !== '' ? resource : null
}

/** A relation value's ids: one id, or the ids of a `many` relation. */
function relationIds(value: unknown): number[] {
  const members = Array.isArray(value) ? value : [value]
  return members.filter((member): member is number => typeof member === 'number')
}

/** A relation value by the names its for-select resolves (e.g. "CODE - Title" for a Formalab class), never the bare id. */
function RelationAttributeValue({ resource, ids }: { resource: string; ids: number[] }) {
  const labels = useForSelectLabels({ resource, ids })
  return <>{ids.map((id) => labels.get(id)?.label ?? PENDING_LABEL).join(', ')}</>
}

function PlainAttributeValue({ attribute, value }: { attribute: DisplayableAttribute; value: unknown }) {
  const { t } = useTranslation()
  const table = readTableAttribute(attribute, value)

  if (table) {
    return <ReadonlyTableValue config={table.config} value={table.value} />
  }

  return formatAttributeValue(attribute, value, t) ?? <DetailEmpty />
}

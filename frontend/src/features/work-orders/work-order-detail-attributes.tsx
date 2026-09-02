import type { TFunction } from 'i18next'
import { useTranslation } from 'react-i18next'
import { ClipboardList } from 'lucide-react'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordField, RecordFieldList, RecordSection } from '@/components/detail/record-panel'
import { formatDateTime } from '@/features/table/cell-renderers'
import type { ApplicableAttributeSummary } from '@/features/work-orders/types'

/**
 * Formats one collected attribute value for read-only display, per the
 * Attribute's shared type vocabulary. Returns `null` for an unset value so the
 * caller can fall back to `DetailEmpty`. Byte-for-byte the same formatting
 * vocabulary `QuoteDetailAttributesSection` uses (spec 0098 twin).
 */
function formatAttributeValue(
  attribute: ApplicableAttributeSummary,
  rawValue: unknown,
  t: TFunction,
): string | null {
  if (rawValue === null || rawValue === undefined || rawValue === '') {
    return null
  }
  if (Array.isArray(rawValue)) {
    const items = rawValue
      .map((item) => formatAttributeScalar(attribute, item, t))
      .filter((item): item is string => item !== null)
    return items.length > 0 ? items.join(', ') : null
  }
  return formatAttributeScalar(attribute, rawValue, t)
}

/** Formats a single (non-array) attribute value, dispatching on `type`. */
function formatAttributeScalar(
  attribute: ApplicableAttributeSummary,
  rawValue: unknown,
  t: TFunction,
): string | null {
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

interface WorkOrderDetailAttributesSectionProps {
  attributes: ApplicableAttributeSummary[]
  values: Record<string, unknown>
  className?: string
}

/**
 * Read-only "Informazioni aggiuntive" on the work order record (spec 0098,
 * AC-023): one `RecordSection` row per applicable Attribute, its value
 * formatted per `type` — the same denomination and formatting vocabulary the
 * form section uses, so what the operator typed reads back identically.
 *
 * Absent entirely when the work order's own quote lines resolve no
 * applicable Attribute (D-1: no line linked, or those categories configure
 * none). Unlike the form, there is no empty-state card here: on a read-only
 * view an empty section carries no information the reader can act on.
 */
export function WorkOrderDetailAttributesSection({
  attributes,
  values,
  className,
}: WorkOrderDetailAttributesSectionProps) {
  const { t } = useTranslation()

  if (attributes.length === 0) {
    return null
  }

  return (
    <RecordSection
      title={t('workOrders.detail.additionalInformation')}
      icon={<ClipboardList />}
      className={className}
    >
      <RecordFieldList>
        {attributes.map((attribute) => (
          <RecordField key={attribute.id} label={attribute.name}>
            {formatAttributeValue(attribute, values[attribute.code], t) ?? <DetailEmpty />}
          </RecordField>
        ))}
      </RecordFieldList>
    </RecordSection>
  )
}

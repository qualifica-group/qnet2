import type { TFunction } from 'i18next'
import { useTranslation } from 'react-i18next'
import { ClipboardList } from 'lucide-react'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordField, RecordFieldList, RecordSection } from '@/components/detail/record-panel'
import { formatDateTime } from '@/features/table/cell-renderers'
import type { ApplicableAttributeSummary } from '@/features/opportunities/types'

/**
 * Formats one collected attribute value for read-only display (spec 0049
 * D-8), per the Attribute's shared type vocabulary. Returns `null` for an
 * unset value so the caller can fall back to `DetailEmpty`.
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

interface CollectedAttributesSectionProps {
  attributes: ApplicableAttributeSummary[]
  values: Record<string, unknown>
}

/**
 * Read-only "Informazioni raccolte" section (spec 0049 D-8, AC-064): one
 * `RecordField` per applicable Attribute, its value formatted per `type`.
 * Absent entirely when the opportunity has no applicable Attribute (no
 * product-category row defines any).
 */
export function CollectedAttributesSection({ attributes, values }: CollectedAttributesSectionProps) {
  const { t } = useTranslation()
  if (attributes.length === 0) {
    return null
  }
  return (
    <RecordSection title={t('opportunities.detail.collectedInformation')} icon={<ClipboardList />} full>
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

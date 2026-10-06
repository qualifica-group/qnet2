import { useTranslation } from 'react-i18next'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { ReadonlyTableValue } from '@/features/custom-fields/components/readonly-table-value'
import { isEmptyCustomFieldValue } from '@/features/custom-fields/custom-fields-values'
import { isTableFieldValue } from '@/features/custom-fields/table-field-model'
import type {
  CustomFieldDescriptor,
  CustomFieldValue,
  TableFieldConfig,
} from '@/features/custom-fields/types'
import { useForSelectLabels } from '@/features/for-select/use-for-select'
import { formatDate, formatDateTime } from '@/lib/formatting/date-display'

/** Shown while a relation label resolves: never the bare id. */
const PENDING_LABEL = '…'

/** The value as a list of members: a multiselect/many-relation already is one, a scalar is a list of one. */
function members(value: CustomFieldValue): (string | number | boolean)[] {
  if (Array.isArray(value)) {
    return value
  }
  return value === null || isTableFieldValue(value) ? [] : [value]
}

/** A relation value's ids (a single id, or the ids of a `many` relation). */
function relationIds(value: CustomFieldValue): number[] {
  return members(value).filter((member): member is number => typeof member === 'number')
}

function RelationValue({ descriptor, value }: CustomFieldValueDisplayProps) {
  const ids = relationIds(value)
  const labels = useForSelectLabels({
    resource: descriptor.relation?.for_select_resource ?? '',
    ids,
    enabled: Boolean(descriptor.relation),
  })
  return <>{ids.map((id) => labels.get(id)?.label ?? PENDING_LABEL).join(', ')}</>
}

interface CustomFieldValueDisplayProps {
  descriptor: CustomFieldDescriptor
  value: CustomFieldValue
}

/**
 * One custom field value as a record detail reads it (spec 0200 D-3): per
 * type, in the same words the form shows — an enum by its option's label, a
 * relation by the names its for-select resolves, a boolean as Si/No, a table
 * as the read-only mini table. An unset value is the kit's empty marker.
 */
export function CustomFieldValueDisplay({ descriptor, value }: CustomFieldValueDisplayProps) {
  const { t } = useTranslation()

  if (descriptor.type !== 'boolean' && isEmptyCustomFieldValue(value)) {
    return <DetailEmpty />
  }

  switch (descriptor.type) {
    case 'table': {
      const config = descriptor.config as TableFieldConfig | null | undefined
      return config && Array.isArray(config.columns) && isTableFieldValue(value) ? (
        <ReadonlyTableValue config={config} value={value} />
      ) : (
        <DetailEmpty />
      )
    }
    case 'relation':
      return <RelationValue descriptor={descriptor} value={value} />
    case 'boolean':
      return <>{value === true ? t('common.yes') : t('common.no')}</>
    case 'enum':
      return (
        <>
          {members(value)
            .map((member) => descriptor.options?.find((option) => option.value === String(member))?.label ?? String(member))
            .join(', ')}
        </>
      )
    case 'date':
      return <>{formatDate(String(value)) || String(value)}</>
    case 'datetime':
      return <>{formatDateTime(String(value)) || String(value)}</>
    case 'textarea':
      return <span className="whitespace-pre-line">{String(value)}</span>
    default:
      return <>{String(value)}</>
  }
}

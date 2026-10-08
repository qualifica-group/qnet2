import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { SlidersHorizontal } from 'lucide-react'
import type { Control } from 'react-hook-form'
import { RecordFieldList, RecordSection } from '@/components/detail/record-panel'
import { RecordInlineField, type InlineEdit } from '@/components/record-form/record-inline-field'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import { CustomFieldItem } from '@/features/custom-fields/CustomFieldsSection'
import { CustomFieldValueDisplay } from '@/features/custom-fields/custom-field-value-display'
import { groupByLabel, sortVisibleCustomFields } from '@/features/custom-fields/custom-fields-grouping'
import {
  isCustomFieldDescriptor,
  rawKey,
  type CustomFieldsFormShape,
  type CustomFieldValue,
} from '@/features/custom-fields/types'

interface CustomFieldRecordSectionsProps<TFieldValues extends CustomFieldsFormShape> {
  /** Domain key of the host resource (the one whose `/meta/{resource}` carries the definitions). */
  resource: string
  /** The values the closed rows show: persisted on a detail, the draft's on a create. */
  values: Record<string, CustomFieldValue>
  inline: InlineEdit
  control: Control<TFieldValues>
  /** On each section (e.g. a column span inside the record's sections grid). */
  className?: string
}

/**
 * The resource's custom fields ("campi flessibili", spec 0021) as record
 * sections whose rows edit in place (spec 0200 D-3): the same blocks, in the
 * same order, as the form (`CustomFieldsSection`), each value opening on the
 * SAME control the form renders (`CustomFieldItem`). On a detail a confirm
 * PATCHes that field alone (the payload diff is sparse); on a create draft it
 * only keeps the value. Renders nothing when the resource has no visible
 * custom field.
 */
export function CustomFieldRecordSections<TFieldValues extends CustomFieldsFormShape>({
  resource,
  values,
  inline,
  control,
  className,
}: CustomFieldRecordSectionsProps<TFieldValues>) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const metaQuery = useResourceMeta(resource)

  const groups = useMemo(
    () =>
      groupByLabel(
        sortVisibleCustomFields(metaQuery.data?.fields.filter(isCustomFieldDescriptor) ?? [], fieldPermission),
      ),
    [fieldPermission, metaQuery.data],
  )

  return (
    <>
      {[...groups.entries()].map(([group, descriptors]) => (
        <RecordSection
          key={group ?? 'ungrouped'}
          title={group ?? t('customFields.section.title')}
          icon={<SlidersHorizontal />}
          className={className}
        >
          <RecordFieldList>
            {descriptors.map((descriptor) => {
              const key = rawKey(descriptor.key)
              return (
                <RecordInlineField
                  key={descriptor.key}
                  field={`custom_fields.${key}`}
                  metaKey={descriptor.key}
                  label={descriptor.label}
                  inline={inline}
                  editor={<CustomFieldItem control={control} descriptor={descriptor} />}
                >
                  <CustomFieldValueDisplay descriptor={descriptor} value={values[key] ?? null} />
                </RecordInlineField>
              )
            })}
          </RecordFieldList>
        </RecordSection>
      ))}
    </>
  )
}

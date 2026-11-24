import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { useController, type Control } from 'react-hook-form'
import { FormControl, FormDescription } from '@/components/ui/form'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { MetaField } from '@/features/authorization/MetaField'
import { ATTRIBUTES_FOR_SELECT_RESOURCE } from '@/features/attributes/attribute-current-attribute'
import type { AttributeFormValues } from '@/features/attributes/use-attribute-form'
import type { ForSelectItem } from '@/features/for-select/types'

interface AttributeCurrentAttributeFieldProps {
  control: Control<AttributeFormValues>
  /** The field being edited, left out of the options; absent on create. */
  attributeId?: number
}

/** The "Campo attuale" picker of a legacy field (spec 0429 D-7): clearable, gated by the field permission. */
export function AttributeCurrentAttributeField({ control, attributeId }: AttributeCurrentAttributeFieldProps) {
  const { t } = useTranslation()
  const { field } = useController({ control, name: 'current_attribute_id' })
  const { field: labelField } = useController({ control, name: 'current_attribute_label' })
  const selectedItem: ForSelectItem | null =
    field.value !== null && labelField.value !== null ? { id: field.value, label: labelField.value } : null
  const params = useMemo(() => (attributeId === undefined ? undefined : { exclude_id: attributeId }), [attributeId])

  return (
    <MetaField
      control={control}
      name="current_attribute_id"
      metaKey="current_attribute_id"
      label={t('attributes.form.currentAttribute')}
      description={<FormDescription>{t('attributes.form.currentAttributeHint')}</FormDescription>}
    >
      {({ disabled }) => (
        <FormControl>
          <AsyncPaginatedSelect
            resource={ATTRIBUTES_FOR_SELECT_RESOURCE}
            params={params}
            value={field.value ?? null}
            onChange={field.onChange}
            onItemChange={(item) => labelField.onChange(item?.label ?? null)}
            selectedItem={selectedItem}
            disabled={disabled}
            labels={{
              placeholder: t('attributes.form.currentAttributePlaceholder'),
              searchPlaceholder: t('attributes.form.currentAttributeSearch'),
              empty: t('attributes.form.currentAttributeEmpty'),
              error: t('attributes.form.currentAttributeError'),
              clearLabel: t('common.clear'),
              triggerLabel: t('attributes.form.currentAttribute'),
              retry: t('common.retry'),
            }}
          />
        </FormControl>
      )}
    </MetaField>
  )
}

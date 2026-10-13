import { useTranslation } from 'react-i18next'
import { IconPicker } from '@/components/icon-picker'
import { useFormField } from '@/components/ui/form'

interface IconPickerControlProps {
  value: string
  onChange: (name: string) => void
  disabled: boolean
  readOnly?: boolean
}

/**
 * Bridges the shared `IconPicker` into the RHF/`FormItem` context: reads the
 * accessible-error triad ids via `useFormField()` (one level inside the
 * `MetaField` render prop) and supplies the localized picker labels, generic
 * UI vocabulary shared with the custom-fields module. One copy for every
 * catalog form (they used to carry their own).
 */
export function IconPickerControl({ value, onChange, disabled, readOnly = false }: IconPickerControlProps) {
  const { t } = useTranslation()
  const { formItemId, formDescriptionId, formMessageId, error } = useFormField()

  return (
    <IconPicker
      value={value}
      onChange={onChange}
      disabled={disabled}
      readOnly={readOnly}
      id={formItemId}
      describedBy={error ? `${formDescriptionId} ${formMessageId}` : formDescriptionId}
      invalid={Boolean(error)}
      labels={{
        placeholder: t('customFields.form.iconPickerPlaceholder'),
        searchPlaceholder: t('customFields.form.iconSearchPlaceholder'),
        empty: t('customFields.form.iconEmpty'),
        clearLabel: t('customFields.form.iconClear'),
      }}
    />
  )
}

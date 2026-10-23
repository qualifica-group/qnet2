import type { ReactNode } from 'react'
import { useFormContext, useWatch } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordInlineField, type InlineEdit } from '@/components/record-form/record-inline-field'
import { FormControl } from '@/components/ui/form'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { MetaField } from '@/features/authorization/MetaField'
import type { CommissionConfigurationFormValues } from './commission-configuration-schema'

/** The form fields rendered as a closed list of options (labels under `commissionConfigurations.options.<name>`). */
export type OptionFieldName = 'recipient_role' | 'application_scope' | 'commission_type' | 'status' | 'recipient_type' | 'supplier_commission_direction'

interface OptionSelectProps {
  name: OptionFieldName
  options: readonly string[]
  /** Authorization key when it differs from `name` (the recipient type follows `recipient_id`). */
  metaKey?: string
  onValueChange?: (next: string) => void
  hint?: string
}

/** Metadata-aware option picker, the editor of every enum field of the configuration. */
export function OptionSelect({ name, options, metaKey = name, onValueChange, hint }: OptionSelectProps) {
  const { t } = useTranslation()
  const { control } = useFormContext<CommissionConfigurationFormValues>()

  return (
    <MetaField control={control} name={name} metaKey={metaKey} label={t(`commissionConfigurations.form.${name}`)} hint={hint}>
      {({ field, disabled }) => (
        <Select
          value={field.value ?? ''}
          onValueChange={(next) => {
            field.onChange(next)
            onValueChange?.(next)
          }}
          disabled={disabled}
        >
          <FormControl>
            <SelectTrigger className="w-full">
              <SelectValue placeholder={t('commissionConfigurations.form.selectPlaceholder')} />
            </SelectTrigger>
          </FormControl>
          <SelectContent>
            {options.map((option) => (
              <SelectItem key={option} value={option}>
                {t(`commissionConfigurations.options.${name}.${option}`)}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      )}
    </MetaField>
  )
}

interface OptionInlineRowProps {
  name: Exclude<OptionFieldName, 'recipient_role' | 'application_scope' | 'recipient_type'>
  options: readonly string[]
  inline: InlineEdit
  canEdit: boolean
  /** Closed value, given the option's label; defaults to the plain label. */
  renderValue?: (label: string, value: string) => ReactNode
  hint?: string
}

/** A one-field row whose editor is an option picker (commission type, status). */
export function OptionInlineRow({ name, options, inline, canEdit, renderValue, hint }: OptionInlineRowProps) {
  const { t } = useTranslation()
  const { control } = useFormContext<CommissionConfigurationFormValues>()
  const value = useWatch({ control, name })
  const label = value ? t(`commissionConfigurations.options.${name}.${value}`) : ''

  return (
    <RecordInlineField
      field={name}
      label={t(`commissionConfigurations.form.${name}`)}
      inline={inline}
      canEdit={canEdit}
      editor={<OptionSelect name={name} options={options} hint={hint} />}
    >
      {value ? renderValue ? renderValue(label, value) : label : <DetailEmpty />}
    </RecordInlineField>
  )
}

/**
 * The refusal of a field the closed row does not render itself (the later
 * fields of a grouped row): a cross-field rule failing on it must still show
 * on the row that owns it.
 */
export function SecondaryFieldError({ name }: { name: keyof CommissionConfigurationFormValues }) {
  const { formState } = useFormContext<CommissionConfigurationFormValues>()
  const message = formState.errors[name]?.message

  return message ? (
    <p role="alert" className="mt-1 text-xs font-medium text-destructive">
      {message}
    </p>
  ) : null
}

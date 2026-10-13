import { useFormContext, useWatch } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { MetaFieldRowContext } from '@/features/authorization/meta-field-row-context'
import { RecordInlineField, type InlineEdit } from '@/components/record-form/record-inline-field'
import { FormControl } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { MetaField } from '@/features/authorization/MetaField'
import { formatDate } from '@/lib/formatting/date-display'
import { SecondaryFieldError } from './commission-configuration-controls'
import type { CommissionConfigurationFormValues } from './commission-configuration-schema'

interface CommissionConfigurationValidityRowProps {
  inline: InlineEdit
  canEdit: boolean
}

/** Start and end of the validity are ONE row: the server refuses an end before the start. */
export function CommissionConfigurationValidityRow({ inline, canEdit }: CommissionConfigurationValidityRowProps) {
  const { t } = useTranslation()
  const { control } = useFormContext<CommissionConfigurationFormValues>()
  const [validFrom, validUntil] = useWatch({ control, name: ['valid_from', 'valid_until'] })
  const from = formatDate(validFrom)

  return (
    <RecordInlineField
      field="valid_from"
      label={t('commissionConfigurations.detail.validityRow')}
      inline={inline}
      canEdit={canEdit}
      editor={
        // The row's label names the group: each sub-field keeps its own visible label.
        <MetaFieldRowContext.Provider value={null}>
          <div className="grid gap-3 sm:grid-cols-2">
            <MetaField
              control={control}
              name="valid_from"
              metaKey="valid_from"
              label={t('commissionConfigurations.form.valid_from')}
            >
              {({ field, disabled, readOnly }) => (
                <FormControl>
                  <Input type="date" {...field} disabled={disabled} readOnly={readOnly} />
                </FormControl>
              )}
            </MetaField>
            <MetaField
              control={control}
              name="valid_until"
              metaKey="valid_until"
              label={t('commissionConfigurations.form.valid_until')}
            >
              {({ field, disabled, readOnly }) => (
                <FormControl>
                  <Input
                    type="date"
                    {...field}
                    value={field.value ?? ''}
                    disabled={disabled}
                    readOnly={readOnly}
                    onChange={(event) => field.onChange(event.target.value || null)}
                  />
                </FormControl>
              )}
            </MetaField>
          </div>
        </MetaFieldRowContext.Provider>
      }
    >
      {validUntil
        ? t('commissionConfigurations.detail.validityRange', { from, until: formatDate(validUntil) })
        : t('commissionConfigurations.detail.validityOpenEnded', { from })}
      <SecondaryFieldError name="valid_until" />
    </RecordInlineField>
  )
}

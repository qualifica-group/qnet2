import { useTranslation } from 'react-i18next'
import { useFormContext, useWatch } from 'react-hook-form'
import { RecordInlineField, type InlineEdit } from '@/components/record-form/record-inline-field'
import { ColorInlineRow, TextInlineRow } from '@/components/record-form/inline-rows'
import { FormControl } from '@/components/ui/form'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { MetaField } from '@/features/authorization/MetaField'
import { QUOTE_SYSTEM_STATUS_BEHAVIORS } from '@/features/quote-system-statuses/types'
import type { QuoteSystemStatusFormValues } from '@/features/quote-system-statuses/quote-system-status-schema'

interface QuoteSystemStatusRowsProps {
  inline: InlineEdit
  canEdit: boolean
  /** A system row has a fixed behavior: only name and color edit. */
  isSystemRow?: boolean
}

/** The system status's rows, shared by the detail (saved one at a time) and the create draft (kept until Salva). */
export function QuoteSystemStatusRows({ inline, canEdit, isSystemRow = false }: QuoteSystemStatusRowsProps) {
  const { t } = useTranslation()
  const { control } = useFormContext<QuoteSystemStatusFormValues>()
  const behavior = useWatch({ control, name: 'behavior' })
  const behaviorLabel = t('quoteSystemStatuses.form.behavior.label')

  return (
    <>
      <TextInlineRow<QuoteSystemStatusFormValues> name="name" label={t('quoteSystemStatuses.form.name')} inline={inline} canEdit={canEdit} />
      <ColorInlineRow<QuoteSystemStatusFormValues> name="color" label={t('quoteSystemStatuses.form.color')} inline={inline} canEdit={canEdit} />
      <RecordInlineField
        field="behavior"
        label={behaviorLabel}
        inline={inline}
        canEdit={canEdit && !isSystemRow}
        editor={
          <MetaField control={control} name="behavior" metaKey="behavior" label={behaviorLabel} hint={t('quoteSystemStatuses.form.behavior.hint')}>
            {({ field, disabled }) => (
              <Select value={field.value} onValueChange={field.onChange} disabled={disabled}>
                <FormControl>
                  <SelectTrigger className="w-full">
                    <SelectValue />
                  </SelectTrigger>
                </FormControl>
                <SelectContent>
                  {QUOTE_SYSTEM_STATUS_BEHAVIORS.map((value) => (
                    <SelectItem key={value} value={value}>
                      {t(`quoteSystemStatuses.form.behavior.${value}`)}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            )}
          </MetaField>
        }
      >
        {t(`quoteSystemStatuses.form.behavior.${behavior}`)}
      </RecordInlineField>
    </>
  )
}

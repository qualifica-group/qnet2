import { useFormContext, useWatch } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { ColorInlineRow, SwitchInlineRow, TextInlineRow } from '@/components/record-form/inline-rows'
import { RecordInlineField, type InlineEdit } from '@/components/record-form/record-inline-field'
import { FormControl } from '@/components/ui/form'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { MetaField } from '@/features/authorization/MetaField'
import type { CostTypeFormValues } from '@/features/cost-types/use-cost-type-form'
import { COST_TYPE_NATURES, type CostTypeNature } from '@/features/cost-types/types'

interface CostTypeRowsProps {
  inline: InlineEdit
  canEdit: boolean
}

/** The nature (direct / indirect / general): localized label when closed, a select over the fixed list when open. */
function CostTypeNatureRow({ inline, canEdit }: CostTypeRowsProps) {
  const { t } = useTranslation()
  const { control } = useFormContext<CostTypeFormValues>()
  const nature = useWatch({ control, name: 'nature' })
  const label = t('costTypes.form.nature')

  return (
    <RecordInlineField
      field="nature"
      label={label}
      inline={inline}
      canEdit={canEdit}
      editor={
        <MetaField control={control} name="nature" metaKey="nature" label={label} hint={t('costTypes.form.hints.nature')}>
          {({ field, disabled }) => (
            <Select
              value={field.value}
              onValueChange={(next) => field.onChange(next as CostTypeNature)}
              disabled={disabled}
            >
              <FormControl>
                <SelectTrigger className="w-full">
                  <SelectValue />
                </SelectTrigger>
              </FormControl>
              <SelectContent>
                {COST_TYPE_NATURES.map((option) => (
                  <SelectItem key={option} value={option}>
                    {t(`costTypes.nature.${option}`)}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          )}
        </MetaField>
      }
    >
      {t(`costTypes.nature.${nature}`)}
    </RecordInlineField>
  )
}

/** The cost type's rows, shared by the detail (saved one at a time) and the create draft (kept until Save). */
export function CostTypeRows({ inline, canEdit }: CostTypeRowsProps) {
  const { t } = useTranslation()

  return (
    <>
      <TextInlineRow<CostTypeFormValues> name="name" label={t('costTypes.form.name')} inline={inline} canEdit={canEdit} />
      <TextInlineRow<CostTypeFormValues>
        name="description"
        label={t('costTypes.form.description')}
        inline={inline}
        canEdit={canEdit}
        multiline
      />
      <CostTypeNatureRow inline={inline} canEdit={canEdit} />
      <ColorInlineRow<CostTypeFormValues> name="color" label={t('costTypes.form.color')} inline={inline} canEdit={canEdit} />
      <SwitchInlineRow<CostTypeFormValues> name="is_active" label={t('costTypes.form.isActive')} inline={inline} canEdit={canEdit} />
    </>
  )
}

import { useTranslation } from 'react-i18next'
import { useFormContext, useWatch } from 'react-hook-form'
import { RecordInlineField, type InlineEdit } from '@/components/record-form/record-inline-field'
import { FormControl } from '@/components/ui/form'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { MetaField } from '@/features/authorization/MetaField'
import { ColorInlineRow, SwitchInlineRow, TextInlineRow } from '@/components/record-form/inline-rows'
import { CONTRACT_STATUS_GROUPS, type ContractStatusGroupValue } from '@/features/status-reorder/types'
import type { ContractStatusFormValues } from '@/features/contract-statuses/use-contract-status-form'

interface ContractStatusRowsProps {
  inline: InlineEdit
  canEdit: boolean
  /** A system row has fixed fields: only name and color edit. */
  isSystemRow?: boolean
  /** BR-5: the current default cannot be unset, only reassigned by making another row default. */
  isCurrentDefault?: boolean
}

/** The contract status's rows, shared by the detail (saved one at a time) and the create draft (kept until Salva). */
export function ContractStatusRows({ inline, canEdit, isSystemRow = false, isCurrentDefault = false }: ContractStatusRowsProps) {
  const { t } = useTranslation()
  const { control } = useFormContext<ContractStatusFormValues>()
  const group = useWatch({ control, name: 'group' })
  const groupLabel = t('contractStatuses.form.group.label')

  return (
    <>
      <TextInlineRow<ContractStatusFormValues> name="name" label={t('contractStatuses.form.name')} inline={inline} canEdit={canEdit} />
      <TextInlineRow<ContractStatusFormValues>
        name="description"
        label={t('contractStatuses.form.description')}
        inline={inline}
        canEdit={canEdit && !isSystemRow}
        multiline
      />
      <ColorInlineRow<ContractStatusFormValues> name="color" label={t('contractStatuses.form.color')} inline={inline} canEdit={canEdit} />
      <RecordInlineField
        field="group"
        label={groupLabel}
        inline={inline}
        canEdit={canEdit && !isSystemRow}
        editor={
          <MetaField control={control} name="group" metaKey="group" label={groupLabel}>
            {({ field, disabled }) => (
              <Select
                value={field.value}
                onValueChange={(next) => field.onChange(next as ContractStatusGroupValue)}
                disabled={disabled}
              >
                <FormControl>
                  <SelectTrigger className="w-full">
                    <SelectValue />
                  </SelectTrigger>
                </FormControl>
                <SelectContent>
                  {CONTRACT_STATUS_GROUPS.map((value) => (
                    <SelectItem key={value} value={value}>
                      {t(`contractStatuses.form.group.${value}`)}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            )}
          </MetaField>
        }
      >
        {t(`contractStatuses.form.group.${group}`)}
      </RecordInlineField>
      <SwitchInlineRow<ContractStatusFormValues>
        name="is_active"
        label={t('contractStatuses.form.isActive')}
        inline={inline}
        canEdit={canEdit && !isSystemRow}
      />
      <SwitchInlineRow<ContractStatusFormValues>
        name="is_default"
        label={t('contractStatuses.form.isDefault')}
        inline={inline}
        canEdit={canEdit && !isSystemRow && !isCurrentDefault}
      />
    </>
  )
}

import { useTranslation } from 'react-i18next'
import { useFormContext, useWatch } from 'react-hook-form'
import { RecordInlineField, type InlineEdit } from '@/components/record-form/record-inline-field'
import { FormControl } from '@/components/ui/form'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { MetaField } from '@/features/authorization/MetaField'
import { ColorInlineRow, SwitchInlineRow, TextInlineRow } from '@/components/record-form/inline-rows'
import { REWARD_STATUS_GROUPS, type RewardStatusGroupValue } from '@/features/status-reorder/types'
import type { RewardStatusFormValues } from '@/features/reward-statuses/use-reward-status-form'

interface RewardStatusRowsProps {
  inline: InlineEdit
  canEdit: boolean
  /** A system row ("In attesa"/"Approvato"/"Negato") has fixed fields: only name and color edit. */
  isSystemRow?: boolean
}

/** The reward status's rows, shared by the detail (saved one at a time) and the create draft (kept until Salva). */
export function RewardStatusRows({ inline, canEdit, isSystemRow = false }: RewardStatusRowsProps) {
  const { t } = useTranslation()
  const { control } = useFormContext<RewardStatusFormValues>()
  const group = useWatch({ control, name: 'group' })
  const groupLabel = t('rewardStatuses.form.group.label')

  return (
    <>
      <TextInlineRow<RewardStatusFormValues> name="name" label={t('rewardStatuses.form.name')} inline={inline} canEdit={canEdit} />
      <TextInlineRow<RewardStatusFormValues>
        name="description"
        label={t('rewardStatuses.form.description')}
        inline={inline}
        canEdit={canEdit && !isSystemRow}
        multiline
      />
      <ColorInlineRow<RewardStatusFormValues> name="color" label={t('rewardStatuses.form.color')} inline={inline} canEdit={canEdit} />
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
                onValueChange={(next) => field.onChange(next as RewardStatusGroupValue)}
                disabled={disabled}
              >
                <FormControl>
                  <SelectTrigger className="w-full">
                    <SelectValue />
                  </SelectTrigger>
                </FormControl>
                <SelectContent>
                  {REWARD_STATUS_GROUPS.map((value) => (
                    <SelectItem key={value} value={value}>
                      {t(`rewardStatuses.form.group.${value}`)}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            )}
          </MetaField>
        }
      >
        {t(`rewardStatuses.form.group.${group}`)}
      </RecordInlineField>
      <SwitchInlineRow<RewardStatusFormValues>
        name="is_active"
        label={t('rewardStatuses.form.isActive')}
        inline={inline}
        canEdit={canEdit && !isSystemRow}
      />
    </>
  )
}

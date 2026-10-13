import { useTranslation } from 'react-i18next'
import { ColorInlineRow, TextInlineRow } from '@/components/record-form/inline-rows'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import type { RewardTypeFormValues } from '@/features/reward-types/use-reward-type-form'

interface RewardTypeRowsProps {
  inline: InlineEdit
  canEdit: boolean
}

/** The reward type's rows, shared by the detail (saved one at a time) and the create draft (kept until Salva). */
export function RewardTypeRows({ inline, canEdit }: RewardTypeRowsProps) {
  const { t } = useTranslation()

  return (
    <>
      <TextInlineRow<RewardTypeFormValues> name="name" label={t('rewardTypes.form.name')} inline={inline} canEdit={canEdit} />
      <ColorInlineRow<RewardTypeFormValues> name="color" label={t('rewardTypes.form.color')} inline={inline} canEdit={canEdit} />
    </>
  )
}

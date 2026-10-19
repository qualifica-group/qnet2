import { useTranslation } from 'react-i18next'
import { NumberInlineRow, SwitchInlineRow, TextInlineRow } from '@/components/record-form/inline-rows'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import type { AbsenceTypeFormValues } from '@/features/absence-types/absence-type-schema'

interface AbsenceTypeRowsProps {
  inline: InlineEdit
  canEdit: boolean
}

/** The absence type's rows, shared by the detail (saved one at a time) and the create draft (kept until Save). */
export function AbsenceTypeRows({ inline, canEdit }: AbsenceTypeRowsProps) {
  const { t } = useTranslation()

  return (
    <>
      <TextInlineRow<AbsenceTypeFormValues> name="name" label={t('absenceTypes.form.name')} inline={inline} canEdit={canEdit} />
      <SwitchInlineRow<AbsenceTypeFormValues>
        name="deducts_balance"
        label={t('absenceTypes.form.deductsBalance')}
        hint={t('absenceTypes.form.deductsBalanceHint')}
        inline={inline}
        canEdit={canEdit}
      />
      <SwitchInlineRow<AbsenceTypeFormValues>
        name="requires_approval"
        label={t('absenceTypes.form.requiresApproval')}
        inline={inline}
        canEdit={canEdit}
      />
      <SwitchInlineRow<AbsenceTypeFormValues> name="is_active" label={t('absenceTypes.form.isActive')} inline={inline} canEdit={canEdit} />
      <NumberInlineRow<AbsenceTypeFormValues>
        name="sort_order"
        label={t('absenceTypes.form.sortOrder')}
        inline={inline}
        canEdit={canEdit}
        min={0}
        step={1}
      />
    </>
  )
}

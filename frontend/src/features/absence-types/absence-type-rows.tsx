import { useTranslation } from 'react-i18next'
import { useFormContext, useWatch } from 'react-hook-form'
import { NumberInlineRow, SwitchInlineRow, TextInlineRow } from '@/components/record-form/inline-rows'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import { AbsenceTypeColorRow } from '@/features/absence-types/absence-type-color-row'
import type { AbsenceTypeFormValues } from '@/features/absence-types/absence-type-schema'

interface AbsenceTypeRowsProps {
  inline: InlineEdit
  canEdit: boolean
}

/** The absence type's rows, shared by the detail (saved one at a time) and the create draft (kept until Save). */
export function AbsenceTypeRows({ inline, canEdit }: AbsenceTypeRowsProps) {
  const { t } = useTranslation()
  const { control } = useFormContext<AbsenceTypeFormValues>()
  const deductsBalance = useWatch({ control, name: 'deducts_balance' })

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
      {deductsBalance ? (
        <NumberInlineRow<AbsenceTypeFormValues>
          name="default_annual_days"
          label={t('absenceTypes.form.defaultAnnualDays')}
          hint={t('absenceTypes.form.defaultAnnualDaysHint')}
          inline={inline}
          canEdit={canEdit}
          min={0}
          max={366}
          step={0.01}
          suffix={t('absenceTypes.form.daysUnit')}
        />
      ) : null}
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
      <SwitchInlineRow<AbsenceTypeFormValues>
        name="allows_full_day"
        label={t('absenceTypes.form.allowsFullDay')}
        hint={t('absenceTypes.form.modesHint')}
        inline={inline}
        canEdit={canEdit}
      />
      <SwitchInlineRow<AbsenceTypeFormValues> name="allows_half_day" label={t('absenceTypes.form.allowsHalfDay')} inline={inline} canEdit={canEdit} />
      <SwitchInlineRow<AbsenceTypeFormValues> name="allows_hourly" label={t('absenceTypes.form.allowsHourly')} inline={inline} canEdit={canEdit} />
      <NumberInlineRow<AbsenceTypeFormValues>
        name="min_notice_days"
        label={t('absenceTypes.form.minNoticeDays')}
        hint={t('absenceTypes.form.minNoticeDaysHint')}
        inline={inline}
        canEdit={canEdit}
        min={0}
        max={365}
        step={1}
        suffix={t('absenceTypes.form.daysUnit')}
      />
      <AbsenceTypeColorRow inline={inline} canEdit={canEdit} />
    </>
  )
}

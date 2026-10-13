import { useTranslation } from 'react-i18next'
import { ColorInlineRow, SwitchInlineRow, TextInlineRow } from '@/components/record-form/inline-rows'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import type { WorkOrderPaymentStatusFormValues } from '@/features/work-order-payment-statuses/use-work-order-payment-status-form'

interface WorkOrderPaymentStatusRowsProps {
  inline: InlineEdit
  canEdit: boolean
}

/** The work order payment status's rows, shared by the detail (saved one at a time) and the create draft (kept until Salva). */
export function WorkOrderPaymentStatusRows({ inline, canEdit }: WorkOrderPaymentStatusRowsProps) {
  const { t } = useTranslation()

  return (
    <>
      <TextInlineRow<WorkOrderPaymentStatusFormValues> name="name" label={t('workOrderPaymentStatuses.form.name')} inline={inline} canEdit={canEdit} />
      <TextInlineRow<WorkOrderPaymentStatusFormValues>
        name="description"
        label={t('workOrderPaymentStatuses.form.description')}
        inline={inline}
        canEdit={canEdit}
        multiline
      />
      <ColorInlineRow<WorkOrderPaymentStatusFormValues> name="color" label={t('workOrderPaymentStatuses.form.color')} inline={inline} canEdit={canEdit} />
      <SwitchInlineRow<WorkOrderPaymentStatusFormValues>
        name="is_active"
        label={t('workOrderPaymentStatuses.form.isActive')}
        inline={inline}
        canEdit={canEdit}
      />
      <SwitchInlineRow<WorkOrderPaymentStatusFormValues>
        name="allows_delivery"
        label={t('workOrderPaymentStatuses.form.allowsDelivery')}
        hint={t('workOrderPaymentStatuses.form.hints.allowsDelivery')}
        inline={inline}
        canEdit={canEdit}
      />
    </>
  )
}

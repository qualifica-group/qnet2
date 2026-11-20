import { useTranslation } from 'react-i18next'
import type { Control } from 'react-hook-form'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordField } from '@/components/detail/record-panel'
import { RecordInlineField, type InlineEdit } from '@/components/record-form/record-inline-field'
import { Badge } from '@/components/ui/badge'
import { FormControl } from '@/components/ui/form'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { MetaField } from '@/features/authorization/MetaField'
import { colorBadgeClass } from '@/features/custom-fields/color-badge-class'
import { money } from '@/features/work-order-contract-data/contract-data-format'
import { NO_STATUS_VALUE } from '@/features/work-order-contract-data/contract-payment-bulk-schema'
import { PaymentStatusLabel } from '@/features/work-order-contract-data/payment-status-label'
import { usePaymentStatusOptions } from '@/features/work-order-contract-data/use-work-order-contract-data'
import type { ContractPaymentStatusRef } from '@/features/work-order-contract-data/types'
import type { WorkOrderFormState, WorkOrderFormValues } from '@/features/work-orders/use-work-order-form'
import { cn } from '@/lib/utils'

/** "Importo" (spec 0402): the net amount of the commessa's lines; absent for an actor who may not see it. */
export function WorkOrderAmountRow({ amount }: { amount: string | null | undefined }) {
  const { t } = useTranslation()

  if (amount === null || amount === undefined) {
    return null
  }

  return (
    <RecordField label={t('workOrders.detail.amount')}>
      <span className="font-medium tabular-nums">{money(amount)}</span>
    </RecordField>
  )
}

interface PaymentStatusEditorProps {
  control: Control<WorkOrderFormValues>
  current: ContractPaymentStatusRef | null
}

/** The configured payment statuses (the same list as the per-line payment), "Nessuno stato" first. */
function WorkOrderPaymentStatusEditor({ control, current }: PaymentStatusEditorProps) {
  const { t } = useTranslation()
  const options = usePaymentStatusOptions(current)
  const label = t('workOrders.detail.paymentStatus')

  return (
    <MetaField control={control} name="work_order_payment_status_id" metaKey="work_order_payment_status_id" label={label}>
      {({ field, disabled }) => (
        <Select
          value={field.value === null || field.value === undefined ? NO_STATUS_VALUE : String(field.value)}
          onValueChange={(next) => field.onChange(next === NO_STATUS_VALUE ? null : Number(next))}
          disabled={disabled}
        >
          <FormControl>
            <SelectTrigger className="h-9 w-full" aria-label={label}>
              <SelectValue />
            </SelectTrigger>
          </FormControl>
          <SelectContent>
            <SelectItem value={NO_STATUS_VALUE}>{t('workOrders.contractData.editor.noStatus')}</SelectItem>
            {options.map((option) => (
              <SelectItem key={option.id} value={String(option.id)}>
                <PaymentStatusLabel status={option} />
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      )}
    </MetaField>
  )
}

interface WorkOrderPaymentStatusRowProps {
  form: WorkOrderFormState
  inline: InlineEdit
  status: ContractPaymentStatusRef | null | undefined
}

/** "Stato pagamento" of the commessa (spec 0402), edited in place by whoever manages its payments. */
export function WorkOrderPaymentStatusRow({ form, inline, status }: WorkOrderPaymentStatusRowProps) {
  const { t } = useTranslation()

  return (
    <RecordInlineField
      field="work_order_payment_status_id"
      label={t('workOrders.detail.paymentStatus')}
      inline={inline}
      editor={<WorkOrderPaymentStatusEditor control={form.form.control} current={status ?? null} />}
    >
      {status ? (
        <Badge variant="outline" title={status.name} className={cn('max-w-full min-w-0 px-1.5 py-0 text-xs', colorBadgeClass(status.color))}>
          <PaymentStatusLabel status={status} />
        </Badge>
      ) : (
        <DetailEmpty />
      )}
    </RecordInlineField>
  )
}

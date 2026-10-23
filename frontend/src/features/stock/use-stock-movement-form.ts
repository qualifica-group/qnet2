import { useMemo } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { serverMessage } from '@/features/absences/requests/absence-server-errors'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { toStockMovementPayload } from '@/features/stock/stock-movement-payload'
import { buildStockMovementSchema, type StockMovementFormValues } from '@/features/stock/stock-movement-schema'
import { useRecordStockMovement } from '@/features/stock/use-record-stock-movement'

/** Payload field names a 422 can land on; `counted_quantity`/`quantity` are separate inputs per type. */
const SERVER_ERROR_FIELDS = [
  'type',
  'product_id',
  'operational_site_id',
  'user_id',
  'to_operational_site_id',
  'quantity',
  'counted_quantity',
  'moved_on',
  'note',
] as const

interface UseStockMovementFormArgs {
  defaultValues: StockMovementFormValues
  /** Called after the server accepted the movement (the page refreshes its grids). */
  onRecorded: () => void
}

/** Owns every non-render concern of the movement form: RHF/Zod wiring, the POST and the 422 mapping. */
export function useStockMovementForm({ defaultValues, onRecorded }: UseStockMovementFormArgs) {
  const { t } = useTranslation()
  const record = useRecordStockMovement()
  const schema = useMemo(() => buildStockMovementSchema(t), [t])
  const form = useForm<StockMovementFormValues>({ resolver: zodResolver(schema), defaultValues })

  async function persist(values: StockMovementFormValues) {
    const payload = toStockMovementPayload(values)
    if (payload === null) {
      return
    }
    try {
      await record.mutateAsync(payload)
      toast.success(t('stock.form.recorded'))
      onRecorded()
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, [...SERVER_ERROR_FIELDS])) {
        toast.error(serverMessage(error) ?? t('stock.form.error'))
      }
    }
  }

  const submit = form.handleSubmit(persist)

  return { form, isSubmitting: record.isPending, submit }
}

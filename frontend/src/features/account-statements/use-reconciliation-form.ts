import { useMemo } from 'react'
import { useForm, useWatch } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { serverMessage } from '@/features/absences/requests/absence-server-errors'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { useAbilities } from '@/features/auth/use-abilities'
import {
  normalizeBalance,
  parseCents,
  reconciliationDifference,
} from '@/features/account-statements/reconciliation-math'
import {
  buildReconciliationSchema,
  EMPTY_RECONCILIATION_VALUES,
  type ReconciliationFormValues,
} from '@/features/account-statements/reconciliation-schema'
import { useCreateReconciliation, useReconciliationPreview } from '@/features/account-statements/use-reconciliation'

const SERVER_ERROR_FIELDS: Array<keyof ReconciliationFormValues> = [
  'statement_date',
  'bank_balance',
  'note',
  'treasury_type_id',
]

/** Wiring of the "New reconciliation" form: preview, live difference, optional adjustment and 422 mapping. */
export function useReconciliationForm(accountId: number, onSaved: () => void) {
  const { t } = useTranslation()
  const { can } = useAbilities()
  const create = useCreateReconciliation(accountId)

  // Step 1: the form values drive the preview (ticked balance at the date) and the live difference
  const form = useForm<ReconciliationFormValues>({ defaultValues: EMPTY_RECONCILIATION_VALUES })
  const [date, bankBalance, createAdjustment] = useWatch({
    control: form.control,
    name: ['statement_date', 'bank_balance', 'create_adjustment'],
  })
  const preview = useReconciliationPreview(accountId, date)
  const difference = preview.data ? reconciliationDifference(bankBalance, preview.data.reconciled_balance) : null
  const hasDifference = difference !== null && parseCents(difference) !== 0
  // The adjustment needs `create` on top of `reconcile` (spec 0363 D-4).
  const canAdjust = hasDifference && can('treasury-movements.create')

  // Step 2: the schema depends on the difference (sub-account mandatory only when there is one)
  const schema = useMemo(() => buildReconciliationSchema(t, hasDifference), [t, hasDifference])

  // Step 3: save
  const submit = form.handleSubmit(async (parsed) => {
    const result = schema.safeParse(parsed)
    if (!result.success) {
      for (const issue of result.error.issues) {
        form.setError(issue.path[0] as keyof ReconciliationFormValues, { message: issue.message })
      }
      return
    }
    const values = result.data
    const adjust = values.create_adjustment && canAdjust
    try {
      await create.mutateAsync({
        statement_date: values.statement_date,
        bank_balance: normalizeBalance(values.bank_balance),
        note: values.note.trim() === '' ? null : values.note.trim(),
        create_adjustment: adjust,
        ...(adjust ? { treasury_type_id: Number(values.treasury_type_id) } : {}),
      })
      toast.success(t('accountStatements.reconciliation.saved'))
      onSaved()
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, SERVER_ERROR_FIELDS)) {
        toast.error(serverMessage(error) ?? t('accountStatements.reconciliation.saveError'))
      }
    }
  })

  return {
    form,
    preview,
    difference,
    showAdjustment: canAdjust,
    adjustmentChecked: createAdjustment && canAdjust,
    isSubmitting: create.isPending,
    submit,
  }
}

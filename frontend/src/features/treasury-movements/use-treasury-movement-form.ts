import { useMemo } from 'react'
import { useForm, type UseFormSetError } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { serverMessage } from '@/features/absences/requests/absence-server-errors'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { createTreasuryMovement, updateTreasuryMovement } from '@/features/treasury-movements/api'
import { treasuryMovementKeys } from '@/features/treasury-movements/query-keys'
import { toTreasuryMovementPayload } from '@/features/treasury-movements/treasury-movement-payload'
import {
  buildTreasuryMovementSchema,
  type TreasuryMovementFormValues,
} from '@/features/treasury-movements/treasury-movement-schema'

/** Request fields a 422 can land on; the three sub-account ids share the one `sub_account` select. */
const SERVER_ERROR_FIELDS = [
  'type',
  'direction',
  'movement_date',
  'financial_account_id',
  'counterpart_account_id',
  'amount',
  'description',
  'registry_id',
  'work_order_id',
  'cost_type_id',
  'revenue_type_id',
  'treasury_type_id',
  'sub_account',
] as const

type ServerErrorField = (typeof SERVER_ERROR_FIELDS)[number]
type ServerErrorValues = Record<ServerErrorField, string>

/** Form field that shows the error of each request field. */
const FORM_FIELD_OF: Record<ServerErrorField, keyof TreasuryMovementFormValues> = {
  type: 'type',
  direction: 'direction',
  movement_date: 'movement_date',
  financial_account_id: 'financial_account_id',
  counterpart_account_id: 'counterpart_account_id',
  amount: 'amount',
  description: 'description',
  registry_id: 'registry_id',
  work_order_id: 'work_order_id',
  cost_type_id: 'sub_account',
  revenue_type_id: 'sub_account',
  treasury_type_id: 'sub_account',
  // The backend reports "none or more than one sub-account" on this key.
  sub_account: 'sub_account',
}

function isServerErrorField(name: string): name is ServerErrorField {
  return name in FORM_FIELD_OF
}

interface UseTreasuryMovementFormArgs {
  /** Id of the movement being edited; null creates. */
  movementId: number | null
  defaultValues: TreasuryMovementFormValues
  onSaved: () => void
}

/** Owns the non-render concerns of the movement form: RHF/Zod wiring, create/update and the 422 mapping. */
export function useTreasuryMovementForm({ movementId, defaultValues, onSaved }: UseTreasuryMovementFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const schema = useMemo(() => buildTreasuryMovementSchema(t), [t])
  const form = useForm<TreasuryMovementFormValues>({ resolver: zodResolver(schema), defaultValues })

  const save = useMutation({
    mutationFn: (values: TreasuryMovementFormValues) => {
      const payload = toTreasuryMovementPayload(values)
      if (payload === null) {
        return Promise.reject(new Error('invalid payload'))
      }
      return movementId === null
        ? createTreasuryMovement(payload)
        : updateTreasuryMovement(movementId, payload)
    },
  })

  async function persist(values: TreasuryMovementFormValues) {
    try {
      await save.mutateAsync(values)
      if (movementId !== null) {
        void queryClient.invalidateQueries({ queryKey: treasuryMovementKeys.detail(movementId) })
      }
      toast.success(t(movementId === null ? 'treasuryMovements.form.created' : 'treasuryMovements.form.updated'))
      onSaved()
    } catch (error) {
      // Keys outside the request fields (e.g. "root") go to the form-level error.
      const setServerError: UseFormSetError<ServerErrorValues> = (name, err) =>
        form.setError(isServerErrorField(name) ? FORM_FIELD_OF[name] : 'root', err)
      const mapped = applyServerValidationErrors(error, setServerError, [...SERVER_ERROR_FIELDS])
      if (!mapped) {
        toast.error(serverMessage(error) ?? t('treasuryMovements.form.error'))
      }
    }
  }

  return { form, isSubmitting: save.isPending, submit: form.handleSubmit(persist) }
}

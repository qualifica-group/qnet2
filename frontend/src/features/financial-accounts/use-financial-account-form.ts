import { useMemo, useState } from 'react'
import { useForm, type Path, type Resolver } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import {
  createFinancialAccount,
  updateFinancialAccount,
} from '@/features/financial-accounts/api'
import {
  EMPTY_FORM_VALUES,
  buildFinancialAccountPayload,
  toFormValues,
} from '@/features/financial-accounts/financial-account-form-payload'
import {
  buildFinancialAccountSchema,
  type FinancialAccountFormValues,
} from '@/features/financial-accounts/financial-account-schema'
import type {
  FinancialAccountDetail,
  FinancialAccountFormMode,
} from '@/features/financial-accounts/types'

/** Server-side field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS: Path<FinancialAccountFormValues>[] = [
  'type',
  'name',
  'company_id',
  'iban',
  'account_number',
  'address_line',
  'postal_code',
  'country_id',
  'state_id',
  'province_id',
  'city_id',
  'card_type',
  'card_circuit',
  'linked_account_id',
  'card_holder',
  'card_number',
  'card_expiry',
  'notes',
]

interface UseFinancialAccountFormArgs {
  mode: FinancialAccountFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (account: FinancialAccountDetail) => void
}

/**
 * Owns every non-render concern of `FinancialAccountForm`: RHF/Zod wiring,
 * default values, server 422 mapping and the create/update submit.
 */
export function useFinancialAccountForm({ mode, onSuccess }: UseFinancialAccountFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)

  const isEdit = mode.type === 'edit'

  const schema = useMemo(() => buildFinancialAccountSchema(t, { isEdit }), [isEdit, t])

  const defaultValues = useMemo<FinancialAccountFormValues>(
    () => (mode.type === 'edit' ? toFormValues(mode.financialAccount) : EMPTY_FORM_VALUES),
    [mode],
  )

  // The resolver validates the discriminated subset of the flat form values; the
  // cast bridges the union input type of the schema to the flat RHF value type.
  const form = useForm<FinancialAccountFormValues>({
    resolver: zodResolver(schema) as unknown as Resolver<FinancialAccountFormValues>,
    defaultValues,
  })

  const onSubmit = async (values: FinancialAccountFormValues) => {
    setServerError(null)
    try {
      // Step 1: re-parse so the payload only carries the chosen type's fields
      const payload = buildFinancialAccountPayload(schema.parse(values))

      // Step 2: persist
      if (mode.type === 'edit') {
        const saved = await updateFinancialAccount(mode.financialAccount.id, payload)
        queryClient.setQueryData(['financial-accounts', 'detail', mode.financialAccount.id], saved)
        toast.success(t('financialAccounts.form.updated'))
        onSuccess(saved)
        return
      }

      const created = await createFinancialAccount(payload)
      toast.success(t('financialAccounts.form.created'))
      onSuccess(created)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, SERVER_ERROR_FIELDS)) {
        setServerError(t('financialAccounts.form.genericError'))
      }
    }
  }

  return { form, isEdit, serverError, onSubmit }
}

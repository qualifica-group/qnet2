import { useState } from 'react'
import type { Path, UseFormReturn } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { apiErrorMessage } from '@/lib/forbidden-message'
import { createLead, leadDetailQueryKey, updateLead } from '@/features/leads/api'
import { buildCreatePayload, buildUpdatePayload } from '@/features/leads/lead-form-payload'
import { editDefaults, type LeadFormValues } from '@/features/leads/use-lead-form'
import type { LeadDetail, LeadFormMode } from '@/features/leads/types'

/** Server-side field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS: Path<LeadFormValues>[] = [
  'registry_id',
  'campaign_id',
  'operational_site_id',
  'source_id',
  'operator_id',
  'notes',
  'products_of_interest',
  'convert_to_opportunity',
]

interface UseLeadFormSubmitArgs {
  form: UseFormReturn<LeadFormValues>
  mode: LeadFormMode
  /** Called after a successful create/update. */
  onSuccess: (lead: LeadDetail) => void
}

/**
 * Owns the lead create/update submit: the partial PATCH (sparse diff), the
 * cache seed and the server 422 mapping. On the in-place detail (spec 0217)
 * the form is reset on the saved record, so the next row starts clean.
 */
export function useLeadFormSubmit({ form, mode, onSuccess }: UseLeadFormSubmitArgs) {
  const { t, i18n } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)

  const onSubmit = async (values: LeadFormValues) => {
    setServerError(null)
    try {
      if (mode.type === 'edit') {
        // Step 1 (edit): PATCH what changed and seed the cached detail with
        // the full shape the server returns (permissions included).
        const saved = await updateLead(mode.lead.id, buildUpdatePayload(values, mode.lead))
        queryClient.setQueryData(leadDetailQueryKey(mode.lead.id), saved)
        // Step 2 (edit): the detail stays mounted on the saved record, clean.
        form.reset(editDefaults(saved), { keepDirtyValues: false })
        toast.success(t('leads.form.updated'))
        onSuccess(saved)
        return
      }

      const created = await createLead(buildCreatePayload(values))
      toast.success(t('leads.form.created'))
      onSuccess(created)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, SERVER_ERROR_FIELDS)) {
        setServerError(apiErrorMessage(error, t('leads.form.genericError'), i18n))
      }
    }
  }

  // Opening or closing an in-place editor starts from a clean slate.
  const clearServerError = () => setServerError(null)

  return { serverError, onSubmit, clearServerError }
}

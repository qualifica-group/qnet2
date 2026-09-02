import { useEffect, useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { useAbilities } from '@/features/auth/use-abilities'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { createLead, leadDetailQueryKey, updateLead } from '@/features/leads/api'
import { buildCreatePayload, buildUpdatePayload } from '@/features/leads/lead-form-payload'
import { recordToEntries } from '@/features/leads/extra-fields'
import {
  buildCreateLeadSchema,
  buildUpdateLeadSchema,
  type CreateLeadFormValues,
} from '@/features/leads/lead-schema'
import type { LeadDetail, LeadFormMode } from '@/features/leads/types'

/** Server-side field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS = [
  'registry_id',
  'campaign_id',
  'operational_site_id',
  'source_id',
  'operator_id',
  'state_id',
  'notes',
  'products_of_interest',
  'convert_to_opportunity',
] as const

export type LeadFormValues = CreateLeadFormValues

interface UseLeadFormArgs {
  mode: LeadFormMode
  /** Called after a successful create/update so the caller can navigate to the detail page. */
  onSuccess: (lead: LeadDetail) => void
}

/**
 * Owns every non-render concern of `LeadForm`: RHF/Zod wiring, default
 * values, server 422 mapping and the create/update submit. The component
 * stays UI-only; this hook is the orchestration point (`onSubmit`).
 */
export function useLeadForm({ mode, onSuccess }: UseLeadFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const { can } = useAbilities()
  const [serverError, setServerError] = useState<string | null>(null)

  const isEdit = mode.type === 'edit'

  // The auto-convert control defaults ON for actors who can create
  // Opportunities and OFF otherwise: it is hidden without the permission
  // (`Can`), so a `true` default there would silently submit an unauthorized
  // conversion. Create-only, hence gated on the mode as well.
  const canConvertToOpportunity = mode.type === 'create' && can('opportunities.create')

  const schema = useMemo(
    () => (isEdit ? buildUpdateLeadSchema(t) : buildCreateLeadSchema(t)),
    [isEdit, t],
  )

  const defaultValues = useMemo<LeadFormValues>(() => {
    if (mode.type === 'edit') {
      const { lead } = mode
      return {
        registry_id: lead.registry_id,
        campaign_id: lead.campaign_id,
        operational_site_id: lead.operational_site_id,
        source_id: lead.source_id,
        operator_id: lead.operator_id,
        state_id: lead.state_id ?? null,
        notes: lead.notes,
        extra_fields: recordToEntries(lead.extra_fields),
        products_of_interest: (lead.products_of_interest ?? []).map((product) => product.id),
        // Create-only control (spec 0044): edit mode never renders the
        // checkbox, so it always defaults to false here.
        convert_to_opportunity: false,
      }
    }
    return {
      registry_id: null,
      campaign_id: null,
      operational_site_id: null,
      source_id: null,
      operator_id: null,
      state_id: null,
      notes: null,
      extra_fields: [],
      products_of_interest: [],
      convert_to_opportunity: canConvertToOpportunity,
    }
  }, [mode, canConvertToOpportunity])

  const form = useForm<LeadFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
  })

  // Abilities resolve asynchronously and the control stays hidden until they
  // do (`Can`), so `defaultValues` may capture a stale `false` at mount.
  // Re-seed the create-only flag once the permission is known; the dependency
  // is a stable primitive afterwards, so a manual toggle is never clobbered.
  useEffect(() => {
    if (mode.type === 'create') {
      form.setValue('convert_to_opportunity', canConvertToOpportunity)
    }
  }, [canConvertToOpportunity, mode.type, form])

  const onSubmit = async (values: LeadFormValues) => {
    setServerError(null)
    const errorFields: Path<LeadFormValues>[] = [...SERVER_ERROR_FIELDS]
    try {
      if (mode.type === 'edit') {
        const saved = await updateLead(mode.lead.id, buildUpdatePayload(values, mode.lead))
        queryClient.setQueryData(leadDetailQueryKey(mode.lead.id), saved)
        toast.success(t('leads.form.updated'))
        onSuccess(saved)
        return
      }

      const created = await createLead(buildCreatePayload(values))
      toast.success(t('leads.form.created'))
      onSuccess(created)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, errorFields)) {
        setServerError(t('leads.form.genericError'))
      }
    }
  }

  return {
    form,
    isEdit,
    serverError,
    onSubmit,
  }
}

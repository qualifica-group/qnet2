import { useEffect, useMemo } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useAbilities } from '@/features/auth/use-abilities'
import { recordToEntries } from '@/features/leads/extra-fields'
import {
  buildCreateLeadSchema,
  buildUpdateLeadSchema,
  type CreateLeadFormValues,
} from '@/features/leads/lead-schema'
import type { LeadDetail, LeadFormMode } from '@/features/leads/types'

export type LeadFormValues = CreateLeadFormValues

/** The persisted lead as the edit form holds it (the in-place detail's clean state). */
export function editDefaults(lead: LeadDetail): LeadFormValues {
  return {
    registry_id: lead.registry_id,
    campaign_id: lead.campaign_id,
    operational_site_id: lead.operational_site_id,
    source_id: lead.source_id,
    operator_id: lead.operator_id,
    notes: lead.notes,
    extra_fields: recordToEntries(lead.extra_fields),
    products_of_interest: (lead.products_of_interest ?? []).map((product) => product.id),
    // Create-only control (spec 0044): the detail never shows it.
    convert_to_opportunity: false,
  }
}

function createDefaults(convertToOpportunity: boolean): LeadFormValues {
  return {
    registry_id: null,
    campaign_id: null,
    operational_site_id: null,
    source_id: null,
    operator_id: null,
    notes: null,
    extra_fields: [],
    products_of_interest: [],
    convert_to_opportunity: convertToOpportunity,
  }
}

interface UseLeadFormArgs {
  mode: LeadFormMode
}

/**
 * Owns the RHF/Zod wiring of the lead create form and of the in-place detail
 * (`useLeadInlineEdit`, edit mode). Submission lives in `useLeadFormSubmit`,
 * the field cascades in `useLeadCascades`.
 *
 * Edit mode IS the lead detail (spec 0217): the persisted record can change
 * under the form, so `values` re-syncs it while `keepDirtyValues` preserves
 * the row still being edited. Every explicit `reset` that means to DROP an
 * edit passes `keepDirtyValues: false`.
 */
export function useLeadForm({ mode }: UseLeadFormArgs) {
  const { t } = useTranslation()
  const { can } = useAbilities()
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

  const defaultValues = useMemo<LeadFormValues>(
    () => (mode.type === 'edit' ? editDefaults(mode.lead) : createDefaults(canConvertToOpportunity)),
    [mode, canConvertToOpportunity],
  )

  const form = useForm<LeadFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
    values: isEdit ? defaultValues : undefined,
    resetOptions: isEdit ? { keepDirtyValues: true } : undefined,
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

  return form
}

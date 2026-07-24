import { useEffect, useMemo, useRef, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { useInvalidateModuleStats } from '@/features/stats/use-invalidate-module-stats'
import {
  createOpportunity,
  OPPORTUNITIES_DOMAIN,
  opportunityDetailQueryKey,
  updateOpportunity,
} from '@/features/opportunities/api'
import {
  buildCreatePayload,
  buildUpdatePayload,
  managerSlotsFromRefs,
  normalizeDecimal,
  type CreatePayloadFromLead,
} from '@/features/opportunities/opportunity-form-payload'
import {
  buildCreateOpportunitySchema,
  buildUpdateOpportunitySchema,
  type CreateOpportunityFormValues,
} from '@/features/opportunities/opportunity-schema'
import { OPPORTUNITY_STATUSES_FOR_SELECT_RESOURCE } from '@/features/opportunity-statuses/for-select-api'
import { useDefaultSystemStatusId } from '@/features/status-reorder/use-default-system-status'
import type { OpportunityDetail, OpportunityFormMode } from '@/features/opportunities/types'

/** Server-side field names mapped onto the form for 422 handling. `lead_id` is never an RHF field (spec 0040 MT-6 handles it separately). */
const SERVER_ERROR_FIELDS = [
  'registry_id',
  'opportunity_status_id',
  'referent_id',
  'commercial_id',
  'reporter_id',
  'supervisor_id',
  'source_id',
  'operational_site_id',
  'state_id',
  'opportunity_workflow_status_id',
  'product_lines',
  'products_of_interest',
  'rewards',
  'manager_slots',
  'start_date',
  'expected_close_date',
  'estimated_value',
  'success_probability',
] as const

export type OpportunityFormValues = CreateOpportunityFormValues

/**
 * The in-form "Lead" select's CURRENT contribution to the submit (spec 0040
 * amendment A-1). Computed by the caller (`OpportunityFormBody`, from
 * `useOpportunityLeadSelection`'s state) and passed in as a plain value —
 * never a resolver callback: `useOpportunityFormSubmit` is called AFTER
 * `useOpportunityLeadSelection` in the component body (it needs `form`,
 * which `useOpportunityLeadSelection` itself depends on), so by the time this
 * hook runs, the freshest state is already known. This ordering, not a ref,
 * is what keeps `onSubmit` un-stale (writing to a ref during render is
 * disallowed by `react-hooks/refs`; this needs no ref at all).
 */
export interface LeadSubmissionState {
  /** D-2: the picked lead is already linked to another opportunity — the submit must be refused, no POST. */
  blocked: boolean
  fromLead: CreatePayloadFromLead | null
}

/** Never blocked, no active lead — the default `LeadSubmissionState` (edit mode, or before any lead is picked in create). */
export const NO_LEAD_SUBMISSION: LeadSubmissionState = { blocked: false, fromLead: null }

interface UseOpportunityFormArgs {
  mode: OpportunityFormMode
}

/**
 * Owns the RHF/Zod wiring of `OpportunityForm`: schema selection and default
 * values (edit hydrates from the loaded instance; create seeds BR-1's
 * derived fields from `mode.fromLead`, if arriving via the `?lead_id=N`
 * deep-link). Submission lives in the sibling `useOpportunityFormSubmit`,
 * split out so the in-form Lead select (`useOpportunityLeadSelection`, which
 * needs `form.setValue`) can be wired in between the two without a circular
 * dependency.
 */
export function useOpportunityForm({ mode }: UseOpportunityFormArgs) {
  const { t } = useTranslation()
  const isEdit = mode.type === 'edit'

  const schema = useMemo(
    () => (isEdit ? buildUpdateOpportunitySchema(t) : buildCreateOpportunitySchema(t)),
    [isEdit, t],
  )

  const defaultValues = useMemo<OpportunityFormValues>(() => {
    if (mode.type === 'edit') {
      const { opportunity } = mode
      return {
        registry_id: opportunity.registry_id,
        opportunity_status_id: opportunity.opportunity_status_id,
        referent_id: opportunity.referent_id,
        commercial_id: opportunity.commercial_id,
        reporter_id: opportunity.reporter_id,
        supervisor_id: opportunity.supervisor_id,
        source_id: opportunity.source_id,
        operational_site_id: opportunity.operational_site_id ?? null,
        state_id: opportunity.state_id ?? null,
        opportunity_workflow_status_id: opportunity.opportunity_workflow_status_id ?? null,
        product_lines: opportunity.product_lines.map((line) => ({
          business_function_id: line.business_function.id,
          product_category_id: line.product_category.id,
        })),
        products_of_interest: (opportunity.products_of_interest ?? []).map((product) => product.id),
        rewards: (opportunity.rewards ?? []).map((reward) => ({ reward_type_id: reward.reward_type.id })),
        manager_slots: managerSlotsFromRefs(opportunity.managers),
        start_date: opportunity.start_date,
        expected_close_date: opportunity.expected_close_date,
        estimated_value: normalizeDecimal(opportunity.estimated_value),
        // A-6: the slider always holds a value; a null stored probability
        // hydrates as 0 ("0%" ≡ "not set").
        success_probability: opportunity.success_probability ?? 0,
      }
    }
    const empty: OpportunityFormValues = {
      registry_id: null,
      opportunity_status_id: null,
      referent_id: null,
      commercial_id: null,
      reporter_id: null,
      supervisor_id: null,
      source_id: null,
      operational_site_id: null,
      state_id: null,
      opportunity_workflow_status_id: null,
      product_lines: [],
      products_of_interest: [],
      rewards: [],
      manager_slots: [],
      start_date: null,
      expected_close_date: null,
      estimated_value: null,
      success_probability: 0,
    }
    if (!mode.fromLead) {
      return empty
    }
    // Spec 0040 MT-6: BR-1's derived fields (whichever aren't null) seed the
    // create form, whether locked (BR-2) or left free by a null derivation.
    // Amendment rev.3 (AC-102/103): the lead's 0/1 product line seeds
    // `product_lines` instead of a locked business_function_id/
    // product_category_id pair — editable/removable like any other row.
    return {
      ...empty,
      ...mode.fromLead.values,
      // Directive 2026-07-22: the lead's Operator seeds the SECOND "Gestore
      // Account" slot, G.A. 1 coming in empty (editable/removable both), and
      // the Supervisor stays empty.
      manager_slots: mode.fromLead.managerSlots,
      product_lines: mode.fromLead.productLines.map((line) => ({
        business_function_id: line.business_function.id,
        product_category_id: line.product_category.id,
      })),
    }
  }, [mode])

  const form = useForm<OpportunityFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
  })

  // Spec 0043 D-3: preselect the system "Nuova" status on create, once the
  // for-select resolves, but only if the field is still untouched — the
  // user always wins. Guarded to apply at most once: an effect is the
  // correct tool here, since RHF's `defaultValues` are fixed at mount and
  // this value only becomes known asynchronously (mirrors `useLeadForm`).
  const defaultStatus = useDefaultSystemStatusId(OPPORTUNITY_STATUSES_FOR_SELECT_RESOURCE, 'new', !isEdit)
  const appliedDefaultStatus = useRef(false)
  useEffect(() => {
    if (isEdit || appliedDefaultStatus.current || defaultStatus.data == null) {
      return
    }
    if (form.getFieldState('opportunity_status_id').isDirty) {
      appliedDefaultStatus.current = true
      return
    }
    form.setValue('opportunity_status_id', defaultStatus.data)
    appliedDefaultStatus.current = true
  }, [isEdit, defaultStatus.data, form])

  return { form, isEdit }
}

interface UseOpportunityFormSubmitArgs {
  form: ReturnType<typeof useOpportunityForm>['form']
  mode: OpportunityFormMode
  /** Create mode only (spec 0040 A-1): the in-form Lead select's current lock/blocked state. `NO_LEAD_SUBMISSION` in edit mode. */
  leadSubmission: LeadSubmissionState
  /** Called after a successful create/update so the caller can navigate to the detail page. */
  onSuccess: (opportunity: OpportunityDetail) => void
}

/**
 * Owns the create/update submit: server 422 mapping and the D-2 "already
 * linked" refusal (AC-087, defense in depth — the Save button is already
 * disabled for this case, see `OpportunityFormBody`).
 */
export function useOpportunityFormSubmit({ form, mode, leadSubmission, onSuccess }: UseOpportunityFormSubmitArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const invalidateStats = useInvalidateModuleStats(OPPORTUNITIES_DOMAIN)
  const [serverError, setServerError] = useState<string | null>(null)

  const onSubmit = async (values: OpportunityFormValues) => {
    setServerError(null)
    const errorFields: Path<OpportunityFormValues>[] = [...SERVER_ERROR_FIELDS]
    try {
      if (mode.type === 'edit') {
        const saved = await updateOpportunity(mode.opportunity.id, buildUpdatePayload(values, mode.opportunity))
        queryClient.setQueryData(opportunityDetailQueryKey(mode.opportunity.id), saved)
        toast.success(t('opportunities.form.updated'))
        invalidateStats()
        onSuccess(saved)
        return
      }

      if (leadSubmission.blocked) {
        setServerError(t('opportunities.form.existingOpportunityTitle'))
        return
      }

      const created = await createOpportunity(
        buildCreatePayload(values, leadSubmission.fromLead ?? undefined),
      )
      toast.success(t('opportunities.form.created'))
      invalidateStats()
      onSuccess(created)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, errorFields)) {
        setServerError(t('opportunities.form.genericError'))
      }
    }
  }

  return { serverError, onSubmit }
}

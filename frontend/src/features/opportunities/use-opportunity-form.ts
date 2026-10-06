import { useMemo, useState } from 'react'
import axios from 'axios'
import { useForm } from 'react-hook-form'
import type { Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { forSelectKeys } from '@/features/for-select/query-keys'
import { useInvalidateModuleStats } from '@/features/stats/use-invalidate-module-stats'
import { OPPORTUNITIES_FOR_SELECT_RESOURCE } from '@/features/opportunities/for-select-api'
import {
  createOpportunity,
  OPPORTUNITIES_DOMAIN,
  opportunityDetailQueryKey,
  updateOpportunity,
} from '@/features/opportunities/api'
import {
  buildCreatePayload,
  buildUpdatePayload,
  type CreatePayloadFromLead,
} from '@/features/opportunities/opportunity-form-payload'
import {
  buildCreateOpportunitySchema,
  buildUpdateOpportunitySchema,
  type CreateOpportunityFormValues,
} from '@/features/opportunities/opportunity-schema'
import type { OpportunityDetail, OpportunityFormMode } from '@/features/opportunities/types'
import { createDefaults, editDefaults } from '@/features/opportunities/opportunity-form-defaults'

/** Server-side field names mapped onto the form for 422 handling. `lead_id` is never an RHF field (spec 0040 MT-6 handles it separately). */
const SERVER_ERROR_FIELDS = [
  'name',
  'registry_id',
  'referent_id',
  'commercial_id',
  'reporter_id',
  'supervisor_id',
  'source_id',
  'operational_site_id',
  'product_lines',
  'products_of_interest',
  'rewards',
  'manager_slots',
  'start_date',
  'expected_close_date',
  'estimated_value',
  'success_probability',
  'general_notes',
] as const

/**
 * User directive 2026-08-31: an anagrafica carries ONE open opportunity at a
 * time. The refusal travels as a plain 422, with the blocking opportunity's id
 * next to the `registry_id` message (RegistryOpenOpportunityGuard) so the form
 * can link straight to it — the API never emits frontend paths.
 */
const EXISTING_OPPORTUNITY_ERROR_KEY = 'existing_opportunity_id'

export interface BlockingOpportunity {
  id: number
  /** The server's own message, already localized by the API. */
  message: string
  /** The refused form's own products: what the offer opened on the blocking opportunity starts from. */
  productIds: number[]
}

/** The blocking opportunity carried by a 422, or null when the failure is any other one. */
function readBlockingOpportunity(error: unknown, productIds: number[]): BlockingOpportunity | null {
  if (!axios.isAxiosError(error) || error.response?.status !== 422) {
    return null
  }

  const errors = error.response.data?.errors as Record<string, string[]> | undefined
  const id = Number(errors?.[EXISTING_OPPORTUNITY_ERROR_KEY]?.[0])
  const message = errors?.registry_id?.[0]

  return Number.isFinite(id) && id > 0 && message ? { id, message, productIds } : null
}

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
 * Owns the RHF/Zod wiring of the opportunity create form and of the in-place
 * detail (`useOpportunityInlineEdit`, edit mode): schema selection and default
 * values (edit hydrates from the loaded instance; create seeds BR-1's derived
 * fields from `mode.fromLead`, if arriving via the `?lead_id=N` deep-link).
 * Submission lives in the sibling `useOpportunityFormSubmit`, split out so the
 * in-form Lead select (`useOpportunityLeadSelection`, which needs
 * `form.setValue`) can be wired in between the two without a circular
 * dependency.
 *
 * Edit mode IS the opportunity detail (spec 0198): the persisted record can
 * change under the form, so `values` re-syncs it while `keepDirtyValues`
 * preserves the row still being edited. Every explicit `reset` that means to
 * DROP an edit passes `keepDirtyValues: false`.
 */
export function useOpportunityForm({ mode }: UseOpportunityFormArgs) {
  const { t } = useTranslation()
  const isEdit = mode.type === 'edit'

  const schema = useMemo(
    () => (mode.type === 'edit' ? buildUpdateOpportunitySchema(t) : buildCreateOpportunitySchema(t)),
    [mode, t],
  )

  const defaultValues = useMemo<OpportunityFormValues>(
    () => (mode.type === 'edit' ? editDefaults(mode.opportunity) : createDefaults(mode.fromLead)),
    [mode],
  )

  const form = useForm<OpportunityFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
    values: isEdit ? defaultValues : undefined,
    resetOptions: isEdit ? { keepDirtyValues: true } : undefined,
  })

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
export function useOpportunityFormSubmit({
  form,
  mode,
  leadSubmission,
  onSuccess,
}: UseOpportunityFormSubmitArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const invalidateStats = useInvalidateModuleStats(OPPORTUNITIES_DOMAIN)
  const [serverError, setServerError] = useState<string | null>(null)
  const [blockingOpportunity, setBlockingOpportunity] = useState<BlockingOpportunity | null>(null)

  // The opportunity for-select `meta` is what a new Offerta copies its roles
  // and team from. RESET, not invalidate: the Offerta form applies that meta
  // once, as soon as it is available, so a stale entry still served while
  // refetching would win over the saved values.
  const resetOpportunityForSelect = () =>
    void queryClient.resetQueries({ queryKey: forSelectKeys.resource(OPPORTUNITIES_FOR_SELECT_RESOURCE) })

  const onSubmit = async (values: OpportunityFormValues) => {
    setServerError(null)
    setBlockingOpportunity(null)
    const errorFields: Path<OpportunityFormValues>[] = [...SERVER_ERROR_FIELDS]
    try {
      if (mode.type === 'edit') {
        // Step 1 (edit): PATCH what changed and refresh the cached detail.
        const saved = await updateOpportunity(mode.opportunity.id, buildUpdatePayload(values, mode.opportunity))
        queryClient.setQueryData(opportunityDetailQueryKey(mode.opportunity.id), saved)
        // Step 2 (edit): the detail stays mounted on the saved record, clean.
        form.reset(editDefaults(saved), { keepDirtyValues: false })
        toast.success(t('opportunities.form.updated'))
        invalidateStats()
        resetOpportunityForSelect()
        onSuccess(saved)
        return
      }

      if (leadSubmission.blocked) {
        setServerError(t('opportunities.form.existingOpportunityTitle'))
        return
      }

      const created = await createOpportunity(buildCreatePayload(values, leadSubmission.fromLead ?? undefined))
      toast.success(t('opportunities.form.created'))
      invalidateStats()
      resetOpportunityForSelect()
      onSuccess(created)
    } catch (error) {
      // The open-opportunity refusal owns its own alert (message + link to the
      // blocking deal), so it is NOT repeated as an inline field error.
      const blocking = readBlockingOpportunity(error, values.products_of_interest)

      if (blocking !== null) {
        setBlockingOpportunity(blocking)

        return
      }

      if (!applyServerValidationErrors(error, form.setError, errorFields)) {
        setServerError(t('opportunities.form.genericError'))
      }
    }
  }

  // Opening or closing an in-place editor starts from a clean slate.
  const clearSubmitErrors = () => {
    setServerError(null)
    setBlockingOpportunity(null)
  }

  return { serverError, blockingOpportunity, onSubmit, clearSubmitErrors }
}

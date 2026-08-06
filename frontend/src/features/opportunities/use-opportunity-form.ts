import { useMemo, useState } from 'react'
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
  DEFAULT_MANAGER_SLOTS,
  type CreateOpportunityFormValues,
} from '@/features/opportunities/opportunity-schema'
import { emptyProductLineRow, type ProductLineRow } from '@/features/product-lines/types'
import type {
  OpportunityDetail,
  OpportunityFormMode,
  OpportunityProductLine,
} from '@/features/opportunities/types'

/** Server-side field names mapped onto the form for 422 handling. `lead_id` is never an RHF field (spec 0040 MT-6 handles it separately). */
const SERVER_ERROR_FIELDS = [
  'registry_id',
  'referent_id',
  'commercial_id',
  'reporter_id',
  'supervisor_id',
  'source_id',
  'operational_site_id',
  'state_id',
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

export type OpportunityFormValues = CreateOpportunityFormValues

/**
 * The create form's G.A. slots: one empty card per assignable position, G.A. 1
 * through G.A. DEFAULT_MANAGER_SLOTS (user directive 2026-07-29) — a UX
 * default, independent of the actual ceiling (spec 0080 A1's `MAX_MANAGERS`,
 * now 12): "Add slot" (`ManagerSlotsField`) reaches the rest. Empty slots are
 * gap-aware and submit as nothing, so this changes what the user SEES, not
 * what is sent.
 */
function defaultManagerSlots(): (number | null)[] {
  return Array.from({ length: DEFAULT_MANAGER_SLOTS }, () => null)
}

/** Keeps every derived slot in place while topping the list up to the four defaults. */
function padManagerSlots(slots: (number | null)[]): (number | null)[] {
  return slots.length >= DEFAULT_MANAGER_SLOTS
    ? slots
    : [...slots, ...Array.from({ length: DEFAULT_MANAGER_SLOTS - slots.length }, () => null)]
}

/** Maps the hydrated `OpportunityProductLine[]` onto the form's own row shape. */
function toProductLineRows(lines: OpportunityProductLine[]): ProductLineRow[] {
  return lines.map((line) => ({
    business_function_id: line.business_function.id,
    product_category_id: line.product_category.id,
  }))
}

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

  // D-5 grandfathering (spec 0077): the update schema's new row-set rules are
  // gated against the loaded opportunity's own persisted rows, so an
  // unrelated field edit on a non-conformant historic record still saves.
  const schema = useMemo(
    () =>
      mode.type === 'edit'
        ? buildUpdateOpportunitySchema(t, toProductLineRows(mode.opportunity.product_lines))
        : buildCreateOpportunitySchema(t),
    [mode, t],
  )

  const defaultValues = useMemo<OpportunityFormValues>(() => {
    if (mode.type === 'edit') {
      const { opportunity } = mode
      return {
        registry_id: opportunity.registry_id,
        referent_id: opportunity.referent_id,
        commercial_id: opportunity.commercial_id,
        reporter_id: opportunity.reporter_id,
        supervisor_id: opportunity.supervisor_id,
        source_id: opportunity.source_id,
        operational_site_id: opportunity.operational_site_id ?? null,
        state_id: opportunity.state_id ?? null,
        product_lines: toProductLineRows(opportunity.product_lines),
        products_of_interest: (opportunity.products_of_interest ?? []).map((product) => product.id),
        rewards: (opportunity.rewards ?? []).map((reward) => ({ reward_type_id: reward.reward_type.id })),
        manager_slots: managerSlotsFromRefs(opportunity.managers),
        start_date: opportunity.start_date,
        expected_close_date: opportunity.expected_close_date,
        estimated_value: normalizeDecimal(opportunity.estimated_value),
        // A-6: the slider always holds a value; a null stored probability
        // hydrates as 0 ("0%" ≡ "not set").
        success_probability: opportunity.success_probability ?? 0,
        general_notes: opportunity.general_notes ?? null,
      }
    }
    const empty: OpportunityFormValues = {
      registry_id: null,
      referent_id: null,
      commercial_id: null,
      reporter_id: null,
      supervisor_id: null,
      source_id: null,
      operational_site_id: null,
      state_id: null,
      // User directive 2026-07-29: the create form opens on ONE empty
      // product-line row (at least one is mandatory anyway) and on the four
      // G.A. slots, so the ranking is visible without pressing "Add" first.
      product_lines: [emptyProductLineRow()],
      products_of_interest: [],
      rewards: [],
      manager_slots: defaultManagerSlots(),
      start_date: null,
      expected_close_date: null,
      estimated_value: null,
      success_probability: 0,
      general_notes: null,
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
      // the Supervisor stays empty. Padded to the four default slots
      // (directive 2026-07-29) without ever dropping a derived one.
      manager_slots: padManagerSlots(mode.fromLead.managerSlots),
      // A lead with no product line still opens on one empty row, like the
      // standalone create form.
      product_lines:
        mode.fromLead.productLines.length > 0
          ? toProductLineRows(mode.fromLead.productLines)
          : [emptyProductLineRow()],
    }
  }, [mode])

  const form = useForm<OpportunityFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
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

      const created = await createOpportunity(buildCreatePayload(values, leadSubmission.fromLead ?? undefined))
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

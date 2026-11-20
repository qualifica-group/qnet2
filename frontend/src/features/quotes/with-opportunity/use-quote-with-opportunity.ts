import { useCallback, useMemo, useRef, useState } from 'react'
import { useWatch, type FieldValues, type Path, type UseFormReturn } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { notifyFormErrors } from '@/features/auth/form-errors'
import { useFormSubmitError } from '@/features/auth/use-form-submit-error'
import { forSelectKeys } from '@/features/for-select/query-keys'
import { OPPORTUNITIES_FOR_SELECT_RESOURCE } from '@/features/opportunities/for-select-api'
import { OPPORTUNITIES_DOMAIN } from '@/features/opportunities/api'
import { useOpportunityForm } from '@/features/opportunities/use-opportunity-form'
import { QUOTES_DOMAIN } from '@/features/quotes/api'
import { useInvalidateModuleStats } from '@/features/stats/use-invalidate-module-stats'
import { apiErrorMessage } from '@/lib/forbidden-message'
import { useQuoteCreateDefaults } from '@/features/quotes/use-quote-create-defaults'
import { useQuoteForm } from '@/features/quotes/use-quote-form'
import {
  checkOpenOpportunity,
  createQuoteWithOpportunity,
} from '@/features/quotes/with-opportunity/with-opportunity-api'
import {
  readWithOpportunityRefusal,
  type BlockFieldError,
} from '@/features/quotes/with-opportunity/with-opportunity-errors'
import {
  alignInheritedFields,
  applyInheritedPatch,
  inheritedFromOpportunity,
  type InheritedFields,
} from '@/features/quotes/with-opportunity/with-opportunity-inheritance'
import { buildWithOpportunityPayload } from '@/features/quotes/with-opportunity/with-opportunity-payload'
import type { OpportunityCreateFormMode } from '@/features/opportunities/types'
import type { QuoteCreateFormMode, QuoteDetail } from '@/features/quotes/types'
import type { QuoteFormValues } from '@/features/quotes/quote-schema'

export type WithOpportunityStep = 'opportunity' | 'quote'

/** Both forms open blank: nothing is preset in this procedure (no lead, no existing opportunity). */
const OPPORTUNITY_MODE: OpportunityCreateFormMode = { type: 'create' }
const QUOTE_MODE: QuoteCreateFormMode = { type: 'create' }
const NO_CATEGORY_IDS: number[] = []

/** The open opportunity that stops step 1, with the sentence the alert shows. */
export interface BlockingOpportunityNotice {
  id: number
  message: string
}

/** Puts each refused field of a block back on its form; a field the form does not own is left to the toast. */
function applyFieldErrors<T extends FieldValues>(form: UseFormReturn<T>, errors: BlockFieldError[]): void {
  const known = new Set(Object.keys(form.getValues()))
  for (const { field, message } of errors) {
    if (known.has(field)) {
      form.setError(field as Path<T>, { message })
    }
  }
}

interface UseQuoteWithOpportunityArgs {
  /** The sequential code suggestion of the offer (`next-code`), prefilled and omitted from the payload while untouched. */
  initialCode: string
  /** Called with the created offer so the host opens its detail. */
  onSuccess: (quote: QuoteDetail) => void
}

/**
 * Orchestrates the new opportunity + offer procedure (spec 0396): two forms
 * that live side by side so "Indietro" keeps every value, the step-1 gate
 * ("Avanti"), the carry-over of step 1 onto the offer (D-5) and the single
 * save with its per-block 422 mapping (AC-017).
 */
export function useQuoteWithOpportunity({ initialCode, onSuccess }: UseQuoteWithOpportunityArgs) {
  const { t, i18n } = useTranslation()
  const queryClient = useQueryClient()
  const invalidateOpportunityStats = useInvalidateModuleStats(OPPORTUNITIES_DOMAIN)
  const invalidateQuoteStats = useInvalidateModuleStats(QUOTES_DOMAIN)
  const [step, setStep] = useState<WithOpportunityStep>('opportunity')
  const [blocking, setBlocking] = useState<BlockingOpportunityNotice | null>(null)
  const [serverError, setServerError] = useFormSubmitError()

  const { form: opportunityForm } = useOpportunityForm({ mode: OPPORTUNITY_MODE })

  // The live Attributes of the offer follow the (still unsaved) opportunity's product lines (D-7).
  const productLines = useWatch({ control: opportunityForm.control, name: 'product_lines' })
  const categoryKey = productLines
    .map((line) => line.product_category_id)
    .filter((id): id is number => id !== null)
    .join(',')
  const withNewOpportunity = useMemo(
    () => ({ categoryIds: categoryKey === '' ? NO_CATEGORY_IDS : categoryKey.split(',').map(Number) }),
    [categoryKey],
  )

  const quoteForm = useQuoteForm({ mode: QUOTE_MODE, onSuccess: onSuccessNoop, initialCode, withNewOpportunity })
  const quoteDefaults = useQuoteCreateDefaults(QUOTE_MODE, quoteForm)
  const lastCopiedRef = useRef<InheritedFields | null>(null)

  const openCheck = useMutation({ mutationFn: checkOpenOpportunity })
  const save = useMutation({ mutationFn: createQuoteWithOpportunity })

  const carryOverToQuote = useCallback(() => {
    const next = inheritedFromOpportunity(opportunityForm.getValues())
    const patch = alignInheritedFields(quoteForm.form.getValues(), lastCopiedRef.current, next)
    applyInheritedPatch(quoteForm.form.setValue, patch)
    lastCopiedRef.current = next
  }, [opportunityForm, quoteForm.form])

  const goNext = useCallback(async () => {
    setServerError(null)
    setBlocking(null)

    // Step 1: the opportunity's own schema, shown on its fields.
    if (!(await opportunityForm.trigger())) {
      notifyFormErrors([t('quotes.withOpportunity.fixErrors')])
      return
    }

    // Step 2: the anti-duplicate pre-check; a blocking opportunity keeps the operator on step 1.
    const values = opportunityForm.getValues()
    try {
      const found = await openCheck.mutateAsync({
        registryId: values.registry_id as number,
        categoryIds: withNewOpportunity.categoryIds,
      })
      if (found !== null) {
        setBlocking({ id: found.id, message: t('quotes.withOpportunity.existingOpen', { name: found.name }) })
        return
      }
    } catch (error) {
      setServerError(apiErrorMessage(error, t('quotes.withOpportunity.genericError'), i18n))
      return
    }

    // Step 3: the offer starts from what step 1 holds.
    carryOverToQuote()
    setStep('quote')
  }, [opportunityForm, openCheck, withNewOpportunity, carryOverToQuote, setServerError, t, i18n])

  const goBack = useCallback(() => {
    setServerError(null)
    setStep('opportunity')
  }, [setServerError])

  const reportRefusal = useCallback(
    (error: unknown) => {
      const refusal = readWithOpportunityRefusal(error)
      if (refusal === null) {
        setServerError(apiErrorMessage(error, t('quotes.withOpportunity.genericError'), i18n))
        return
      }

      applyFieldErrors(opportunityForm, refusal.opportunity)
      applyFieldErrors(quoteForm.form, refusal.quote)
      notifyFormErrors(refusal.messages)

      if (refusal.duplicate !== null) {
        setBlocking({ id: refusal.duplicate.opportunityId, message: refusal.duplicate.message })
      }
      // The opportunity block is validated first server-side: its refusals win the step.
      setStep(refusal.opportunity.length > 0 || refusal.duplicate !== null ? 'opportunity' : 'quote')
    },
    [opportunityForm, quoteForm.form, setServerError, t, i18n],
  )

  const saveBoth = async (quoteValues: QuoteFormValues) => {
    setServerError(null)
    setBlocking(null)
    try {
      const payload = buildWithOpportunityPayload(opportunityForm.getValues(), quoteValues, initialCode)
      const created = await save.mutateAsync(payload)
      toast.success(t('quotes.withOpportunity.created'))
      invalidateOpportunityStats()
      invalidateQuoteStats()
      // New quotes copy roles/team from the opportunity for-select `meta`: never serve a stale entry.
      void queryClient.resetQueries({ queryKey: forSelectKeys.resource(OPPORTUNITIES_FOR_SELECT_RESOURCE) })
      onSuccess(created)
    } catch (error) {
      reportRefusal(error)
    }
  }

  return {
    step,
    goNext,
    goBack,
    opportunityForm,
    quoteForm,
    quoteDefaults,
    blocking,
    isChecking: openCheck.isPending,
    serverError,
    saveBoth,
  }
}

/** The offer form's own submit is never used here: the procedure saves both blocks at once. */
function onSuccessNoop(): void {}

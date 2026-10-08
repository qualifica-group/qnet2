import { useFormState } from 'react-hook-form'
import { useAllProductTypologies } from '@/features/product-typologies/for-select-api'
import { QuoteNewAttributesFields } from '@/features/quotes/quote-attributes-section'
import { QuoteCostsTab } from '@/features/quotes/quote-costs-tab'
import { QuoteOfferTab } from '@/features/quotes/quote-offer-tab'
import { QuoteLiveSummary } from '@/features/quotes/quote-summary'
import type { QuoteLineRowErrors } from '@/features/quotes/quote-line-row'
import type { QuoteFormState } from '@/features/quotes/use-quote-form'
import type { QuoteLine } from '@/features/quotes/types'

/*
 * The two line-grid editors and the live summary, as both the detail's
 * in-place "Righe" rows and the create form's open grids mount them (spec
 * 0197): the same grids, caches and per-row errors in either place.
 */

/** The per-row messages of one tab, as `QuoteLinesField` places them. */
function asRowErrors(errors: unknown): (QuoteLineRowErrors | undefined)[] | undefined {
  return Array.isArray(errors) ? (errors as (QuoteLineRowErrors | undefined)[]) : undefined
}

interface QuoteOfferLinesEditorProps {
  quoteForm: QuoteFormState
  /** The persisted revenue rows, hydrating the pickers' labels; `[]` on create. */
  knownLines: QuoteLine[]
  quoteId?: number
  /**
   * Detail only: the Attribute codes the persisted lines already cover. A
   * product change bringing new ones shows them under the grid, filled in the
   * same save (spec 0197 D-4).
   */
  persistedAttributeCodes?: ReadonlySet<string>
}

/** "Righe offerta": the revenue grid (spec 0065 AC-072), scoped to the Opportunita's categories. */
export function QuoteOfferLinesEditor({
  quoteForm,
  knownLines,
  quoteId,
  persistedAttributeCodes,
}: QuoteOfferLinesEditorProps) {
  const { form, attributeContext } = quoteForm
  const { errors } = useFormState({ control: form.control, name: 'offer_lines' })

  return (
    <div className="flex min-w-0 flex-col gap-3">
      <QuoteOfferTab
        control={form.control}
        errors={asRowErrors(errors.offer_lines)}
        knownLines={knownLines}
        vatRatePercentFor={quoteForm.vatRatePercentFor}
        rememberVatRatePercent={quoteForm.rememberVatRatePercent}
        rememberProductTypology={quoteForm.rememberProductTypology}
        rememberProductName={quoteForm.rememberProductName}
        quoteId={quoteId}
      />
      {persistedAttributeCodes ? (
        <QuoteNewAttributesFields
          attributes={attributeContext.applicable_attributes}
          persistedCodes={persistedAttributeCodes}
          control={form.control}
        />
      ) : null}
    </div>
  )
}

interface QuoteCostLinesEditorProps {
  quoteForm: QuoteFormState
  /** The persisted cost rows, hydrating the pickers' labels; `[]` on create. */
  knownLines: QuoteLine[]
}

/** "Righe costo" (spec 0065 AC-073): the unscoped cost grid, each row attributable to an offer row (spec 0144). */
export function QuoteCostLinesEditor({ quoteForm, knownLines }: QuoteCostLinesEditorProps) {
  const { form } = quoteForm
  const { errors } = useFormState({ control: form.control, name: 'cost_lines' })

  return (
    <QuoteCostsTab
      control={form.control}
      errors={asRowErrors(errors.cost_lines)}
      knownLines={knownLines}
      vatRatePercentFor={quoteForm.vatRatePercentFor}
      rememberVatRatePercent={quoteForm.rememberVatRatePercent}
      productNameFor={quoteForm.productNameFor}
    />
  )
}

/**
 * The live summary of the rows being typed (AC-071), with the configured
 * typology catalogue (spec 0099 D-7) fetched here — cached reference data,
 * one request — so `QuoteLiveSummary` stays a pure recompute. Mounted only
 * while the rows are edited: the persisted summary is authoritative otherwise.
 */
export function QuoteLinesLiveSummary({ quoteForm }: { quoteForm: QuoteFormState }) {
  const typologyOptions = useAllProductTypologies()

  return (
    <QuoteLiveSummary
      control={quoteForm.form.control}
      vatRatePercentFor={quoteForm.vatRatePercentFor}
      productTypologyIdFor={quoteForm.productTypologyIdFor}
      typologyOptions={typologyOptions}
      productNameFor={quoteForm.productNameFor}
    />
  )
}

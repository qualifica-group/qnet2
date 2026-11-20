import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import type { Control, Path } from 'react-hook-form'
import { EyeOff, TrendingUp } from 'lucide-react'
import { FormSection } from '@/components/form-section'
import { MetaField } from '@/features/authorization/MetaField'
import { MetaFieldRowContext } from '@/features/authorization/meta-field-row-context'
import type { ProductLineRow } from '@/features/product-lines/types'
import type { QuoteLineRowErrors } from '@/features/quotes/quote-line-types'
import { MAX_LINES_PER_TAB, type QuoteLineFormValues } from '@/features/quotes/quote-schema'
import type { QuoteLine } from '@/features/quotes/types'
import { QuoteLineEditor } from '@/features/quotes/line-editor/quote-line-editor'
import type { QuoteCatalogScope } from '@/features/quotes/line-editor/types'
import { RequestSimplifiedOfferPicker } from '@/features/request-management/request-simplified-offer-picker'
import { useOfferLinesAutofill } from '@/features/request-management/use-offer-lines-autofill'
import { useRequestOfferScope } from '@/features/request-management/use-request-offer-scope'

/**
 * The two controls this section drives. Any form carrying them mounts it
 * as-is — the work panel and the create form both do (user directive
 * 2026-08-07: the same component in both places), which is why this is a
 * shape and not one form's own values type.
 */
export interface RequestOfferLinesFormShape {
  product_lines: ProductLineRow[]
  offer_lines: QuoteLineFormValues[]
}

interface RequestOfferLinesFieldProps<TFieldValues extends RequestOfferLinesFormShape> {
  control: Control<TFieldValues>
  /** The persisted rows, for label hydration (product/aliquota). `[]` on create. */
  knownLines: QuoteLine[]
  errors?: (QuoteLineRowErrors | undefined)[]
  vatRatePercentFor: (vatRateId: number) => number | null
  rememberVatRatePercent: (vatRateId: number, percent: number) => void
  /** Height cap of the catalogue list (spec 0268): the grid dialog grants it more room than a page section. */
  catalogListClassName?: string
  /** Outside Gestione Richieste (the contract detail): the spec 0414 "hide the offer" rule does not apply. */
  ignoreHiddenRule?: boolean
}

/**
 * The row editor itself, without the card around it (user directive
 * 2026-09-07): the grid's quick-edit dialog IS the surface here, so wrapping
 * the field in a second titled card inside it would repeat the dialog's own
 * heading. The work panel and the create form keep mounting
 * `RequestOfferLinesSection` below, which is this field plus that card.
 *
 * The editor is the "catalogo + righe" `QuoteLineEditor` (spec 0268): the
 * same `offer_lines` rows the Offerte form writes (`QuoteLineFormValues`, the
 * same server rules), composed from a scoped product catalogue instead of a
 * per-row picker. The Offerte form mounts the very same editor (spec 0269).
 *
 * What this module adds or leaves out compared with `QuoteOfferTab`:
 *  - no provvigioni: the endpoint prohibits the block, so whatever the
 *    Offerte form configured is preserved server-side;
 *  - no "sblocca" affordance: the catalogue is scoped to the categories THIS
 *    form carries (watched, since they are edited right above), and the
 *    server widens the classification itself when a product falls outside
 *    them (OpportunityProductLineCoverage, D-7);
 *  - the single-row cap of a `single`-managed category (spec 0077) turns the
 *    catalogue into a single pick; the server enforces the cap either way;
 *  - `simplified` (spec 0114, D-3) swaps the editor for a single product
 *    select (spec 0414 D-4, `RequestSimplifiedOfferPicker`): the server
 *    congeals quantity/unit price/VAT rate from the picked product regardless;
 *  - a branch hiding the offer (spec 0414) leaves the catalogue; with every
 *    covered branch hidden the field shows a notice instead of the editor.
 */
export function RequestOfferLinesField<TFieldValues extends RequestOfferLinesFormShape>({
  control,
  knownLines,
  errors,
  vatRatePercentFor,
  rememberVatRatePercent,
  catalogListClassName,
  ignoreHiddenRule = false,
}: RequestOfferLinesFieldProps<TFieldValues>) {
  const { t } = useTranslation()
  // The shape guarantees both keys on every accepted form, but TS cannot
  // narrow a literal to `Path<TFieldValues>` through the generic (same idiom
  // as `QuoteWorkflowStatusField`).
  const linesField = 'offer_lines' as Path<TFieldValues>

  // Spec 0077 (one row under a `single` category, the strictest covered one
  // governs), spec 0114 (simplified as soon as ONE covered category grants
  // it) and spec 0414 (hidden branches leave the offer), all off the cached
  // tree the product-line pickers read.
  const { categoryTree, categoryIds, offerHidden, singleCategoryMode, simplified } = useRequestOfferScope(
    control,
    ignoreHiddenRule,
  )

  // A category exposing exactly ONE product fills its row by itself (user
  // directive 2026-09-09): picking the classification is the whole gesture,
  // there is nothing left for the operator to choose in that picker. Capped
  // by the same `single`-mode ceiling "Aggiungi riga" mirrors above.
  useOfferLinesAutofill({
    control,
    name: linesField,
    categoryIds,
    maxRows: singleCategoryMode ? 1 : MAX_LINES_PER_TAB,
    rememberVatRatePercent,
    simplified,
  })

  const catalogScope = useMemo<QuoteCatalogScope>(() => ({ kind: 'categories', categoryIds }), [categoryIds])

  if (offerHidden) {
    return (
      <p className="flex items-center gap-2 rounded-lg border border-dashed bg-card px-3 py-4 text-xs text-muted-foreground">
        <EyeOff aria-hidden="true" className="size-3.5 shrink-0" />
        {t('requestManagement.offerLines.hiddenByCategory')}
      </p>
    )
  }

  const maxRows = singleCategoryMode ? 1 : MAX_LINES_PER_TAB

  return (
    // The editor's rows pane already titles the collection ("Righe
    // dell'offerta"): the field label stays for assistive tech only.
    <MetaFieldRowContext.Provider value={linesField}>
      <MetaField
        control={control}
        name={linesField}
        metaKey="offer_lines"
        label={t('quotes.form.offerTab.fieldLabel')}
      >
        {({ field, disabled }) => {
          // Same unavoidable cast as the field path above: the generic keeps
          // the shape's key but not its value type.
          const rows = field.value as QuoteLineFormValues[]

          const lockedHint = t('requestManagement.offerLines.hintNoCategory')

          return simplified ? (
            <RequestSimplifiedOfferPicker
              value={rows}
              onChange={field.onChange}
              disabled={disabled}
              categoryIds={categoryIds}
              lockedHint={lockedHint}
              maxRows={maxRows}
              singleCategoryMode={singleCategoryMode}
              knownLines={knownLines}
              errors={errors}
              vatRatePercentFor={vatRatePercentFor}
              rememberVatRatePercent={rememberVatRatePercent}
            />
          ) : (
            <QuoteLineEditor
              value={rows}
              onChange={field.onChange}
              variant="revenue"
              disabled={disabled}
              catalogScope={catalogScope}
              lockedHint={lockedHint}
              categoryTree={categoryTree}
              maxRows={maxRows}
              singleCategoryMode={singleCategoryMode}
              knownLines={knownLines}
              errors={errors}
              vatRatePercentFor={vatRatePercentFor}
              rememberVatRatePercent={rememberVatRatePercent}
              catalogListClassName={catalogListClassName}
            />
          )
        }}
      </MetaField>
    </MetaFieldRowContext.Provider>
  )
}

/** "Linee dell'offerta" as a titled card: the form surfaces (work panel, create form) mount this. */
export function RequestOfferLinesSection<TFieldValues extends RequestOfferLinesFormShape>(
  props: RequestOfferLinesFieldProps<TFieldValues>,
) {
  const { t } = useTranslation()
  // Spec 0414: the card stays closed until a product line is picked (user
  // directive 2026-11-18), and a classification whose every branch hides the
  // offer drops it altogether.
  const { noCategory, offerHidden } = useRequestOfferScope(props.control, props.ignoreHiddenRule)

  if (noCategory || offerHidden) {
    return null
  }

  return (
    <FormSection
      icon={TrendingUp}
      title={t('quotes.form.sections.offer.title')}
      description={t('quotes.form.sections.offer.description')}
    >
      <RequestOfferLinesField {...props} />
    </FormSection>
  )
}

import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { useWatch, type Control, type Path } from 'react-hook-form'
import { TrendingUp } from 'lucide-react'
import { FormSection } from '@/components/form-section'
import { MetaField } from '@/features/authorization/MetaField'
import { MetaFieldRowContext } from '@/features/authorization/meta-field-row-context'
import { resolveManagementMode, resolveSimplifiedOfferLine } from '@/features/product-lines/category-tree-scope'
import type { ProductLineRow } from '@/features/product-lines/types'
import { useProductCategoryTree } from '@/features/product-categories/use-product-category-tree'
import type { ProductCategoryTreeNode } from '@/features/product-categories/types'
import { knownVatRatesFrom } from '@/features/quotes/quote-lines-field'
import type { QuoteLineRowErrors } from '@/features/quotes/quote-line-row'
import { MAX_LINES_PER_TAB, type QuoteLineFormValues } from '@/features/quotes/quote-schema'
import type { QuoteLine } from '@/features/quotes/types'
import { OfferLinesEditor } from '@/features/request-management/offer-lines-editor/offer-lines-editor'
import { useOfferLinesAutofill } from '@/features/request-management/use-offer-lines-autofill'

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
}

/** Stable empty tree while the shared query is still loading: no fresh reference per render. */
const EMPTY_TREE: ProductCategoryTreeNode[] = []

/**
 * The row editor itself, without the card around it (user directive
 * 2026-09-07): the grid's quick-edit dialog IS the surface here, so wrapping
 * the field in a second titled card inside it would repeat the dialog's own
 * heading. The work panel and the create form keep mounting
 * `RequestOfferLinesSection` below, which is this field plus that card.
 *
 * The editor is the "catalogo + righe" `OfferLinesEditor` (spec 0268): the
 * same `offer_lines` rows the Offerte form writes (`QuoteLineFormValues`, the
 * same server rules), composed from a scoped product catalogue instead of a
 * per-row picker. The Offerte module keeps its own `QuoteLinesField`.
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
 *  - `simplified` (spec 0114, D-3) drops quantity/unit price/VAT rate from
 *    the rows and congeals them from the picked product, client-side preview
 *    of what the server writes regardless.
 */
export function RequestOfferLinesField<TFieldValues extends RequestOfferLinesFormShape>({
  control,
  knownLines,
  errors,
  vatRatePercentFor,
  rememberVatRatePercent,
  catalogListClassName,
}: RequestOfferLinesFieldProps<TFieldValues>) {
  const { t } = useTranslation()
  // The shape guarantees both keys on every accepted form, but TS cannot
  // narrow a literal to `Path<TFieldValues>` through the generic (same idiom
  // as `QuoteWorkflowStatusField`).
  const linesField = 'offer_lines' as Path<TFieldValues>
  const productLines = useWatch({ control, name: 'product_lines' as Path<TFieldValues> }) as ProductLineRow[]

  const categoryIds = useMemo(
    () => [
      ...new Set(
        (productLines ?? [])
          .map((line) => line.product_category_id)
          .filter((id): id is number => id !== null),
      ),
    ],
    [productLines],
  )

  // Spec 0077: an opportunity managed on a `single` product category carries
  // one offer row. Resolved over ALL the covered categories — rev.2 revoked
  // INV-1, so they no longer necessarily share a root and the strictest one
  // governs — against the same cached tree the product-line pickers read,
  // which is where the mode lives.
  const categoryTree = useProductCategoryTree().data ?? EMPTY_TREE
  const singleCategoryMode = resolveManagementMode(categoryTree, categoryIds) === 'single'

  // Spec 0114: as soon as ONE covered category carries the simplified-offer-
  // line rule, the row editor drops quantity/unit price/VAT rate and the
  // server congeals them from the picked product — the same tree the
  // management mode reads, no extra request.
  const simplified = resolveSimplifiedOfferLine(categoryTree, categoryIds)

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

  const knownVatRates = useMemo(() => knownVatRatesFrom(knownLines), [knownLines])

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

          return (
            <div className="flex flex-col gap-2">
              <OfferLinesEditor
                value={rows}
                onChange={field.onChange}
                disabled={disabled}
                categoryIds={categoryIds}
                categoryTree={categoryTree}
                maxRows={singleCategoryMode ? 1 : MAX_LINES_PER_TAB}
                singleCategoryMode={singleCategoryMode}
                simplified={simplified}
                knownLines={knownLines}
                knownVatRates={knownVatRates}
                errors={errors}
                vatRatePercentFor={vatRatePercentFor}
                rememberVatRatePercent={rememberVatRatePercent}
                catalogListClassName={catalogListClassName}
              />

              {/* The catalogue carries the scope/single-row hints itself (spec
                  0268); only the simplified rule explains the rows' missing inputs. */}
              {simplified ? (
                <p className="text-xs text-muted-foreground">{t('requestManagement.offerLines.hintSimplified')}</p>
              ) : null}
            </div>
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

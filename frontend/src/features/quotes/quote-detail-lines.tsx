import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { TrendingDown, TrendingUp } from 'lucide-react'
import { Tabs, TabsContent, TabsTrigger } from '@/components/ui/tabs'
import { FormTabStrip, FORM_TAB_TRIGGER_CLASS } from '@/components/form-tab-strip'
import { RecordInlineField } from '@/components/record-form/record-inline-field'
import { QuoteLinesReadOnlyList } from '@/features/quotes/quote-lines-read-only'
import {
  QuoteCostLinesEditor,
  QuoteLinesLiveSummary,
  QuoteOfferLinesEditor,
} from '@/features/quotes/quote-lines-editors'
import {
  computeProductMargins,
  costLinesFromPersistedCostLines,
  productLinesFromPersistedOfferLines,
} from '@/features/quotes/quote-product-margins-calc'
import {
  QuoteSummary,
  totalsFromPersistedSummary,
  typologyBucketsFromPersistedSummary,
} from '@/features/quotes/quote-summary'
import { round2 } from '@/features/quotes/quote-totals'
import type { QuoteDetailEditor } from '@/features/quotes/use-quote-inline-edit'
import type { QuoteDetailWithPermissions } from '@/features/quotes/types'

const OFFER_TAB = 'offer'
const COSTS_TAB = 'costs'

/** The two line rows of the band: while either is open, the summary follows the rows being typed. */
const LINE_FIELDS = new Set(['offer_lines', 'cost_lines'])

/** The persisted summary (D-9) and margins block: no client recomputation for a saved quote. */
function PersistedLinesSummary({ quote }: { quote: QuoteDetailWithPermissions }) {
  const showCommissions = quote.permissions.fields.commissions?.visible ?? true

  // Spec 0144 AC-015: the detail's own "Margine per prodotto" block reads the
  // CONGEALED `net_amount`s (D-9), never recomputed client-side. Spec 0145
  // (D-9): the "Commissioni" column sums each row's persisted
  // `calculated_amount`; the block itself is hidden without the SAME
  // `showCommissions` field permission.
  const productMargins = useMemo(
    () =>
      computeProductMargins(
        productLinesFromPersistedOfferLines(quote.offer_lines),
        costLinesFromPersistedCostLines(quote.cost_lines),
      ),
    [quote.offer_lines, quote.cost_lines],
  )

  return (
    <QuoteSummary
      totals={totalsFromPersistedSummary(quote.summary)}
      commissionTotals={{
        commercial: Number(quote.summary.commissions?.commercial ?? 0),
        reporter: Number(quote.summary.commissions?.reporter ?? 0),
        supervisor: Number(quote.summary.commissions?.supervisor ?? 0),
        supplier: Number(quote.summary.commissions?.supplier ?? 0),
      }}
      typologyBuckets={typologyBucketsFromPersistedSummary(quote.summary)}
      productMargins={productMargins}
      showProductMargins={showCommissions}
    />
  )
}

interface QuoteDetailLinesProps {
  quote: QuoteDetailWithPermissions
  editor: QuoteDetailEditor
}

/**
 * The record card's closing band (user directive: no card and no heading of
 * its own; strip, rows and summary share ONE band with no rule between them):
 * the two line sets behind a tab strip, then the summary, outside any
 * `TabsContent` so it stays visible on both tabs.
 *
 * Each set is ONE in-place row across the band (spec 0197 D-4): its pencil
 * swaps the read-only list for the very grid the create form uses, Save
 * PATCHes that set alone (a full replace, D-8). While a grid is open the
 * summary is the live one of the rows being typed; the revenue grid also
 * carries the Attributes its new products bring in.
 */
export function QuoteDetailLines({ quote, editor }: QuoteDetailLinesProps) {
  const { t } = useTranslation()
  const [activeTab, setActiveTab] = useState(OFFER_TAB)
  const { inline } = editor
  const showCommissions = quote.permissions.fields.commissions?.visible ?? true
  const editingLines = inline.editingField !== null && LINE_FIELDS.has(inline.editingField)
  const persistedAttributeCodes = useMemo(
    () => new Set(quote.applicable_attributes.map((attribute) => attribute.code)),
    [quote.applicable_attributes],
  )

  // Spec 0144 AC-016: the cost list's own "Associated product" cell resolves
  // an `offer_line_id` through this SAME offer's persisted revenue rows.
  const offerLineLabelsById = useMemo(() => {
    const labels: Record<number, string> = {}
    quote.offer_lines.forEach((line, index) => {
      labels[line.id] = t('quotes.form.costsTab.associatedProductOption', { product: line.product.name, n: index + 1 })
    })
    return labels
  }, [quote.offer_lines, t])

  // Spec 0145 (D-1), revenue variant only: `offer_line_id -> imputed cost
  // net`, feeding each row's own commissions dialog base (D-1/D-2).
  const allocatedCostNetByOfferId = useMemo(() => {
    const totals: Record<number, number> = {}
    for (const cost of quote.cost_lines) {
      if (cost.offer_line_id != null) {
        totals[cost.offer_line_id] = round2((totals[cost.offer_line_id] ?? 0) + Number(cost.net_amount))
      }
    }
    return totals
  }, [quote.cost_lines])

  return (
    <Tabs value={activeTab} onValueChange={setActiveTab} className="gap-0">
      <div className="flex min-w-0 flex-col gap-3 border-t p-4">
        <FormTabStrip value={activeTab} onValueChange={setActiveTab}>
          <TabsTrigger value={OFFER_TAB} className={FORM_TAB_TRIGGER_CLASS}>
            <TrendingUp aria-hidden="true" />
            {t('quotes.form.tabs.offer')}
          </TabsTrigger>
          <TabsTrigger value={COSTS_TAB} className={FORM_TAB_TRIGGER_CLASS}>
            <TrendingDown aria-hidden="true" />
            {t('quotes.form.tabs.costs')}
          </TabsTrigger>
        </FormTabStrip>
        <TabsContent value={OFFER_TAB}>
          <RecordInlineField
            field="offer_lines"
            label={t('quotes.form.offerTab.fieldLabel')}
            layout="block"
            inline={inline}
            editor={
              <QuoteOfferLinesEditor
                quoteForm={editor}
                knownLines={quote.offer_lines}
                quoteId={quote.id}
                persistedAttributeCodes={persistedAttributeCodes}
              />
            }
          >
            <QuoteLinesReadOnlyList
              lines={quote.offer_lines}
              showCommissions={showCommissions}
              allocatedCostNetById={allocatedCostNetByOfferId}
            />
          </RecordInlineField>
        </TabsContent>
        <TabsContent value={COSTS_TAB}>
          <RecordInlineField
            field="cost_lines"
            label={t('quotes.form.costsTab.fieldLabel')}
            layout="block"
            inline={inline}
            editor={<QuoteCostLinesEditor quoteForm={editor} knownLines={quote.cost_lines} />}
          >
            <QuoteLinesReadOnlyList lines={quote.cost_lines} variant="cost" offerLineLabelsById={offerLineLabelsById} />
          </RecordInlineField>
        </TabsContent>
        {editingLines ? <QuoteLinesLiveSummary quoteForm={editor} /> : <PersistedLinesSummary quote={quote} />}
      </div>
    </Tabs>
  )
}

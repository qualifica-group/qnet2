import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useFormState } from 'react-hook-form'
import { TrendingDown, TrendingUp } from 'lucide-react'
import { Tabs, TabsContent, TabsTrigger } from '@/components/ui/tabs'
import { FormTabStrip, FORM_TAB_TRIGGER_CLASS, TabErrorDot } from '@/components/form-tab-strip'
import {
  QuoteCostLinesEditor,
  QuoteLinesLiveSummary,
  QuoteOfferLinesEditor,
} from '@/features/quotes/quote-lines-editors'
import type { QuoteFormState } from '@/features/quotes/use-quote-form'
import type { QuoteLine } from '@/features/quotes/types'

const OFFER_TAB = 'offer'
const COSTS_TAB = 'costs'

/** A draft has no persisted rows to hydrate the pickers' labels from. */
const NO_LINES: QuoteLine[] = []

/**
 * The create form's closing band, where the detail keeps its rows (spec 0197
 * D-6): the Offerta/Costi strip, then the two grids OPEN — they are tabular
 * editors, not single fields, and the Offerta one already opens on an empty
 * row (directive 2026-09-01) — and the live summary below, visible on both
 * tabs (AC-070/071). A tab whose rows hold an error carries a dot.
 */
export function QuoteCreateLines({ quoteForm }: { quoteForm: QuoteFormState }) {
  const { t } = useTranslation()
  const [activeTab, setActiveTab] = useState(OFFER_TAB)
  const { errors } = useFormState({ control: quoteForm.form.control, name: ['offer_lines', 'cost_lines'] })
  const tabHasErrorsLabel = t('quotes.form.tabs.tabHasErrors')

  return (
    <Tabs value={activeTab} onValueChange={setActiveTab} className="gap-0">
      <div className="flex min-w-0 flex-col gap-3 border-t p-4">
        <FormTabStrip value={activeTab} onValueChange={setActiveTab}>
          <TabsTrigger value={OFFER_TAB} className={FORM_TAB_TRIGGER_CLASS}>
            <TrendingUp aria-hidden="true" />
            {t('quotes.form.tabs.offer')}
            {errors.offer_lines ? <TabErrorDot label={tabHasErrorsLabel} /> : null}
          </TabsTrigger>
          <TabsTrigger value={COSTS_TAB} className={FORM_TAB_TRIGGER_CLASS}>
            <TrendingDown aria-hidden="true" />
            {t('quotes.form.tabs.costs')}
            {errors.cost_lines ? <TabErrorDot label={tabHasErrorsLabel} /> : null}
          </TabsTrigger>
        </FormTabStrip>
        <TabsContent value={OFFER_TAB}>
          <QuoteOfferLinesEditor quoteForm={quoteForm} knownLines={NO_LINES} />
        </TabsContent>
        <TabsContent value={COSTS_TAB}>
          <QuoteCostLinesEditor quoteForm={quoteForm} knownLines={NO_LINES} />
        </TabsContent>
        <QuoteLinesLiveSummary quoteForm={quoteForm} />
      </div>
    </Tabs>
  )
}

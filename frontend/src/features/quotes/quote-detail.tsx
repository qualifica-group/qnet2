import { useTranslation } from 'react-i18next'
import { FileText, Handshake, NotebookText, TrendingDown, TrendingUp } from 'lucide-react'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { FORM_TAB_LIST_CLASS, FORM_TAB_TRIGGER_CLASS } from '@/components/form-tab-strip'
import {
  DetailEmpty,
  DetailField,
  DetailGrid,
  DetailHero,
  DetailMeta,
  DetailMonogram,
  DetailPanel,
  DetailSection,
} from '@/components/detail/detail-panel'
import { formatDateTime } from '@/features/table/cell-renderers'
import { formatQuoteAmount, QuoteSummary, totalsFromPersistedSummary } from '@/features/quotes/quote-summary'
import type { QuoteDetailWithPermissions, QuoteLine } from '@/features/quotes/types'

const OFFER_TAB = 'offer'
const COSTS_TAB = 'costs'
const NOTES_TAB = 'notes'

const LINES_GRID_CLASS = 'grid grid-cols-[minmax(200px,1.4fr)_100px_90px_110px_140px_100px_100px_110px] gap-2'

/** Read-only rendering of one tab's persisted rows (D-7: `product.code`/`name` are live, amounts are frozen at save time, D-10). */
function QuoteLinesReadOnlyList({ lines }: { lines: QuoteLine[] }) {
  const { t } = useTranslation()

  if (lines.length === 0) {
    return <p className="text-xs text-muted-foreground">{t('quotes.detail.linesEmpty')}</p>
  }

  return (
    <div className="overflow-x-auto rounded-lg border bg-surface">
      <div className="min-w-[760px] text-xs">
        <div className={`${LINES_GRID_CLASS} border-b bg-muted/40 px-2 py-1.5 font-medium text-muted-foreground`}>
          <span>{t('quotes.form.lineProductHeader')}</span>
          <span>{t('quotes.form.lineCodeHeader')}</span>
          <span>{t('quotes.form.lineQuantityHeader')}</span>
          <span>{t('quotes.form.lineUnitPriceHeader')}</span>
          <span>{t('quotes.form.lineVatRateHeader')}</span>
          <span className="text-right">{t('quotes.form.lineNetHeader')}</span>
          <span className="text-right">{t('quotes.form.lineVatHeader')}</span>
          <span className="text-right">{t('quotes.form.lineTotalHeader')}</span>
        </div>
        {lines.map((line) => (
          <div key={line.id} className={`${LINES_GRID_CLASS} border-b px-2 py-1.5 last:border-b-0`}>
            <span className="truncate">{line.product.name}</span>
            <span className="truncate font-mono text-muted-foreground">{line.product.code}</span>
            <span className="tabular-nums">{formatQuoteAmount(Number(line.quantity))}</span>
            <span className="tabular-nums">{formatQuoteAmount(Number(line.unit_price))}</span>
            <span className="truncate">{line.vat_rate ? line.vat_rate.name : <DetailEmpty />}</span>
            <span className="text-right tabular-nums">{formatQuoteAmount(Number(line.net_amount))}</span>
            <span className="text-right tabular-nums">{formatQuoteAmount(Number(line.vat_amount))}</span>
            <span className="text-right font-medium tabular-nums">{formatQuoteAmount(Number(line.total_amount))}</span>
          </div>
        ))}
      </div>
    </div>
  )
}

interface QuoteDetailViewProps {
  quote: QuoteDetailWithPermissions
}

/**
 * Read-only detail of a single quote (spec 0065): the same three tabs
 * (Offerta/Costi/Note) as the form, and the persisted economic summary
 * (`quote.summary`, D-9) rendered below them via the shared `QuoteSummary`
 * presentational component — no client recomputation here, the server value
 * is authoritative for an already-saved quote.
 */
export function QuoteDetailView({ quote }: QuoteDetailViewProps) {
  const { t } = useTranslation()
  const createdAt = formatDateTime(quote.created_at)
  const totals = totalsFromPersistedSummary(quote.summary)

  return (
    <DetailPanel>
      <DetailHero media={<DetailMonogram name={quote.title} icon={<FileText />} />} title={quote.title} subtitle={quote.code} />

      <DetailSection>
        <DetailGrid>
          <DetailField label={t('quotes.detail.opportunity')} icon={<Handshake />}>
            {quote.opportunity.name}
          </DetailField>
          <DetailField label={t('quotes.detail.quoteStatus')}>{quote.quote_status.name}</DetailField>
          <DetailField label={t('quotes.detail.commercial')}>
            {quote.commercial ? quote.commercial.name : <DetailEmpty />}
          </DetailField>
          <DetailField label={t('quotes.detail.reporter')}>
            {quote.reporter ? quote.reporter.name : <DetailEmpty />}
          </DetailField>
          <DetailField label={t('quotes.detail.supervisor')}>
            {quote.supervisor ? quote.supervisor.name : <DetailEmpty />}
          </DetailField>
        </DetailGrid>
      </DetailSection>

      <DetailSection>
        <Tabs defaultValue={OFFER_TAB} className="flex flex-col gap-3">
          <TabsList className={FORM_TAB_LIST_CLASS}>
            <TabsTrigger value={OFFER_TAB} className={FORM_TAB_TRIGGER_CLASS}>
              <TrendingUp aria-hidden="true" />
              {t('quotes.form.tabs.offer')}
            </TabsTrigger>
            <TabsTrigger value={COSTS_TAB} className={FORM_TAB_TRIGGER_CLASS}>
              <TrendingDown aria-hidden="true" />
              {t('quotes.form.tabs.costs')}
            </TabsTrigger>
            <TabsTrigger value={NOTES_TAB} className={FORM_TAB_TRIGGER_CLASS}>
              <NotebookText aria-hidden="true" />
              {t('quotes.form.tabs.notes')}
            </TabsTrigger>
          </TabsList>

          <TabsContent value={OFFER_TAB}>
            <QuoteLinesReadOnlyList lines={quote.offer_lines} />
          </TabsContent>
          <TabsContent value={COSTS_TAB}>
            <QuoteLinesReadOnlyList lines={quote.cost_lines} />
          </TabsContent>
          <TabsContent value={NOTES_TAB}>
            <p className="text-sm whitespace-pre-wrap text-foreground">
              {quote.internal_notes ?? <DetailEmpty />}
            </p>
          </TabsContent>
        </Tabs>

        <div className="mt-4">
          <QuoteSummary totals={totals} />
        </div>
      </DetailSection>

      {createdAt ? <DetailMeta label={t('quotes.detail.createdAt')}>{createdAt}</DetailMeta> : null}
    </DetailPanel>
  )
}

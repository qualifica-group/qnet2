import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Download, FileText, HandCoins, Handshake, MapPin, NotebookText, TrendingDown, TrendingUp } from 'lucide-react'
import { Button } from '@/components/ui/button'
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
import { useQuoteDocument } from '@/features/quotes/use-quote-document'
import type { QuoteDetailWithPermissions, QuoteLine } from '@/features/quotes/types'
import { QuoteCommissionsDialog } from './quote-commissions-dialog'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'

const OFFER_TAB = 'offer'
const COSTS_TAB = 'costs'
const NOTES_TAB = 'notes'

const LINES_GRID_CLASS = 'grid grid-cols-[minmax(200px,1.4fr)_100px_90px_110px_140px_100px_100px_110px_36px] gap-2'

/** Read-only rendering of one tab's persisted rows (D-7: `product.code`/`name` are live, amounts are frozen at save time, D-10). */
function ReadOnlyLine({ line, index, showCommissions }: { line: QuoteLine; index: number; showCommissions: boolean }) {
  const { t } = useTranslation()
  const [open, setOpen] = useState(false)
  return (
    <div className={`${LINES_GRID_CLASS} border-b px-2 py-1.5 last:border-b-0`}>
      <span className="truncate">{line.product.name}</span>
      <span className="truncate font-mono text-muted-foreground">{line.product.code}</span>
      <span className="tabular-nums">{formatQuoteAmount(Number(line.quantity))}</span>
      <span className="tabular-nums">{formatQuoteAmount(Number(line.unit_price))}</span>
      <span className="truncate">{line.vat_rate ? line.vat_rate.name : <DetailEmpty />}</span>
      <span className="text-right tabular-nums">{formatQuoteAmount(Number(line.net_amount))}</span>
      <span className="text-right tabular-nums">{formatQuoteAmount(Number(line.vat_amount))}</span>
      <span className="text-right font-medium tabular-nums">{formatQuoteAmount(Number(line.total_amount))}</span>
      {showCommissions ? <><Button type="button" variant="ghost" size="icon-sm" aria-label={t('quotes.form.commissions.action', { n: index + 1 })} onClick={() => setOpen(true)}><HandCoins aria-hidden="true" /></Button>{open ? <QuoteCommissionsDialog open={open} onOpenChange={setOpen} lineNumber={index + 1} productName={line.product.name} productId={line.product_id} quantity={Number(line.quantity)} unitPrice={Number(line.unit_price)} commissions={(line.commissions ?? []).map((commission) => ({ id: commission.id, recipient_role: commission.recipient_role, recipient_type: commission.recipient_type, recipient_id: commission.recipient_id, recipient: commission.recipient, commission_type: commission.commission_type, value: Number(commission.value), internal_note: commission.internal_note, origin: commission.origin, commission_configuration_id: commission.commission_configuration_id }))} disabled onSave={() => undefined} /> : null}</> : null}
    </div>
  )
}

function QuoteLinesReadOnlyList({ lines, showCommissions = false }: { lines: QuoteLine[]; showCommissions?: boolean }) {
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
          <span className="sr-only">{t('quotes.form.commissions.header')}</span>
        </div>
        {lines.map((line, index) => <ReadOnlyLine key={line.id} line={line} index={index} showCommissions={showCommissions} />)}
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
  const { generate: generateDocument, isGenerating } = useQuoteDocument()
  const canGenerateDocument = quote.permissions.actions.generate_document
  const generatingThisQuote = isGenerating(quote.id)

  return (
    <ResourcePermissionsProvider permissions={quote.permissions}>
    <DetailPanel>
      <DetailHero media={<DetailMonogram name={quote.title} icon={<FileText />} />} title={quote.title} subtitle={quote.code} />

      {canGenerateDocument ? (
        <div className="flex justify-end border-b px-6 py-3">
          <Button
            type="button"
            variant="secondary"
            size="sm"
            onClick={() => void generateDocument(quote.id, quote.code)}
            disabled={generatingThisQuote}
          >
            <Download aria-hidden="true" />
            {generatingThisQuote ? t('quotes.detail.generatingDocument') : t('actions.generateWord')}
          </Button>
        </div>
      ) : null}

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
          <DetailField label={t('quotes.detail.company')}>
            {quote.company ? quote.company.name : <DetailEmpty />}
          </DetailField>
          <DetailField label={t('quotes.detail.companySite')}>
            {quote.company_site ? quote.company_site.name : <DetailEmpty />}
          </DetailField>
          <DetailField label={t('quotes.detail.operationalSite')} icon={<MapPin />}>
            {quote.operational_site ? quote.operational_site.label : <DetailEmpty />}
          </DetailField>
          <DetailField label={t('quotes.detail.layout')} icon={<FileText />}>
            {quote.layout ? quote.layout.name : <DetailEmpty />}
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
            <QuoteLinesReadOnlyList
              lines={quote.offer_lines}
              showCommissions={quote.permissions.fields.commissions?.visible ?? true}
            />
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
          <QuoteSummary
            totals={totals}
            commissionTotals={{
              commercial: Number(quote.summary.commissions?.commercial ?? 0),
              reporter: Number(quote.summary.commissions?.reporter ?? 0),
              supervisor: Number(quote.summary.commissions?.supervisor ?? 0),
              supplier: Number(quote.summary.commissions?.supplier ?? 0),
            }}
          />
        </div>
      </DetailSection>

      {createdAt ? <DetailMeta label={t('quotes.detail.createdAt')}>{createdAt}</DetailMeta> : null}
    </DetailPanel>
    </ResourcePermissionsProvider>
  )
}

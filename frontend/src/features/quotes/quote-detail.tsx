import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { CreditCard, Download, FileText, Handshake, MapPin, NotebookText, TrendingDown, TrendingUp } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Tabs, TabsContent, TabsTrigger } from '@/components/ui/tabs'
import { FormTabStrip, FORM_TAB_TRIGGER_CLASS } from '@/components/form-tab-strip'
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
import { QuoteDetailAttributes } from '@/features/quotes/quote-detail-attributes'
import { QuoteSummary, totalsFromPersistedSummary } from '@/features/quotes/quote-summary'
import { useQuoteDocument } from '@/features/quotes/use-quote-document'
import { QuoteLinesReadOnlyList } from '@/features/quotes/quote-lines-read-only'
import type { QuoteDetailWithPermissions } from '@/features/quotes/types'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'

const OFFER_TAB = 'offer'
const COSTS_TAB = 'costs'
const NOTES_TAB = 'notes'

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
  // Controlled, so the tab strip can hand the selection over to its select
  // fallback when the tabs no longer fit.
  const [activeTab, setActiveTab] = useState(OFFER_TAB)
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
          <DetailField label={t('quotes.detail.workflowStatus')}>
            {quote.quote_workflow_status.name}
          </DetailField>
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
        <Tabs value={activeTab} onValueChange={setActiveTab} className="flex flex-col gap-3">
          <FormTabStrip value={activeTab} onValueChange={setActiveTab}>
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
          </FormTabStrip>

          <TabsContent value={OFFER_TAB}>
            <QuoteLinesReadOnlyList
              lines={quote.offer_lines}
              showCommissions={quote.permissions.fields.commissions?.visible ?? true}
            />
          </TabsContent>
          <TabsContent value={COSTS_TAB}>
            <QuoteLinesReadOnlyList lines={quote.cost_lines} />
          </TabsContent>
          <TabsContent value={NOTES_TAB} className="flex flex-col gap-3">
            <DetailField label={t('quotes.detail.paymentMethod')} icon={<CreditCard />}>
              {quote.payment_method ? quote.payment_method.name : <DetailEmpty />}
            </DetailField>
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

      <QuoteDetailAttributes
        attributes={quote.applicable_attributes}
        values={quote.attribute_values}
      />

      {createdAt ? <DetailMeta label={t('quotes.detail.createdAt')}>{createdAt}</DetailMeta> : null}
    </DetailPanel>
    </ResourcePermissionsProvider>
  )
}

import { useTranslation } from 'react-i18next'
import { Download, FileText, HandCoins, Pencil, TrendingDown, TrendingUp, Wallet } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordCardHeader, RecordStat, RecordStatStrip } from '@/components/detail/record-panel'
import { Button } from '@/components/ui/button'
import { WorkflowStatusBadge } from '@/features/quote-workflows/workflow-status-badge'
import { formatQuoteAmount } from '@/features/quotes/quote-summary'
import { useQuoteDocument } from '@/features/quotes/use-quote-document'
import { cn } from '@/lib/utils'
import type { QuoteDetailWithPermissions } from '@/features/quotes/types'

/**
 * Identity band and KPI strip of the offer record card, mirroring
 * `OpportunityDetailHeader`: both read the same handful of top-level fields
 * (status, persisted summary) and are always mounted together.
 */

/** The four commission roles of the persisted summary (D-9), in the order the summary card lists them. */
const COMMISSION_ROLES = ['commercial', 'reporter', 'supervisor', 'supplier'] as const

interface QuoteDetailHeaderProps {
  quote: QuoteDetailWithPermissions
  /** Opens the module's edit surface; absent = no edit affordance. */
  onEdit?: () => void
}

/**
 * Identity band: monogram, title, code subtitle, working-status pill, and the
 * two record-level actions (generate the `.docx`, edit) pinned top-right —
 * where every other CRM record surface in this app puts them, instead of the
 * standalone action bar the previous layout kept under the hero.
 */
export function QuoteDetailHeader({ quote, onEdit }: QuoteDetailHeaderProps) {
  const { t } = useTranslation()
  const { generate: generateDocument, isGenerating } = useQuoteDocument()
  const canGenerateDocument = quote.permissions.actions.generate_document
  const canEdit = Boolean(onEdit) && quote.permissions.resource.update
  const generatingThisQuote = isGenerating(quote.id)

  return (
    <RecordCardHeader
      media={
        <DetailMonogram
          name={quote.title}
          icon={<FileText />}
          className="size-10 text-base [&>svg]:size-5"
        />
      }
      title={quote.title}
      subtitle={quote.code}
      badges={
        <WorkflowStatusBadge
          name={quote.quote_workflow_status.name}
          color={quote.quote_workflow_status.color}
        />
      }
      actions={
        <>
          {canGenerateDocument ? (
            <Button
              type="button"
              variant="secondary"
              size="sm"
              onClick={() => void generateDocument(quote.id, quote.code)}
              disabled={generatingThisQuote}
            >
              <Download aria-hidden="true" />
              {generatingThisQuote ? t('quotes.detail.generatingDocument') : t('actions.generatePdf')}
            </Button>
          ) : null}
          {canEdit ? (
            <Button size="sm" onClick={onEdit}>
              <Pencil aria-hidden="true" />
              {t('common.edit')}
            </Button>
          ) : null}
        </>
      }
    />
  )
}

interface QuoteDetailStatsProps {
  quote: QuoteDetailWithPermissions
}

/**
 * KPI strip over the PERSISTED summary (D-9), never a client recomputation:
 * net revenue, net cost, net margin and the total commissioned amount — the
 * four numbers an operator scans before reading a single row. The per-role and
 * per-VAT breakdown stays in `QuoteSummary`, below the rows.
 *
 * The commission total is hidden entirely when the actor may not see
 * commissions, the same field gate the rows themselves honour.
 */
export function QuoteDetailStats({ quote }: QuoteDetailStatsProps) {
  const { t } = useTranslation()
  const marginNet = Number(quote.summary.margin.net)
  const showCommissions = quote.permissions.fields.commissions?.visible ?? true
  const commissionTotal = COMMISSION_ROLES.reduce(
    (total, role) => total + Number(quote.summary.commissions?.[role] ?? 0),
    0,
  )

  return (
    <RecordStatStrip>
      <RecordStat
        label={t('quotes.columns.revenueNet')}
        icon={<TrendingUp aria-hidden="true" />}
        value={formatQuoteAmount(Number(quote.summary.revenue.net))}
        hint={t('quotes.detail.stats.grossHint', {
          amount: formatQuoteAmount(Number(quote.summary.revenue.gross)),
        })}
      />
      <RecordStat
        label={t('quotes.columns.costNet')}
        icon={<TrendingDown aria-hidden="true" />}
        value={formatQuoteAmount(Number(quote.summary.cost.net))}
        hint={t('quotes.detail.stats.grossHint', {
          amount: formatQuoteAmount(Number(quote.summary.cost.gross)),
        })}
      />
      <RecordStat
        label={t('quotes.columns.marginNet')}
        icon={<Wallet aria-hidden="true" />}
        value={
          <span className={cn(marginNet < 0 && 'text-destructive')}>{formatQuoteAmount(marginNet)}</span>
        }
        hint={t('quotes.form.summary.marginHint')}
      />
      {showCommissions ? (
        <RecordStat
          label={t('quotes.detail.stats.commissions')}
          icon={<HandCoins aria-hidden="true" />}
          value={formatQuoteAmount(commissionTotal)}
        />
      ) : null}
    </RecordStatStrip>
  )
}

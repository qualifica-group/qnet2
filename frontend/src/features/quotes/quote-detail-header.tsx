import { useTranslation } from 'react-i18next'
import { Download, FileSignature, FileText, HandCoins, TrendingDown, TrendingUp, Wallet } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordCardHeader, RecordStat, RecordStatStrip } from '@/components/detail/record-panel'
import { Button } from '@/components/ui/button'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import { useModuleOpener } from '@/features/modules/use-module-opener'
import { useViewableDomain } from '@/features/modules/use-viewable-domain'
import { WorkflowStatusBadge } from '@/features/workflow-statuses/workflow-status-badge'
import { QuoteRecalculateCommissions } from '@/features/quotes/quote-recalculate-commissions'
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
  /** Called after a commission recalculation, so the host refreshes whatever lists the offer. */
  onChanged?: () => void
}

/** The registry domain "Apri contratto" opens (spec 0315). */
const CONTRACTS_DOMAIN = 'contracts'

/**
 * "Apri contratto" (spec 0315): the Contract born from this closed-won offer,
 * always in a modal so the offer stays open underneath (D-3). Shown only when
 * the offer has a contract and the actor may view the Contratti module (D-2);
 * the contract endpoint still authorizes server-side.
 */
function QuoteContractButton({ contractId }: { contractId: number }) {
  const viewableDomain = useViewableDomain(CONTRACTS_DOMAIN)

  return viewableDomain === null ? null : <QuoteContractOpener contractId={contractId} />
}

/** Split out so the opener only mounts once the actor may view contracts. */
function QuoteContractOpener({ contractId }: { contractId: number }) {
  const { t } = useTranslation()
  const opener = useModuleOpener(CONTRACTS_DOMAIN, { forceMode: OPEN_MODE_MODAL })

  return (
    <>
      <Button type="button" variant="secondary" size="sm" onClick={() => opener.openView({ id: contractId, actions: [] })}>
        <FileSignature aria-hidden="true" />
        {t('actions.openContract')}
      </Button>
      {opener.sheet}
    </>
  )
}

/**
 * Identity band: monogram, title, code subtitle, working-status pill, and the
 * record-level action (generate the `.docx`) pinned top-right — where every
 * other CRM record surface in this app puts them. There is no Edit button:
 * the record edits in place, field by field (spec 0197).
 */
export function QuoteDetailHeader({ quote, onChanged }: QuoteDetailHeaderProps) {
  const { t } = useTranslation()
  const { generate: generateDocument, isGenerating } = useQuoteDocument()
  const canGenerateDocument = quote.permissions.actions.generate_document
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
      legacyId={quote.old_id}
      subtitle={quote.code}
      badges={
        <WorkflowStatusBadge
          name={quote.quote_workflow_status.name}
          color={quote.quote_workflow_status.color}
        />
      }
      actions={
        <>
          {quote.contract_id ? <QuoteContractButton contractId={quote.contract_id} /> : null}
          {quote.permissions.actions.recalculate_commissions ? (
            <QuoteRecalculateCommissions quoteId={quote.id} onChanged={onChanged} />
          ) : null}
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
        </>
      }
    />
  )
}

interface QuoteStatsStripProps {
  revenueNet: number
  revenueGross: number
  costNet: number
  costGross: number
  marginNet: number
  /** The total commissioned amount; `null` hides the tile (no commissions visibility). */
  commissionTotal: number | null
}

/**
 * KPI strip of the offer record: net revenue, net cost, net margin and the
 * total commissioned amount — the four numbers an operator scans before
 * reading a single row. Pure render: the detail feeds the persisted summary
 * (D-9), the create form the live totals of the lines being typed.
 */
export function QuoteStatsStrip({
  revenueNet,
  revenueGross,
  costNet,
  costGross,
  marginNet,
  commissionTotal,
}: QuoteStatsStripProps) {
  const { t } = useTranslation()

  return (
    <RecordStatStrip>
      <RecordStat
        label={t('quotes.columns.revenueNet')}
        icon={<TrendingUp aria-hidden="true" />}
        value={formatQuoteAmount(revenueNet)}
        hint={t('quotes.detail.stats.grossHint', { amount: formatQuoteAmount(revenueGross) })}
      />
      <RecordStat
        label={t('quotes.columns.costNet')}
        icon={<TrendingDown aria-hidden="true" />}
        value={formatQuoteAmount(costNet)}
        hint={t('quotes.detail.stats.grossHint', { amount: formatQuoteAmount(costGross) })}
      />
      <RecordStat
        label={t('quotes.columns.marginNet')}
        icon={<Wallet aria-hidden="true" />}
        value={<span className={cn(marginNet < 0 && 'text-destructive')}>{formatQuoteAmount(marginNet)}</span>}
        hint={t('quotes.form.summary.marginHint')}
      />
      {commissionTotal !== null ? (
        <RecordStat
          label={t('quotes.detail.stats.commissions')}
          icon={<HandCoins aria-hidden="true" />}
          value={formatQuoteAmount(commissionTotal)}
        />
      ) : null}
    </RecordStatStrip>
  )
}

/**
 * The detail's KPI strip over the PERSISTED summary (D-9), never a client
 * recomputation. The commission total is hidden entirely when the actor may
 * not see commissions, the same field gate the rows themselves honour.
 */
export function QuoteDetailStats({ quote }: { quote: QuoteDetailWithPermissions }) {
  const showCommissions = quote.permissions.fields.commissions?.visible ?? true
  const commissionTotal = COMMISSION_ROLES.reduce(
    (total, role) => total + Number(quote.summary.commissions?.[role] ?? 0),
    0,
  )

  return (
    <QuoteStatsStrip
      revenueNet={Number(quote.summary.revenue.net)}
      revenueGross={Number(quote.summary.revenue.gross)}
      costNet={Number(quote.summary.cost.net)}
      costGross={Number(quote.summary.cost.gross)}
      marginNet={Number(quote.summary.margin.net)}
      commissionTotal={showCommissions ? commissionTotal : null}
    />
  )
}

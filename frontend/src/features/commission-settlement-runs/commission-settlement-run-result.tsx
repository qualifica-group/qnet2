import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { formatEuro } from '@/features/invoices/invoice-format'
import type { CreatedSettlement, SkippedSettlement } from '@/features/commission-settlement-runs/types'

const HEAD = 'px-2 py-1 text-left text-xs font-medium text-muted-foreground'
const NUM_HEAD = 'px-2 py-1 text-right text-xs font-medium text-muted-foreground'
const CELL = 'px-2 py-1 text-xs'
const NUM_CELL = 'px-2 py-1 text-right text-xs tabular-nums'

const SETTLEMENTS_PATH = '/commission-settlements'

/** The settlements a run created, each linked to its draft. */
export function RunCreatedTable({ created }: { created: CreatedSettlement[] }) {
  const { t } = useTranslation()
  return created.length === 0 ? (
    <p className="text-xs text-muted-foreground">{t('commissionSettlementRuns.result.noneCreated')}</p>
  ) : (
    <div className="overflow-x-auto rounded-lg border border-border bg-card">
      <table className="w-full min-w-max border-collapse">
        <caption className="sr-only">{t('commissionSettlementRuns.result.createdCaption')}</caption>
        <thead className="border-b border-border bg-surface">
          <tr>
            <th scope="col" className={HEAD}>{t('commissionSettlementRuns.result.referent')}</th>
            <th scope="col" className={HEAD}>{t('commissionSettlementRuns.result.company')}</th>
            <th scope="col" className={NUM_HEAD}>{t('commissionSettlementRuns.result.taxable')}</th>
            <th scope="col" className={NUM_HEAD}>{t('commissionSettlementRuns.result.net')}</th>
            <th scope="col" className={HEAD}>{t('commissionSettlementRuns.result.open')}</th>
          </tr>
        </thead>
        <tbody>
          {created.map((item) => (
            <tr key={item.settlement_id} className="border-b border-border last:border-b-0">
              <td className={CELL}>{item.referent.name}</td>
              <td className={CELL}>{item.company.name}</td>
              <td className={NUM_CELL}>{formatEuro(item.taxable_amount)}</td>
              <td className={NUM_CELL}>{formatEuro(item.net_payable)}</td>
              <td className={CELL}>
                <Link
                  to={`${SETTLEMENTS_PATH}/${item.settlement_id}`}
                  className="text-primary underline-offset-2 hover:underline"
                  aria-label={t('commissionSettlementRuns.result.openSettlement', {
                    referent: item.referent.name,
                    company: item.company.name,
                  })}
                >
                  {t('commissionSettlementRuns.result.openDraft')}
                </Link>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

/** The agents a run skipped, with the translated reason. */
export function RunSkippedTable({ skipped }: { skipped: SkippedSettlement[] }) {
  const { t } = useTranslation()
  return skipped.length === 0 ? (
    <p className="text-xs text-muted-foreground">{t('commissionSettlementRuns.result.noneSkipped')}</p>
  ) : (
    <div className="overflow-x-auto rounded-lg border border-border bg-card">
      <table className="w-full min-w-max border-collapse">
        <caption className="sr-only">{t('commissionSettlementRuns.result.skippedCaption')}</caption>
        <thead className="border-b border-border bg-surface">
          <tr>
            <th scope="col" className={HEAD}>{t('commissionSettlementRuns.result.referent')}</th>
            <th scope="col" className={HEAD}>{t('commissionSettlementRuns.result.company')}</th>
            <th scope="col" className={NUM_HEAD}>{t('commissionSettlementRuns.result.taxable')}</th>
            <th scope="col" className={HEAD}>{t('commissionSettlementRuns.result.reason')}</th>
          </tr>
        </thead>
        <tbody>
          {skipped.map((item) => (
            <tr key={`${item.referent.id}-${item.company.id}`} className="border-b border-border last:border-b-0">
              <td className={CELL}>{item.referent.name}</td>
              <td className={CELL}>{item.company.name}</td>
              <td className={NUM_CELL}>{formatEuro(item.taxable_amount)}</td>
              <td className={CELL}>{t(`commissionSettlementRuns.skipReasons.${item.reason}`)}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

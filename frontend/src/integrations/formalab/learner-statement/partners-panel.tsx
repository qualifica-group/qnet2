import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { ChevronDown, ChevronRight, TriangleAlert } from 'lucide-react'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { SegmentedControl } from '@/components/ui/segmented-control'
import { PurchaseOrderStatusBadge } from '@/features/purchase-orders/purchase-order-status-badge'
import type { PurchaseOrderStatus } from '@/features/purchase-orders/types'
import { REGISTRIES_FOR_SELECT_RESOURCE } from '@/features/registries/for-select-api'
import { cn } from '@/lib/utils'
import { euro, weight } from '@/integrations/formalab/learner-statement/format'
import type { CommissionScenario, LearnerStatement, StatementPartner } from '@/integrations/formalab/learner-statement/types'

/** The supplier picker lists supplier registries only. */
const SUPPLIER_PARAMS: Record<string, number> = { is_supplier: 1 }

interface PartnersPanelProps {
  statement: LearnerStatement
  onScenario: (scenario: CommissionScenario) => void
  onSupplier: (partner: string, supplierId: number | null) => void
  saving: boolean
}

/** "Riepilogo Provvigioni per Partner Commerciale" with the scenario switch (spec 0427 AC-006). */
export function PartnersPanel({ statement, onScenario, onSupplier, saving }: PartnersPanelProps) {
  const { t } = useTranslation('formalab')
  const { partners, payment_state: state } = statement

  if (partners.items.length === 0) {
    return null
  }

  const tone = statement.commission_scenario === 'economic' ? 'bg-success/20' : 'bg-primary/15'

  return (
    <section aria-labelledby="learner-statement-partners" className="flex flex-col gap-2">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <h3 id="learner-statement-partners" className="text-sm font-semibold">
          {t('learnerStatement.partners.title')}
        </h3>
        <div className="flex flex-wrap items-center gap-2">
          <Badge variant="secondary" className={cn('text-[11px] text-foreground', tone)}>
            {t('learnerStatement.partners.partialProfit', { amount: euro(partners.partial_profit) })}
          </Badge>
          <Badge variant="secondary" className={cn('text-[11px] text-foreground', tone)}>
            {t('learnerStatement.partners.profitPerLearner', { amount: euro(partners.profit_per_learner) })}
          </Badge>
          <SegmentedControl<CommissionScenario>
            aria-label={t('learnerStatement.partners.scenario')}
            title={statement.financial_available ? undefined : t('learnerStatement.partners.financialUnavailable')}
            value={statement.commission_scenario}
            options={[
              { value: 'economic', label: t('learnerStatement.partners.economic') },
              { value: 'financial', label: t('learnerStatement.partners.financial') },
            ]}
            onValueChange={onScenario}
            disabled={!statement.financial_available}
          />
        </div>
      </div>

      {state === 'advance_and_balance' ? (
        <p role="note" className="rounded-md bg-warning/15 px-2 py-1.5 text-xs">
          {t('learnerStatement.partners.twoTranches', {
            advance: weight(partners.tranche.advance_weight),
            advanceAmount: euro(partners.tranche.advance_commissions),
            balance: weight(partners.tranche.balance_weight),
            balanceAmount: euro(partners.tranche.balance_commissions),
          })}
        </p>
      ) : null}
      {state === 'advance_only' ? (
        <p role="note" className="rounded-md bg-primary/10 px-2 py-1.5 text-xs">
          {t('learnerStatement.partners.advanceAndForecast', {
            advance: weight(partners.tranche.advance_weight),
            advanceAmount: euro(partners.tranche.advance_commissions),
            balance: weight(partners.tranche.forecast_balance_weight),
            balanceAmount: euro(partners.tranche.forecast_balance_commissions),
          })}
        </p>
      ) : null}
      {state === 'advance_and_balance' || state === 'advance_only' ? (
        <p className="text-xs text-muted-foreground">{t('learnerStatement.partners.totalsNote')}</p>
      ) : null}

      <div className="overflow-x-auto rounded-lg border bg-card">
        <table className="w-full min-w-max text-xs">
          <thead className="bg-surface text-left text-muted-foreground">
            <tr>
              <th scope="col" className="px-2 py-1.5 font-medium">{t('learnerStatement.partners.columns.partner')}</th>
              <th scope="col" className="px-2 py-1.5 text-center font-medium">{t('learnerStatement.partners.columns.learners')}</th>
              <th scope="col" className="px-2 py-1.5 text-right font-medium">{t('learnerStatement.partners.columns.total')}</th>
              <th scope="col" className="px-2 py-1.5 font-medium">{t('learnerStatement.partners.columns.supplier')}</th>
              <th scope="col" className="px-2 py-1.5 font-medium">{t('learnerStatement.partners.columns.order')}</th>
            </tr>
          </thead>
          <tbody>
            {partners.items.map((partner) => (
              <PartnerRows key={partner.key} partner={partner} canManage={statement.can_manage} onSupplier={onSupplier} saving={saving} />
            ))}
          </tbody>
          <tfoot className="bg-warning/15 font-semibold">
            <tr>
              <td colSpan={2} className="px-2 py-1.5 text-right">{t('learnerStatement.partners.total')}</td>
              <td className="px-2 py-1.5 text-right tabular-nums">{euro(partners.total)}</td>
              <td colSpan={2} />
            </tr>
          </tfoot>
        </table>
      </div>
    </section>
  )
}

interface PartnerRowsProps {
  partner: StatementPartner
  canManage: boolean
  onSupplier: (partner: string, supplierId: number | null) => void
  saving: boolean
}

function PartnerRows({ partner, canManage, onSupplier, saving }: PartnerRowsProps) {
  const { t } = useTranslation('formalab')
  const { t: tc } = useTranslation()
  const [open, setOpen] = useState(false)
  const detailsId = `partner-details-${partner.key}`
  const selected = partner.supplier ? { id: partner.supplier.id, label: partner.supplier.name ?? `#${partner.supplier.id}` } : null

  return (
    <>
      <tr className="border-t">
        <td className="px-2 py-1">
          <span className="font-semibold">{partner.name}</span>
          <Button
            type="button"
            variant="ghost"
            size="sm"
            className="ml-1 h-6 px-1.5 text-xs"
            aria-expanded={open}
            aria-controls={detailsId}
            onClick={() => setOpen((value) => !value)}
          >
            {open ? <ChevronDown className="size-3.5" aria-hidden="true" /> : <ChevronRight className="size-3.5" aria-hidden="true" />}
            {t('learnerStatement.partners.details')}
          </Button>
        </td>
        <td className="px-2 py-1 text-center">{partner.learners_count}</td>
        <td className="px-2 py-1 text-right font-semibold tabular-nums">{euro(partner.total)}</td>
        <td className="min-w-56 px-2 py-1">
          {canManage ? (
            <AsyncPaginatedSelect
              resource={REGISTRIES_FOR_SELECT_RESOURCE}
              params={SUPPLIER_PARAMS}
              value={partner.supplier?.id ?? null}
              onChange={(supplierId) => onSupplier(partner.name, supplierId)}
              selectedItem={selected}
              disabled={saving}
              className="min-h-7 py-0.5 text-xs"
              labels={{
                placeholder: t('learnerStatement.partners.supplierPlaceholder'),
                searchPlaceholder: t('learnerStatement.partners.supplierSearch'),
                empty: t('learnerStatement.partners.supplierEmpty'),
                error: t('learnerStatement.partners.supplierError'),
                clearLabel: tc('common.clear'),
                triggerLabel: t('learnerStatement.partners.supplierOf', { name: partner.name }),
                retry: tc('common.retry'),
              }}
            />
          ) : (
            (partner.supplier?.name ?? <span className="text-muted-foreground">-</span>)
          )}
        </td>
        <td className="px-2 py-1">
          <PartnerOrder partner={partner} />
        </td>
      </tr>
      {open ? (
        <tr id={detailsId} className="border-t bg-surface">
          <td colSpan={5} className="p-0">
            <table className="w-full text-xs">
              <thead className="text-left text-muted-foreground">
                <tr>
                  <th scope="col" className="px-3 py-1 font-medium">{t('learnerStatement.partners.columns.learner')}</th>
                  <th scope="col" className="px-3 py-1 text-center font-medium">{t('learnerStatement.partners.columns.type')}</th>
                  <th scope="col" className="px-3 py-1 text-right font-medium">{t('learnerStatement.partners.columns.commission')}</th>
                </tr>
              </thead>
              <tbody>
                {partner.learners.map((learner, index) => (
                  <tr key={`${learner.name}-${index}`} className="border-t">
                    <td className="px-3 py-1">{learner.name}</td>
                    <td className="px-3 py-1 text-center">
                      {learner.type === 'percentage' ? `${learner.percentage.toFixed(1)}%` : <Badge variant="secondary" className="text-[11px]">{t('learnerStatement.partners.fixedAmount')}</Badge>}
                    </td>
                    <td className="px-3 py-1 text-right tabular-nums">{euro(learner.commission)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </td>
        </tr>
      ) : null}
    </>
  )
}

/** The partner's purchase order on this work order, or why there is none. */
function PartnerOrder({ partner }: { partner: StatementPartner }) {
  const { t } = useTranslation('formalab')
  const order = partner.purchase_order

  if (order) {
    return (
      <Link to={`/purchase-orders/${order.id}`} className="inline-flex items-center gap-1.5 hover:underline">
        <span className="font-medium">{t('learnerStatement.partners.orderNumber', { number: order.number })}</span>
        <PurchaseOrderStatusBadge status={order.status as PurchaseOrderStatus} />
        <span className="tabular-nums">{euro(order.total)}</span>
      </Link>
    )
  }

  if (!partner.supplier) {
    return (
      <span className="inline-flex items-center gap-1 text-muted-foreground" title={t('learnerStatement.partners.noSupplierHint')}>
        <TriangleAlert className="size-3.5 text-warning" aria-hidden="true" />
        {t('learnerStatement.partners.noSupplier')}
      </span>
    )
  }

  return <span className="text-muted-foreground">-</span>
}

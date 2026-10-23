import { useTranslation } from 'react-i18next'
import { ArrowLeftRight, PackagePlus } from 'lucide-react'
import { PageHeader } from '@/components/page-header'
import { Button } from '@/components/ui/button'
import { FilterTabs } from '@/components/ui/filter-tabs'
import { Can } from '@/features/auth/can'
import { useAbilities } from '@/features/auth/use-abilities'
import { StockBalancesGrid } from '@/features/stock/stock-balances-grid'
import { StockHistorySheet } from '@/features/stock/stock-history-sheet'
import { StockMovementSheet } from '@/features/stock/stock-movement-sheet'
import { StockMovementsGrid } from '@/features/stock/stock-movements-grid'
import { STOCK_MANAGE_PERMISSION, STOCK_VIEWS, STOCK_VIEW_PERMISSION } from '@/features/stock/types'
import { useStockPage } from '@/features/stock/use-stock-page'

/**
 * Dotazioni page (spec 0307): the "Giacenze" and "Movimenti" grids, the movement Sheet and the history Sheet.
 * Gated by `stock.viewAny`; Carico, Trasferimento and the writing row actions need `stock.manage`.
 */
export default function StockPage() {
  const { t } = useTranslation()
  const { can } = useAbilities()
  const page = useStockPage()
  const canManage = can(STOCK_MANAGE_PERMISSION)

  return (
    <Can
      permission={STOCK_VIEW_PERMISSION}
      fallback={<p className="text-sm text-muted-foreground">{t('stock.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-4">
        <PageHeader
          actions={
            <div className="flex flex-wrap items-center justify-end gap-2">
              <FilterTabs
                aria-label={t('stock.page.viewsLabel')}
                value={page.view}
                options={STOCK_VIEWS.map((value) => ({ value, label: t(`stock.page.views.${value}`) }))}
                onValueChange={page.setView}
              />
              {canManage ? (
                <>
                  <Button type="button" onClick={() => page.openMovement('LOAD')}>
                    <PackagePlus aria-hidden="true" />
                    {t('stock.page.load')}
                  </Button>
                  <Button type="button" variant="secondary" onClick={() => page.openMovement('TRANSFER')}>
                    <ArrowLeftRight aria-hidden="true" />
                    {t('stock.page.transfer')}
                  </Button>
                </>
              ) : null}
            </div>
          }
        />
        {page.view === 'balances' ? (
          <StockBalancesGrid tableRef={page.balancesRef} onAction={page.handleRowAction} />
        ) : (
          <StockMovementsGrid tableRef={page.movementsRef} />
        )}
      </div>

      <StockHistorySheet target={page.historyTarget} onClose={page.closeHistory} />
      {canManage ? (
        <StockMovementSheet initialValues={page.formValues} onClose={page.closeMovement} onRecorded={page.handleRecorded} />
      ) : null}
    </Can>
  )
}

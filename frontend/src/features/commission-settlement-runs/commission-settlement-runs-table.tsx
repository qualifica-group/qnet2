import { useCallback, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Play } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { PageHeader } from '@/components/page-header'
import { Can } from '@/features/auth/can'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'
import { commissionSettlementRunColumnRenderers } from '@/features/commission-settlement-runs/column-renderers'
import { CommissionSettlementRunSheet } from '@/features/commission-settlement-runs/commission-settlement-run-sheet'
import { CommissionSettlementRunDetailSheet } from '@/features/commission-settlement-runs/commission-settlement-run-detail-sheet'
import { COMMISSION_SETTLEMENT_RUNS_DOMAIN } from '@/features/commission-settlement-runs/types'

/**
 * Read-only runs adapter over the generic table (spec 0213): "Nuovo lancio"
 * opens the launch dialog (its outcome lists created and skipped agents) and
 * the `view` row action reopens the same outcome for a past run.
 */
export function CommissionSettlementRunsTable() {
  const { t } = useTranslation()
  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])

  const [launching, setLaunching] = useState(false)
  const [viewRunId, setViewRunId] = useState<number | null>(null)

  const handleAction: RowActionHandler = useCallback((action: TableActionDefinition, row: TableRow) => {
    if (action.key === 'view') {
      setViewRunId(Number(row.id))
    }
  }, [])

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <Can permission="commission-settlements.create">
            <Button onClick={() => setLaunching(true)}>
              <Play aria-hidden="true" />
              {t('commissionSettlementRuns.form.newRun')}
            </Button>
          </Can>
        }
      />

      <TableView
        ref={tableRef}
        domain={COMMISSION_SETTLEMENT_RUNS_DOMAIN}
        renderers={commissionSettlementRunColumnRenderers}
        onAction={handleAction}
      />

      <CommissionSettlementRunSheet open={launching} onClose={() => setLaunching(false)} onLaunched={refreshGrid} />
      <CommissionSettlementRunDetailSheet runId={viewRunId} onClose={() => setViewRunId(null)} />
    </div>
  )
}

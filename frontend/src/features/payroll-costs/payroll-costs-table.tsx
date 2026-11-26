import { useCallback, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Upload } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { PageHeader } from '@/components/page-header'
import { Can } from '@/features/auth/can'
import type { RowActionHandler } from '@/features/table/row-actions'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import { payrollCostColumnRenderers } from '@/features/payroll-costs/column-renderers'
import { PayrollImportDialog } from '@/features/payroll-costs/payroll-import-dialog'
import { PAYROLL_COSTS_DOMAIN } from '@/features/payroll-costs/types'

/** The grid exposes no row action: the table still requires a handler, so it is an explicit no-op. */
const NO_ROW_ACTIONS: RowActionHandler = () => undefined

/** Read-only payroll costs grid (generic table, domain `payroll-costs`) with the import action. */
export function PayrollCostsTable() {
  const { t } = useTranslation()
  const tableRef = useRef<TableViewHandle>(null)
  const [importOpen, setImportOpen] = useState(false)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <Can permission="payroll-costs.import">
            <Button size="sm" onClick={() => setImportOpen(true)}>
              <Upload aria-hidden="true" />
              {t('payrollCosts.import.button')}
            </Button>
          </Can>
        }
      />

      <TableView ref={tableRef} domain={PAYROLL_COSTS_DOMAIN} renderers={payrollCostColumnRenderers} onAction={NO_ROW_ACTIONS} />

      <PayrollImportDialog open={importOpen} onOpenChange={setImportOpen} onApplied={refreshGrid} />
    </div>
  )
}

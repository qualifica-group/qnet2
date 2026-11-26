import { useCallback, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import type { ICellRendererParams } from 'ag-grid-community'
import { FileText, Plus, Upload } from 'lucide-react'
import { PageHeader } from '@/components/page-header'
import { Button } from '@/components/ui/button'
import { ResourceActivityDialog } from '@/features/activity-log/resource-activity-dialog'
import { Can } from '@/features/auth/can'
import { PURCHASE_INVOICES_DOMAIN } from '@/features/purchase-invoices/api'
import { purchaseInvoiceColumnRenderers } from '@/features/purchase-invoices/column-renderers'
import { PayslipImportDialog } from '@/features/purchase-invoices/payslips/payslip-import-dialog'
import { PurchaseInvoiceDeleteDialog } from '@/features/purchase-invoices/purchase-invoice-delete-dialog'
import { PurchaseInvoiceDetailPanel } from '@/features/purchase-invoices/purchase-invoice-detail-panel'
import { PurchaseInvoiceDetailSheet } from '@/features/purchase-invoices/purchase-invoice-detail-sheet'
import { PurchaseInvoiceEditorDialog } from '@/features/purchase-invoices/purchase-invoice-editor-dialog'
import { usePurchaseInvoiceRowActions } from '@/features/purchase-invoices/use-purchase-invoice-row-actions'
import type { ActionIconMap } from '@/features/table/action-icon-map'
import { FilterTabs } from '@/components/ui/filter-tabs'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import type { TableRow } from '@/features/table/types'
import {
  PURCHASE_INVOICE_TYPE_TABS,
  usePurchaseInvoiceTypeTab,
  type PurchaseInvoiceTypeTab,
} from '@/features/purchase-invoices/use-purchase-invoice-type-tab'

/** Icon keys the backend catalog uses for the document-specific actions. */
const PURCHASE_INVOICE_ACTION_ICONS: ActionIconMap = {
  'file-text': FileText,
}

/**
 * Thin passive-invoices adapter over the generic table: one row per document,
 * actions come from the server row (view, delete, activity; the record edits in place),
 * the expanded row lazily loads lines and installments. Every write re-authorizes server-side.
 */
export function PurchaseInvoicesTable() {
  const { t } = useTranslation()
  const tableRef = useRef<TableViewHandle>(null)
  const typeTab = usePurchaseInvoiceTypeTab(tableRef)
  const actions = usePurchaseInvoiceRowActions()
  const [isCreating, setIsCreating] = useState(false)
  const [isImporting, setIsImporting] = useState(false)
  // The editor and delete mutations already invalidate every document query: only the SSRM cache needs a purge.
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])

  const renderDetail = useCallback(
    (params: ICellRendererParams<TableRow>) => <PurchaseInvoiceDetailPanel {...params} onChanged={refreshGrid} />,
    [refreshGrid],
  )

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <>
            <Can permission={`${PURCHASE_INVOICES_DOMAIN}.import`}>
              <Button variant="secondary" onClick={() => setIsImporting(true)}>
                <Upload aria-hidden="true" />
                {t('purchaseInvoiceViews.payslipImport.button')}
              </Button>
            </Can>
            <Can permission={`${PURCHASE_INVOICES_DOMAIN}.create`}>
              <Button onClick={() => setIsCreating(true)}>
                <Plus aria-hidden="true" />
                {t('purchaseInvoiceViews.newInvoice')}
              </Button>
            </Can>
          </>
        }
      />

      <FilterTabs<PurchaseInvoiceTypeTab>
        aria-label={t('purchaseInvoiceViews.tabs.label')}
        value={typeTab.tab}
        options={PURCHASE_INVOICE_TYPE_TABS.map((tab) => ({ value: tab, label: t(`purchaseInvoiceViews.tabs.${tab}`) }))}
        onValueChange={typeTab.setTab}
      />

      <TableView
        ref={tableRef}
        domain={PURCHASE_INVOICES_DOMAIN}
        forcedFilterModel={typeTab.forcedFilterModel}
        onFilterModelChange={typeTab.onFilterModelChange}
        renderers={purchaseInvoiceColumnRenderers}
        iconMap={PURCHASE_INVOICE_ACTION_ICONS}
        onAction={actions.handleAction}
        masterDetail
        detailCellRenderer={renderDetail}
        detailRowAutoHeight
      />

      <PurchaseInvoiceDetailSheet invoiceId={actions.viewId} onClose={actions.closeView} onChanged={refreshGrid} />
      <PurchaseInvoiceDeleteDialog target={actions.deleteTarget} onClose={actions.closeDelete} onDeleted={refreshGrid} />
      {isCreating ? (
        <PurchaseInvoiceEditorDialog
          open
          onOpenChange={(open) => (open ? undefined : setIsCreating(false))}
          onSaved={refreshGrid}
        />
      ) : null}
      <PayslipImportDialog open={isImporting} onOpenChange={setIsImporting} onApplied={refreshGrid} />
      <ResourceActivityDialog
        resource={PURCHASE_INVOICES_DOMAIN}
        row={actions.activityRow}
        onOpenChange={(open) => (open ? undefined : actions.closeActivity())}
      />
    </div>
  )
}

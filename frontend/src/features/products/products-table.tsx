import { useCallback, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import axios from 'axios'
import { Plus, Tags } from 'lucide-react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { PageHeader } from '@/components/page-header'
import { Can } from '@/features/auth/can'
import { useAbilities } from '@/features/auth/use-abilities'
import { ResourceActivityDialog } from '@/features/activity-log/resource-activity-dialog'
import { ModuleStatsPanel } from '@/features/stats/module-stats-panel'
import { StatsToggleButton } from '@/features/stats/stats-toggle-button'
import { useStatsPanel } from '@/features/stats/use-stats-panel'
import { useInvalidateModuleStats } from '@/features/stats/use-invalidate-module-stats'
import { useModuleOpener } from '@/features/modules/use-module-opener'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'
import type { BulkAction } from '@/features/table/use-bulk-actions-slot'
import { productColumnRenderers } from '@/features/products/column-renderers'
import { deleteProduct } from '@/features/products/api'
import { ProductCostTypeDialog, type ProductCostTypeTarget } from '@/features/products/product-cost-type-dialog'

/** Domain key used to mount the generic table for products. */
const PRODUCTS_DOMAIN = 'products'

const UPDATE_PERMISSION = 'products.update'
const NO_TARGETS: readonly ProductCostTypeTarget[] = []

/**
 * Thin Products adapter over the generic table. It mounts `<TableView>` with
 * the `products` domain, its custom cell renderers and a row-action handler,
 * and delegates the open mode (dedicated page vs modal Sheet) of view/edit/
 * create to `useModuleOpener`, resolved from the user's preference (spec
 * 0042). Its default stays the bespoke dedicated pages (spec 0022); the
 * generic table still owns the delete flow (confirm + toast + grid refresh)
 * and the SSRM grid refresh after every mutation via the table's imperative
 * handle. Permission gating is an affordance only; the backend re-authorizes
 * each call.
 */
export function ProductsTable() {
  const { t } = useTranslation()
  const stats = useStatsPanel(PRODUCTS_DOMAIN)
  const invalidateStats = useInvalidateModuleStats(PRODUCTS_DOMAIN)

  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])

  const [deletingId, setDeletingId] = useState<number | null>(null)
  const [activityRow, setActivityRow] = useState<TableRow | null>(null)
  const [costTypeTargets, setCostTypeTargets] = useState<readonly ProductCostTypeTarget[]>(NO_TARGETS)
  const { can } = useAbilities()
  const canUpdate = can(UPDATE_PERMISSION)

  // After a modal create/edit succeeds the Sheet closes itself; the grid and
  // the stats panel are this adapter's to refresh. The detail query is
  // invalidated inside `ProductFormScreen`. Page mode never calls this.
  const onSaved = useCallback(() => {
    refreshGrid()
    invalidateStats()
  }, [refreshGrid, invalidateStats])

  const { openCreate, openView, sheet } = useModuleOpener(PRODUCTS_DOMAIN, { onSaved })

  const runDelete = useCallback(
    async (row: TableRow) => {
      setDeletingId(Number(row.id))
      try {
        await deleteProduct(Number(row.id))
        toast.success(t('products.form.deleted'))
        refreshGrid()
        invalidateStats()
      } catch (error) {
        const status = axios.isAxiosError(error) ? error.response?.status : undefined
        toast.error(
          status === 403 ? t('products.form.deleteForbidden') : t('products.form.deleteError'),
        )
      } finally {
        setDeletingId(null)
      }
    },
    [refreshGrid, t, invalidateStats],
  )

  const handleAction: RowActionHandler = useCallback(
    (action: TableActionDefinition, row: TableRow) => {
      switch (action.key) {
        case 'view':
          openView(row)
          break
        case 'delete':
          void runDelete(row)
          break
        case 'activity':
          setActivityRow(row)
          break
        default:
          break
      }
    },
    [openView, runDelete],
  )

  // The server re-authorizes: hiding the action is only an affordance.
  const getBulkActions = useCallback(
    (selection: { rows: TableRow[] }): BulkAction[] =>
      canUpdate
        ? [
            {
              key: 'set-cost-type',
              label: t('products.bulkCostType.bulkAction'),
              icon: Tags,
              onSelect: () =>
                setCostTypeTargets(selection.rows.map((row) => ({ id: Number(row.id), name: String(row.name ?? row.id) }))),
            },
          ]
        : [],
    [canUpdate, t],
  )

  const isBusy = useCallback((row: TableRow) => row.id === deletingId, [deletingId])

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <>
            <StatsToggleButton
              domain={PRODUCTS_DOMAIN}
              isOpen={stats.isOpen}
              onToggle={stats.toggle}
            />
            <Can permission="products.create">
              <Button onClick={openCreate}>
                <Plus aria-hidden="true" />
                {t('products.form.newProduct')}
              </Button>
            </Can>
          </>
        }
      />

      <ModuleStatsPanel domain={PRODUCTS_DOMAIN} isOpen={stats.isOpen} />

      <TableView
        ref={tableRef}
        domain={PRODUCTS_DOMAIN}
        renderers={productColumnRenderers}
        onAction={handleAction}
        isBusy={isBusy}
        getBulkActions={getBulkActions}
      />

      <ProductCostTypeDialog
        key={costTypeTargets.map((target) => target.id).join(',')}
        targets={costTypeTargets}
        onOpenChange={(open) => (open ? undefined : setCostTypeTargets(NO_TARGETS))}
        onApplied={() => {
          setCostTypeTargets(NO_TARGETS)
          refreshGrid()
        }}
      />

      {sheet}

      <ResourceActivityDialog
        resource={PRODUCTS_DOMAIN}
        row={activityRow}
        onOpenChange={(open) => {
          if (!open) {
            setActivityRow(null)
          }
        }}
      />
    </div>
  )
}

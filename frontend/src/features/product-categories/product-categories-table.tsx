import { useCallback, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import axios from 'axios'
import { FolderTree, LayoutGrid, Plus } from 'lucide-react'
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
import type { ActionIconMap } from '@/features/table/action-icon-map'
import type { BulkAction, TableSelection } from '@/features/table/use-bulk-actions-slot'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'
import { productCategoryColumnRenderers } from '@/features/product-categories/column-renderers'
import { BulkMoveCategoriesDialog } from '@/features/product-categories/bulk-move-categories-dialog'
import { ProductCategoryAttributeLayoutSheet } from '@/features/product-categories/product-category-attribute-layout-sheet'
import { productCategoryKeys } from '@/features/product-categories/query-keys'
import { deleteProductCategory } from '@/features/product-categories/api'

/** Domain key used to mount the generic table for product categories. */
const PRODUCT_CATEGORIES_DOMAIN = 'product-categories'

/** Domain-specific row-action icon, merged over the shared defaults (spec 0062 revision). */
const PRODUCT_CATEGORIES_ACTION_ICONS: ActionIconMap = { 'layout-grid': LayoutGrid }

/**
 * Thin Product Categories adapter over the generic table. It mounts
 * `<TableView>` with the `product-categories` domain, its custom cell
 * renderers and a row-action handler, and delegates the open mode (modal
 * Sheet vs dedicated page) of view/edit/create to `useModuleOpener`, resolved
 * from the user's preference (spec 0042). It still owns the delete flow
 * (confirm + toast + grid refresh, surfacing the backend's restrictive-delete
 * 409/422 when a category still has children or products) and refreshes the
 * SSRM grid after every mutation (mirrors `ProductsTable`). The category
 * form's own parent-picker still flattens `GET /product-categories/tree` (see
 * `product-category-form-body.tsx`) — only the LIST surface moved from a
 * custom tree view to this grid.
 */
export function ProductCategoriesTable() {
  const { t } = useTranslation()
  const stats = useStatsPanel(PRODUCT_CATEGORIES_DOMAIN)
  const invalidateStats = useInvalidateModuleStats(PRODUCT_CATEGORIES_DOMAIN)

  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])

  const [deletingId, setDeletingId] = useState<number | null>(null)
  const [activityRow, setActivityRow] = useState<TableRow | null>(null)
  const [layoutCategoryId, setLayoutCategoryId] = useState<number | null>(null)
  const [layoutCategoryName, setLayoutCategoryName] = useState<string | null>(null)

  // Bulk move (spec 0063): this adapter owns the selection ids and the
  // post-success refresh; the dialog owns the destination pick, the request
  // and the conflict feedback.
  const { can } = useAbilities()
  const queryClient = useQueryClient()
  const [moveOpen, setMoveOpen] = useState(false)
  const [moveIds, setMoveIds] = useState<number[]>([])

  const onMoved = useCallback(
    (moved: number) => {
      toast.success(t('productCategories.bulkMove.success', { count: moved }))
      refreshGrid()
      tableRef.current?.clearSelection()
      invalidateStats()
      // The cached tree feeds every category picker, this dialog's included.
      void queryClient.invalidateQueries({ queryKey: productCategoryKeys.tree })
    },
    [refreshGrid, invalidateStats, queryClient, t],
  )

  // Surfaced inside the generic table's single "Actions" dropdown. Left
  // `undefined` when the actor cannot update categories, so the checkbox
  // column stays off entirely rather than offering an empty menu.
  const getBulkActions = can('product-categories.update')
    ? (selection: TableSelection): BulkAction[] => [
        {
          key: 'bulk-move',
          label: t('productCategories.bulkMove.tableButton'),
          icon: FolderTree,
          onSelect: () => {
            setMoveIds(selection.ids)
            setMoveOpen(true)
          },
        },
      ]
    : undefined

  // After a modal create/edit succeeds the Sheet closes itself; the grid and
  // the stats panel are this adapter's to refresh. The detail query and the
  // tree cache are invalidated inside `ProductCategoryFormScreen`. Page mode
  // never calls this.
  const onSaved = useCallback(() => {
    refreshGrid()
    invalidateStats()
  }, [refreshGrid, invalidateStats])

  const { openCreate, openView, openEdit, sheet } = useModuleOpener(PRODUCT_CATEGORIES_DOMAIN, {
    onSaved,
  })

  const runDelete = useCallback(
    async (row: TableRow) => {
      setDeletingId(row.id)
      try {
        await deleteProductCategory(row.id)
        toast.success(t('productCategories.form.deleted'))
        refreshGrid()
        invalidateStats()
      } catch (error) {
        const status = axios.isAxiosError(error) ? error.response?.status : undefined
        if (status === 403) {
          toast.error(t('productCategories.form.deleteForbidden'))
        } else if (status === 409 || status === 422) {
          toast.error(t('productCategories.form.deleteInUse'))
        } else {
          toast.error(t('productCategories.form.deleteError'))
        }
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
        case 'edit':
          openEdit(row)
          break
        case 'delete':
          void runDelete(row)
          break
        case 'activity':
          setActivityRow(row)
          break
        case 'layout':
          setLayoutCategoryId(row.id)
          setLayoutCategoryName(typeof row.name === 'string' ? row.name : null)
          break
        default:
          break
      }
    },
    [openView, openEdit, runDelete],
  )

  const isBusy = useCallback((row: TableRow) => row.id === deletingId, [deletingId])

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <>
            <StatsToggleButton
              domain={PRODUCT_CATEGORIES_DOMAIN}
              isOpen={stats.isOpen}
              onToggle={stats.toggle}
            />
            <Can permission="product-categories.create">
              <Button onClick={openCreate}>
                <Plus aria-hidden="true" />
                {t('productCategories.form.newRootCategory')}
              </Button>
            </Can>
          </>
        }
      />

      <ModuleStatsPanel domain={PRODUCT_CATEGORIES_DOMAIN} isOpen={stats.isOpen} />

      <TableView
        ref={tableRef}
        domain={PRODUCT_CATEGORIES_DOMAIN}
        renderers={productCategoryColumnRenderers}
        onAction={handleAction}
        isBusy={isBusy}
        getBulkActions={getBulkActions}
        iconMap={PRODUCT_CATEGORIES_ACTION_ICONS}
      />

      <BulkMoveCategoriesDialog
        open={moveOpen}
        onOpenChange={setMoveOpen}
        selectedIds={moveIds}
        onMoved={onMoved}
      />

      {sheet}

      <ResourceActivityDialog
        resource={PRODUCT_CATEGORIES_DOMAIN}
        row={activityRow}
        onOpenChange={(open) => {
          if (!open) {
            setActivityRow(null)
          }
        }}
      />

      <ProductCategoryAttributeLayoutSheet
        categoryId={layoutCategoryId}
        categoryName={layoutCategoryName}
        onOpenChange={(open) => {
          if (!open) {
            setLayoutCategoryId(null)
            setLayoutCategoryName(null)
          }
        }}
      />
    </div>
  )
}

import { forwardRef } from 'react'
import { Plus } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import type { ActionIconMap } from '@/features/table/action-icon-map'
import type { AdvancedFilterValues } from '@/features/table/advanced-filters/types'
import type { TableRendererMap } from '@/features/table/renderer-registry'
import type { RowActionHandler, RowActionState } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'
import type { CellCommitInterceptor } from '@/features/table/use-table-cell-edit'

export interface RegistryRelatedCreate {
  label: string
  onCreate: () => void
}

interface RegistryRelatedGridProps {
  domain: string
  registryId: number
  renderers?: TableRendererMap
  iconMap?: ActionIconMap
  onAction: RowActionHandler
  isBusy?: (row: TableRow) => boolean
  /** Per-row tint/label/disabled of an action, forwarded to `TableView` (e.g. the Commesse "€"). */
  resolveActionState?: (action: TableActionDefinition, row: TableRow) => RowActionState | undefined
  onRowCountChanged: (count: number | null) => void
  /** The "Nuovo" affordance; `null` without the module's `create` permission. */
  create: RegistryRelatedCreate | null
  interceptCellCommit?: CellCommitInterceptor
  /** One-visit advanced filters over the module's defaults (spec 0151 D-2), e.g. the Task tab's `assignment`. */
  advancedFiltersOverride?: AdvancedFilterValues
}

/**
 * One tab of the anagrafica's related records (spec 0199): the module's OWN
 * grid (`TableView` with its renderers, row actions and icons) narrowed to
 * this client by `rowScope` — a row-set scope, never a user filter, so the
 * module's preferences and saved filters are untouched — and the module's
 * "Nuovo" above it.
 */
export const RegistryRelatedGrid = forwardRef<TableViewHandle, RegistryRelatedGridProps>(function RegistryRelatedGrid(
  {
    domain,
    registryId,
    renderers,
    iconMap,
    onAction,
    isBusy,
    resolveActionState,
    onRowCountChanged,
    create,
    interceptCellCommit,
    advancedFiltersOverride,
  },
  ref,
) {
  return (
    <div className="flex min-w-0 flex-col gap-3">
      {create ? (
        <div className="flex justify-end">
          <Button size="sm" onClick={create.onCreate}>
            <Plus aria-hidden="true" />
            {create.label}
          </Button>
        </div>
      ) : null}

      <TableView
        ref={ref}
        domain={domain}
        rowScope={{ registryId }}
        renderers={renderers}
        onAction={onAction}
        isBusy={isBusy}
        resolveActionState={resolveActionState}
        iconMap={iconMap}
        onRowCountChanged={onRowCountChanged}
        interceptCellCommit={interceptCellCommit}
        advancedFiltersOverride={advancedFiltersOverride}
      />
    </div>
  )
})

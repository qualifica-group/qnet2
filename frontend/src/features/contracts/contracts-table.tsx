import { useCallback, useRef, useState } from 'react'
import { BadgeCheck, Ban, CalendarClock, Layers, RotateCcw, Shuffle } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { PageHeader } from '@/components/page-header'
import { useAbilities } from '@/features/auth/use-abilities'
import { ResourceActivityDialog } from '@/features/activity-log/resource-activity-dialog'
import { useModuleOpener } from '@/features/modules/use-module-opener'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { ActionIconMap } from '@/features/table/action-icon-map'
import type { TableActionDefinition, TableRow } from '@/features/table/types'
import type { BulkAction } from '@/features/table/use-bulk-actions-slot'
import { ContractMultiProgramDialog } from '@/features/contracts/contract-multi-program-dialog'
import { MULTI_PROGRAM_MAX_CONTRACTS } from '@/features/contracts/contract-multi-program-schema'
import { contractColumnRenderers } from '@/features/contracts/column-renderers'
import { CONTRACTS_DOMAIN } from '@/features/contracts/api'

/**
 * Row-action keys whose real UI (a dialog with inputs, gated on its own
 * permission) lives ONLY inside `ContractActionsBar`/`ContractEditDialog`
 * on the detail view — never duplicated as a standalone grid dialog. The
 * backend's row-action catalog (spec 0072, MT-04; `program` renamed from
 * `schedule` in spec 0095) advertises them per-row (`change_status`,
 * `validate`, `program`, `terminate`, `reactivate`) so the grid still needs
 * to react to a click: it opens the detail, where the actual gated action
 * button/dialog is.
 */
const ACTIONS_OPENING_DETAIL = new Set(['change_status', 'validate', 'program', 'terminate', 'reactivate'])

/**
 * The icon names `ContractColumnCatalog::actions()` advertises that the
 * shared `defaultActionIconMap` does not know (it only covers the generic
 * CRUD ones). Without them every contract domain action fell back to the
 * same neutral three-dots glyph, so the row read as five identical buttons
 * (direttiva utente 2026-08-31 rev.2: "icone diverse per ogni action").
 */
const CONTRACT_ACTION_ICONS: ActionIconMap = {
  'check-circle': BadgeCheck,
  'calendar-clock': CalendarClock,
  shuffle: Shuffle,
  ban: Ban,
  'rotate-ccw': RotateCcw,
}

const PROGRAM_PERMISSION = 'contracts.program'
const MIN_MULTI_SELECTION = 2
const NO_CONTRACT_IDS: number[] = []

/**
 * Thin Contracts adapter over the generic table (spec 0072, mirrors
 * `QuotesTable`). No "New contract" button: a contract is never created nor
 * deleted by hand (D-6) — `ContractPolicy::abilities()` exposes no
 * `create`/`delete`, so the row-action catalog never advertises them either.
 * `view` and every domain-action key in `ACTIONS_OPENING_DETAIL` all open the
 * detail (see its doc); `activity` opens the shared activity dialog inline.
 * Permission gating is an affordance only; the backend re-authorizes each
 * call.
 */
export function ContractsTable() {
  const { t } = useTranslation()
  const { can } = useAbilities()
  const canProgram = can(PROGRAM_PERMISSION)
  const [multiProgramIds, setMultiProgramIds] = useState(NO_CONTRACT_IDS)
  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])

  const [activityRow, setActivityRow] = useState<TableRow | null>(null)

  const { openView, sheet } = useModuleOpener(CONTRACTS_DOMAIN, {
    onSaved: refreshGrid,
  })

  const handleAction: RowActionHandler = useCallback(
    (action: TableActionDefinition, row: TableRow) => {
      if (action.key === 'view' || ACTIONS_OPENING_DETAIL.has(action.key)) {
        openView(row)
        return
      }
      if (action.key === 'activity') {
        setActivityRow(row)
      }
    },
    [openView],
  )

  // Spec 0267: shown only for 2+ rows and `contracts.program`; the server re-authorizes every contract.
  const getBulkActions = useCallback(
    (selection: { rows: TableRow[] }): BulkAction[] => {
      if (!canProgram || selection.rows.length < MIN_MULTI_SELECTION) {
        return []
      }
      // Spec 0267, D-14: a contract that cannot be grouped is explained inside the dialog, by the server.
      const blockedReason =
        selection.rows.length > MULTI_PROGRAM_MAX_CONTRACTS
          ? t('contracts.actions.multiProgramDialog.tooMany', { max: MULTI_PROGRAM_MAX_CONTRACTS })
          : undefined
      return [
        {
          key: 'program-multi-quote',
          label: t('contracts.actions.multiProgramDialog.bulkAction'),
          icon: Layers,
          disabled: blockedReason !== undefined,
          disabledReason: blockedReason,
          onSelect: () => {
            if (blockedReason !== undefined) {
              toast.error(blockedReason)
              return
            }
            setMultiProgramIds(selection.rows.map((row) => Number(row.id)))
          },
        },
      ]
    },
    [canProgram, t],
  )

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader />

      <TableView
        ref={tableRef}
        domain={CONTRACTS_DOMAIN}
        renderers={contractColumnRenderers}
        iconMap={CONTRACT_ACTION_ICONS}
        onAction={handleAction}
        getBulkActions={getBulkActions}
      />

      <ContractMultiProgramDialog
        open={multiProgramIds.length > 0}
        onOpenChange={(open) => (open ? undefined : setMultiProgramIds(NO_CONTRACT_IDS))}
        contractIds={multiProgramIds}
      />

      {sheet}

      <ResourceActivityDialog
        resource={CONTRACTS_DOMAIN}
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

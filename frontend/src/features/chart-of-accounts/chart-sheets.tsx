import { ResourceActivityDialog } from '@/features/activity-log/resource-activity-dialog'
import { ChartAccountSheet } from '@/features/chart-of-accounts/chart-account-sheet'
import type { SheetState } from '@/features/chart-of-accounts/sheet-state'
import { SubAccountSheet } from '@/features/chart-of-accounts/sub-account-sheet'
import type { ChartGroup } from '@/features/chart-of-accounts/types'

interface ChartSheetsProps {
  sheet: SheetState | null
  groups: ChartGroup[]
  onClose: () => void
}

/** Renders the single overlay open on top of the tree (form sheet or activity history). */
export function ChartSheets({ sheet, groups, onClose }: ChartSheetsProps) {
  if (sheet === null) return null

  switch (sheet.type) {
    case 'group-create':
    case 'group-edit':
    case 'account-create':
    case 'account-edit':
      return <ChartAccountSheet key={sheetKey(sheet)} state={sheet} groups={groups} onClose={onClose} />
    case 'sub-create':
    case 'sub-edit':
      return <SubAccountSheet key={sheetKey(sheet)} state={sheet} groups={groups} onClose={onClose} />
    case 'activity':
      return (
        <ResourceActivityDialog
          resource={sheet.resource}
          row={{ id: sheet.id, actions: [] }}
          onOpenChange={(open) => !open && onClose()}
        />
      )
  }
}

/** A fresh form per target: its defaults are read once at mount. */
function sheetKey(sheet: Exclude<SheetState, { type: 'activity' }>): string {
  switch (sheet.type) {
    case 'group-create':
      return 'group-create'
    case 'group-edit':
      return `group-edit:${sheet.group.id}`
    case 'account-create':
      return `account-create:${sheet.group.id}`
    case 'account-edit':
      return `account-edit:${sheet.account.id}`
    case 'sub-create':
      return `sub-create:${sheet.account.id}`
    case 'sub-edit':
      return `sub-edit:${sheet.sub.source}:${sheet.sub.id}`
  }
}

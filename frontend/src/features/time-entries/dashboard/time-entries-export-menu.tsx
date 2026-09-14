/**
 * Export options menu of the "Periodo" card (spec 0122 D-12, AC-039): the two
 * exports gated by `can_export`/`can_export_monthly` (D-8: "Creatore NO,
 * Admin SI'"). Renders nothing when neither permission is granted, mirroring
 * q-net's `canSeeExportButton` guard.
 */

import { useTranslation } from 'react-i18next'
import { Download } from 'lucide-react'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { TimeEntriesMonthlyExportDialog } from '@/features/time-entries/dashboard/time-entries-monthly-export-dialog'
import { useTimeEntriesExport } from '@/features/time-entries/dashboard/use-time-entries-export'
import type { FilteredTimeEntriesExportParams } from '@/features/time-entries/types'

interface TimeEntriesExportMenuProps {
  canExport: boolean
  canExportMonthly: boolean
  filteredParams: FilteredTimeEntriesExportParams
  defaultUserId: number | null
  anchorDate: string
}

export function TimeEntriesExportMenu({
  canExport,
  canExportMonthly,
  filteredParams,
  defaultUserId,
  anchorDate,
}: TimeEntriesExportMenuProps) {
  const { t } = useTranslation()
  const exportState = useTimeEntriesExport({ filteredParams, defaultUserId, anchorDate })

  if (!canExport && !canExportMonthly) {
    return null
  }

  return (
    <>
      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <Button aria-label={t('timeEntries.export.menu')} type="button" variant="default" size="icon">
            <Download className="size-4" aria-hidden="true" />
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
          <DropdownMenuItem
            disabled={!canExport || defaultUserId === null || exportState.isExportingFiltered}
            onSelect={() => exportState.exportFiltered()}
          >
            {t('timeEntries.export.filtered')}
          </DropdownMenuItem>
          <DropdownMenuItem disabled={!canExportMonthly} onSelect={() => exportState.openMonthlyDialog()}>
            {t('timeEntries.export.monthly')}
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>

      <TimeEntriesMonthlyExportDialog state={exportState} />
    </>
  )
}

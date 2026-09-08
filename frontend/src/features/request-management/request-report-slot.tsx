import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { FileDown } from 'lucide-react'
import { DropdownMenuItem } from '@/components/ui/dropdown-menu'
import { Can } from '@/features/auth/can'
import { RequestReportDialog } from '@/features/request-management/request-report-dialog'

/**
 * `importSlot` of the Gestione Richieste toolbar (spec 0106): the menu entry
 * that opens the report dialog, gated by `request-management.report`. The UI
 * hides it; the backend re-authorizes on every one of the three routes.
 *
 * Self-contained (menu item + dialog + open state) so `request-management-
 * table.tsx` only wires one prop instead of growing past the size budget
 * (mirrors `SavedViewsSlot`'s extraction reason).
 */
export function RequestReportSlot() {
  const { t } = useTranslation()
  const [open, setOpen] = useState(false)

  return (
    <Can permission="request-management.report">
      <DropdownMenuItem
        onSelect={(event) => {
          // Radix closes the menu (and unmounts its subtree) on `onSelect`
          // by default, before the dialog would get a chance to mount —
          // the same guard `TableView` uses for its own export entry.
          event.preventDefault()
          setOpen(true)
        }}
      >
        <FileDown aria-hidden="true" />
        {t('requestManagement.report.action')}
      </DropdownMenuItem>

      <RequestReportDialog open={open} onOpenChange={setOpen} />
    </Can>
  )
}

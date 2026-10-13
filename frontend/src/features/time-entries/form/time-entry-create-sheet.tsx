/**
 * Right-hand Sheet creating a new time entry (spec 0122 MT-F2, q-net's
 * `WorkActivityCreateModal`), with the record's rows closed (spec 0226 D-6).
 * `defaultDate`/`userId` come from the caller's own context (a day card's "+",
 * a team member's dashboard) — never a field in this form.
 */

import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet'
import { useConfirm } from '@/components/confirm-dialog-context'
import { TimeEntryCreateForm } from '@/features/time-entries/form/time-entry-create-form'

interface TimeEntryCreateSheetProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  defaultDate?: string
  userId?: number
}

export function TimeEntryCreateSheet({ open, onOpenChange, defaultDate, userId }: TimeEntryCreateSheetProps) {
  const { t } = useTranslation()
  const confirm = useConfirm()
  const [isDirty, setIsDirty] = useState(false)

  const guardedOpenChange = async (next: boolean) => {
    if (!next && isDirty) {
      const confirmed = await confirm({
        title: t('timeEntries.form.unsavedChangesTitle'),
        description: t('timeEntries.form.unsavedChangesDescription'),
        tone: 'warning',
      })
      if (!confirmed) {
        return
      }
    }
    onOpenChange(next)
  }

  return (
    <Sheet open={open} onOpenChange={(next) => void guardedOpenChange(next)}>
      <SheetContent className="gap-0 p-0" storageKey="sheet-width:time-entries-create">
        <SheetHeader>
          <SheetTitle>{t('timeEntries.form.createTitle')}</SheetTitle>
          <SheetDescription>{t('timeEntries.form.createSubtitle')}</SheetDescription>
        </SheetHeader>

        <TimeEntryCreateForm
          userId={userId}
          defaultDate={defaultDate}
          onSuccess={() => onOpenChange(false)}
          onCancel={() => void guardedOpenChange(false)}
          onDirtyChange={setIsDirty}
          footerClassName="sticky bottom-0 z-20 bg-background/95 backdrop-blur supports-backdrop-filter:bg-background/80"
        />
      </SheetContent>
    </Sheet>
  )
}

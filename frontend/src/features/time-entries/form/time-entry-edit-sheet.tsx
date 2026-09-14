/**
 * Right-hand Sheet editing an existing time entry (spec 0122 MT-F2). Loads
 * the record itself (`GET /api/time-entries/{id}`) so it can be opened from
 * anywhere with just an id — skeleton while pending, retryable error state,
 * same shape as `TaskForm`'s own loading/error branches.
 */

import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet'
import { useConfirm } from '@/components/confirm-dialog-context'
import { fetchTimeEntry } from '@/features/time-entries/api'
import { timeEntryKeys } from '@/features/time-entries/query-keys'
import { TimeEntryFormBody } from '@/features/time-entries/form/time-entry-form-body'

interface TimeEntryEditSheetProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  timeEntryId: number
}

export function TimeEntryEditSheet({ open, onOpenChange, timeEntryId }: TimeEntryEditSheetProps) {
  const { t } = useTranslation()
  const confirm = useConfirm()
  const [isDirty, setIsDirty] = useState(false)

  const query = useQuery({
    queryKey: timeEntryKeys.detail(timeEntryId),
    queryFn: () => fetchTimeEntry(timeEntryId),
    enabled: open,
  })

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
      <SheetContent className="gap-0 p-0" storageKey="sheet-width:time-entries-edit">
        <SheetHeader>
          <SheetTitle>{t('timeEntries.form.editTitle')}</SheetTitle>
          <SheetDescription>{t('timeEntries.form.editSubtitle')}</SheetDescription>
        </SheetHeader>

        {query.isPending ? (
          <div className="flex flex-col gap-3 p-4" aria-hidden="true">
            <Skeleton className="h-9 w-full" />
            <Skeleton className="h-24 w-full" />
            <Skeleton className="h-9 w-full" />
          </div>
        ) : query.isError ? (
          <div className="flex flex-col items-start gap-3 p-4">
            <p className="text-sm text-destructive" role="alert">
              {t('timeEntries.page.loadError')}
            </p>
            <Button variant="outline" size="sm" className="bg-card" onClick={() => void query.refetch()}>
              {t('timeEntries.page.retry')}
            </Button>
          </div>
        ) : (
          <TimeEntryFormBody
            mode={{ type: 'edit', entry: query.data }}
            onSuccess={() => onOpenChange(false)}
            onCancel={() => void guardedOpenChange(false)}
            onDeleted={() => onOpenChange(false)}
            onDirtyChange={setIsDirty}
            headerTitle={t('timeEntries.form.editTitle')}
            headerDescription={t('timeEntries.form.editSubtitle')}
            footerClassName="sticky bottom-0 z-20 bg-background/95 backdrop-blur supports-backdrop-filter:bg-background/80"
          />
        )}
      </SheetContent>
    </Sheet>
  )
}

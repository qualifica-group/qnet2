/**
 * Right-hand Sheet showing a time entry's detail, editable in place (spec
 * 0226 D-5: there is no edit form). Loads the record itself
 * (`GET /api/time-entries/{id}`) so it can be opened from anywhere with just
 * an id — skeleton while pending, retryable error state.
 */

import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet'
import { fetchTimeEntry } from '@/features/time-entries/api'
import { timeEntryKeys } from '@/features/time-entries/query-keys'
import { TimeEntryDetail } from '@/features/time-entries/form/time-entry-detail'

interface TimeEntryDetailSheetProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  timeEntryId: number
}

export function TimeEntryDetailSheet({ open, onOpenChange, timeEntryId }: TimeEntryDetailSheetProps) {
  const { t } = useTranslation()

  const query = useQuery({
    queryKey: timeEntryKeys.detail(timeEntryId),
    queryFn: () => fetchTimeEntry(timeEntryId),
    enabled: open,
  })

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent className="gap-0 p-0" storageKey="sheet-width:time-entries-detail">
        <SheetHeader>
          <SheetTitle>{t('timeEntries.form.detailTitle')}</SheetTitle>
          <SheetDescription>{t('timeEntries.form.detailSubtitle')}</SheetDescription>
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
          <TimeEntryDetail entry={query.data} onDeleted={() => onOpenChange(false)} />
        )}
      </SheetContent>
    </Sheet>
  )
}

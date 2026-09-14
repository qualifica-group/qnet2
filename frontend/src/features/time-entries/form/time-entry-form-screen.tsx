/**
 * Page variant of the time entry form (spec 0122 MT-F2), for `/time-entries/new`
 * and `/time-entries/:id` (routing wired by MT-F7). Same `TimeEntryFormBody`
 * as the Sheets, without the Sheet chrome or the close guard — a full page
 * navigation away is the router's own concern, not this component's.
 */

import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { fetchTimeEntry } from '@/features/time-entries/api'
import { timeEntryKeys } from '@/features/time-entries/query-keys'
import { TimeEntryFormBody } from '@/features/time-entries/form/time-entry-form-body'
import type { TimeEntry } from '@/features/time-entries/types'

interface TimeEntryFormScreenProps {
  mode: 'create' | 'edit'
  /** Required when `mode === 'edit'`. */
  timeEntryId?: number
  defaultDate?: string
  userId?: number
  /** Called after a successful save, a delete, or Annulla — the router decides where to go. */
  onDone: () => void
}

export function TimeEntryFormScreen({ mode, timeEntryId, defaultDate, userId, onDone }: TimeEntryFormScreenProps) {
  const { t } = useTranslation()
  const isEdit = mode === 'edit' && timeEntryId !== undefined

  const query = useQuery({
    queryKey: timeEntryKeys.detail(timeEntryId ?? -1),
    queryFn: () => fetchTimeEntry(timeEntryId as number),
    enabled: isEdit,
  })

  if (isEdit && query.isPending) {
    return (
      <div className="flex flex-col gap-3 p-4" aria-hidden="true">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-40 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  if (isEdit && query.isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive" role="alert">
          {t('timeEntries.page.loadError')}
        </p>
        <Button variant="outline" size="sm" className="bg-card" onClick={() => void query.refetch()}>
          {t('timeEntries.page.retry')}
        </Button>
      </div>
    )
  }

  const headerTitle = isEdit ? t('timeEntries.form.editTitle') : t('timeEntries.form.createTitle')
  const headerDescription = isEdit ? t('timeEntries.form.editSubtitle') : t('timeEntries.form.createSubtitle')

  return (
    <div className="mx-auto flex h-full w-full max-w-3xl flex-col">
      <TimeEntryFormBody
        mode={isEdit ? { type: 'edit', entry: query.data as TimeEntry } : { type: 'create', userId, defaultDate }}
        onSuccess={() => onDone()}
        onCancel={onDone}
        onDeleted={onDone}
        headerTitle={headerTitle}
        headerDescription={headerDescription}
      />
    </div>
  )
}

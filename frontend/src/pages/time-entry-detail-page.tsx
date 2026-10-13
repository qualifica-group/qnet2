import { useTranslation } from 'react-i18next'
import { useNavigate, useParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { Can } from '@/features/auth/can'
import { PageHeader } from '@/components/page-header'
import { fetchTimeEntry } from '@/features/time-entries/api'
import { TimeEntryDetail } from '@/features/time-entries/form/time-entry-detail'
import { timeEntryKeys } from '@/features/time-entries/query-keys'
import { parseEntityId } from '@/routes/entity-id'
import NotFoundPage from '@/pages/not-found-page'

/**
 * `/time-entries/:id`: page variant of the time entry detail, editable in
 * place (spec 0226 D-5: there is no edit form). Validates the id shape,
 * gates access, loads the record and returns to the dashboard on delete.
 */
export default function TimeEntryDetailPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { id } = useParams()
  const timeEntryId = parseEntityId(id)

  const query = useQuery({
    queryKey: timeEntryKeys.detail(timeEntryId ?? -1),
    queryFn: () => fetchTimeEntry(timeEntryId as number),
    enabled: timeEntryId !== null,
  })

  if (timeEntryId === null) {
    return <NotFoundPage />
  }

  return (
    <Can
      permission="time-entries.viewAny"
      fallback={<p className="text-sm text-muted-foreground">{t('timeEntries.page.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-4">
        <PageHeader />
        <div className="mx-auto flex w-full max-w-3xl flex-1 flex-col overflow-hidden rounded-lg border">
          {query.isPending ? (
            <div className="flex flex-col gap-3 p-4" aria-hidden="true">
              <Skeleton className="h-9 w-full" />
              <Skeleton className="h-40 w-full" />
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
            <TimeEntryDetail entry={query.data} onDeleted={() => void navigate('/time-entries')} />
          )}
        </div>
      </div>
    </Can>
  )
}

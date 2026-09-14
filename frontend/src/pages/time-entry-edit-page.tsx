import { useTranslation } from 'react-i18next'
import { useNavigate, useParams } from 'react-router-dom'
import { Can } from '@/features/auth/can'
import { PageHeader } from '@/components/page-header'
import { TimeEntryFormScreen } from '@/features/time-entries/form/time-entry-form-screen'
import { parseEntityId } from '@/routes/entity-id'
import NotFoundPage from '@/pages/not-found-page'

/**
 * `/time-entries/:id`: page variant of the edit form (spec 0122 MT-F7, D-3
 * has no separate `/edit` deep link for this module). `TimeEntryFormScreen`
 * loads the record itself; this page only validates the id shape and gates
 * access.
 */
export default function TimeEntryEditPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { id } = useParams()
  const timeEntryId = parseEntityId(id)

  if (timeEntryId === null) {
    return <NotFoundPage />
  }

  return (
    <Can
      permission="time-entries.update"
      fallback={<p className="text-sm text-muted-foreground">{t('timeEntries.page.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-4">
        <PageHeader />
        <div className="flex flex-1 flex-col overflow-hidden rounded-lg border">
          <TimeEntryFormScreen mode="edit" timeEntryId={timeEntryId} onDone={() => void navigate('/time-entries')} />
        </div>
      </div>
    </Can>
  )
}

import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router-dom'
import { Can } from '@/features/auth/can'
import { PageHeader } from '@/components/page-header'
import { TimeEntryFormScreen } from '@/features/time-entries/form/time-entry-form-screen'

/**
 * `/time-entries/new`: page variant of the create form (spec 0122 MT-F7).
 * `TimeEntryFormScreen` owns the form itself; this page only gates access
 * and returns to the dashboard once done.
 */
export default function TimeEntryNewPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()

  return (
    <Can
      permission="time-entries.create"
      fallback={<p className="text-sm text-muted-foreground">{t('timeEntries.page.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-4">
        <PageHeader />
        <div className="flex flex-1 flex-col overflow-hidden rounded-lg border">
          <TimeEntryFormScreen mode="create" onDone={() => void navigate('/time-entries')} />
        </div>
      </div>
    </Can>
  )
}

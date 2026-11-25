import { useTranslation } from 'react-i18next'
import { PageHeader } from '@/components/page-header'
import { Skeleton } from '@/components/ui/skeleton'
import { TableView } from '@/features/table/table-view'
import NotFoundPage from '@/pages/not-found-page'
import { FORMALAB_CLASSES_DOMAIN } from '@/integrations/formalab/api'
import { SyncButton } from '@/integrations/formalab/components/sync-button'
import { FORMALAB_KEY } from '@/integrations/formalab/definition-key'
import { useIsIntegrationActive } from '@/integrations/use-active-integrations'

/** The catalogue is read-only: no row action is handled. */
const NO_ROW_ACTIONS = () => undefined

/**
 * `/dev/formalab/classes` (super-admin: the route sits behind the role guard,
 * the table endpoint re-checks it). Not found when the integration is not active.
 */
export default function FormalabClassesPage() {
  const { isActive, isLoading } = useIsIntegrationActive(FORMALAB_KEY)

  if (isLoading) {
    return <Skeleton className="h-40 w-full" aria-busy="true" />
  }
  if (!isActive) {
    return <NotFoundPage />
  }
  return <ClassesContent />
}

function ClassesContent() {
  const { t } = useTranslation('formalab')

  return (
    <div className="flex min-w-0 flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <SyncButton />
        }
      />
      <section className="flex flex-col gap-2" aria-labelledby="formalab-classes-title">
        <h2 id="formalab-classes-title" className="text-base font-semibold">
          {t('classes.title')}
        </h2>
        <TableView domain={FORMALAB_CLASSES_DOMAIN} onAction={NO_ROW_ACTIONS} />
      </section>
    </div>
  )
}

import { useTranslation } from 'react-i18next'
import { Link, useParams } from 'react-router-dom'
import { ArrowLeft } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { PageHeader } from '@/components/page-header'
import { RequestWorkPanelScreen } from '@/features/request-management/request-work-panel'
import { ENROLLEE_MODULE, RequestModuleProvider } from '@/features/request-management/request-module'
import { parseEntityId } from '@/routes/entity-id'
import NotFoundPage from '@/pages/not-found-page'

const LIST_PATH = ENROLLEE_MODULE.routeBasePath

/**
 * Dedicated page of a single enrollee record (spec 0130), the enrollee-
 * management twin of `RequestManagementDetailPage`: same reasons it cannot be
 * the generic `ModuleDetailPage` shell (no edit route, the work panel paints
 * its own page background). `RequestModuleProvider` makes the SAME
 * `RequestWorkPanelScreen` resolve `enrollee-management.*` paths/permissions
 * instead of `request-management.*`'s — back link stays inside this module.
 */
export default function EnrolleeManagementDetailPage() {
  const { t } = useTranslation()
  const { id } = useParams()
  const entityId = parseEntityId(id)

  if (entityId === null) {
    return <NotFoundPage />
  }

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <Button variant="outline" asChild>
            <Link to={LIST_PATH}>
              <ArrowLeft aria-hidden="true" />
              {t('common.back')}
            </Link>
          </Button>
        }
      />

      <div className="flex flex-1 flex-col overflow-hidden rounded-xl border shadow-sm">
        <RequestModuleProvider module={ENROLLEE_MODULE}>
          <RequestWorkPanelScreen id={entityId} />
        </RequestModuleProvider>
      </div>
    </div>
  )
}

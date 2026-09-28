import { useTranslation } from 'react-i18next'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { ArrowLeft } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { PageHeader } from '@/components/page-header'
import { RequestWorkPanelScreen } from '@/features/request-management/request-work-panel'
import { parseEntityId } from '@/routes/entity-id'
import NotFoundPage from '@/pages/not-found-page'

const LIST_PATH = '/request-management'

/**
 * Dedicated page of a single request (spec 0049 D-9), replacing the generic
 * `ModuleDetailPage` for this module only. Two reasons it cannot be the
 * generic shell: this module has no edit route (`generateRoutes: false`,
 * D-9/D-10), so the generic "Edit" button would deep-link to NotFound; and the
 * work panel paints its own page background, so the frame around it must not
 * add `bg-card` on top of it.
 */
export default function RequestManagementDetailPage() {
  const { t } = useTranslation()
  const { id } = useParams()
  const navigate = useNavigate()
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
        <RequestWorkPanelScreen id={entityId} onSaved={() => void navigate(LIST_PATH)} />
      </div>
    </div>
  )
}

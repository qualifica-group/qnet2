import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { BookOpen } from 'lucide-react'
import { PageHeader } from '@/components/page-header'
import { Button } from '@/components/ui/button'
import { Can } from '@/features/auth/can'
import { ApiClientsTab } from '@/features/api-integrations/components/api-clients-tab'

/** Route of the API reference page (Develop section). */
export const API_DOCS_PATH = '/dev/api-docs'

/**
 * "API & integrations" admin page (spec 0209): API client management. The
 * `api-clients.view` gate is a UX affordance; every endpoint re-authorizes
 * server-side.
 */
export default function ApiIntegrationsPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="api-clients.view"
      fallback={<p className="text-sm text-muted-foreground">{t('apiIntegrations.forbidden')}</p>}
    >
      <div className="flex min-w-0 flex-1 flex-col gap-4">
        <PageHeader
          title={t('apiIntegrations.title')}
          subtitle={t('apiIntegrations.subtitle')}
          actions={
            <Button asChild size="sm" variant="secondary">
              <Link to={API_DOCS_PATH}>
                <BookOpen className="size-3.5" aria-hidden />
                {t('apiIntegrations.docsLink')}
              </Link>
            </Button>
          }
        />
        <ApiClientsTab />
      </div>
    </Can>
  )
}

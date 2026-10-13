import { useTranslation } from 'react-i18next'
import { PageHeader } from '@/components/page-header'
import { Can } from '@/features/auth/can'
import { ApiDocsContent } from '@/features/api-integrations/components/api-docs-content'

/**
 * "API documentation" page of the Develop section: the generated API reference.
 * The `api-clients.view` gate is a UX affordance; every endpoint re-authorizes
 * server-side.
 */
export default function ApiDocsPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="api-clients.view"
      fallback={<p className="text-sm text-muted-foreground">{t('apiIntegrations.forbidden')}</p>}
    >
      <div className="flex min-w-0 flex-1 flex-col gap-3">
        <PageHeader />
        <ApiDocsContent />
      </div>
    </Can>
  )
}

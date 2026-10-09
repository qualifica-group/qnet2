import { useTranslation } from 'react-i18next'
import { PageHeader } from '@/components/page-header'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Can } from '@/features/auth/can'
import { ApiClientsTab } from '@/features/api-integrations/components/api-clients-tab'
import { ApiDocsTab } from '@/features/api-integrations/components/api-docs-tab'

const TAB_TRIGGER_CLASS = 'px-2.5 py-1 text-xs'

/**
 * "API & integrations" admin page (spec 0209): client management and the
 * generated API documentation. The `api-clients.view` gate is a UX affordance;
 * every endpoint re-authorizes server-side.
 */
export default function ApiIntegrationsPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="api-clients.view"
      fallback={<p className="text-sm text-muted-foreground">{t('apiIntegrations.forbidden')}</p>}
    >
      <div className="flex min-w-0 flex-1 flex-col gap-4">
        <PageHeader title={t('apiIntegrations.title')} subtitle={t('apiIntegrations.subtitle')} />
        <Tabs defaultValue="clients">
          <TabsList>
            <TabsTrigger value="clients" className={TAB_TRIGGER_CLASS}>
              {t('apiIntegrations.tabs.clients')}
            </TabsTrigger>
            <TabsTrigger value="docs" className={TAB_TRIGGER_CLASS}>
              {t('apiIntegrations.tabs.docs')}
            </TabsTrigger>
          </TabsList>
          <TabsContent value="clients">
            <ApiClientsTab />
          </TabsContent>
          <TabsContent value="docs">
            <ApiDocsTab />
          </TabsContent>
        </Tabs>
      </div>
    </Can>
  )
}

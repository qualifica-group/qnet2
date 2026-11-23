import type { IntegrationI18nResources } from '@/integrations/types'

/**
 * Strings shared by every integration (namespace `integrations`), e.g. the
 * "Integrazioni esterne" menu section the backend providers put their pages in.
 */
const resources: IntegrationI18nResources = {
  namespace: 'integrations',
  it: { nav: { externalIntegrations: 'Integrazioni esterne' } },
  en: { nav: { externalIntegrations: 'External integrations' } },
}

export default resources

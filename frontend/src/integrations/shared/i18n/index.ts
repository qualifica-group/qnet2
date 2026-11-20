import type { IntegrationI18nResources } from '@/integrations/types'

/**
 * Strings shared by every integration (namespace `integrations`), e.g. the
 * "Servizi esterni" menu group the backend providers put their pages in.
 */
const resources: IntegrationI18nResources = {
  namespace: 'integrations',
  it: { nav: { externalServices: 'Servizi esterni' } },
  en: { nav: { externalServices: 'External services' } },
}

export default resources

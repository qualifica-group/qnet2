import { lazyRoute } from '@/routes/lazy-route'
import { FORMALAB_KEY } from '@/integrations/formalab/definition-key'
import { useFormalabWorkOrderTab } from '@/integrations/formalab/use-formalab-work-order-tab'
import type { IntegrationDefinition } from '@/integrations/types'

const FormalabStatusPage = lazyRoute(() => import('@/integrations/formalab/components/formalab-status-page'))

export const formalabIntegration: IntegrationDefinition = {
  key: FORMALAB_KEY,
  useWorkOrderTabs: useFormalabWorkOrderTab,
  devRoutes: [{ path: 'dev/formalab', element: <FormalabStatusPage /> }],
}

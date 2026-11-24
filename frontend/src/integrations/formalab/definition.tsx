import { lazyRoute } from '@/routes/lazy-route'
import { FormalabCategorySection } from '@/integrations/formalab/components/category-settings-section'
import { FORMALAB_KEY } from '@/integrations/formalab/definition-key'
import { LearnerStatementAction } from '@/integrations/formalab/learner-statement/learner-statement-action'
import { useFormalabWorkOrderTab } from '@/integrations/formalab/use-formalab-work-order-tab'
import type { IntegrationDefinition } from '@/integrations/types'

const FormalabStatusPage = lazyRoute(() => import('@/integrations/formalab/components/formalab-status-page'))
const FormalabClassesPage = lazyRoute(() => import('@/integrations/formalab/components/formalab-classes-page'))

export const formalabIntegration: IntegrationDefinition = {
  key: FORMALAB_KEY,
  useWorkOrderTabs: useFormalabWorkOrderTab,
  ProductCategorySection: FormalabCategorySection,
  WorkOrderAccountingActions: LearnerStatementAction,
  devRoutes: [
    { path: 'dev/formalab', element: <FormalabStatusPage /> },
    { path: 'dev/formalab/classes', element: <FormalabClassesPage /> },
  ],
}

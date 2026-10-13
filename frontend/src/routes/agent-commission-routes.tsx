import type { RouteObject } from 'react-router-dom'
import { lazyRoute } from '@/routes/lazy-route'

// Agent commissions section (spec 0212), split out of router.tsx to keep it under the file-size limit
// (engineering.md §6). Deep-link routes (`new`/`:id`/`:id/edit`) of the profiles come from the module registry.
const AgentFiscalProfilesPage = lazyRoute(() => import('@/pages/agent-fiscal-profiles-page'))
const CommissionAccrualsPage = lazyRoute(() => import('@/pages/commission-accruals-page'))

export const AGENT_COMMISSION_ROUTES: RouteObject[] = [
  { path: 'agent-fiscal-profiles', element: <AgentFiscalProfilesPage /> },
  { path: 'commission-accruals', element: <CommissionAccrualsPage /> },
]

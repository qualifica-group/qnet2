import type { RouteObject } from 'react-router-dom'
import { lazyRoute } from '@/routes/lazy-route'

// Agent commissions section (specs 0212, 0213), split out of router.tsx to keep it under the file-size limit
// (engineering.md §6). Deep-link routes (`new`/`:id`/`:id/edit`) of the profiles and of the ENASARCO
// parameters come from the module registry.
const AgentFiscalProfilesPage = lazyRoute(() => import('@/pages/agent-fiscal-profiles-page'))
const CommissionAccrualsPage = lazyRoute(() => import('@/pages/commission-accruals-page'))
const CommissionSettlementRunsPage = lazyRoute(() => import('@/pages/commission-settlement-runs-page'))
const CommissionSettlementsPage = lazyRoute(() => import('@/pages/commission-settlements-page'))
const CommissionSettlementDetailPage = lazyRoute(() => import('@/pages/commission-settlement-detail-page'))
const EnasarcoParametersPage = lazyRoute(() => import('@/pages/enasarco-parameters-page'))

export const AGENT_COMMISSION_ROUTES: RouteObject[] = [
  { path: 'agent-fiscal-profiles', element: <AgentFiscalProfilesPage /> },
  { path: 'commission-accruals', element: <CommissionAccrualsPage /> },
  { path: 'commission-settlement-runs', element: <CommissionSettlementRunsPage /> },
  { path: 'commission-settlements', element: <CommissionSettlementsPage /> },
  { path: 'commission-settlements/:id', element: <CommissionSettlementDetailPage /> },
  { path: 'enasarco-parameters', element: <EnasarcoParametersPage /> },
]

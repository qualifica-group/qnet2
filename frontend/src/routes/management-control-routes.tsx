import type { RouteObject } from 'react-router-dom'
import { lazyRoute } from '@/routes/lazy-route'

// Management control section (specs 0268-0271), split out of router.tsx to keep it under the file-size limit
// (engineering.md §6).
const IncomeStatementPage = lazyRoute(() => import('@/pages/management-control-income-statement-page'))
const ExposurePage = lazyRoute(() => import('@/pages/management-control-exposure-page'))
const CashFlowPage = lazyRoute(() => import('@/pages/management-control-cash-flow-page'))
const TrendPage = lazyRoute(() => import('@/pages/management-control-trend-page'))
const BillingPage = lazyRoute(() => import('@/pages/management-control-billing-page'))
const AccountsPage = lazyRoute(() => import('@/pages/management-control-accounts-page'))

export const MANAGEMENT_CONTROL_ROUTES: RouteObject[] = [
  { path: 'management-control/income-statement', element: <IncomeStatementPage /> },
  { path: 'management-control/exposure', element: <ExposurePage /> },
  { path: 'management-control/cash-flow', element: <CashFlowPage /> },
  { path: 'management-control/trend', element: <TrendPage /> },
  { path: 'management-control/billing', element: <BillingPage /> },
  { path: 'management-control/accounts', element: <AccountsPage /> },
]

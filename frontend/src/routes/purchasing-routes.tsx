import type { RouteObject } from 'react-router-dom'
import { lazyRoute } from '@/routes/lazy-route'

// Purchasing section (RDA spec 0208, ODA + cost types spec 0210, payable installments spec 0211, passive invoices spec 0250, status catalogs spec 0248), split out of
// router.tsx to keep it under the file-size limit (engineering.md §6).
const PurchaseRequestsPage = lazyRoute(() => import('@/pages/purchase-requests-page'))
const PurchaseRequestFormPage = lazyRoute(() => import('@/pages/purchase-request-form-page'))
const PurchaseRequestLinesPage = lazyRoute(() => import('@/pages/purchase-request-lines-page'))
const PurchaseOrdersPage = lazyRoute(() => import('@/pages/purchase-orders-page'))
const PurchaseOrderFormPage = lazyRoute(() => import('@/pages/purchase-order-form-page'))
const CostTypesPage = lazyRoute(() => import('@/pages/cost-types-page'))
const PurchaseRequestPrioritiesPage = lazyRoute(() => import('@/pages/purchase-request-priorities-page'))
const PurchaseOrderStatusesPage = lazyRoute(() => import('@/pages/purchase-order-statuses-page'))
const PurchaseRequestLineStatusesPage = lazyRoute(() => import('@/pages/purchase-request-line-statuses-page'))
const PurchaseInvoicesPage = lazyRoute(() => import('@/pages/purchase-invoices-page'))
const PurchaseInvoiceInstallmentsPage = lazyRoute(() => import('@/pages/purchase-invoice-installments-page'))

export const PURCHASING_ROUTES: RouteObject[] = [
  { path: 'purchase-requests', element: <PurchaseRequestsPage /> },
  { path: 'purchase-requests/new', element: <PurchaseRequestFormPage /> },
  { path: 'purchase-requests/:id', element: <PurchaseRequestFormPage /> },
  { path: 'purchase-request-lines', element: <PurchaseRequestLinesPage /> },
  { path: 'purchase-orders', element: <PurchaseOrdersPage /> },
  { path: 'purchase-orders/new', element: <PurchaseOrderFormPage /> },
  { path: 'purchase-orders/:id', element: <PurchaseOrderFormPage /> },
  { path: 'cost-types', element: <CostTypesPage /> },
  { path: 'purchase-request-priorities', element: <PurchaseRequestPrioritiesPage /> },
  { path: 'purchase-order-statuses', element: <PurchaseOrderStatusesPage /> },
  { path: 'purchase-request-line-statuses', element: <PurchaseRequestLineStatusesPage /> },
  { path: 'purchase-invoices', element: <PurchaseInvoicesPage /> },
  { path: 'purchase-invoice-installments', element: <PurchaseInvoiceInstallmentsPage /> },
]

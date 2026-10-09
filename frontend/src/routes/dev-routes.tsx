import { Navigate, type RouteObject } from 'react-router-dom'
import { RedirectKeepingLocation } from '@/routes/redirect-keeping-location'
import { lazyRoute } from '@/routes/lazy-route'
import { MigrationRouteGuard } from '@/features/migrations/migration-route-guard'

const MigrationsPage = lazyRoute(() => import('@/features/migrations/migrations-page'))
const SystemHealthPage = lazyRoute(() => import('@/features/system-health/system-health-page'))
const ApiIntegrationsPage = lazyRoute(
  () => import('@/features/api-integrations/api-integrations-page'),
)
const ApiDocsPage = lazyRoute(() => import('@/features/api-integrations/api-docs-page'))

/** Routes of the "Develop" menu section, plus redirects from their former URLs. */
export const devRoutes: RouteObject[] = [
  {
    // Breadcrumb target of `/dev/*`: `/dev` has no page of its own.
    path: 'dev',
    element: <Navigate to="/dev/api-docs" replace />,
  },
  {
    element: <MigrationRouteGuard />,
    children: [
      { path: 'dev/migrations', element: <MigrationsPage /> },
      { path: 'dev/system-health', element: <SystemHealthPage /> },
    ],
  },
  { path: 'dev/api-clients', element: <ApiIntegrationsPage /> },
  { path: 'dev/api-docs', element: <ApiDocsPage /> },
  { path: 'migrations/*', element: <RedirectKeepingLocation to="/dev/migrations" /> },
  { path: 'admin/system-health', element: <RedirectKeepingLocation to="/dev/system-health" /> },
  { path: 'admin/api-integrations', element: <RedirectKeepingLocation to="/dev/api-clients" /> },
]

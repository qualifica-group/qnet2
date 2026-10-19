import type { RouteObject } from 'react-router-dom'
import { lazyRoute } from '@/routes/lazy-route'

// HR section (job roles 0271, collective agreements 0272, org chart 0273),
// split out of router.tsx to keep it under the file-size limit
// (engineering.md §6). Permission gates live inside each page (`<Can>`).
const CollectiveAgreementsPage = lazyRoute(() => import('@/pages/collective-agreements-page'))
const JobRolesPage = lazyRoute(() => import('@/pages/job-roles-page'))
const JobActivitiesPage = lazyRoute(() => import('@/pages/job-activities-page'))
const OrgChartPage = lazyRoute(() => import('@/pages/org-chart-page'))

export const HR_ROUTES: RouteObject[] = [
  { path: 'collective-agreements', element: <CollectiveAgreementsPage /> },
  { path: 'job-roles', element: <JobRolesPage /> },
  { path: 'job-activities', element: <JobActivitiesPage /> },
  { path: 'org-chart', element: <OrgChartPage /> },
]

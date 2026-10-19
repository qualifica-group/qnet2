import type { RouteObject } from 'react-router-dom'
import { lazyRoute } from '@/routes/lazy-route'

// HR section (job roles 0271, collective agreements 0272, org chart 0273, absences 0278, staff training 0284, skills 0286),
// split out of router.tsx to keep it under the file-size limit
// (engineering.md §6). Permission gates live inside each page (`<Can>`).
const CollectiveAgreementsPage = lazyRoute(() => import('@/pages/collective-agreements-page'))
const JobRolesPage = lazyRoute(() => import('@/pages/job-roles-page'))
const JobActivitiesPage = lazyRoute(() => import('@/pages/job-activities-page'))
const AbsencesPage = lazyRoute(() => import('@/features/absences/absences-page'))
const AbsenceTypesPage = lazyRoute(() => import('@/features/absence-types/absence-types-page'))
const StaffTrainingsPage = lazyRoute(() => import('@/features/staff-trainings/staff-trainings-page'))
const StaffCoursesPage = lazyRoute(() => import('@/features/staff-courses/staff-courses-page'))
const UserSkillsPage = lazyRoute(() => import('@/features/user-skills/user-skills-page'))
const SkillsPage = lazyRoute(() => import('@/features/skills/skills-page'))
const OrgChartPage = lazyRoute(() => import('@/pages/org-chart-page'))

export const HR_ROUTES: RouteObject[] = [
  { path: 'collective-agreements', element: <CollectiveAgreementsPage /> },
  { path: 'job-roles', element: <JobRolesPage /> },
  { path: 'job-activities', element: <JobActivitiesPage /> },
  { path: 'org-chart', element: <OrgChartPage /> },
  { path: 'absences', element: <AbsencesPage /> },
  { path: 'absence-types', element: <AbsenceTypesPage /> },
  { path: 'staff-trainings', element: <StaffTrainingsPage /> },
  { path: 'staff-courses', element: <StaffCoursesPage /> },
  { path: 'user-skills', element: <UserSkillsPage /> },
  { path: 'skills', element: <SkillsPage /> },
]

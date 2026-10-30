import type { RouteObject } from 'react-router-dom'
import { lazyRoute } from '@/routes/lazy-route'

// Task module (spec 0101, templates spec 0124), split out of router.tsx to keep
// it under the file-size limit (engineering.md §6). ONLY the list routes are
// declared by hand: every `new`/`:id`/`:id/edit` deep link is generated from
// the module registry by `buildModuleRoutes()` (spec 0042, AC-012), since all
// the `*-screens.tsx` export a `moduleScreen`.
const TasksPage = lazyRoute(() => import('@/pages/tasks-page'))
const TaskStatusesPage = lazyRoute(() => import('@/pages/task-statuses-page'))
const TaskTypesPage = lazyRoute(() => import('@/pages/task-types-page'))
const SupportTopicsPage = lazyRoute(() => import('@/pages/support-topics-page'))
const TaskCategoriesPage = lazyRoute(() => import('@/pages/task-categories-page'))
const TaskPrioritiesPage = lazyRoute(() => import('@/pages/task-priorities-page'))
const TaskImportancesPage = lazyRoute(() => import('@/pages/task-importances-page'))
const TaskTemplatesPage = lazyRoute(() => import('@/pages/task-templates-page'))

export const TASK_ROUTES: RouteObject[] = [
  { path: 'tasks', element: <TasksPage /> },
  { path: 'task-statuses', element: <TaskStatusesPage /> },
  { path: 'task-types', element: <TaskTypesPage /> },
  { path: 'task-categories', element: <TaskCategoriesPage /> },
  { path: 'support-topics', element: <SupportTopicsPage /> },
  { path: 'task-priorities', element: <TaskPrioritiesPage /> },
  { path: 'task-importances', element: <TaskImportancesPage /> },
  { path: 'task-templates', element: <TaskTemplatesPage /> },
]

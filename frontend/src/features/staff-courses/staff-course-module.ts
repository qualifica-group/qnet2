import { OPEN_MODE_MODAL } from '@/features/modules/types'
import { STAFF_COURSES_DOMAIN } from '@/features/staff-courses/types'
import type { ModuleDefinition } from '@/features/modules/types'

/** Auto-registered in the module registry (spec 0042). The screens load on first use from `staff-course-screens.tsx`. */
export const moduleDefinition: ModuleDefinition = {
  domain: STAFF_COURSES_DOMAIN,
  basePath: `/${STAFF_COURSES_DOMAIN}`,
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.staffCourses',
  // The detail IS the edit form: no edit route, no Edit button.
  generateEditRoute: false,
  detailOwnsEditAction: true,
  // The create form renders its own identity band, so hosts must not stack a second heading.
  formOwnsHeader: true,
}

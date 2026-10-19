import { useWatch, type Control } from 'react-hook-form'
import { useForSelectLabels } from '@/features/for-select/use-for-select'
import { JOB_ROLES_FOR_SELECT_RESOURCE } from '@/features/job-roles/for-select-api'
import type { StaffCourseFormValues } from '@/features/staff-courses/staff-course-schema'
import type { StaffCourseJobRole } from '@/features/staff-courses/types'

/** Shown while a label resolves: never the bare id. */
const PENDING_LABEL = '…'

/** Stable empty default: a fresh array per render would break the label hook's memo. */
const NO_IDS: number[] = []

/** The create draft's picked job roles as named records, via the cache the picker fills for its own trigger. */
export function useStaffCourseDraftRoles(control: Control<StaffCourseFormValues>): StaffCourseJobRole[] {
  const ids = useWatch({ control, name: 'job_role_ids' }) ?? NO_IDS
  const labels = useForSelectLabels({ resource: JOB_ROLES_FOR_SELECT_RESOURCE, ids })

  return ids.map((id) => ({ id, name: labels.get(id)?.label ?? PENDING_LABEL }))
}

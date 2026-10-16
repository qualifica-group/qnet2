import { useWatch, type Control } from 'react-hook-form'
import { useForSelectLabels } from '@/features/for-select/use-for-select'
import { JOB_ACTIVITIES_FOR_SELECT_RESOURCE } from '@/features/job-activities/for-select-api'
import type { JobRoleFormValues } from '@/features/job-roles/use-job-role-form'
import type { JobRoleActivity } from '@/features/job-roles/types'

/** Shown while a label resolves: never the bare id. */
const PENDING_LABEL = '…'

/** Stable empty default: a fresh array per render would break the label hook's memo. */
const NO_IDS: number[] = []

/** The create draft's picked activities as named records, via the cache the picker fills for its own trigger. */
export function useJobRoleDraftActivities(control: Control<JobRoleFormValues>): JobRoleActivity[] {
  const ids = useWatch({ control, name: 'job_activity_ids' }) ?? NO_IDS
  const labels = useForSelectLabels({ resource: JOB_ACTIVITIES_FOR_SELECT_RESOURCE, ids })

  return ids.map((id) => ({ id, name: labels.get(id)?.label ?? PENDING_LABEL }))
}

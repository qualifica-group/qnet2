import { useWatch, type Control } from 'react-hook-form'
import { useForSelectLabels } from '@/features/for-select/use-for-select'
import { JOB_ROLES_FOR_SELECT_RESOURCE } from '@/features/job-roles/for-select-api'
import type { SkillFormValues } from '@/features/skills/skill-schema'
import type { SkillJobRole } from '@/features/skills/types'

/** Shown while a label resolves: never the bare id. */
const PENDING_LABEL = '…'

/** Stable empty default: a fresh array per render would break the label hook's memo. */
const NO_ROWS: SkillFormValues['job_roles'] = []
const NO_IDS: number[] = []

/** The create draft's picked job roles as named records with their level, via the cache the pickers fill. */
export function useSkillDraftJobRoles(control: Control<SkillFormValues>): SkillJobRole[] {
  const rows = useWatch({ control, name: 'job_roles' }) ?? NO_ROWS
  const ids = rows.flatMap((row) => (row.job_role_id === null ? NO_IDS : [row.job_role_id]))
  const labels = useForSelectLabels({ resource: JOB_ROLES_FOR_SELECT_RESOURCE, ids })

  return rows.flatMap((row) =>
    row.job_role_id === null
      ? []
      : [{ id: row.job_role_id, name: labels.get(row.job_role_id)?.label ?? PENDING_LABEL, min_level: row.min_level }],
  )
}

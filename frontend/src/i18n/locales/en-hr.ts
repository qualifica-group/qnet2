/**
 * HR domain: Job roles, Job activities, Collective agreements catalogues and org chart (specs 0271, 0272, 0273).
 * Grouped here to keep `en.ts` within the size limits (`engineering.md` §6).
 */

import { collectiveAgreements } from './en-collective-agreements'
import { jobActivities } from './en-job-activities'
import { jobRoles } from './en-job-roles'
import { orgChart } from './en-org-chart'

export const hr = {
  collectiveAgreements,
  jobActivities,
  jobRoles,
  orgChart,
}

/**
 * HR domain: Job roles, Job activities and Collective agreements catalogues (specs 0271, 0272).
 * Grouped here to keep `it.ts` within the size limits (`engineering.md` §6).
 */

import { collectiveAgreements } from './it-collective-agreements'
import { jobActivities } from './it-job-activities'
import { jobRoles } from './it-job-roles'

export const hr = {
  collectiveAgreements,
  jobActivities,
  jobRoles,
}

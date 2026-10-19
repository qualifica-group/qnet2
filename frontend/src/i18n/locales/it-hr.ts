/**
 * HR domain: Job roles, Job activities and Collective agreements catalogues (specs 0271, 0272).
 * Grouped here to keep `it.ts` within the size limits (`engineering.md` §6).
 */

import { absenceTypes } from './it-absence-types'
import { absenceBalances } from './it-absence-balances'
import { absences } from './it-absences'
import { collectiveAgreements } from './it-collective-agreements'
import { jobActivities } from './it-job-activities'
import { jobRoles } from './it-job-roles'
import { orgChart } from './it-org-chart'
import { staffCourses } from './it-staff-courses'
import { staffTrainings } from './it-staff-trainings'

export const hr = {
  absences: { ...absences, balances: absenceBalances },
  absenceTypes,
  collectiveAgreements,
  jobActivities,
  jobRoles,
  orgChart,
  staffCourses,
  staffTrainings,
}

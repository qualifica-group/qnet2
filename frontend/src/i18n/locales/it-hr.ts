/**
 * HR domain: Job roles and Job activities catalogues (spec 0271).
 * Grouped here to keep `it.ts` within the size limits (`engineering.md` §6).
 */

import { absenceTypes } from './it-absence-types'
import { absenceBalances } from './it-absence-balances'
import { absences } from './it-absences'
import { contractTypes } from './it-contract-types'
import { jobActivities } from './it-job-activities'
import { jobRoles } from './it-job-roles'
import { orgChart } from './it-org-chart'
import { staffCourses } from './it-staff-courses'
import { staffTrainings } from './it-staff-trainings'
import { skills } from './it-skills'
import { userSkills } from './it-user-skills'

export const hr = {
  absences: { ...absences, balances: absenceBalances },
  absenceTypes,
  contractTypes,
  jobActivities,
  jobRoles,
  orgChart,
  staffCourses,
  staffTrainings,
  skills,
  userSkills,
}

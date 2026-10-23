/**
 * HR domain: Job roles, Job activities catalogues and org chart (specs 0271, 0273).
 * Grouped here to keep `en.ts` within the size limits (`engineering.md` §6).
 */

import { absenceTypes } from './en-absence-types'
import { absenceBalances } from './en-absence-balances'
import { absences } from './en-absences'
import { companyClosures } from './en-company-closures'
import { contractTypes } from './en-contract-types'
import { jobActivities } from './en-job-activities'
import { jobRoles } from './en-job-roles'
import { orgChart } from './en-org-chart'
import { staffCourses } from './en-staff-courses'
import { staffTrainings } from './en-staff-trainings'
import { skills } from './en-skills'
import { stock } from './en-stock'
import { userSkills } from './en-user-skills'

export const hr = {
  absences: { ...absences, balances: absenceBalances },
  absenceTypes,
  companyClosures,
  contractTypes,
  jobActivities,
  jobRoles,
  orgChart,
  staffCourses,
  staffTrainings,
  skills,
  userSkills,
  stock,
}

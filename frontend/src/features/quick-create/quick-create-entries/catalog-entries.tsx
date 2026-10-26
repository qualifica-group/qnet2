import { lazy } from 'react'
import type { QuickCreateEntry, QuickCreateFormProps } from '@/features/quick-create/types'
import { COST_TYPES_FOR_SELECT_RESOURCE } from '@/features/cost-types/for-select-api'
import { JOB_ROLES_FOR_SELECT_RESOURCE } from '@/features/job-roles/for-select-api'
import { JOB_ACTIVITIES_FOR_SELECT_RESOURCE } from '@/features/job-activities/for-select-api'
import { SKILLS_DOMAIN } from '@/features/skills/types'
import { STAFF_COURSES_DOMAIN } from '@/features/staff-courses/types'

/**
 * Quick-create entries for the HR and cost catalogs (cost types, job roles,
 * job activities, skills, staff courses): their create form takes no mode and
 * their select label is the record's `name`, like the entries in
 * `lookup-entries.tsx` (spec 0028 D3: every async relation to a module).
 */

const costTypes: QuickCreateEntry = {
  titleKey: 'costTypes.form.createTitle',
  descriptionKey: 'costTypes.form.createSubtitle',
  permission: 'cost-types.create',
  form: lazy(async () => {
    const { CostTypeForm } = await import('@/features/cost-types/cost-type-form')
    return {
      default: ({ onSuccess, onCancel }: QuickCreateFormProps) => (
        <CostTypeForm onSuccess={(costType) => onSuccess({ id: costType.id, name: costType.name })} onCancel={onCancel} />
      ),
    }
  }),
}

const jobRoles: QuickCreateEntry = {
  titleKey: 'jobRoles.form.createTitle',
  descriptionKey: 'jobRoles.form.createSubtitle',
  permission: 'job-roles.create',
  form: lazy(async () => {
    const { JobRoleForm } = await import('@/features/job-roles/job-role-form')
    return {
      default: ({ onSuccess, onCancel }: QuickCreateFormProps) => (
        <JobRoleForm onSuccess={(jobRole) => onSuccess({ id: jobRole.id, name: jobRole.name })} onCancel={onCancel} />
      ),
    }
  }),
}

const jobActivities: QuickCreateEntry = {
  titleKey: 'jobActivities.form.createTitle',
  descriptionKey: 'jobActivities.form.createSubtitle',
  permission: 'job-activities.create',
  form: lazy(async () => {
    const { JobActivityForm } = await import('@/features/job-activities/job-activity-form')
    return {
      default: ({ onSuccess, onCancel }: QuickCreateFormProps) => (
        <JobActivityForm onSuccess={(jobActivity) => onSuccess({ id: jobActivity.id, name: jobActivity.name })} onCancel={onCancel} />
      ),
    }
  }),
}

const skills: QuickCreateEntry = {
  titleKey: 'skills.form.createTitle',
  descriptionKey: 'skills.form.createSubtitle',
  permission: 'skills.create',
  form: lazy(async () => {
    const { SkillForm } = await import('@/features/skills/skill-form')
    return {
      default: ({ onSuccess, onCancel }: QuickCreateFormProps) => (
        <SkillForm onSuccess={(skill) => onSuccess({ id: skill.id, name: skill.name })} onCancel={onCancel} />
      ),
    }
  }),
}

const staffCourses: QuickCreateEntry = {
  titleKey: 'staffCourses.form.createTitle',
  descriptionKey: 'staffCourses.form.createSubtitle',
  permission: 'staff-courses.create',
  form: lazy(async () => {
    const { StaffCourseForm } = await import('@/features/staff-courses/staff-course-form')
    return {
      default: ({ onSuccess, onCancel }: QuickCreateFormProps) => (
        <StaffCourseForm onSuccess={(course) => onSuccess({ id: course.id, name: course.name })} onCancel={onCancel} />
      ),
    }
  }),
}

/** resource -> entry, for the HR and cost catalogs covered by this file. */
export const catalogEntries: Record<string, QuickCreateEntry> = {
  [COST_TYPES_FOR_SELECT_RESOURCE]: costTypes,
  [JOB_ROLES_FOR_SELECT_RESOURCE]: jobRoles,
  [JOB_ACTIVITIES_FOR_SELECT_RESOURCE]: jobActivities,
  [SKILLS_DOMAIN]: skills,
  [STAFF_COURSES_DOMAIN]: staffCourses,
}

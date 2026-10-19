import { z } from 'zod'
import type { TFunction } from 'i18next'
import { dayOf } from '@/features/absences/calendar/calendar-month'
import { STAFF_TRAINING_OUTCOMES } from '@/features/staff-trainings/types'

/** Backend `notes` limit (`max:2000`, spec 0284). */
export const NOTES_MAX_LENGTH = 2000

/** Zod schema of the training form, mirroring the server rules: person, course, completion day not after today, outcome, notes. */
export function buildStaffTrainingSchema(t: TFunction) {
  return z
    .object({
      user_id: z.number().nullable(),
      staff_course_id: z.number().nullable(),
      completed_on: z.string(),
      outcome: z.enum(STAFF_TRAINING_OUTCOMES),
      notes: z.string().max(NOTES_MAX_LENGTH, t('staffTrainings.form.notesTooLong')),
    })
    .superRefine((values, ctx) => {
      if (values.user_id === null) {
        ctx.addIssue({ code: 'custom', path: ['user_id'], message: t('staffTrainings.form.userRequired') })
      }
      if (values.staff_course_id === null) {
        ctx.addIssue({ code: 'custom', path: ['staff_course_id'], message: t('staffTrainings.form.courseRequired') })
      }
      if (values.completed_on === '') {
        ctx.addIssue({ code: 'custom', path: ['completed_on'], message: t('staffTrainings.form.dateRequired') })
      } else if (values.completed_on > dayOf(new Date())) {
        ctx.addIssue({ code: 'custom', path: ['completed_on'], message: t('staffTrainings.form.dateInFuture') })
      }
    })
}

export type StaffTrainingFormValues = z.infer<ReturnType<typeof buildStaffTrainingSchema>>

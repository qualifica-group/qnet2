import type { StaffTrainingFormValues } from '@/features/staff-trainings/staff-training-schema'
import type { StaffTraining, StaffTrainingPayload } from '@/features/staff-trainings/types'

export const EMPTY_STAFF_TRAINING_FORM: StaffTrainingFormValues = {
  user_id: null,
  staff_course_id: null,
  completed_on: '',
  outcome: 'passed',
  notes: '',
}

/** Form values of a saved training. */
export function staffTrainingFormValues(training: StaffTraining): StaffTrainingFormValues {
  return {
    user_id: training.user.id,
    staff_course_id: training.course.id,
    completed_on: training.completed_on,
    outcome: training.outcome,
    notes: training.notes ?? '',
  }
}

/** Wire payload; null only when the schema was bypassed (a required id missing). A blank note is sent as `null`. */
export function toStaffTrainingPayload(values: StaffTrainingFormValues): StaffTrainingPayload | null {
  if (values.user_id === null || values.staff_course_id === null) {
    return null
  }
  const notes = values.notes.trim()
  return {
    user_id: values.user_id,
    staff_course_id: values.staff_course_id,
    completed_on: values.completed_on,
    outcome: values.outcome,
    notes: notes === '' ? null : notes,
  }
}

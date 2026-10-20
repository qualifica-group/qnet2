import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { serverMessage } from '@/features/absences/requests/absence-server-errors'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { createStaffTraining, updateStaffTraining } from '@/features/staff-trainings/api'
import {
  EMPTY_STAFF_TRAINING_FORM,
  staffTrainingFormValues,
  toStaffTrainingPayload,
} from '@/features/staff-trainings/staff-training-payload'
import {
  buildStaffTrainingSchema,
  type StaffTrainingFormValues,
} from '@/features/staff-trainings/staff-training-schema'
import { useStaffTrainingRefresh } from '@/features/staff-trainings/use-staff-training-refresh'
import type { StaffTraining } from '@/features/staff-trainings/types'

/** Server-side field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS = ['user_id', 'staff_course_id', 'completed_on', 'outcome', 'notes'] as const

interface UseStaffTrainingFormArgs {
  /** The training being edited; omitted for a new one. */
  training?: StaffTraining
  refreshGrid?: () => void
  onSaved: (saved: StaffTraining) => void
}

/**
 * Owns every non-render concern of the training form: RHF/Zod wiring, the create/update call and
 * the 422 mapping. After a save the expiry is computed by the server and shown in the detail.
 */
export function useStaffTrainingForm({ training, refreshGrid, onSaved }: UseStaffTrainingFormArgs) {
  const { t } = useTranslation()
  const refresh = useStaffTrainingRefresh(refreshGrid)
  const [isSubmitting, setIsSubmitting] = useState(false)

  const schema = useMemo(() => buildStaffTrainingSchema(t), [t])
  const form = useForm<StaffTrainingFormValues>({
    resolver: zodResolver(schema),
    defaultValues: training ? staffTrainingFormValues(training) : EMPTY_STAFF_TRAINING_FORM,
  })

  async function persist(values: StaffTrainingFormValues) {
    const payload = toStaffTrainingPayload(values)
    if (payload === null) {
      return
    }
    setIsSubmitting(true)
    try {
      const saved = training ? await updateStaffTraining(training.id, payload) : await createStaffTraining(payload)
      refresh(saved.id)
      toast.success(t(training ? 'staffTrainings.form.updated' : 'staffTrainings.form.created'))
      onSaved(saved)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, [...SERVER_ERROR_FIELDS])) {
        toast.error(serverMessage(error) ?? t('staffTrainings.form.error'))
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  const submit = form.handleSubmit(persist)

  return { form, isSubmitting, submit }
}

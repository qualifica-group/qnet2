import { useTranslation } from 'react-i18next'
import type { Control } from 'react-hook-form'
import { FormControl } from '@/components/ui/form'
import { Switch } from '@/components/ui/switch'
import { MetaField } from '@/features/authorization/MetaField'
import type { TaskFormValues } from '@/features/tasks/task-schema'

/*
 * "Chiusura" (spec 0121 D-7, RECTIFIES spec 0116's shape of this section):
 * two independent flags, `requires_closure_feedback` and `requires_validation`,
 * each a plain `MetaField` switch with a short label and its explanation in
 * the (i) hint. The feedback TEXT is not collected here — it is written only
 * from the completion pop-up (`TaskCompleteDialog`).
 *
 * Spec 0154 D-6 adds a THIRD, create-only switch: "Crea gia' completato"
 * closes the task outright at the estimated minutes, bypassing validation —
 * unlike the two flags above, `is_completed` gates nothing else in the form.
 *
 * Each field stands alone (spec 0195 D-3): the create form lays them out in
 * its "Chiusura" section, the task detail edits the two flags in place.
 */

/** The i18n leaves of each closure flag: its label and its (i) hint. */
const CLOSURE_FLAG_LABEL_KEYS = {
  requires_closure_feedback: { label: 'tasks.form.requiresClosureFeedback', hint: 'tasks.form.requiresClosureFeedbackHint' },
  requires_validation: { label: 'tasks.form.requiresValidation', hint: 'tasks.form.requiresValidationHint' },
} as const

interface TaskClosureFlagFieldProps {
  control: Control<TaskFormValues>
  name: keyof typeof CLOSURE_FLAG_LABEL_KEYS
}

export function TaskClosureFlagField({ control, name }: TaskClosureFlagFieldProps) {
  const { t } = useTranslation()
  const keys = CLOSURE_FLAG_LABEL_KEYS[name]

  return (
    <MetaField control={control} name={name} metaKey={name} label={t(keys.label)} hint={t(keys.hint)} layout="inline">
      {({ field, disabled }) => (
        <FormControl>
          <Switch checked={field.value} onCheckedChange={field.onChange} disabled={disabled} />
        </FormControl>
      )}
    </MetaField>
  )
}

/** Spec 0154 D-6: "Crea gia' completato", create only. */
export function TaskIsCompletedField({ control }: { control: Control<TaskFormValues> }) {
  const { t } = useTranslation()

  return (
    <MetaField
      control={control}
      name="is_completed"
      metaKey="is_completed"
      label={t('tasks.form.isCompleted')}
      hint={t('tasks.form.isCompletedHint')}
      layout="inline"
    >
      {({ field, disabled }) => (
        <FormControl>
          <Switch checked={field.value} onCheckedChange={field.onChange} disabled={disabled} />
        </FormControl>
      )}
    </MetaField>
  )
}

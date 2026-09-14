import { useTranslation } from 'react-i18next'
import { MessageSquareWarning } from 'lucide-react'
import type { Control } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { FormControl } from '@/components/ui/form'
import { Switch } from '@/components/ui/switch'
import { MetaField } from '@/features/authorization/MetaField'
import { useResourcePermissions } from '@/features/authorization/permissions'
import type { TaskFormValues } from '@/features/tasks/task-schema'

interface TaskClosureSectionProps {
  control: Control<TaskFormValues>
}

/**
 * "Chiusura" (spec 0121 D-7, RECTIFIES spec 0116's shape of this section):
 * two independent flags, `requires_closure_feedback` and `requires_validation`,
 * each a plain `MetaField` switch. The feedback TEXT is no longer collected
 * here — it left the form entirely and is written only from the completion
 * pop-up (`TaskCompleteDialog`), so this section renders neither a textarea
 * nor any requiredness rule that used to depend on the picked status' phase.
 */
export function TaskClosureSection({ control }: TaskClosureSectionProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()

  const feedbackVisible = fieldPermission('requires_closure_feedback').visible
  const validationVisible = fieldPermission('requires_validation').visible

  if (!feedbackVisible && !validationVisible) {
    return null
  }

  return (
    <FormSection
      icon={MessageSquareWarning}
      title={t('tasks.form.sections.closure.title')}
      description={t('tasks.form.sections.closure.description')}
    >
      {feedbackVisible ? (
        <MetaField
          control={control}
          name="requires_closure_feedback"
          metaKey="requires_closure_feedback"
          label={t('tasks.form.requiresClosureFeedback')}
          description={t('tasks.form.requiresClosureFeedbackHint')}
          layout="inline"
        >
          {({ field, disabled }) => (
            <FormControl>
              <Switch checked={field.value} onCheckedChange={field.onChange} disabled={disabled} />
            </FormControl>
          )}
        </MetaField>
      ) : null}

      {validationVisible ? (
        <MetaField
          control={control}
          name="requires_validation"
          metaKey="requires_validation"
          label={t('tasks.form.requiresValidation')}
          description={t('tasks.form.requiresValidationHint')}
          layout="inline"
        >
          {({ field, disabled }) => (
            <FormControl>
              <Switch checked={field.value} onCheckedChange={field.onChange} disabled={disabled} />
            </FormControl>
          )}
        </MetaField>
      ) : null}
    </FormSection>
  )
}

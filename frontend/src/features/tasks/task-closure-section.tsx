import { useTranslation } from 'react-i18next'
import { MessageSquareWarning } from 'lucide-react'
import { useWatch, type Control } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { FormControl } from '@/components/ui/form'
import { Switch } from '@/components/ui/switch'
import { Textarea } from '@/components/ui/textarea'
import { MetaField } from '@/features/authorization/MetaField'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { isClosingStatus } from '@/features/tasks/task-schema'
import type { TaskFormValues } from '@/features/tasks/task-schema'
import type { TaskStatusGroupValue } from '@/features/status-reorder/types'

interface TaskClosureSectionProps {
  control: Control<TaskFormValues>
  /** `group` (phase) of the status currently picked; the ONLY thing this rule branches on (AC-024). */
  statusGroup: TaskStatusGroupValue | null
}

/**
 * "Feedback di chiusura" (D-7). The flag is a plain `MetaField`; the feedback
 * itself is marked required exactly when the flag is on AND the picked status
 * sits in a closing PHASE — a condition on live FORM state that the backend's
 * field-permission ceiling cannot express, so it is decided here. An ordinary
 * status in a closing phase closes the task like a seeded one (rectification
 * of 2026-09-04), so the rule must not look at `system_key`.
 *
 * This REPLICATES the server rule for UX, it does not replace it: the
 * authority stays `TaskClosureFeedbackGuard`, which evaluates the RESULTING
 * status of a partial PATCH (AC-035) — something the client cannot know. The
 * 422 path in `useTaskForm` is therefore never removed.
 *
 * The textarea stays MOUNTED even when the rule is off: unlike the work
 * order's "motivo chiusura", the feedback is a value one may record ahead of
 * the closure, and unmounting it would silently drop what the user typed.
 */
export function TaskClosureSection({ control, statusGroup }: TaskClosureSectionProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const requiresFeedback = useWatch({ control, name: 'requires_closure_feedback' })

  if (!fieldPermission('requires_closure_feedback').visible) {
    return null
  }

  const feedbackRequired = requiresFeedback && isClosingStatus(statusGroup)

  return (
    <FormSection
      icon={MessageSquareWarning}
      title={t('tasks.form.sections.closure.title')}
      description={t('tasks.form.sections.closure.description')}
    >
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

      <MetaField
        control={control}
        name="closure_feedback"
        metaKey="closure_feedback"
        label={t('tasks.form.closureFeedback')}
        required={feedbackRequired}
      >
        {({ field, disabled, readOnly }) => (
          <FormControl>
            <Textarea
              rows={3}
              disabled={disabled}
              readOnly={readOnly}
              value={field.value ?? ''}
              onChange={(event) => field.onChange(event.target.value || null)}
              onBlur={field.onBlur}
              name={field.name}
              ref={field.ref}
            />
          </FormControl>
        )}
      </MetaField>
    </FormSection>
  )
}

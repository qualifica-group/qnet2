import { useTranslation } from 'react-i18next'
import { Label } from '@/components/ui/label'
import { CompletionBar } from '@/components/completion-bar'
import { FIELD_STACK_CLASS } from '@/components/record-form/layout'

interface TaskCompletionReadoutProps {
  /** Derived from the picked status (D-6); `null` while no status is picked. */
  percentage: number | null
}

/**
 * AC-084: the completion percentage shown inside the form. Deliberately NOT a
 * `MetaField` and not bound to react-hook-form — it is a PROJECTION of the
 * picked status (D-6), not a writable field, so it has no key in
 * `TaskFormValues` and can never end up in a payload. `tasks` has no such
 * column: changing the status changes this readout, changing the status'
 * configured percentage changes it for every task in that status.
 */
export function TaskCompletionReadout({ percentage }: TaskCompletionReadoutProps) {
  const { t } = useTranslation()

  return (
    <div className={FIELD_STACK_CLASS}>
      <Label>{t('tasks.form.completionPercentage')}</Label>
      <CompletionBar
        value={percentage}
        label={t('tasks.form.completionPercentage')}
        size="sm"
        barClassName="flex-1"
        valueClassName="w-10 text-right"
      />
      <p className="text-xs text-muted-foreground">{t('tasks.form.completionPercentageHint')}</p>
    </div>
  )
}

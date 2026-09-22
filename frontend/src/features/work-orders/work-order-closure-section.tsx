import { TriangleAlert } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { useWatch, type Control } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { FormControl } from '@/components/ui/form'
import { Switch } from '@/components/ui/switch'
import { Textarea } from '@/components/ui/textarea'
import { MetaField } from '@/features/authorization/MetaField'
import { useResourcePermissions } from '@/features/authorization/permissions'
import type { WorkOrderFormValues } from '@/features/work-orders/use-work-order-form'

interface WorkOrderClosureSectionProps {
  control: Control<WorkOrderFormValues>
  /** Applies the toggle AND, when it turns off, clears the reason (D-4) — owned by `useWorkOrderForm`. */
  onForceClosedChange: (checked: boolean) => void
  /**
   * Spec 0146 D-8/AC-032: the commessa's `open_tasks_count`, `0` on create
   * (there is nothing persisted to close yet). Drives the warning below —
   * never a gate on the switch itself, D-8's side effect is unconditional.
   */
  openTasksCount: number
  /** The PERSISTED `is_force_closed` (edit mode), `false` on create — the warning is about a false→true TRANSITION, not an already-closed commessa reopened and reclosed. */
  wasAlreadyForceClosed: boolean
}

/**
 * "Chiusura forzata" (D-4/AC-073). The switch is a plain `MetaField`, but
 * "Motivo chiusura" is mounted ONLY while the switch is on — a FORM-STATE
 * condition the backend's field-permission ceiling cannot express (it is not
 * about who may edit the field, but about when it applies at all), so it is
 * decided here rather than left to `MetaField`'s own `visible`.
 *
 * Spec 0146 D-8/AC-032: turning the switch ON (from an actually-open
 * commessa) with `open_tasks_count > 0` shows an inline warning naming how
 * many task will be force-closed with a negative outcome — a plain
 * conditional `<p role="alert">` right below the switch, not a separate
 * confirm dialog: the effect is already unconditional server-side (D-8), so
 * this is purely informational, the simplest surface that fits an inline
 * form section already built around conditional rows (mirrors the
 * `force_close_reason` row just below it).
 */
export function WorkOrderClosureSection({
  control,
  onForceClosedChange,
  openTasksCount,
  wasAlreadyForceClosed,
}: WorkOrderClosureSectionProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const isForceClosed = useWatch({ control, name: 'is_force_closed' })
  const showOpenTasksWarning = isForceClosed && !wasAlreadyForceClosed && openTasksCount > 0

  if (!fieldPermission('is_force_closed').visible) {
    return null
  }

  return (
    <FormSection
      icon={TriangleAlert}
      title={t('workOrders.form.sections.closure.title')}
      description={t('workOrders.form.sections.closure.description')}
    >
      <MetaField
        control={control}
        name="is_force_closed"
        metaKey="is_force_closed"
        label={t('workOrders.form.isForceClosed')}
        layout="inline"
      >
        {({ field, disabled }) => (
          <FormControl>
            <Switch checked={field.value} onCheckedChange={onForceClosedChange} disabled={disabled} />
          </FormControl>
        )}
      </MetaField>

      {showOpenTasksWarning ? (
        <p role="alert" className="flex items-start gap-1.5 text-xs font-medium text-amber-600 dark:text-amber-400">
          <TriangleAlert aria-hidden="true" className="mt-0.5 size-3.5 shrink-0" />
          <span>{t('workOrders.form.openTasksWarning', { count: openTasksCount })}</span>
        </p>
      ) : null}

      {isForceClosed && fieldPermission('force_close_reason').visible ? (
        <MetaField
          control={control}
          name="force_close_reason"
          metaKey="force_close_reason"
          label={t('workOrders.form.forceCloseReason')}
          required
        >
          {({ field, disabled, readOnly }) => (
            <FormControl>
              <Textarea
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
      ) : null}
    </FormSection>
  )
}

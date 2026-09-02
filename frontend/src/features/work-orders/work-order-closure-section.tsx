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
}

/**
 * "Chiusura forzata" (D-4/AC-073). The switch is a plain `MetaField`, but
 * "Motivo chiusura" is mounted ONLY while the switch is on — a FORM-STATE
 * condition the backend's field-permission ceiling cannot express (it is not
 * about who may edit the field, but about when it applies at all), so it is
 * decided here rather than left to `MetaField`'s own `visible`.
 */
export function WorkOrderClosureSection({ control, onForceClosedChange }: WorkOrderClosureSectionProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const isForceClosed = useWatch({ control, name: 'is_force_closed' })

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

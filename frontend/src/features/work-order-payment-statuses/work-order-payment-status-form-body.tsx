import { Flag } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { FormSection } from '@/components/form-section'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Switch } from '@/components/ui/switch'
import { Textarea } from '@/components/ui/textarea'
import { Form, FormControl } from '@/components/ui/form'
import { MetaField } from '@/features/authorization/MetaField'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { ColorTokenPicker } from '@/features/custom-fields/components/color-token-picker'
import { useWorkOrderPaymentStatusForm } from '@/features/work-order-payment-statuses/use-work-order-payment-status-form'
import type {
  WorkOrderPaymentStatusDetail,
  WorkOrderPaymentStatusFormMode,
} from '@/features/work-order-payment-statuses/types'

interface WorkOrderPaymentStatusFormBodyProps {
  mode: WorkOrderPaymentStatusFormMode
  onSuccess: (workOrderPaymentStatus: WorkOrderPaymentStatusDetail) => void
  onCancel: () => void
}

/**
 * The work order payment status create/edit form UI. Every field is wrapped
 * in `MetaField` (spec 0004): hidden means absent, non-editable means
 * disabled, `required` comes from the resolved `ResourcePermissions`. There
 * is no system row in this lookup, so every field is editable. `sort_order`
 * has no form field (server-managed). All non-render logic lives in
 * `useWorkOrderPaymentStatusForm`.
 */
export function WorkOrderPaymentStatusFormBody({ mode, onSuccess, onCancel }: WorkOrderPaymentStatusFormBodyProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const { form, serverError, onSubmit } = useWorkOrderPaymentStatusForm({ mode, onSuccess })

  const identityVisible =
    fieldPermission('name').visible ||
    fieldPermission('description').visible ||
    fieldPermission('color').visible ||
    fieldPermission('allows_delivery').visible ||
    fieldPermission('is_active').visible

  return (
    <div className="flex flex-1 flex-col overflow-y-auto">
      <Form {...form}>
        <form
          onSubmit={form.handleSubmit(onSubmit)}
          className="flex flex-col gap-4 p-4"
          noValidate
        >
          {identityVisible && (
            <FormSection
              icon={Flag}
              title={t('workOrderPaymentStatuses.form.sections.identity.title')}
              description={t('workOrderPaymentStatuses.form.sections.identity.description')}
            >
              <MetaField
                control={form.control}
                name="name"
                metaKey="name"
                label={t('workOrderPaymentStatuses.form.name')}
              >
                {({ field, disabled, readOnly }) => (
                  <FormControl>
                    <Input autoComplete="off" disabled={disabled} readOnly={readOnly} {...field} />
                  </FormControl>
                )}
              </MetaField>

              <MetaField
                control={form.control}
                name="description"
                metaKey="description"
                label={t('workOrderPaymentStatuses.form.description')}
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

              <MetaField
                control={form.control}
                name="color"
                metaKey="color"
                label={t('workOrderPaymentStatuses.form.color')}
              >
                {({ field, disabled }) => (
                  <FormControl>
                    <ColorTokenPicker
                      value={field.value}
                      onChange={field.onChange}
                      disabled={disabled}
                    />
                  </FormControl>
                )}
              </MetaField>

              <MetaField
                control={form.control}
                name="is_active"
                metaKey="is_active"
                label={t('workOrderPaymentStatuses.form.isActive')}
              >
                {({ field, disabled }) => (
                  <FormControl>
                    <Switch
                      checked={field.value}
                      onCheckedChange={field.onChange}
                      disabled={disabled}
                    />
                  </FormControl>
                )}
              </MetaField>

              <MetaField
                control={form.control}
                name="allows_delivery"
                metaKey="allows_delivery"
                label={t('workOrderPaymentStatuses.form.allowsDelivery')}
                hint={t('workOrderPaymentStatuses.form.hints.allowsDelivery')}
              >
                {({ field, disabled }) => (
                  <FormControl>
                    <Switch
                      checked={field.value}
                      onCheckedChange={field.onChange}
                      disabled={disabled}
                    />
                  </FormControl>
                )}
              </MetaField>
            </FormSection>
          )}

          {serverError && (
            <p className="text-sm font-medium text-destructive" role="alert">
              {serverError}
            </p>
          )}

          <div className="mt-auto flex justify-end gap-2 pt-2">
            <Button
              type="button"
              variant="outline"
              onClick={onCancel}
              disabled={form.formState.isSubmitting}
            >
              {t('workOrderPaymentStatuses.form.cancel')}
            </Button>
            <Button type="submit" disabled={form.formState.isSubmitting}>
              {form.formState.isSubmitting
                ? t('workOrderPaymentStatuses.form.saving')
                : t('workOrderPaymentStatuses.form.save')}
            </Button>
          </div>
        </form>
      </Form>
    </div>
  )
}

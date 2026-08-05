import { CreditCard } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { FormSection } from '@/components/form-section'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Switch } from '@/components/ui/switch'
import { Textarea } from '@/components/ui/textarea'
import { Form, FormControl } from '@/components/ui/form'
import { MetaField } from '@/features/authorization/MetaField'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { usePaymentMethodForm } from '@/features/payment-methods/use-payment-method-form'
import type {
  PaymentMethodDetail,
  PaymentMethodFormMode,
} from '@/features/payment-methods/types'

interface PaymentMethodFormBodyProps {
  mode: PaymentMethodFormMode
  onSuccess: (paymentMethod: PaymentMethodDetail) => void
  onCancel: () => void
}

/** Formats a raw numeric field's RHF value for a controlled `<input type="number">`. */
function numberInputValue(value: number | null): string {
  return value === null ? '' : String(value)
}

/**
 * The payment method create/edit form UI. `name`, `code`,
 * `payment_method_code`, `description`, `payment_instructions`,
 * `payment_days` and `is_active` are each wrapped in
 * `MetaField` (spec 0004): hidden means absent, non-editable means disabled,
 * `required` comes from the resolved `ResourcePermissions` — no hardcoded
 * permission logic lives here. `code`'s immutability after create (D-3) is
 * NOT a frontend decision: the backend's field-permission ceiling reports it
 * `editable: true` only when there is no model context (create), so on edit
 * `MetaField` disables it automatically — the same mechanism every other
 * field uses, no `isSystemRow`-style hardcoding needed here. `sort_order`
 * has no form field (D-1, server-managed). All non-render logic lives in
 * `usePaymentMethodForm`.
 */
export function PaymentMethodFormBody({ mode, onSuccess, onCancel }: PaymentMethodFormBodyProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const { form, serverError, onSubmit } = usePaymentMethodForm({ mode, onSuccess })

  const identityVisible =
    fieldPermission('name').visible ||
    fieldPermission('code').visible ||
    fieldPermission('payment_method_code').visible ||
    fieldPermission('description').visible ||
    fieldPermission('payment_instructions').visible ||
    fieldPermission('payment_days').visible ||
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
              icon={CreditCard}
              title={t('paymentMethods.form.sections.identity.title')}
              description={t('paymentMethods.form.sections.identity.description')}
            >
              <MetaField
                control={form.control}
                name="name"
                metaKey="name"
                label={t('paymentMethods.form.name')}
              >
                {({ field, disabled, readOnly }) => (
                  <FormControl>
                    <Input autoComplete="off" disabled={disabled} readOnly={readOnly} {...field} />
                  </FormControl>
                )}
              </MetaField>

              <MetaField
                control={form.control}
                name="code"
                metaKey="code"
                label={t('paymentMethods.form.code')}
                hint={mode.type === 'edit' ? t('paymentMethods.form.hints.codeLocked') : undefined}
              >
                {({ field, disabled, readOnly }) => (
                  <FormControl>
                    <Input autoComplete="off" disabled={disabled} readOnly={readOnly} {...field} />
                  </FormControl>
                )}
              </MetaField>

              <MetaField
                control={form.control}
                name="payment_method_code"
                metaKey="payment_method_code"
                label={t('paymentMethods.form.paymentMethodCode')}
                hint={t('paymentMethods.form.hints.paymentMethodCode')}
              >
                {({ field, disabled, readOnly }) => (
                  <FormControl>
                    <Input
                      autoComplete="off"
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
                name="description"
                metaKey="description"
                label={t('paymentMethods.form.description')}
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
                name="payment_instructions"
                metaKey="payment_instructions"
                label={t('paymentMethods.form.paymentInstructions')}
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
                name="payment_days"
                metaKey="payment_days"
                label={t('paymentMethods.form.paymentDays')}
              >
                {({ field, disabled, readOnly }) => (
                  <FormControl>
                    <Input
                      type="number"
                      step="1"
                      min={0}
                      max={3650}
                      disabled={disabled}
                      readOnly={readOnly}
                      value={numberInputValue(field.value)}
                      onChange={(event) =>
                        field.onChange(
                          event.target.value === '' ? null : Number(event.target.value),
                        )
                      }
                      onBlur={field.onBlur}
                      name={field.name}
                      ref={field.ref}
                    />
                  </FormControl>
                )}
              </MetaField>

              <MetaField
                control={form.control}
                name="is_active"
                metaKey="is_active"
                label={t('paymentMethods.form.isActive')}
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
              {t('paymentMethods.form.cancel')}
            </Button>
            <Button type="submit" disabled={form.formState.isSubmitting}>
              {form.formState.isSubmitting
                ? t('paymentMethods.form.saving')
                : t('paymentMethods.form.save')}
            </Button>
          </div>
        </form>
      </Form>
    </div>
  )
}

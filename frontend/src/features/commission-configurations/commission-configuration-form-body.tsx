import { CalendarRange, CircleDollarSign, FileText, Settings2 } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { useWatch } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { RelationSelectField } from '@/components/form/relation-select-field'
import { Button } from '@/components/ui/button'
import { Form, FormControl } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { MetaField } from '@/features/authorization/MetaField'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { PRODUCT_CATEGORIES_FOR_SELECT_RESOURCE } from '@/features/product-categories/for-select-api'
import { PRODUCTS_FOR_SELECT_RESOURCE } from '@/features/products/for-select-api'
import { CommissionConfigurationRecipientField } from './commission-configuration-recipient-field'
import { useCommissionConfigurationForm } from './use-commission-configuration-form'
import type {
  CommissionConfigurationDetail,
  CommissionConfigurationFormMode,
  CommissionRole,
  CommissionScope,
  CommissionStatus,
  CommissionType,
} from './types'

interface Props {
  mode: CommissionConfigurationFormMode
  onSuccess: (configuration: CommissionConfigurationDetail) => void
  onCancel: () => void
}

const OPTIONS = {
  recipient_role: ['COMMERCIAL', 'REPORTER', 'SUPERVISOR', 'SUPPLIER'],
  application_scope: ['PRODUCT_CATEGORY', 'PRODUCT', 'RECIPIENT'],
  commission_type: ['FIXED_AMOUNT', 'PERCENTAGE'],
  status: ['ACTIVE', 'SUSPENDED'],
} as const

export function CommissionConfigurationFormBody({ mode, onSuccess, onCancel }: Props) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const { form, serverError, onSubmit } = useCommissionConfigurationForm({ mode, onSuccess })
  const scope = useWatch({ control: form.control, name: 'application_scope' })
  const role = useWatch({ control: form.control, name: 'recipient_role' })
  const commissionType = useWatch({ control: form.control, name: 'commission_type' })
  const relationLabels = {
    placeholder: t('commissionConfigurations.form.selectPlaceholder'),
    emptyLabel: t('commissionConfigurations.form.selectEmpty'),
    errorLabel: t('commissionConfigurations.form.selectError'),
    clearLabel: t('common.clear'),
    retryLabel: t('common.retry'),
  }
  const selectedCategory =
    mode.type === 'edit' ? mode.configuration.product_category : null
  const selectedProduct = mode.type === 'edit' ? mode.configuration.product : null
  const selectedRecipient = mode.type === 'edit' ? (mode.configuration.recipient ?? null) : null
  const scopeRelationField =
    scope === 'PRODUCT' ? 'product_id' : scope === 'PRODUCT_CATEGORY' ? 'product_category_id' : null
  const scopeVisible =
    fieldPermission('name').visible ||
    fieldPermission('recipient_role').visible ||
    fieldPermission('application_scope').visible ||
    (scopeRelationField ? fieldPermission(scopeRelationField).visible : false) ||
    fieldPermission('recipient_id').visible
  const calculationVisible =
    fieldPermission('commission_type').visible ||
    fieldPermission('value').visible ||
    fieldPermission('priority').visible
  const validityVisible =
    fieldPermission('valid_from').visible ||
    fieldPermission('valid_until').visible ||
    fieldPermission('status').visible

  const selectField = <T extends CommissionRole | CommissionScope | CommissionType | CommissionStatus>(
    name: 'recipient_role' | 'application_scope' | 'commission_type' | 'status',
    options: readonly T[],
    onChangeExtra?: (next: T) => void,
  ) => (
    <MetaField control={form.control} name={name} metaKey={name} label={t(`commissionConfigurations.form.${name}`)}>
      {({ field, disabled }) => (
        <Select
          value={field.value}
          onValueChange={(next) => {
            field.onChange(next)
            onChangeExtra?.(next as T)
          }}
          disabled={disabled}
        >
          <FormControl><SelectTrigger className="w-full"><SelectValue /></SelectTrigger></FormControl>
          <SelectContent>
            {options.map((option) => (
              <SelectItem key={option} value={option}>
                {t(`commissionConfigurations.options.${name}.${option}`)}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      )}
    </MetaField>
  )

  return (
    <div className="flex flex-1 flex-col overflow-y-auto">
      <Form {...form}>
        <form onSubmit={form.handleSubmit(onSubmit)} className="flex flex-col gap-4 p-4" noValidate>
          {scopeVisible ? <FormSection icon={Settings2} title={t('commissionConfigurations.form.sections.scope')}>
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <div className="sm:col-span-2">
                <MetaField control={form.control} name="name" metaKey="name" label={t('commissionConfigurations.form.name')}>
                  {({ field, disabled, readOnly }) => <FormControl><Input {...field} disabled={disabled} readOnly={readOnly} /></FormControl>}
                </MetaField>
              </div>
              {selectField('recipient_role', OPTIONS.recipient_role, () =>
                form.setValue('recipient_id', null, { shouldDirty: true, shouldValidate: true }),
              )}
              {selectField('application_scope', OPTIONS.application_scope)}
              {scope === 'RECIPIENT' ? null : scope === 'PRODUCT_CATEGORY' ? (
                <RelationSelectField control={form.control} name="product_category_id" metaKey="product_category_id" label={t('commissionConfigurations.form.product_category_id')} resource={PRODUCT_CATEGORIES_FOR_SELECT_RESOURCE} searchPlaceholder={t('commissionConfigurations.form.searchCategory')} selected={selectedCategory} {...relationLabels} />
              ) : (
                <RelationSelectField control={form.control} name="product_id" metaKey="product_id" label={t('commissionConfigurations.form.product_id')} resource={PRODUCTS_FOR_SELECT_RESOURCE} searchPlaceholder={t('commissionConfigurations.form.searchProduct')} selected={selectedProduct} {...relationLabels} />
              )}
              <CommissionConfigurationRecipientField control={form.control} role={role} selected={selectedRecipient} labels={relationLabels} />
            </div>
          </FormSection> : null}

          {calculationVisible ? <FormSection icon={CircleDollarSign} title={t('commissionConfigurations.form.sections.calculation')}>
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
              {selectField('commission_type', OPTIONS.commission_type)}
              <MetaField control={form.control} name="value" metaKey="value" label={t('commissionConfigurations.form.value')}>
                {({ field, disabled, readOnly }) => <FormControl><div className="flex items-center gap-2"><Input type="number" min={0} step="0.0001" {...field} disabled={disabled} readOnly={readOnly} onChange={(event) => field.onChange(Number(event.target.value))} /><span className="text-sm text-muted-foreground" aria-hidden="true">{commissionType === 'PERCENTAGE' ? '%' : '€'}</span></div></FormControl>}
              </MetaField>
              <MetaField control={form.control} name="priority" metaKey="priority" label={t('commissionConfigurations.form.priority')} hint={t('commissionConfigurations.form.hints.priority')}>
                {({ field, disabled, readOnly }) => <FormControl><Input type="number" step="1" {...field} disabled={disabled} readOnly={readOnly} onChange={(event) => field.onChange(Number(event.target.value))} /></FormControl>}
              </MetaField>
            </div>
          </FormSection> : null}

          {validityVisible ? <FormSection icon={CalendarRange} title={t('commissionConfigurations.form.sections.validity')}>
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <MetaField control={form.control} name="valid_from" metaKey="valid_from" label={t('commissionConfigurations.form.valid_from')}>
                {({ field, disabled, readOnly }) => <FormControl><Input type="date" {...field} disabled={disabled} readOnly={readOnly} /></FormControl>}
              </MetaField>
              <MetaField control={form.control} name="valid_until" metaKey="valid_until" label={t('commissionConfigurations.form.valid_until')}>
                {({ field, disabled, readOnly }) => <FormControl><Input type="date" {...field} value={field.value ?? ''} disabled={disabled} readOnly={readOnly} onChange={(event) => field.onChange(event.target.value || null)} /></FormControl>}
              </MetaField>
              {selectField('status', OPTIONS.status)}
            </div>
          </FormSection> : null}

          {fieldPermission('internal_note').visible ? <FormSection icon={FileText} title={t('commissionConfigurations.form.sections.notes')}>
            <MetaField control={form.control} name="internal_note" metaKey="internal_note" label={t('commissionConfigurations.form.internal_note')}>
              {({ field, disabled, readOnly }) => <FormControl><Textarea {...field} value={field.value ?? ''} disabled={disabled} readOnly={readOnly} onChange={(event) => field.onChange(event.target.value || null)} /></FormControl>}
            </MetaField>
          </FormSection> : null}
          {serverError ? <p role="alert" className="text-sm font-medium text-destructive">{serverError}</p> : null}
          <div className="sticky bottom-0 -mx-4 -mb-4 mt-auto flex justify-end gap-2 border-t bg-background/95 px-4 py-3">
            <Button type="button" variant="outline" onClick={onCancel} disabled={form.formState.isSubmitting}>{t('common.cancel')}</Button>
            <Button type="submit" disabled={form.formState.isSubmitting}>{form.formState.isSubmitting ? t('commissionConfigurations.form.saving') : t('commissionConfigurations.form.save')}</Button>
          </div>
        </form>
      </Form>
    </div>
  )
}

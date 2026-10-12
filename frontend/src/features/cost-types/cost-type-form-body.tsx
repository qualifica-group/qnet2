import { Tags } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { FormSection } from '@/components/form-section'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Switch } from '@/components/ui/switch'
import { Textarea } from '@/components/ui/textarea'
import { Form, FormControl } from '@/components/ui/form'
import { MetaField } from '@/features/authorization/MetaField'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { useCostTypeForm } from '@/features/cost-types/use-cost-type-form'
import type { CostTypeDetail, CostTypeFormMode } from '@/features/cost-types/types'

interface CostTypeFormBodyProps {
  mode: CostTypeFormMode
  onSuccess: (costType: CostTypeDetail) => void
  onCancel: () => void
}

/**
 * The cost type create/edit form UI. Every field is wrapped in `MetaField`
 * (hidden means absent, non-editable means disabled, `required` from the
 * resolved `ResourcePermissions`); all non-render logic lives in `useCostTypeForm`.
 */
export function CostTypeFormBody({ mode, onSuccess, onCancel }: CostTypeFormBodyProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const { form, serverError, onSubmit } = useCostTypeForm({ mode, onSuccess })

  const identityVisible =
    fieldPermission('name').visible ||
    fieldPermission('description').visible ||
    fieldPermission('sort_order').visible ||
    fieldPermission('is_active').visible

  return (
    <div className="flex flex-1 flex-col overflow-y-auto">
      <Form {...form}>
        <form onSubmit={form.handleSubmit(onSubmit)} className="flex flex-col gap-4 p-4" noValidate>
          {identityVisible && (
            <FormSection
              icon={Tags}
              title={t('costTypes.form.sections.identity.title')}
              description={t('costTypes.form.sections.identity.description')}
            >
              <MetaField control={form.control} name="name" metaKey="name" label={t('costTypes.form.name')}>
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
                label={t('costTypes.form.description')}
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
                name="sort_order"
                metaKey="sort_order"
                label={t('costTypes.form.sortOrder')}
              >
                {({ field, disabled, readOnly }) => (
                  <FormControl>
                    <Input
                      type="number"
                      step="1"
                      min={0}
                      disabled={disabled}
                      readOnly={readOnly}
                      value={Number.isNaN(field.value) ? '' : String(field.value)}
                      onChange={(event) => field.onChange(event.target.value === '' ? Number.NaN : Number(event.target.value))}
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
                label={t('costTypes.form.isActive')}
              >
                {({ field, disabled }) => (
                  <FormControl>
                    <Switch checked={field.value} onCheckedChange={field.onChange} disabled={disabled} />
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
              className="bg-card"
              onClick={onCancel}
              disabled={form.formState.isSubmitting}
            >
              {t('costTypes.form.cancel')}
            </Button>
            <Button type="submit" disabled={form.formState.isSubmitting}>
              {form.formState.isSubmitting ? t('costTypes.form.saving') : t('costTypes.form.save')}
            </Button>
          </div>
        </form>
      </Form>
    </div>
  )
}

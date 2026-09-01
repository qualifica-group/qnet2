import { Ruler } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { FormSection } from '@/components/form-section'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Form, FormControl } from '@/components/ui/form'
import { MetaField } from '@/features/authorization/MetaField'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { useUnitOfMeasureForm } from '@/features/units-of-measure/use-unit-of-measure-form'
import type {
  UnitOfMeasureDetail,
  UnitOfMeasureFormMode,
} from '@/features/units-of-measure/types'

interface UnitOfMeasureFormBodyProps {
  mode: UnitOfMeasureFormMode
  onSuccess: (unitOfMeasure: UnitOfMeasureDetail) => void
  onCancel: () => void
}

/**
 * The unit of measure create/edit form UI. `name`, `symbol`, `code` and
 * `description` are each wrapped in `MetaField` (spec 0004): hidden means
 * absent, non-editable means disabled, `required` comes from the resolved
 * `ResourcePermissions` — no hardcoded permission logic lives here. `code`'s
 * immutability after create (D-1) is NOT a frontend decision: the backend's
 * field-permission ceiling reports it `editable: true` only when there is no
 * model context (create), so on edit `MetaField` disables it automatically —
 * the same mechanism every other field uses. All non-render logic lives in
 * `useUnitOfMeasureForm`.
 */
export function UnitOfMeasureFormBody({ mode, onSuccess, onCancel }: UnitOfMeasureFormBodyProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const { form, serverError, onSubmit } = useUnitOfMeasureForm({ mode, onSuccess })

  const identityVisible =
    fieldPermission('name').visible ||
    fieldPermission('symbol').visible ||
    fieldPermission('code').visible ||
    fieldPermission('description').visible

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
              icon={Ruler}
              title={t('unitsOfMeasure.form.sections.identity.title')}
              description={t('unitsOfMeasure.form.sections.identity.description')}
            >
              <MetaField
                control={form.control}
                name="name"
                metaKey="name"
                label={t('unitsOfMeasure.form.name')}
              >
                {({ field, disabled, readOnly }) => (
                  <FormControl>
                    <Input autoComplete="off" disabled={disabled} readOnly={readOnly} {...field} />
                  </FormControl>
                )}
              </MetaField>

              <MetaField
                control={form.control}
                name="symbol"
                metaKey="symbol"
                label={t('unitsOfMeasure.form.symbol')}
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
                label={t('unitsOfMeasure.form.code')}
                hint={mode.type === 'edit' ? t('unitsOfMeasure.form.hints.codeLocked') : undefined}
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
                label={t('unitsOfMeasure.form.description')}
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
              {t('unitsOfMeasure.form.cancel')}
            </Button>
            <Button type="submit" disabled={form.formState.isSubmitting}>
              {form.formState.isSubmitting
                ? t('unitsOfMeasure.form.saving')
                : t('unitsOfMeasure.form.save')}
            </Button>
          </div>
        </form>
      </Form>
    </div>
  )
}

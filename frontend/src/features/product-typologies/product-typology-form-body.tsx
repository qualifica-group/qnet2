import { Shapes } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { FormSection } from '@/components/form-section'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Form, FormControl } from '@/components/ui/form'
import { MetaField } from '@/features/authorization/MetaField'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { useProductTypologyForm } from '@/features/product-typologies/use-product-typology-form'
import type {
  ProductTypologyDetail,
  ProductTypologyFormMode,
} from '@/features/product-typologies/types'

interface ProductTypologyFormBodyProps {
  mode: ProductTypologyFormMode
  onSuccess: (productTypology: ProductTypologyDetail) => void
  onCancel: () => void
}

/**
 * The product typology create/edit form UI. `name`, `code` and
 * `description` are each wrapped in `MetaField` (spec 0004): hidden means
 * absent, non-editable means disabled, `required` comes from the resolved
 * `ResourcePermissions` — no hardcoded permission logic lives here. `code`'s
 * immutability after create (D-2) is NOT a frontend decision: the backend's
 * field-permission ceiling reports it `editable: true` only when there is no
 * model context (create), so on edit `MetaField` disables it automatically —
 * the same mechanism every other field uses. All non-render logic lives in
 * `useProductTypologyForm`.
 */
export function ProductTypologyFormBody({ mode, onSuccess, onCancel }: ProductTypologyFormBodyProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const { form, serverError, onSubmit } = useProductTypologyForm({ mode, onSuccess })

  const identityVisible =
    fieldPermission('name').visible ||
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
              icon={Shapes}
              title={t('productTypologies.form.sections.identity.title')}
              description={t('productTypologies.form.sections.identity.description')}
            >
              <MetaField
                control={form.control}
                name="name"
                metaKey="name"
                label={t('productTypologies.form.name')}
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
                label={t('productTypologies.form.code')}
                hint={mode.type === 'edit' ? t('productTypologies.form.hints.codeLocked') : undefined}
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
                label={t('productTypologies.form.description')}
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
              {t('productTypologies.form.cancel')}
            </Button>
            <Button type="submit" disabled={form.formState.isSubmitting}>
              {form.formState.isSubmitting
                ? t('productTypologies.form.saving')
                : t('productTypologies.form.save')}
            </Button>
          </div>
        </form>
      </Form>
    </div>
  )
}

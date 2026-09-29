import { FolderArchive } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { FormSection } from '@/components/form-section'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Switch } from '@/components/ui/switch'
import { Textarea } from '@/components/ui/textarea'
import { Form, FormControl } from '@/components/ui/form'
import { MetaField } from '@/features/authorization/MetaField'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { useDocumentBundleForm } from '@/features/document-bundles/use-document-bundle-form'
import type { DocumentBundle, DocumentBundleFormMode } from '@/features/document-bundles/types'

interface DocumentBundleFormBodyProps {
  mode: DocumentBundleFormMode
  onSuccess: (documentBundle: DocumentBundle) => void
  onCancel: () => void
}

/** The metadata keys this form owns, in render order. */
const FIELD_KEYS = ['name', 'description', 'is_active'] as const

/**
 * The document bundle create/edit form UI. Every field is wrapped in
 * `MetaField` (spec 0004): hidden means absent, non-editable means disabled,
 * `required` comes from the resolved `ResourcePermissions` — no hardcoded
 * permission logic lives here. Files are NOT a field of this form: they are
 * managed by the detail's `DocumentsSection` once the bundle exists (data
 * contract: files pass through the generic `/api/attachments` endpoints).
 * All non-render logic lives in `useDocumentBundleForm`.
 */
export function DocumentBundleFormBody({ mode, onSuccess, onCancel }: DocumentBundleFormBodyProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const { form, serverError, onSubmit } = useDocumentBundleForm({ mode, onSuccess })

  const identityVisible = FIELD_KEYS.some((key) => fieldPermission(key).visible)

  return (
    <div className="flex flex-1 flex-col overflow-y-auto">
      <Form {...form}>
        <form onSubmit={form.handleSubmit(onSubmit)} className="flex flex-col gap-4 p-4" noValidate>
          {identityVisible && (
            <FormSection
              icon={FolderArchive}
              title={t('documentBundles.form.sections.identity.title')}
              description={t('documentBundles.form.sections.identity.description')}
            >
              <MetaField
                control={form.control}
                name="name"
                metaKey="name"
                label={t('documentBundles.form.name')}
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
                label={t('documentBundles.form.description')}
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
                name="is_active"
                metaKey="is_active"
                label={t('documentBundles.form.isActive')}
                layout="inline"
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
              {t('documentBundles.form.cancel')}
            </Button>
            <Button type="submit" disabled={form.formState.isSubmitting}>
              {form.formState.isSubmitting ? t('documentBundles.form.saving') : t('documentBundles.form.save')}
            </Button>
          </div>
        </form>
      </Form>
    </div>
  )
}

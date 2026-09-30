import { useTranslation } from 'react-i18next'
import { SlidersHorizontal } from 'lucide-react'
import { FormSection } from '@/components/form-section'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Form, FormControl } from '@/components/ui/form'
import { MetaField } from '@/features/authorization/MetaField'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { useAttributeForm } from '@/features/attributes/use-attribute-form'
import { DefinitionFieldPreview } from '@/features/custom-fields/components/definition-field-preview'
import { DefinitionOptionsEditor } from '@/features/custom-fields/components/definition-options-editor'
import { DefinitionPresentationFields } from '@/features/custom-fields/components/definition-presentation-fields'
import { DefinitionRelationTargetEditor } from '@/features/custom-fields/components/definition-relation-target-editor'
import { DefinitionTableColumnsEditor } from '@/features/custom-fields/components/definition-table-columns-editor'
import { DefinitionTypeConfigFields } from '@/features/custom-fields/components/definition-type-config-fields'
import { DefinitionTypePicker } from '@/features/custom-fields/components/definition-type-picker'
import { CustomFieldsSection } from '@/features/custom-fields/CustomFieldsSection'
import { useCustomFieldEntities } from '@/features/custom-fields/use-custom-field-entities'
import type { AttributeDetail, AttributeFormMode } from '@/features/attributes/types'

interface AttributeFormBodyProps {
  mode: AttributeFormMode
  onSuccess: (attribute: AttributeDetail) => void
  onCancel: () => void
}

/**
 * The attribute create/edit form UI, aligned to the custom field definition
 * form (spec 0017/0021): `code`/`name` wrapped in `MetaField`, then the same
 * shared `type`-conditional sub-forms `CustomFieldDefinitionFormBody` mounts
 * (type picker, per-type config, ENUM options / RELATION target — mutually
 * exclusive, per the selected `type` — and the presentation fields), plus the
 * live preview. No `validation` sub-form here: an attribute's `required`
 * lives on the category pivot (`attribute_category.is_required`), not on the
 * definition. All non-render logic lives in `useAttributeForm`.
 */
export function AttributeFormBody({ mode, onSuccess, onCancel }: AttributeFormBodyProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const { form, serverError, onSubmit } = useAttributeForm({ mode, onSuccess })
  const entitiesQuery = useCustomFieldEntities()
  const entities = entitiesQuery.data ?? []

  const type = form.watch('type')
  const optionsError = form.formState.errors.options?.message

  const identityVisible =
    fieldPermission('code').visible || fieldPermission('name').visible || fieldPermission('type').visible

  return (
    <div className="flex flex-1 flex-col overflow-y-auto">
      <Form {...form}>
        <form onSubmit={form.handleSubmit(onSubmit)} className="flex flex-col gap-4 p-4" noValidate>
          <DefinitionFieldPreview control={form.control} label={form.watch('name')} />

          {identityVisible && (
            <FormSection
              icon={SlidersHorizontal}
              title={t('attributes.form.sections.identity.title')}
              description={t('attributes.form.sections.identity.description')}
            >
              <MetaField control={form.control} name="code" metaKey="code" label={t('attributes.form.code')}>
                {({ field, disabled, readOnly }) => (
                  <FormControl>
                    <Input autoComplete="off" disabled={disabled} readOnly={readOnly} {...field} />
                  </FormControl>
                )}
              </MetaField>

              <MetaField control={form.control} name="name" metaKey="name" label={t('attributes.form.name')}>
                {({ field, disabled, readOnly }) => (
                  <FormControl>
                    <Input autoComplete="off" disabled={disabled} readOnly={readOnly} {...field} />
                  </FormControl>
                )}
              </MetaField>

              <DefinitionTypePicker control={form.control} lockIdentity={mode.type === 'edit'} />
            </FormSection>
          )}

          <DefinitionTypeConfigFields control={form.control} type={type} />

          {type === 'enum' && (
            <DefinitionOptionsEditor control={form.control} optionsError={optionsError} />
          )}

          <DefinitionTableColumnsEditor control={form.control} type={type} />

          {type === 'relation' && (
            <DefinitionRelationTargetEditor control={form.control} entities={entities} />
          )}

          <DefinitionPresentationFields control={form.control} />

          <CustomFieldsSection resource="attributes" control={form.control} />

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
              {t('attributes.form.cancel')}
            </Button>
            <Button type="submit" disabled={form.formState.isSubmitting}>
              {form.formState.isSubmitting
                ? t('attributes.form.saving')
                : t('attributes.form.save')}
            </Button>
          </div>
        </form>
      </Form>
    </div>
  )
}

import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Form } from '@/components/ui/form'
import { DefinitionBaseFields } from '@/features/custom-fields/components/definition-base-fields'
import { DefinitionFieldPreview } from '@/features/custom-fields/components/definition-field-preview'
import { DefinitionFlagsFields } from '@/features/custom-fields/components/definition-flags-fields'
import { DefinitionOrganizationFields } from '@/features/custom-fields/components/definition-organization-fields'
import { DefinitionPresentationFields } from '@/features/custom-fields/components/definition-presentation-fields'
import { DefinitionOptionsEditor } from '@/features/custom-fields/components/definition-options-editor'
import { DefinitionRelationTargetEditor } from '@/features/custom-fields/components/definition-relation-target-editor'
import { DefinitionTableColumnsEditor } from '@/features/custom-fields/components/definition-table-columns-editor'
import { DefinitionTypeConfigFields } from '@/features/custom-fields/components/definition-type-config-fields'
import { DefinitionValidationEditor } from '@/features/custom-fields/components/definition-validation-editor'
import { useCustomFieldDefinitionForm } from '@/features/custom-fields/use-custom-field-definition-form'
import { useCustomFieldEntities } from '@/features/custom-fields/use-custom-field-entities'
import type { CustomFieldDefinitionDetail, CustomFieldDefinitionFormMode } from '@/features/custom-fields/types'

interface CustomFieldDefinitionFormBodyProps {
  mode: CustomFieldDefinitionFormMode
  onSuccess: (definition: CustomFieldDefinitionDetail) => void
  onCancel: () => void
}

/**
 * The custom field DEFINITION create/edit form UI (spec 0021 AC-025):
 * identity fields wrapped in `MetaField`, then the `type`-conditional
 * sub-forms — the ENUM options editor and the RELATION target editor are
 * mutually exclusive (never both visible), the per-type config editor and
 * the validation builder both read the selected `type` to show only the
 * applicable controls. All non-render logic lives in
 * `useCustomFieldDefinitionForm` (mirrors `AttributeFormBody`).
 */
export function CustomFieldDefinitionFormBody({
  mode,
  onSuccess,
  onCancel,
}: CustomFieldDefinitionFormBodyProps) {
  const { t } = useTranslation()
  const { form, serverError, onSubmit } = useCustomFieldDefinitionForm({ mode, onSuccess })
  const entitiesQuery = useCustomFieldEntities()
  const entities = entitiesQuery.data ?? []

  const type = form.watch('type')
  const optionsError = form.formState.errors.options?.message

  return (
    <div className="flex flex-1 flex-col overflow-y-auto">
      <Form {...form}>
        <form onSubmit={form.handleSubmit(onSubmit)} className="flex flex-col gap-4 p-4" noValidate>
          <DefinitionFieldPreview
            control={form.control}
            label={form.watch('label')}
            required={form.watch('validation.required')}
          />

          <DefinitionBaseFields
            control={form.control}
            entities={entities}
            lockIdentity={mode.type === 'edit'}
          />

          {/* Type-dependent settings sit ABOVE Presentation: the admin decides
              WHAT the field does before polishing how it LOOKS. */}
          <DefinitionTypeConfigFields control={form.control} type={type} />

          {type === 'enum' && (
            <DefinitionOptionsEditor control={form.control} optionsError={optionsError} />
          )}

          <DefinitionTableColumnsEditor control={form.control} type={type} />

          {type === 'relation' && (
            <DefinitionRelationTargetEditor control={form.control} entities={entities} />
          )}

          <DefinitionValidationEditor control={form.control} type={type} />

          <DefinitionPresentationFields control={form.control} />

          <DefinitionOrganizationFields control={form.control} />

          <DefinitionFlagsFields control={form.control} type={type} />

          {serverError && (
            <p className="text-sm font-medium text-destructive" role="alert">
              {serverError}
            </p>
          )}

          <div className="mt-auto flex justify-end gap-2 pt-2">
            <Button type="button" variant="outline" onClick={onCancel} disabled={form.formState.isSubmitting}>
              {t('customFields.form.cancel')}
            </Button>
            <Button type="submit" disabled={form.formState.isSubmitting}>
              {form.formState.isSubmitting ? t('customFields.form.saving') : t('customFields.form.save')}
            </Button>
          </div>
        </form>
      </Form>
    </div>
  )
}

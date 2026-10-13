import { useTranslation } from 'react-i18next'
import type { UseFormReturn } from 'react-hook-form'
import { Info, Paintbrush } from 'lucide-react'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordFieldList, RecordSection } from '@/components/detail/record-panel'
import { RecordInlineField, type InlineEdit } from '@/components/record-form/record-inline-field'
import { Badge } from '@/components/ui/badge'
import {
  DefinitionEntityTypeField,
  DefinitionGroupField,
  DefinitionIsActiveField,
  DefinitionIsIndexedField,
  DefinitionKeyField,
  DefinitionLabelField,
  DefinitionSortOrderField,
} from '@/features/custom-fields/components/definition-identity-fields'
import { DefinitionFieldPreview } from '@/features/custom-fields/components/definition-field-preview'
import { DefinitionPresentationFields } from '@/features/custom-fields/components/definition-presentation-fields'
import { DefinitionTypePicker } from '@/features/custom-fields/components/definition-type-picker'
import type { CustomFieldDefinitionFormValues } from '@/features/custom-fields/custom-field-definition-schema'
import { PresentationSummary } from '@/features/custom-fields/custom-field-definition-summaries'
import { CustomFieldDefinitionTypeSections } from '@/features/custom-fields/custom-field-definition-type-sections'
import { PRESENTATION_ROW } from '@/features/custom-fields/use-custom-field-definition-inline-edit'

interface DefinitionSectionsProps {
  form: UseFormReturn<CustomFieldDefinitionFormValues>
  inline: InlineEdit
  /** Create form: `entity_type`/`type`/`key` are editable (they are fixed once the definition exists) and the type editors stay open. */
  creating: boolean
  /** `false` without the definition's `update` permission: no row offers a pencil. */
  canEdit: boolean
}

/**
 * The sections of a custom field definition (spec 0231), shared by the
 * detail (rows edit in place) and the create form (rows closed until
 * clicked, nothing saved per row): the live preview, "Dettagli",
 * "Presentazione", then the type-dependent editors. Closed rows read the
 * form values, which mirror the saved definition while no editor is open.
 */
export function CustomFieldDefinitionSections({ form, inline, creating, canEdit }: DefinitionSectionsProps) {
  const { t } = useTranslation()
  const { control } = form
  const values = form.watch()
  const rowProps = { inline, canEdit }
  const emptyOr = (text: string) => (text !== '' ? <span className="break-words">{text}</span> : <DetailEmpty />)

  return (
    <>
      <div className="min-w-0 @2xl:col-span-2">
        <DefinitionFieldPreview control={control} label={values.label} required={values.validation.required} />
      </div>

      <RecordSection title={t('customFields.detail.details')} icon={<Info />}>
        <RecordFieldList>
          <RecordInlineField
            field="entity_type"
            label={t('customFields.form.entityType')}
            inline={inline}
            canEdit={creating}
            editor={<DefinitionEntityTypeField control={control} />}
          >
            {values.entity_type !== '' ? (
              <Badge variant="outline">{t(`customFields.entities.${values.entity_type}`, { defaultValue: values.entity_type })}</Badge>
            ) : (
              <DetailEmpty />
            )}
          </RecordInlineField>
          <RecordInlineField
            field="type"
            label={t('customFields.form.type')}
            inline={inline}
            canEdit={creating}
            editor={<DefinitionTypePicker control={control} lockIdentity={false} />}
          >
            <Badge variant="secondary">{t(`customFields.types.${values.type}`)}</Badge>
          </RecordInlineField>
          <RecordInlineField
            field="key"
            label={t('customFields.form.key')}
            inline={inline}
            canEdit={creating}
            editor={<DefinitionKeyField control={control} />}
          >
            {values.key !== '' ? <span className="font-mono">{values.key}</span> : <DetailEmpty />}
          </RecordInlineField>
          <RecordInlineField
            field="label"
            label={t('customFields.form.label')}
            editor={<DefinitionLabelField control={control} />}
            {...rowProps}
          >
            {emptyOr(values.label)}
          </RecordInlineField>
          <RecordInlineField
            field="group"
            label={t('customFields.form.group')}
            editor={<DefinitionGroupField control={control} />}
            {...rowProps}
          >
            {emptyOr(values.group)}
          </RecordInlineField>
          <RecordInlineField
            field="sort_order"
            label={t('customFields.form.sortOrder')}
            editor={<DefinitionSortOrderField control={control} />}
            {...rowProps}
          >
            <span>{values.sort_order}</span>
          </RecordInlineField>
          <RecordInlineField
            field="is_active"
            label={t('customFields.form.isActive')}
            editor={<DefinitionIsActiveField control={control} />}
            {...rowProps}
          >
            <span>{values.is_active ? t('common.yes') : t('common.no')}</span>
          </RecordInlineField>
          {values.type !== 'table' ? (
            <RecordInlineField
              field="is_indexed"
              label={t('customFields.form.isIndexed')}
              editor={<DefinitionIsIndexedField control={control} />}
              {...rowProps}
            >
              <span>{values.is_indexed ? t('common.yes') : t('common.no')}</span>
            </RecordInlineField>
          ) : null}
        </RecordFieldList>
      </RecordSection>

      <RecordSection title={t('customFields.form.sections.presentation.title')} icon={<Paintbrush />}>
        <RecordInlineField
          field={PRESENTATION_ROW}
          metaKey="description"
          label={t('customFields.form.sections.presentation.title')}
          layout="block"
          editor={<DefinitionPresentationFields control={control} />}
          {...rowProps}
        >
          <PresentationSummary values={values} />
        </RecordInlineField>
      </RecordSection>

      <CustomFieldDefinitionTypeSections form={form} inline={inline} creating={creating} canEdit={canEdit} />
    </>
  )
}

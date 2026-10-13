import { useTranslation } from 'react-i18next'
import type { UseFormReturn } from 'react-hook-form'
import { Info, Paintbrush } from 'lucide-react'
import { RecordFieldList, RecordSection } from '@/components/detail/record-panel'
import { TextInlineRow } from '@/components/record-form/inline-rows'
import { RecordInlineField, type InlineEdit } from '@/components/record-form/record-inline-field'
import { Badge } from '@/components/ui/badge'
import { toDefinitionValues } from '@/features/attributes/attribute-definition-values'
import { AttributeTypeSections } from '@/features/attributes/attribute-type-sections'
import type { AttributeFormValues } from '@/features/attributes/use-attribute-form'
import { DefinitionFieldPreview } from '@/features/custom-fields/components/definition-field-preview'
import { DefinitionPresentationFields } from '@/features/custom-fields/components/definition-presentation-fields'
import { DefinitionTypePicker } from '@/features/custom-fields/components/definition-type-picker'
import { CustomFieldRecordSections } from '@/features/custom-fields/custom-field-record-sections'
import { PresentationSummary } from '@/features/custom-fields/custom-field-definition-summaries'
import { FIELD_TYPE_ICONS } from '@/features/custom-fields/field-type-icons'
import { PRESENTATION_ROW } from '@/features/custom-fields/use-custom-field-definition-inline-edit'

interface AttributeSectionsProps {
  form: UseFormReturn<AttributeFormValues>
  inline: InlineEdit
  /** Create form: `code`/`type` are editable (fixed once the attribute exists) and the type editors stay open. */
  creating: boolean
  /** `false` without the attribute's `update` permission: no row offers a pencil. */
  canEdit: boolean
}

/**
 * The sections of an attribute, shared by the detail (rows edit in place) and
 * the create form (rows closed until clicked, nothing saved per row): the live
 * preview, "Dettagli", "Presentazione", the type-dependent editors and the
 * resource's custom fields. Closed rows read the form values, which mirror the
 * saved attribute while no editor is open.
 */
export function AttributeSections({ form, inline, creating, canEdit }: AttributeSectionsProps) {
  const { t } = useTranslation()
  const { control } = form
  const values = form.watch()
  const TypeIcon = FIELD_TYPE_ICONS[values.type]

  return (
    <>
      <div className="min-w-0 @2xl:col-span-2">
        <DefinitionFieldPreview control={control} label={values.name} />
      </div>

      <RecordSection title={t('attributes.detail.details')} icon={<Info />}>
        <RecordFieldList>
          <TextInlineRow<AttributeFormValues>
            name="code"
            label={t('attributes.form.code')}
            inline={inline}
            canEdit={creating}
          />
          <TextInlineRow<AttributeFormValues>
            name="name"
            label={t('attributes.form.name')}
            inline={inline}
            canEdit={canEdit}
          />
          <RecordInlineField
            field="type"
            label={t('customFields.form.type')}
            inline={inline}
            canEdit={creating}
            editor={<DefinitionTypePicker control={control} lockIdentity={false} />}
          >
            <Badge variant="secondary" className="gap-1">
              <TypeIcon className="size-3.5" aria-hidden="true" />
              {t(`customFields.types.${values.type}`)}
            </Badge>
          </RecordInlineField>
        </RecordFieldList>
      </RecordSection>

      <RecordSection title={t('customFields.form.sections.presentation.title')} icon={<Paintbrush />}>
        <RecordInlineField
          field={PRESENTATION_ROW}
          metaKey="description"
          label={t('customFields.form.sections.presentation.title')}
          layout="block"
          inline={inline}
          canEdit={canEdit}
          editor={<DefinitionPresentationFields control={control} />}
        >
          <PresentationSummary values={toDefinitionValues(values)} />
        </RecordInlineField>
      </RecordSection>

      <AttributeTypeSections form={form} inline={inline} creating={creating} canEdit={canEdit} />

      <CustomFieldRecordSections
        resource="attributes"
        values={values.custom_fields}
        inline={inline}
        control={control}
      />
    </>
  )
}

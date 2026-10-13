import { useTranslation } from 'react-i18next'
import type { UseFormReturn } from 'react-hook-form'
import { ListChecks, Settings2, Table2, Waypoints } from 'lucide-react'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import { toDefinitionValues } from '@/features/attributes/attribute-definition-values'
import type { AttributeFormValues } from '@/features/attributes/use-attribute-form'
import { DefinitionOptionsEditor } from '@/features/custom-fields/components/definition-options-editor'
import { DefinitionTableColumnsEditor } from '@/features/custom-fields/components/definition-table-columns-editor'
import { DefinitionTypeConfigFields } from '@/features/custom-fields/components/definition-type-config-fields'
import {
  ConfigSummary,
  OptionsSummary,
  RelationSummary,
  TableSummary,
} from '@/features/custom-fields/custom-field-definition-summaries'
import { RelationTargetEditor, TypeSection } from '@/features/custom-fields/custom-field-definition-type-sections'
import { TABLE_ROW } from '@/features/custom-fields/use-custom-field-definition-inline-edit'
import type { CustomFieldType } from '@/features/custom-fields/types'

/** Types whose `config` has controls (the rest have none, so no config row: see `DefinitionTypeConfigFields`). */
const TYPES_WITH_CONFIG: readonly CustomFieldType[] = ['text', 'textarea', 'integer', 'decimal', 'boolean', 'enum']

interface TypeSectionsProps {
  form: UseFormReturn<AttributeFormValues>
  inline: InlineEdit
  /** Create form: the editors stay open, like the quote's line grids; the detail opens one per pencil. */
  creating: boolean
  /** `false` without the attribute's `update` permission: no row offers a pencil. */
  canEdit: boolean
}

/** The type-dependent settings of an attribute: config, then options | relation | table columns (no validation: `required` lives on the category pivot). */
export function AttributeTypeSections({ form, inline, creating, canEdit }: TypeSectionsProps) {
  const { t } = useTranslation()
  const { control } = form
  const values = form.watch()
  const summaryValues = toDefinitionValues(values)
  const { type } = values
  const shared = { creating, canEdit, inline }

  return (
    <>
      {TYPES_WITH_CONFIG.includes(type) ? (
        <TypeSection
          {...shared}
          title={t('customFields.form.sections.config.title')}
          icon={<Settings2 />}
          field="config"
          editor={<DefinitionTypeConfigFields control={control} type={type} />}
          summary={<ConfigSummary values={summaryValues} />}
        />
      ) : null}

      {type === 'enum' ? (
        <TypeSection
          {...shared}
          title={t('customFields.form.sections.options.title')}
          icon={<ListChecks />}
          field="options"
          editor={<DefinitionOptionsEditor control={control} optionsError={form.formState.errors.options?.message} />}
          summary={<OptionsSummary values={summaryValues} />}
        />
      ) : null}

      {type === 'relation' ? (
        <TypeSection
          {...shared}
          title={t('customFields.form.sections.relation.title')}
          icon={<Waypoints />}
          field="relation_target"
          editor={<RelationTargetEditor control={control} />}
          summary={<RelationSummary values={summaryValues} />}
        />
      ) : null}

      {type === 'table' ? (
        <TypeSection
          {...shared}
          title={t('customFields.tableEditor.title')}
          icon={<Table2 />}
          field={TABLE_ROW}
          metaKey="config"
          editor={<DefinitionTableColumnsEditor control={control} type={type} />}
          summary={<TableSummary values={summaryValues} />}
        />
      ) : null}
    </>
  )
}

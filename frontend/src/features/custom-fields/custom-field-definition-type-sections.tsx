import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import type { Control, UseFormReturn } from 'react-hook-form'
import { ListChecks, Settings2, ShieldCheck, Table2, Waypoints } from 'lucide-react'
import { RecordSection } from '@/components/detail/record-panel'
import { RecordInlineField, type InlineEdit } from '@/components/record-form/record-inline-field'
import type { CustomFieldEntity } from '@/features/custom-fields/api'
import { DefinitionOptionsEditor } from '@/features/custom-fields/components/definition-options-editor'
import { DefinitionRelationTargetEditor } from '@/features/custom-fields/components/definition-relation-target-editor'
import { DefinitionTableColumnsEditor } from '@/features/custom-fields/components/definition-table-columns-editor'
import { DefinitionTypeConfigFields } from '@/features/custom-fields/components/definition-type-config-fields'
import { DefinitionValidationEditor } from '@/features/custom-fields/components/definition-validation-editor'
import type { CustomFieldDefinitionFormValues } from '@/features/custom-fields/custom-field-definition-schema'
import {
  ConfigSummary,
  OptionsSummary,
  RelationSummary,
  TableSummary,
  ValidationSummary,
} from '@/features/custom-fields/custom-field-definition-summaries'
import { useCustomFieldRelationTargets } from '@/features/custom-fields/use-custom-field-entities'
import { TABLE_ROW } from '@/features/custom-fields/use-custom-field-definition-inline-edit'
import type { CustomFieldType } from '@/features/custom-fields/types'

/** Types whose `config` has controls (the rest have none, so no config row: see `DefinitionTypeConfigFields`). */
const NO_ENTITIES: CustomFieldEntity[] = []

const TYPES_WITH_CONFIG: readonly CustomFieldType[] = ['text', 'textarea', 'integer', 'decimal', 'boolean', 'enum']

interface TypeSectionsProps {
  form: UseFormReturn<CustomFieldDefinitionFormValues>
  inline: InlineEdit
  /** Create form: the editors stay open, like the quote's line grids; the detail opens one per pencil. */
  creating: boolean
  /** `false` without the definition's `update` permission: no row offers a pencil. */
  canEdit: boolean
}

interface TypeSectionProps {
  title: string
  icon: ReactNode
  /** The row key, which is also the `inline.editingField` it opens. */
  field: string
  /** The field permission gating the row when it is not `field` itself. */
  metaKey?: string
  creating: boolean
  canEdit: boolean
  inline: InlineEdit
  editor: ReactNode
  summary: ReactNode
}

/** Fetches the relation targets itself, so they are only requested while the editor is on screen. */
function RelationTargetEditor({ control }: { control: Control<CustomFieldDefinitionFormValues> }) {
  const entities = useCustomFieldRelationTargets().data ?? NO_ENTITIES

  return <DefinitionRelationTargetEditor control={control} entities={entities} />
}

/** One full-width section holding an editor that is one block row on the detail and open on the create form. */
function TypeSection({ title, icon, field, metaKey, creating, canEdit, inline, editor, summary }: TypeSectionProps) {
  return (
    <RecordSection title={title} icon={icon} full>
      {creating ? (
        editor
      ) : (
        <RecordInlineField
          field={field}
          metaKey={metaKey}
          label={title}
          layout="block"
          inline={inline}
          canEdit={canEdit}
          editor={editor}
        >
          {summary}
        </RecordInlineField>
      )}
    </RecordSection>
  )
}

/** The type-dependent settings (spec 0231 D-3): config, options | relation | table columns, validation. */
export function CustomFieldDefinitionTypeSections({ form, inline, creating, canEdit }: TypeSectionsProps) {
  const { t } = useTranslation()
  const { control } = form
  const values = form.watch()
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
          summary={<ConfigSummary values={values} />}
        />
      ) : null}

      {type === 'enum' ? (
        <TypeSection
          {...shared}
          title={t('customFields.form.sections.options.title')}
          icon={<ListChecks />}
          field="options"
          editor={<DefinitionOptionsEditor control={control} optionsError={form.formState.errors.options?.message} />}
          summary={<OptionsSummary values={values} />}
        />
      ) : null}

      {type === 'relation' ? (
        <TypeSection
          {...shared}
          title={t('customFields.form.sections.relation.title')}
          icon={<Waypoints />}
          field="relation_target"
          editor={<RelationTargetEditor control={control} />}
          summary={<RelationSummary values={values} />}
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
          summary={<TableSummary values={values} />}
        />
      ) : null}

      <TypeSection
        {...shared}
        title={t('customFields.form.sections.validation.title')}
        icon={<ShieldCheck />}
        field="validation"
        editor={<DefinitionValidationEditor control={control} type={type} />}
        summary={<ValidationSummary values={values} />}
      />
    </>
  )
}

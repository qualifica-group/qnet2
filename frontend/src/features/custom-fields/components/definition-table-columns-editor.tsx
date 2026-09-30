import type { Control } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { Plus, Table2 } from 'lucide-react'
import { FormSection } from '@/components/form-section'
import { Button } from '@/components/ui/button'
import { DefinitionTableColumnRow } from '@/features/custom-fields/components/definition-table-column-row'
import { DefinitionTableSettings } from '@/features/custom-fields/components/definition-table-settings'
import type { FieldDefinitionFormValues } from '@/features/custom-fields/field-definition-form-values'
import { useDefinitionTableColumns } from '@/features/custom-fields/use-definition-table-columns'
import type { CustomFieldType } from '@/features/custom-fields/types'

interface DefinitionTableColumnsEditorProps<T extends FieldDefinitionFormValues> {
  control: Control<T>
  type: CustomFieldType
}

/**
 * `table` definition editor (spec 0180 AC-021), shared by the custom field
 * definition form AND the attribute form: add / reorder / remove columns, per
 * column label/key/type/required (+ inline options for `enum`), row selection,
 * grid summary and row limits. Renders nothing for other types.
 *
 * Generic over `T` (see `DefinitionTypePicker` for why `Control<T>` cannot be
 * fixed to `Control<FieldDefinitionFormValues>`).
 */
export function DefinitionTableColumnsEditor<T extends FieldDefinitionFormValues>({
  control,
  type,
}: DefinitionTableColumnsEditorProps<T>) {
  const baseControl = control as unknown as Control<FieldDefinitionFormValues>
  if (type !== 'table') {
    return null
  }
  return <TableColumnsSection control={baseControl} />
}

function TableColumnsSection({ control }: { control: Control<FieldDefinitionFormValues> }) {
  const { t } = useTranslation()
  const editor = useDefinitionTableColumns(control)

  return (
    <FormSection
      icon={Table2}
      title={t('customFields.tableEditor.title')}
      aside={
        <Button type="button" variant="secondary" size="sm" disabled={!editor.canAddColumn} onClick={editor.addColumn}>
          <Plus aria-hidden="true" />
          {t('customFields.tableEditor.addColumn')}
        </Button>
      }
    >
      <ul className="flex flex-col gap-2">
        {editor.fields.map((columnField, index) => (
          <DefinitionTableColumnRow
            key={columnField.id}
            control={control}
            index={index}
            total={editor.fields.length}
            type={editor.table.columns[index]?.type ?? columnField.type}
            optionsError={editor.optionsErrorAt(index)}
            onLabelChange={(label) => editor.changeLabel(index, label)}
            onKeyChange={(key) => editor.changeKey(index, key)}
            onTypeChange={(next) => editor.changeType(index, next)}
            onMove={(to) => editor.moveColumn(index, to)}
            onRemove={() => editor.removeColumn(index)}
          />
        ))}
      </ul>
      {editor.columnsError ? (
        <p className="text-xs font-medium text-destructive" role="alert">
          {editor.columnsError}
        </p>
      ) : null}
      <DefinitionTableSettings
        control={control}
        table={editor.table}
        summaryColumns={editor.summaryColumns}
        onSelectableChange={editor.setSelectable}
        onSummaryColumnChange={editor.setSummaryColumn}
      />
    </FormSection>
  )
}

import { useMemo } from 'react'
import { useFormContext, type FieldValues, type UseFormReturn } from 'react-hook-form'
import { Plus } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { useFormField } from '@/components/ui/form'
import { useIsMobile } from '@/hooks/use-mobile'
import { TableFieldDesktopView } from '@/features/custom-fields/components/table-field-desktop-view'
import { TableFieldMobileView } from '@/features/custom-fields/components/table-field-mobile-view'
import type { CustomFieldControlProps } from '@/features/custom-fields/components/custom-field-control-props'
import { readCellError, readRowsError } from '@/features/custom-fields/table-field-errors'
import { getTableConfig } from '@/features/custom-fields/table-field-model'
import { useTableFieldRows } from '@/features/custom-fields/use-table-field-rows'

interface TableFieldEditorProps extends CustomFieldControlProps {
  /** RHF error node of the bound field (nested `rows.N.<col>` messages); absent outside a form. */
  error: unknown
}

/**
 * `type: 'table'` (spec 0180): repeatable rows whose columns come from
 * `descriptor.config`. Desktop renders a compact table, mobile one card per
 * row. Cells reuse the scalar controls, so column rules live in one place.
 */
function TableFieldEditor({
  descriptor,
  value,
  onChange,
  disabled,
  readOnly,
  id,
  describedBy,
  error,
}: TableFieldEditorProps) {
  const { t } = useTranslation()
  const isMobile = useIsMobile()
  const config = useMemo(() => getTableConfig(descriptor), [descriptor])
  const { rows, canAddRow, addRow, removeRow, changeCell, selectRow } = useTableFieldRows(config, value, onChange)

  const editable = !disabled && !readOnly
  const rowsError = readRowsError(error)
  const rowsErrorId = `${id}-rows-error`
  const View = isMobile ? TableFieldMobileView : TableFieldDesktopView

  return (
    <div
      id={id}
      role="group"
      aria-label={descriptor.label}
      aria-describedby={rowsError ? `${describedBy} ${rowsErrorId}`.trim() : describedBy}
      className="flex min-w-0 flex-col gap-2"
    >
      {rows.length === 0 ? (
        <p className="text-xs text-muted-foreground">{t('customFields.tableField.empty')}</p>
      ) : (
        <View
          idPrefix={id}
          config={config}
          rows={rows}
          disabled={disabled}
          readOnly={readOnly}
          canRemove={editable}
          cellError={(index, columnKey) => readCellError(error, index, columnKey)}
          onCellChange={changeCell}
          onSelect={selectRow}
          onRemove={removeRow}
        />
      )}
      {rowsError ? (
        <span id={rowsErrorId} role="alert" className="text-xs text-destructive">
          {rowsError}
        </span>
      ) : null}
      {editable ? (
        <Button type="button" size="sm" variant="secondary" className="self-start" disabled={!canAddRow} onClick={addRow}>
          <Plus className="size-3.5" aria-hidden="true" />
          {t('customFields.tableField.addRow')}
        </Button>
      ) : null}
    </div>
  )
}

/** Inside a form field: read the nested errors from RHF. */
function BoundTableFieldControl(props: CustomFieldControlProps) {
  const { error } = useFormField()
  return <TableFieldEditor {...props} error={error} />
}

/**
 * Registry entry. The definition preview mounts controls outside any form, so
 * the RHF-bound variant is picked only when a form context exists.
 */
export function TableFieldControl(props: CustomFieldControlProps) {
  const form = useFormContext() as UseFormReturn<FieldValues> | null
  return form ? <BoundTableFieldControl {...props} /> : <TableFieldEditor {...props} error={undefined} />
}

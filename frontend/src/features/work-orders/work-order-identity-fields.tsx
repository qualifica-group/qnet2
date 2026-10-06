import { useTranslation } from 'react-i18next'
import type { Control } from 'react-hook-form'
import { FormControl } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import { RelationSelectField, type RelationFieldRef } from '@/components/form/relation-select-field'
import { MetaField } from '@/features/authorization/MetaField'
import { TASK_TEMPLATES_FOR_SELECT_RESOURCE } from '@/features/task-templates/for-select-api'
import type { WorkOrderFormValues } from '@/features/work-orders/use-work-order-form'
import type { WorkOrderType } from '@/features/work-orders/types'

/*
 * The work order's own scalar fields, one component each (spec 0195 D-3
 * applied to Commesse): the SAME control is the editor of a detail row and of
 * a create-draft row. Every one sits in `MetaField`: hidden means absent,
 * non-editable means disabled, `required` comes from the field permission.
 */

const WORK_ORDER_TYPES: WorkOrderType[] = ['processing', 'project']

interface FieldProps {
  control: Control<WorkOrderFormValues>
}

/** `code`: editable only at creation (D-1), the field permission says so. */
export function WorkOrderCodeField({ control }: FieldProps) {
  const { t } = useTranslation()
  return (
    <MetaField control={control} name="code" metaKey="code" label={t('workOrders.form.code')}>
      {({ field, disabled, readOnly }) => (
        <FormControl>
          <Input
            autoComplete="off"
            disabled={disabled}
            readOnly={readOnly}
            placeholder={t('workOrders.form.codePlaceholder')}
            {...field}
          />
        </FormControl>
      )}
    </MetaField>
  )
}

export function WorkOrderTitleField({ control }: FieldProps) {
  const { t } = useTranslation()
  return (
    <MetaField control={control} name="title" metaKey="title" label={t('workOrders.form.title')}>
      {({ field, disabled, readOnly }) => (
        <FormControl>
          <Input autoComplete="off" disabled={disabled} readOnly={readOnly} {...field} />
        </FormControl>
      )}
    </MetaField>
  )
}

export function WorkOrderTypeField({ control }: FieldProps) {
  const { t } = useTranslation()
  return (
    <MetaField control={control} name="type" metaKey="type" label={t('workOrders.form.type')}>
      {({ field, disabled }) => (
        <Select value={field.value} onValueChange={field.onChange} disabled={disabled}>
          <FormControl>
            <SelectTrigger className="w-full">
              <SelectValue />
            </SelectTrigger>
          </FormControl>
          <SelectContent>
            {WORK_ORDER_TYPES.map((type) => (
              <SelectItem key={type} value={type}>
                {t(`workOrders.options.type.${type}`)}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      )}
    </MetaField>
  )
}

/** "Data inizio": required, so never `null` (an untouched create keeps `''` for the schema to reject). */
export function WorkOrderStartDateField({ control }: FieldProps) {
  const { t } = useTranslation()
  return (
    <MetaField control={control} name="start_date" metaKey="start_date" label={t('workOrders.form.startDate')}>
      {({ field, disabled, readOnly }) => (
        <FormControl>
          <Input
            type="date"
            disabled={disabled}
            readOnly={readOnly}
            value={field.value}
            onChange={(event) => field.onChange(event.target.value)}
            onBlur={field.onBlur}
            name={field.name}
            ref={field.ref}
          />
        </FormControl>
      )}
    </MetaField>
  )
}

export function WorkOrderCallbackDateField({ control }: FieldProps) {
  const { t } = useTranslation()
  return (
    <MetaField control={control} name="callback_date" metaKey="callback_date" label={t('workOrders.form.callbackDate')}>
      {({ field, disabled, readOnly }) => (
        <FormControl>
          <Input
            type="date"
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
  )
}

interface TextAreaFieldProps extends FieldProps {
  name: 'description' | 'internal_notes'
  label: string
}

/** "Descrizione" / "Note interne": free text, an emptied box is `null`. */
export function WorkOrderTextAreaField({ control, name, label }: TextAreaFieldProps) {
  return (
    <MetaField control={control} name={name} metaKey={name} label={label}>
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
  )
}

interface TaskTemplateFieldProps extends FieldProps {
  /** The current template's `{id, name}`, so the trigger never falls back to `#id`. */
  selected: RelationFieldRef | null
}

/** "Modello di Task" (spec 0124 D-9): picked at creation only (D-5), the field permission locks it afterwards. */
export function WorkOrderTaskTemplateField({ control, selected }: TaskTemplateFieldProps) {
  const { t } = useTranslation()
  return (
    <RelationSelectField
      control={control}
      name="task_template_id"
      metaKey="task_template_id"
      label={t('workOrders.form.taskTemplateId')}
      hint={t('workOrders.form.hints.taskTemplateHelp')}
      resource={TASK_TEMPLATES_FOR_SELECT_RESOURCE}
      searchPlaceholder={t('workOrders.form.taskTemplateSearchPlaceholder')}
      selected={selected}
      placeholder={t('workOrders.form.taskTemplatePlaceholder')}
      emptyLabel={t('workOrders.form.taskTemplateEmpty')}
      errorLabel={t('workOrders.form.taskTemplateError')}
      clearLabel={t('workOrders.form.taskTemplateClear')}
      retryLabel={t('common.retry')}
    />
  )
}

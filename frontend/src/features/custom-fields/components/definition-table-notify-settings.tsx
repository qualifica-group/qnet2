import type { Control } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { AsyncPaginatedMultiSelect } from '@/components/ui/async-paginated-multi-select'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { Checkbox } from '@/components/ui/checkbox'
import { EmailRecipientsInput } from '@/components/ui/email-recipients-input'
import { FormControl, FormDescription, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import type { FieldDefinitionFormValues } from '@/features/custom-fields/field-definition-form-values'
import {
  TABLE_NOTIFY_MAX_EMAILS,
  type TableDefinitionBag,
} from '@/features/custom-fields/field-definition-table'
import { tableNotifyTriggerOptions } from '@/features/custom-fields/table-notify-triggers'

/** Radix `Select` cannot hold `''`, so "every added row" travels as this sentinel. */
const ROW_ADDED = '__row_added__'
/** The templates carrying the record placeholders (work order variables, spec 0175). */
const TEMPLATE_PARAMS = { module: 'work_orders' }

interface DefinitionTableNotifySettingsProps {
  control: Control<FieldDefinitionFormValues>
  table: TableDefinitionBag
}

/**
 * Row event of a `table` definition (spec 0390): when it fires, which
 * "Modello email" goes out and who receives it. Shared by the custom field and
 * attribute forms through `DefinitionTableColumnsEditor`.
 */
export function DefinitionTableNotifySettings({ control, table }: DefinitionTableNotifySettingsProps) {
  const { t } = useTranslation()

  return (
    <div className="flex flex-col gap-2 rounded-lg border bg-muted/40 p-2.5">
      <FormField
        control={control}
        name="table.notify_enabled"
        render={({ field }) => (
          <label className="flex items-center gap-2 text-xs font-medium">
            <Checkbox checked={field.value} onCheckedChange={(checked) => field.onChange(checked === true)} />
            {t('customFields.tableEditor.notify.enabled')}
          </label>
        )}
      />
      {table.notify_enabled ? <NotifyFields control={control} table={table} /> : null}
    </div>
  )
}

function NotifyFields({ control, table }: DefinitionTableNotifySettingsProps) {
  const { t } = useTranslation()
  const triggers = tableNotifyTriggerOptions(table)
  const selectLabels = {
    searchPlaceholder: t('customFields.tableEditor.notify.search'),
    empty: t('customFields.tableEditor.notify.empty'),
    error: t('customFields.tableEditor.notify.loadError'),
    removeLabel: t('customFields.tableEditor.notify.remove'),
    retry: t('common.retry'),
  }

  return (
    <div className="flex flex-col gap-2">
      <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
        <FormField
          control={control}
          name="table.notify_trigger"
          render={({ field }) => (
            <FormItem>
              <FormLabel className="text-xs">{t('customFields.tableEditor.notify.trigger')}</FormLabel>
              <Select
                value={field.value === '' ? ROW_ADDED : field.value}
                onValueChange={(next) => field.onChange(next === ROW_ADDED ? '' : next)}
              >
                <FormControl>
                  <SelectTrigger className="h-8 w-full text-xs">
                    <SelectValue />
                  </SelectTrigger>
                </FormControl>
                <SelectContent>
                  <SelectItem value={ROW_ADDED}>{t('customFields.tableEditor.notify.triggerRowAdded')}</SelectItem>
                  {triggers.map((option) => (
                    <SelectItem key={option.key} value={option.key}>
                      {t('customFields.tableEditor.notify.triggerSwitchedOn', { label: option.label })}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={control}
          name="table.notify_email_template_id"
          render={({ field }) => (
            <FormItem>
              <FormLabel className="text-xs">{t('customFields.tableEditor.notify.template')}</FormLabel>
              <FormControl>
                <AsyncPaginatedSelect
                  resource="email-templates"
                  params={TEMPLATE_PARAMS}
                  value={field.value}
                  onChange={field.onChange}
                  labels={{
                    placeholder: t('customFields.tableEditor.notify.templatePlaceholder'),
                    searchPlaceholder: t('customFields.tableEditor.notify.templateSearch'),
                    empty: t('customFields.tableEditor.notify.templateEmpty'),
                    error: t('customFields.tableEditor.notify.templateError'),
                    retry: t('common.retry'),
                    clearLabel: t('customFields.tableEditor.notify.clearTemplate'),
                    triggerLabel: t('customFields.tableEditor.notify.template'),
                  }}
                />
              </FormControl>
              <FormDescription className="text-xs">{t('customFields.tableEditor.notify.templateHint')}</FormDescription>
            </FormItem>
          )}
        />
      </div>

      <span className="text-xs font-medium">{t('customFields.tableEditor.notify.recipients')}</span>
      <FormField
        control={control}
        name="table.notify_record_team"
        render={({ field }) => (
          <FormItem>
            <label className="flex items-center gap-2 text-xs">
              <Checkbox checked={field.value} onCheckedChange={(checked) => field.onChange(checked === true)} />
              {t('customFields.tableEditor.notify.recordTeam')}
            </label>
            <FormDescription className="text-xs">{t('customFields.tableEditor.notify.recordTeamHint')}</FormDescription>
            <FormMessage />
          </FormItem>
        )}
      />
      <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
        <FormField
          control={control}
          name="table.notify_user_ids"
          render={({ field }) => (
            <FormItem>
              <FormLabel className="text-xs">{t('customFields.tableEditor.notify.users')}</FormLabel>
              <FormControl>
                <AsyncPaginatedMultiSelect
                  resource="users"
                  value={field.value}
                  onChange={field.onChange}
                  showAvatar
                  labels={{
                    ...selectLabels,
                    placeholder: t('customFields.tableEditor.notify.usersPlaceholder'),
                    triggerLabel: t('customFields.tableEditor.notify.users'),
                  }}
                />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={control}
          name="table.notify_role_ids"
          render={({ field }) => (
            <FormItem>
              <FormLabel className="text-xs">{t('customFields.tableEditor.notify.roles')}</FormLabel>
              <FormControl>
                <AsyncPaginatedMultiSelect
                  resource="roles"
                  value={field.value}
                  onChange={field.onChange}
                  labels={{
                    ...selectLabels,
                    placeholder: t('customFields.tableEditor.notify.rolesPlaceholder'),
                    triggerLabel: t('customFields.tableEditor.notify.roles'),
                  }}
                />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
      </div>
      <FormField
        control={control}
        name="table.notify_emails"
        render={({ field }) => (
          <FormItem>
            <FormLabel className="text-xs">{t('customFields.tableEditor.notify.emails')}</FormLabel>
            <FormControl>
              <EmailRecipientsInput
                value={field.value}
                onChange={field.onChange}
                maxItems={TABLE_NOTIFY_MAX_EMAILS}
                placeholder={t('customFields.tableEditor.notify.emailsPlaceholder')}
                removeLabel={t('customFields.tableEditor.notify.remove')}
                onInvalidInput={() => toast.error(t('customFields.tableEditor.notify.emailInvalid'))}
              />
            </FormControl>
            <FormMessage />
          </FormItem>
        )}
      />
    </div>
  )
}

import { useTranslation } from 'react-i18next'
import type { Control } from 'react-hook-form'
import { AsyncPaginatedMultiSelect } from '@/components/ui/async-paginated-multi-select'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { FormControl, FormDescription, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { TASK_TEMPLATES_FOR_SELECT_RESOURCE } from '@/features/task-templates/for-select-api'
import { USERS_FOR_SELECT_RESOURCE } from '@/features/users/for-select-api'
import type { ContractProgramFormValues } from '@/features/contracts/contract-program-schema'
import type { WorkOrderType } from '@/features/work-orders/types'

const WORK_ORDER_TYPES: WorkOrderType[] = ['processing', 'project']

/** Where the four shared fields live in the form: the "Valori comuni" block or one group. */
export type ProgramFieldsPrefix = 'common' | `groups.${number}`

interface ContractProgramSharedFieldsProps {
  control: Control<ContractProgramFormValues>
  prefix: ProgramFieldsPrefix
  /** Group fields are mandatory (start date, supervisors); the common defaults are not. */
  required: boolean
}

/**
 * Type / start date / task template / supervisors: the four values a group
 * has in common with the "Valori comuni" defaults (spec 0215 D-7), rendered
 * by the same component in both places so wording and rules cannot drift.
 */
export function ContractProgramSharedFields({ control, prefix, required }: ContractProgramSharedFieldsProps) {
  const { t } = useTranslation()

  return (
    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
      <FormField
        control={control}
        name={`${prefix}.type`}
        render={({ field }) => (
          <FormItem>
            <FormLabel required={required}>{t('workOrders.form.type')}</FormLabel>
            <Select value={field.value} onValueChange={field.onChange}>
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
            <FormMessage />
          </FormItem>
        )}
      />

      <FormField
        control={control}
        name={`${prefix}.start_date`}
        render={({ field }) => (
          <FormItem>
            <FormLabel required={required}>{t('workOrders.form.startDate')}</FormLabel>
            <FormControl>
              <Input type="date" {...field} />
            </FormControl>
            <FormMessage />
          </FormItem>
        )}
      />

      <FormField
        control={control}
        name={`${prefix}.task_template_id`}
        render={({ field }) => (
          <FormItem>
            <FormLabel>{t('contracts.actions.programDialog.taskTemplateLabel')}</FormLabel>
            <FormControl>
              <AsyncPaginatedSelect
                resource={TASK_TEMPLATES_FOR_SELECT_RESOURCE}
                value={field.value}
                onChange={field.onChange}
                labels={{
                  placeholder: t('contracts.actions.programDialog.taskTemplatePlaceholder'),
                  searchPlaceholder: t('contracts.actions.programDialog.taskTemplateSearchPlaceholder'),
                  empty: t('contracts.actions.programDialog.taskTemplateEmpty'),
                  error: t('contracts.actions.programDialog.taskTemplateError'),
                  clearLabel: t('contracts.actions.programDialog.taskTemplateClear'),
                  triggerLabel: t('contracts.actions.programDialog.taskTemplateLabel'),
                  retry: t('common.retry'),
                }}
              />
            </FormControl>
            <FormDescription>{t('contracts.actions.programDialog.taskTemplateHelp')}</FormDescription>
            <FormMessage />
          </FormItem>
        )}
      />

      <FormField
        control={control}
        name={`${prefix}.supervisor_ids`}
        render={({ field }) => (
          <FormItem>
            <FormLabel required={required}>{t('workOrders.form.supervisors')}</FormLabel>
            <FormControl>
              <AsyncPaginatedMultiSelect
                resource={USERS_FOR_SELECT_RESOURCE}
                value={field.value}
                onChange={field.onChange}
                showAvatar
                labels={{
                  placeholder: t('workOrders.form.supervisorsPlaceholder'),
                  searchPlaceholder: t('workOrders.form.supervisorsSearch'),
                  empty: t('workOrders.form.supervisorsEmpty'),
                  error: t('workOrders.form.supervisorsError'),
                  removeLabel: t('common.remove'),
                  triggerLabel: t('workOrders.form.supervisors'),
                  retry: t('common.retry'),
                }}
              />
            </FormControl>
            <FormMessage />
          </FormItem>
        )}
      />
    </div>
  )
}

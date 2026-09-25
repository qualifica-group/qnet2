import { useTranslation } from 'react-i18next'
import { Trash2 } from 'lucide-react'
import type { Control } from 'react-hook-form'
import { Button } from '@/components/ui/button'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { FormControl, FormField, FormItem, FormMessage } from '@/components/ui/form'
import { operatorsForRuleType, resolveRuleType } from '@/features/table/custom-filters/rule-types'
import type { RuleBuilderFormValues } from '@/features/table/custom-filters/rule-schema'
import { RuleValueInput } from '@/features/table/custom-filters/rule-value-input'
import type { TableColumn } from '@/features/table/types'

/** i18n key per operator token, under `table.customFilters.operators`. */
const OPERATOR_LABEL_KEY: Record<string, string> = {
  contains: 'contains',
  equals: 'equals',
  not_equals: 'notEquals',
  blank: 'blank',
  not_blank: 'notBlank',
  lt: 'lt',
  lte: 'lte',
  gt: 'gt',
  gte: 'gte',
  between: 'between',
  today: 'today',
  this_week: 'thisWeek',
  this_month: 'thisMonth',
  last_n_days: 'lastNDays',
  in: 'in',
  not_in: 'notIn',
  is: 'is',
}

interface RuleRowProps {
  control: Control<RuleBuilderFormValues>
  group: 'and' | 'or'
  index: number
  domain: string
  columns: TableColumn[]
  fieldValue: string
  operatorValue: string
  onFieldChange: (nextField: string) => void
  onRemove: () => void
}

/** One condition row: field / operator / value, plus its remove button. */
export function RuleRow({
  control,
  group,
  index,
  domain,
  columns,
  fieldValue,
  operatorValue,
  onFieldChange,
  onRemove,
}: RuleRowProps) {
  const { t } = useTranslation()
  const name = `${group}.${index}` as const

  const column = columns.find((candidate) => candidate.id === fieldValue)
  const ruleType = column ? resolveRuleType(column) : null
  const operators = ruleType ? operatorsForRuleType(ruleType, column?.hasFilterValues !== false) : []

  return (
    <div className="flex flex-wrap items-start gap-1.5 rounded-lg border border-border p-2">
      <FormField
        control={control}
        name={`${name}.field`}
        render={({ field }) => (
          <FormItem className="min-w-40">
            <Select
              value={field.value}
              onValueChange={(next) => {
                field.onChange(next)
                onFieldChange(next)
              }}
            >
              <FormControl>
                <SelectTrigger className="w-full" aria-label={t('table.customFilters.field')}>
                  <SelectValue placeholder={t('table.customFilters.field')} />
                </SelectTrigger>
              </FormControl>
              <SelectContent>
                {columns.map((candidate) => (
                  <SelectItem key={candidate.id} value={candidate.id}>
                    {t(candidate.label)}
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
        name={`${name}.operator`}
        render={({ field }) => (
          <FormItem className="min-w-36">
            <Select value={field.value} onValueChange={field.onChange} disabled={!ruleType}>
              <FormControl>
                <SelectTrigger className="w-full" aria-label={t('table.customFilters.operator')}>
                  <SelectValue placeholder={t('table.customFilters.operator')} />
                </SelectTrigger>
              </FormControl>
              <SelectContent>
                {operators.map((operator) => (
                  <SelectItem key={operator} value={operator}>
                    {t(`table.customFilters.operators.${OPERATOR_LABEL_KEY[operator]}`)}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            <FormMessage />
          </FormItem>
        )}
      />

      {ruleType ? (
        <RuleValueInput
          control={control}
          group={group}
          index={index}
          domain={domain}
          columnId={fieldValue}
          ruleType={ruleType}
          operator={operatorValue}
        />
      ) : null}

      <Button
        type="button"
        variant="ghost"
        size="icon-xs"
        className="mt-0.5 shrink-0"
        aria-label={t('table.customFilters.removeRule')}
        onClick={onRemove}
      >
        <Trash2 aria-hidden="true" />
      </Button>
    </div>
  )
}

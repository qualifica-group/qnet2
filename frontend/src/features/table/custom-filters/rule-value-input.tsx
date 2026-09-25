import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import type { Control } from 'react-hook-form'
import { Input } from '@/components/ui/input'
import { MultiSelect } from '@/components/ui/multi-select'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { FormControl, FormField, FormItem, FormMessage } from '@/components/ui/form'
import { fetchTableColumnValues } from '@/features/table/api'
import { VALUELESS_OPERATORS, type RuleOperator, type RuleType } from '@/features/table/custom-filters/rule-types'
import type { RuleBuilderFormValues } from '@/features/table/custom-filters/rule-schema'

interface RuleValueInputProps {
  control: Control<RuleBuilderFormValues>
  group: 'and' | 'or'
  index: number
  domain: string
  columnId: string
  ruleType: RuleType
  operator: string
}

/** Fetches a set column's distinct values for the `in`/`not_in` multi-select (reuses POST /tables/{domain}/values). */
function useRuleSetOptions(domain: string, columnId: string, enabled: boolean) {
  return useQuery({
    queryKey: ['table', domain, 'custom-filter-values', columnId],
    queryFn: () => fetchTableColumnValues(domain, { columnId }),
    enabled,
    staleTime: 60_000,
  })
}

/**
 * Renders the value control(s) for one rule row, driven entirely by the
 * field's resolved `ruleType` and the selected `operator` — a valueless
 * operator (`blank`, `today`, …) renders nothing.
 */
export function RuleValueInput({ control, group, index, domain, columnId, ruleType, operator }: RuleValueInputProps) {
  const { t } = useTranslation()
  const name = `${group}.${index}` as const
  const setOptionsQuery = useRuleSetOptions(domain, columnId, ruleType === 'set')

  if (VALUELESS_OPERATORS.has(operator as RuleOperator) || operator === '') {
    return null
  }

  if (ruleType === 'set') {
    const options = (setOptionsQuery.data?.values ?? [])
      .filter((value): value is string => value !== null)
      .map((value) => ({ value, label: value }))
    return (
      <FormField
        control={control}
        name={`${name}.values`}
        render={({ field }) => (
          <FormItem className="min-w-40 flex-1">
            <FormControl>
              <MultiSelect
                options={options}
                value={field.value}
                onChange={field.onChange}
                placeholder={t('table.advancedFilters.selectPlaceholder')}
                aria-label={t('table.customFilters.value')}
              />
            </FormControl>
            <FormMessage />
          </FormItem>
        )}
      />
    )
  }

  if (ruleType === 'boolean') {
    return (
      <FormField
        control={control}
        name={`${name}.value`}
        render={({ field }) => (
          <FormItem className="w-32">
            <Select value={field.value} onValueChange={field.onChange}>
              <FormControl>
                <SelectTrigger className="w-full" aria-label={t('table.customFilters.value')}>
                  <SelectValue placeholder={t('table.advancedFilters.selectPlaceholder')} />
                </SelectTrigger>
              </FormControl>
              <SelectContent>
                <SelectItem value="true">{t('common.yes')}</SelectItem>
                <SelectItem value="false">{t('common.no')}</SelectItem>
              </SelectContent>
            </Select>
            <FormMessage />
          </FormItem>
        )}
      />
    )
  }

  if (operator === 'last_n_days') {
    return (
      <FormField
        control={control}
        name={`${name}.value`}
        render={({ field }) => (
          <FormItem className="w-28">
            <FormControl>
              <Input type="number" min={1} max={366} aria-label={t('table.customFilters.value')} {...field} />
            </FormControl>
            <FormMessage />
          </FormItem>
        )}
      />
    )
  }

  const inputType = ruleType === 'number' ? 'number' : ruleType === 'date' ? 'date' : 'text'

  if (operator === 'between') {
    return (
      <div className="flex flex-1 items-center gap-1.5">
        <FormField
          control={control}
          name={`${name}.value`}
          render={({ field }) => (
            <FormItem className="flex-1">
              <FormControl>
                <Input
                  type={inputType}
                  placeholder={t('table.advancedFilters.rangeFrom')}
                  aria-label={t('table.advancedFilters.rangeFrom')}
                  {...field}
                />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <span aria-hidden="true" className="text-muted-foreground">
          {t('table.advancedFilters.rangeSeparator')}
        </span>
        <FormField
          control={control}
          name={`${name}.valueTo`}
          render={({ field }) => (
            <FormItem className="flex-1">
              <FormControl>
                <Input
                  type={inputType}
                  placeholder={t('table.advancedFilters.rangeTo')}
                  aria-label={t('table.advancedFilters.rangeTo')}
                  {...field}
                />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
      </div>
    )
  }

  return (
    <FormField
      control={control}
      name={`${name}.value`}
      render={({ field }) => (
        <FormItem className="min-w-40 flex-1">
          <FormControl>
            <Input
              type={inputType}
              maxLength={ruleType === 'text' ? 255 : undefined}
              aria-label={t('table.customFilters.value')}
              {...field}
            />
          </FormControl>
          <FormMessage />
        </FormItem>
      )}
    />
  )
}

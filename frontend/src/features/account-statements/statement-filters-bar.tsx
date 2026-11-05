import { useId } from 'react'
import { useTranslation } from 'react-i18next'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { STATEMENT_TYPES, type StatementFilters, type StatementType } from '@/features/account-statements/types'

interface StatementFiltersBarProps {
  filters: StatementFilters
  onChange: (filters: StatementFilters) => void
}

/** Period, types, registry and free-text search of the statement. */
export function StatementFiltersBar({ filters, onChange }: StatementFiltersBarProps) {
  const { t } = useTranslation()
  const fromId = useId()
  const toId = useId()
  const searchId = useId()

  const toggleType = (type: StatementType) => {
    const types = filters.types.includes(type)
      ? filters.types.filter((current) => current !== type)
      : [...filters.types, type]
    onChange({ ...filters, types })
  }

  return (
    <div className="flex flex-col gap-3 rounded-lg border bg-surface p-3">
      <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div className="flex flex-col gap-1">
          <Label htmlFor={fromId} className="text-xs text-muted-foreground">
            {t('accountStatements.filters.dateFrom')}
          </Label>
          <Input
            id={fromId}
            type="date"
            value={filters.dateFrom}
            onChange={(event) => onChange({ ...filters, dateFrom: event.target.value })}
          />
        </div>
        <div className="flex flex-col gap-1">
          <Label htmlFor={toId} className="text-xs text-muted-foreground">
            {t('accountStatements.filters.dateTo')}
          </Label>
          <Input
            id={toId}
            type="date"
            value={filters.dateTo}
            onChange={(event) => onChange({ ...filters, dateTo: event.target.value })}
          />
        </div>
        <div className="col-span-2 flex min-w-0 flex-col gap-1 lg:col-span-1">
          <Label className="text-xs text-muted-foreground">{t('accountStatements.filters.registry')}</Label>
          <AsyncPaginatedSelect
            resource="registries"
            value={filters.registryId}
            onChange={(registryId) => onChange({ ...filters, registryId })}
            labels={{
              placeholder: t('accountStatements.filters.registryPlaceholder'),
              searchPlaceholder: t('accountStatements.filters.registrySearch'),
              empty: t('accountStatements.filters.registryEmpty'),
              error: t('accountStatements.filters.registryError'),
              clearLabel: t('accountStatements.filters.registryClear'),
              triggerLabel: t('accountStatements.filters.registry'),
              retry: t('accountStatements.retry'),
            }}
          />
        </div>
        <div className="col-span-2 flex flex-col gap-1 lg:col-span-1">
          <Label htmlFor={searchId} className="text-xs text-muted-foreground">
            {t('accountStatements.filters.search')}
          </Label>
          <Input
            id={searchId}
            type="search"
            value={filters.search}
            onChange={(event) => onChange({ ...filters, search: event.target.value })}
            placeholder={t('accountStatements.filters.searchPlaceholder')}
          />
        </div>
      </div>
      <fieldset className="flex flex-wrap gap-x-4 gap-y-1">
        <legend className="mb-1 text-xs text-muted-foreground">{t('accountStatements.filters.types')}</legend>
        {STATEMENT_TYPES.map((type) => (
          <label key={type} className="flex items-center gap-1.5 text-xs">
            <input
              type="checkbox"
              className="size-3.5 accent-primary"
              checked={filters.types.includes(type)}
              onChange={() => toggleType(type)}
            />
            {t(`accountStatements.types.${type}`)}
          </label>
        ))}
      </fieldset>
    </div>
  )
}

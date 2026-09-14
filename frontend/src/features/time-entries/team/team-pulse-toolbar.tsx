/**
 * Search + filters popover of the team view card header (spec 0122 D-10),
 * q-net parity (`team-pulse/team-pulse-toolbar.tsx`) with the Regione filter
 * dropped (out of scope) and `MultiSelect`/`Select` from `components/ui`
 * (no `SearchableMultiSelect` counterpart in qnet-2 — these lists are short
 * enough that in-popover search is not needed).
 *
 */

import { Search, SlidersHorizontal, Users, X } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { MultiSelect } from '@/components/ui/multi-select'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { cn } from '@/lib/utils'
import {
  countActiveTeamFilters,
  type TeamActivityFilter,
  type TeamFiltersValue,
  type TeamOption,
} from '@/features/time-entries/team/team-tree'
import { TeamCoverageRangeFilter } from '@/features/time-entries/team/team-coverage-range-filter'

interface TeamPulseToolbarProps {
  title: string
  membersCount: number
  searchInput: string
  onSearchInputChange: (value: string) => void
  roleOptions: TeamOption[]
  businessFunctionOptions: TeamOption[]
  operationalSiteOptions: TeamOption[]
  filters: TeamFiltersValue
  onRolesChange: (values: string[]) => void
  onBusinessFunctionsChange: (values: string[]) => void
  onOperationalSitesChange: (values: string[]) => void
  onActivityChange: (value: TeamActivityFilter) => void
  onCoverageRangeChange: (value: [number, number]) => void
  onReset: () => void
}

export function TeamPulseToolbar({
  title,
  membersCount,
  searchInput,
  onSearchInputChange,
  roleOptions,
  businessFunctionOptions,
  operationalSiteOptions,
  filters,
  onRolesChange,
  onBusinessFunctionsChange,
  onOperationalSitesChange,
  onActivityChange,
  onCoverageRangeChange,
  onReset,
}: TeamPulseToolbarProps) {
  const { t } = useTranslation()
  const activeCount = countActiveTeamFilters(filters)

  return (
    <div className="flex flex-col gap-3 border-b border-border/60 px-4 py-2.5 lg:flex-row lg:items-center lg:justify-between">
      <div className="flex items-center gap-2">
        <Users className="size-4 text-muted-foreground" aria-hidden="true" />
        <span className="text-sm font-semibold text-foreground">{title}</span>
        <span className="text-xs text-muted-foreground">({membersCount})</span>
      </div>
      <div className="flex w-full flex-col gap-2 lg:w-auto lg:flex-row lg:items-center">
        <div className="relative w-full lg:w-64">
          <Search
            className="pointer-events-none absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
            aria-hidden="true"
          />
          <Input
            type="text"
            value={searchInput}
            onChange={(event) => onSearchInputChange(event.target.value)}
            placeholder={t('timeEntries.team.searchPlaceholder')}
            aria-label={t('timeEntries.team.searchPlaceholder')}
            className="pl-8 pr-8"
          />
          {searchInput ? (
            <button
              type="button"
              onClick={() => onSearchInputChange('')}
              aria-label={t('common.clear')}
              className="absolute right-2 top-1/2 -translate-y-1/2 rounded p-0.5 text-muted-foreground hover:bg-muted hover:text-foreground"
            >
              <X className="size-3.5" />
            </button>
          ) : null}
        </div>
        <Popover>
          <PopoverTrigger asChild>
            <Button
              type="button"
              variant="outline"
              size="sm"
              className={cn('gap-2', activeCount > 0 && 'border-primary/50 text-primary')}
            >
              <SlidersHorizontal className="size-3.5" aria-hidden="true" />
              <span>{t('timeEntries.team.filters')}</span>
              {activeCount > 0 ? (
                <span className="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-primary px-1.5 text-[11px] font-semibold text-primary-foreground">
                  {activeCount}
                </span>
              ) : null}
            </Button>
          </PopoverTrigger>
          <PopoverContent align="end" className="w-[320px] p-0 sm:w-[360px]">
            <div className="flex items-center justify-between border-b border-border/60 px-4 py-2.5">
              <div className="flex items-center gap-2">
                <SlidersHorizontal className="size-4 text-muted-foreground" aria-hidden="true" />
                <span className="text-sm font-semibold text-foreground">{t('timeEntries.team.filters')}</span>
              </div>
              {activeCount > 0 ? (
                <button
                  type="button"
                  onClick={onReset}
                  className="text-xs font-medium text-muted-foreground hover:text-foreground"
                >
                  {t('timeEntries.filters.reset')}
                </button>
              ) : null}
            </div>
            <div className="flex max-h-[60vh] flex-col gap-3 overflow-y-auto p-4">
              {roleOptions.length > 0 ? (
                <div className="flex flex-col gap-1.5">
                  <label className="text-xs font-medium text-muted-foreground">{t('timeEntries.team.role')}</label>
                  <MultiSelect
                    options={roleOptions}
                    value={filters.roles}
                    onChange={onRolesChange}
                    placeholder={t('timeEntries.team.role')}
                    aria-label={t('timeEntries.team.role')}
                  />
                </div>
              ) : null}
              {businessFunctionOptions.length > 0 ? (
                <div className="flex flex-col gap-1.5">
                  <label className="text-xs font-medium text-muted-foreground">
                    {t('timeEntries.team.jobFunction')}
                  </label>
                  <MultiSelect
                    options={businessFunctionOptions}
                    value={filters.businessFunctions}
                    onChange={onBusinessFunctionsChange}
                    placeholder={t('timeEntries.team.jobFunction')}
                    aria-label={t('timeEntries.team.jobFunction')}
                  />
                </div>
              ) : null}
              {operationalSiteOptions.length > 0 ? (
                <div className="flex flex-col gap-1.5">
                  <label className="text-xs font-medium text-muted-foreground">
                    {t('timeEntries.team.operatingSite')}
                  </label>
                  <MultiSelect
                    options={operationalSiteOptions}
                    value={filters.operationalSites}
                    onChange={onOperationalSitesChange}
                    placeholder={t('timeEntries.team.operatingSite')}
                    aria-label={t('timeEntries.team.operatingSite')}
                  />
                </div>
              ) : null}
              <div className="flex flex-col gap-1.5">
                <label className="text-xs font-medium text-muted-foreground">
                  {t('timeEntries.team.activity')}
                </label>
                <Select value={filters.activity} onValueChange={(value) => onActivityChange(value as TeamActivityFilter)}>
                  <SelectTrigger className="w-full">
                    <SelectValue placeholder={t('timeEntries.team.activity')} />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">{t('timeEntries.team.activityAll')}</SelectItem>
                    <SelectItem value="active">{t('timeEntries.team.active')}</SelectItem>
                    <SelectItem value="inactive">{t('timeEntries.team.inactive')}</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div className="flex flex-col gap-1.5">
                <label className="text-xs font-medium text-muted-foreground">{t('timeEntries.pulse.coverage')}</label>
                <TeamCoverageRangeFilter value={filters.coverageRange} onChange={onCoverageRangeChange} />
              </div>
            </div>
          </PopoverContent>
        </Popover>
      </div>
    </div>
  )
}

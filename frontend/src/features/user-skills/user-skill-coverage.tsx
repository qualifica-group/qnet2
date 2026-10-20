import { useTranslation } from 'react-i18next'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { FilterTabs } from '@/components/ui/filter-tabs'
import { Skeleton } from '@/components/ui/skeleton'
import { BUSINESS_FUNCTIONS_FOR_SELECT_RESOURCE } from '@/features/business-functions/for-select-api'
import { COVERAGE_STATUS_ICON } from '@/features/user-skills/coverage-status-icons'
import { UserSkillCoverageTable } from '@/features/user-skills/user-skill-coverage-table'
import { useUserSkillCoverage } from '@/features/user-skills/use-user-skill-coverage'
import { COVERAGE_STATUSES, type CoverageStatus } from '@/features/user-skills/types'

const SKILLS_RESOURCE = 'skills'
const ALL_STATUSES = 'all'
type StatusTab = CoverageStatus | typeof ALL_STATUSES

const STATUS_TABS: readonly StatusTab[] = [ALL_STATUSES, ...COVERAGE_STATUSES]

interface UserSkillCoverageProps {
  onOpen: (userSkillId: number) => void
}

/**
 * The "Copertura" tab (spec 0286 D-6): status tabs, function and skill filters, the legend and
 * the person x required skill table.
 */
export function UserSkillCoverage({ onOpen }: UserSkillCoverageProps) {
  const { t } = useTranslation()
  const { status, setStatus, businessFunctionId, setBusinessFunctionId, skillId, setSkillId, query } =
    useUserSkillCoverage()
  const coverage = query.data

  return (
    <section className="flex flex-col gap-3" aria-label={t('userSkills.coverage.caption')}>
      <FilterTabs<StatusTab>
        aria-label={t('userSkills.coverage.statusFilter')}
        value={status ?? ALL_STATUSES}
        options={STATUS_TABS.map((value) => ({
          value,
          label: t(value === ALL_STATUSES ? 'userSkills.coverage.allStatuses' : `userSkills.coverage.status.${value}`),
        }))}
        onValueChange={(value) => setStatus(value === ALL_STATUSES ? null : value)}
      />

      <div className="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
        <div className="w-full sm:w-64">
          <AsyncPaginatedSelect
            resource={BUSINESS_FUNCTIONS_FOR_SELECT_RESOURCE}
            value={businessFunctionId}
            onChange={setBusinessFunctionId}
            labels={{
              placeholder: t('userSkills.coverage.functionPlaceholder'),
              searchPlaceholder: t('userSkills.coverage.functionSearch'),
              empty: t('userSkills.coverage.functionEmpty'),
              error: t('userSkills.coverage.functionError'),
              clearLabel: t('common.clear'),
              triggerLabel: t('userSkills.coverage.functionFilter'),
              retry: t('common.retry'),
            }}
          />
        </div>
        <div className="w-full sm:w-64">
          <AsyncPaginatedSelect
            resource={SKILLS_RESOURCE}
            value={skillId}
            onChange={setSkillId}
            labels={{
              placeholder: t('userSkills.coverage.skillPlaceholder'),
              searchPlaceholder: t('userSkills.coverage.skillSearch'),
              empty: t('userSkills.coverage.skillEmpty'),
              error: t('userSkills.coverage.skillError'),
              clearLabel: t('common.clear'),
              triggerLabel: t('userSkills.coverage.skillFilter'),
              retry: t('common.retry'),
            }}
          />
        </div>
      </div>

      <ul className="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
        {COVERAGE_STATUSES.map((value) => {
          const Icon = COVERAGE_STATUS_ICON[value]
          return (
            <li key={value} className="inline-flex items-center gap-1.5">
              <Icon className="size-3.5" aria-hidden="true" />
              {t(`userSkills.coverage.legend.${value}`)}
            </li>
          )
        })}
      </ul>

      {query.isError ? (
        <p role="alert" className="text-sm text-destructive">
          {t('userSkills.coverage.error')}
        </p>
      ) : coverage ? (
        coverage.rows.length > 0 ? (
          <UserSkillCoverageTable rows={coverage.rows} onOpen={onOpen} />
        ) : (
          <p className="text-sm text-muted-foreground">{t('userSkills.coverage.empty')}</p>
        )
      ) : (
        <Skeleton className="h-40 w-full" />
      )}
    </section>
  )
}

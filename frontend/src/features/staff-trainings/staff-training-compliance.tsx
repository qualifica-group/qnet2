import { useTranslation } from 'react-i18next'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { FilterTabs } from '@/components/ui/filter-tabs'
import { Skeleton } from '@/components/ui/skeleton'
import { BUSINESS_FUNCTIONS_FOR_SELECT_RESOURCE } from '@/features/business-functions/for-select-api'
import { COMPLIANCE_STATUS_ICON } from '@/features/staff-trainings/compliance-status-icons'
import { StaffTrainingComplianceTable } from '@/features/staff-trainings/staff-training-compliance-table'
import { useStaffTrainingCompliance } from '@/features/staff-trainings/use-staff-training-compliance'
import { COMPLIANCE_STATUSES, type ComplianceStatus } from '@/features/staff-trainings/types'

const STAFF_COURSES_RESOURCE = 'staff-courses'
const ALL_STATUSES = 'all'
type StatusTab = ComplianceStatus | typeof ALL_STATUSES

const STATUS_TABS: readonly StatusTab[] = [ALL_STATUSES, ...COMPLIANCE_STATUSES]

interface StaffTrainingComplianceProps {
  onOpen: (trainingId: number) => void
}

/**
 * The "Situazione" tab (spec 0284 D-4/D-6): status tabs, function and course filters, the legend
 * with the expiring window and the person x course table.
 */
export function StaffTrainingCompliance({ onOpen }: StaffTrainingComplianceProps) {
  const { t } = useTranslation()
  const { status, setStatus, businessFunctionId, setBusinessFunctionId, staffCourseId, setStaffCourseId, query } =
    useStaffTrainingCompliance()
  const compliance = query.data

  return (
    <section className="flex flex-col gap-3" aria-label={t('staffTrainings.compliance.caption')}>
      <FilterTabs<StatusTab>
        aria-label={t('staffTrainings.compliance.statusFilter')}
        value={status ?? ALL_STATUSES}
        options={STATUS_TABS.map((value) => ({
          value,
          label: t(value === ALL_STATUSES ? 'staffTrainings.compliance.allStatuses' : `staffTrainings.compliance.status.${value}`),
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
              placeholder: t('staffTrainings.compliance.functionPlaceholder'),
              searchPlaceholder: t('staffTrainings.compliance.functionSearch'),
              empty: t('staffTrainings.compliance.functionEmpty'),
              error: t('staffTrainings.compliance.functionError'),
              clearLabel: t('common.clear'),
              triggerLabel: t('staffTrainings.compliance.functionFilter'),
              retry: t('common.retry'),
            }}
          />
        </div>
        <div className="w-full sm:w-64">
          <AsyncPaginatedSelect
            resource={STAFF_COURSES_RESOURCE}
            value={staffCourseId}
            onChange={setStaffCourseId}
            labels={{
              placeholder: t('staffTrainings.compliance.coursePlaceholder'),
              searchPlaceholder: t('staffTrainings.compliance.courseSearch'),
              empty: t('staffTrainings.compliance.courseEmpty'),
              error: t('staffTrainings.compliance.courseError'),
              clearLabel: t('common.clear'),
              triggerLabel: t('staffTrainings.compliance.courseFilter'),
              retry: t('common.retry'),
            }}
          />
        </div>
      </div>

      <ul className="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
        {COMPLIANCE_STATUSES.map((value) => {
          const Icon = COMPLIANCE_STATUS_ICON[value]
          return (
            <li key={value} className="inline-flex items-center gap-1.5">
              <Icon className="size-3.5" aria-hidden="true" />
              {value === 'expiring' && compliance
                ? t('staffTrainings.compliance.legend.expiring', { days: compliance.expiring_days })
                : t(`staffTrainings.compliance.legend.${value}`)}
            </li>
          )
        })}
      </ul>

      {query.isError ? (
        <p role="alert" className="text-sm text-destructive">
          {t('staffTrainings.compliance.error')}
        </p>
      ) : compliance ? (
        compliance.rows.length > 0 ? (
          <StaffTrainingComplianceTable rows={compliance.rows} onOpen={onOpen} />
        ) : (
          <p className="text-sm text-muted-foreground">{t('staffTrainings.compliance.empty')}</p>
        )
      ) : (
        <Skeleton className="h-40 w-full" />
      )}
    </section>
  )
}

import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { RewardStatusesTable } from '@/features/reward-statuses/reward-statuses-table'

/**
 * Reward statuses page. Light composition only: gates access with
 * `reward-statuses.viewAny` and mounts the thin Reward Statuses adapter,
 * which in turn mounts the generic table (`domain="reward-statuses"`). The
 * generic table owns config loading and loading/empty/error states; no
 * business logic or data fetching lives here.
 */
export default function RewardStatusesPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="reward-statuses.viewAny"
      fallback={<p className="text-sm text-muted-foreground">{t('rewardStatuses.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <RewardStatusesTable />
      </div>
    </Can>
  )
}

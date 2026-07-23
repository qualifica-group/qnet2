import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { RewardTypesTable } from '@/features/reward-types/reward-types-table'

/**
 * Reward types page. Light composition only: gates access with
 * `reward-types.viewAny` and mounts the thin Reward Types adapter, which in
 * turn mounts the generic table (`domain="reward-types"`). The generic table
 * owns config loading and loading/error/empty states; no business logic or
 * data fetching lives here.
 */
export default function RewardTypesPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="reward-types.viewAny"
      fallback={<p className="text-sm text-muted-foreground">{t('rewardTypes.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <RewardTypesTable />
      </div>
    </Can>
  )
}

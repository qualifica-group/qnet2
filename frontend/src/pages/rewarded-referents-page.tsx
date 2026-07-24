import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { RewardedReferentsTable } from '@/features/rewarded-referents/rewarded-referents-table'

/**
 * Rewarded Referents page (spec 0059). Light composition only: gates access
 * with `rewarded-referents.viewAny` and mounts the thin adapter, which in
 * turn mounts the generic table (`domain="rewarded-referents"`). Read-only
 * module (D-6): no create/edit affordance.
 */
export default function RewardedReferentsPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="rewarded-referents.viewAny"
      fallback={<p className="text-sm text-muted-foreground">{t('rewardedReferents.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <RewardedReferentsTable />
      </div>
    </Can>
  )
}

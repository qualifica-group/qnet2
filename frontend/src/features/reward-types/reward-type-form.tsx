import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { useRewardTypeFormMeta } from '@/features/reward-types/use-reward-type-form-meta'
import { RewardTypeFormBody } from '@/features/reward-types/reward-type-form-body'
import type { RewardTypeDetail, RewardTypeFormMode } from '@/features/reward-types/types'

interface RewardTypeFormProps {
  mode: RewardTypeFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (rewardType: RewardTypeDetail) => void
  /** Called when the user cancels the form. */
  onCancel: () => void
}

/**
 * Reusable RHF + Zod form used for both creating and editing a reward type.
 * Metadata-driven (spec 0004): resolves the resource's `ResourcePermissions`
 * before rendering — edit mode from the loaded instance detail, create mode
 * from `GET /meta/reward-types` — then hands off to `RewardTypeFormBody`,
 * which reads every field from that context via `MetaField`/
 * `useResourcePermissions()`.
 */
export function RewardTypeForm(props: RewardTypeFormProps) {
  const { t } = useTranslation()
  const meta = useRewardTypeFormMeta(props.mode)

  if (meta.status === 'loading') {
    return (
      <div className="flex flex-col gap-4 p-4" aria-hidden="true">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  if (meta.status === 'error') {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive" role="alert">
          {t('authorization.loadError')}
        </p>
        <Button variant="outline" size="sm" onClick={meta.retry}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  return (
    <ResourcePermissionsProvider permissions={meta.permissions}>
      <RewardTypeFormBody {...props} />
    </ResourcePermissionsProvider>
  )
}

import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { RecordFormSkeleton } from '@/components/record-form/record-form-skeleton'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import { RewardTypeFormBody } from '@/features/reward-types/reward-type-form-body'
import type { RewardTypeDetail } from '@/features/reward-types/types'

interface RewardTypeFormProps {
  /** Called after a successful create so the caller can navigate to the detail. */
  onSuccess: (rewardType: RewardTypeDetail) => void
  /** Called when the user cancels the form. */
  onCancel: () => void
}

/**
 * The reward type create form (there is no edit form: the detail edits in
 * place). Metadata-driven (spec 0004): resolves the create-context
 * `ResourcePermissions` (`GET /meta/reward-types`) before rendering
 * `RewardTypeFormBody`.
 */
export function RewardTypeForm(props: RewardTypeFormProps) {
  const { t } = useTranslation()
  const metaQuery = useResourceMeta('reward-types')

  if (metaQuery.isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive" role="alert">
          {t('authorization.loadError')}
        </p>
        <Button variant="outline" size="sm" className="bg-card" onClick={() => void metaQuery.refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (!metaQuery.data) {
    return <RecordFormSkeleton />
  }

  return (
    <ResourcePermissionsProvider permissions={metaQuery.data.permissions}>
      <RewardTypeFormBody {...props} />
    </ResourcePermissionsProvider>
  )
}

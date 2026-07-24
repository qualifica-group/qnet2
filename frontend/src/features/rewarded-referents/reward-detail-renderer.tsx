import { useTranslation } from 'react-i18next'
import type { ICellRendererParams } from 'ag-grid-community'
import { Inbox } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { RewardCard } from '@/features/rewards/reward-card'
import { useReferentRewards } from '@/features/rewarded-referents/use-referent-rewards'
import type { TableRow } from '@/features/table/types'

/** Skeleton placeholder mirroring the card grid's shape while the lazy fetch is in flight. */
function DetailLoadingState() {
  return (
    <div className="grid grid-cols-1 gap-3 p-3 sm:grid-cols-2 lg:grid-cols-3">
      {Array.from({ length: 3 }).map((_, index) => (
        <Skeleton key={index} className="h-32 w-full" />
      ))}
    </div>
  )
}

interface DetailErrorStateProps {
  message: string
  retryLabel: string
  onRetry: () => void
}

/** Error state with a retry action (AC-027): never a bare spinner, never a silently empty row. */
function DetailErrorState({ message, retryLabel, onRetry }: DetailErrorStateProps) {
  return (
    <div className="flex flex-col items-start gap-3 p-4">
      <p className="text-sm text-destructive">{message}</p>
      <Button type="button" variant="outline" size="sm" onClick={onRetry}>
        {retryLabel}
      </Button>
    </div>
  )
}

/** Empty state (AC-028): the race window between the list count and the detail fetch. */
function DetailEmptyState({ message }: { message: string }) {
  return (
    <div className="flex flex-col items-center justify-center gap-2 px-6 py-8 text-center">
      <span className="flex size-10 items-center justify-center rounded-full bg-muted text-muted-foreground">
        <Inbox aria-hidden="true" className="size-5" />
      </span>
      <p className="text-sm font-medium text-muted-foreground">{message}</p>
    </div>
  )
}

/**
 * AG Grid `detailCellRenderer` for the `rewarded-referents` master/detail
 * (spec 0059 D-4): given the master row (a Referent), lazy-loads its reward
 * list on mount (`useReferentRewards`) and renders a responsive grid of
 * `RewardCard`s. Isolated to this feature (constraints: the pattern is not
 * generalized into `components/data-table/`).
 */
export function RewardDetailRenderer({ data }: ICellRendererParams<TableRow>) {
  const { t } = useTranslation()
  const referentId = typeof data?.id === 'number' ? data.id : null

  const { data: rewards, isPending, isError, refetch } = useReferentRewards(referentId ?? 0, {
    enabled: referentId != null,
  })

  if (referentId == null) {
    return null
  }

  if (isPending) {
    return <DetailLoadingState />
  }

  if (isError) {
    return (
      <DetailErrorState
        message={t('rewardedReferents.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => void refetch()}
      />
    )
  }

  if (rewards.length === 0) {
    return <DetailEmptyState message={t('rewardedReferents.detail.empty')} />
  }

  const labels = {
    assignedAt: t('rewardedReferents.detail.assignedAt'),
    sourceRemoved: t('rewardedReferents.detail.sourceRemoved'),
    client: t('rewardedReferents.detail.client'),
    categories: t('rewardedReferents.detail.categories'),
    commercialStatus: t('rewardedReferents.detail.commercialStatus'),
    workflowStatus: t('rewardedReferents.detail.workflowStatus'),
    operator: t('rewardedReferents.detail.operator'),
  }

  return (
    <div className="grid grid-cols-1 gap-3 p-3 sm:grid-cols-2 lg:grid-cols-3">
      {rewards.map((reward) => (
        <RewardCard key={reward.id} reward={reward} labels={labels} />
      ))}
    </div>
  )
}

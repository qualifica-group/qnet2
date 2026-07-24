import { useEffect, useRef } from 'react'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import type { ICellRendererParams } from 'ag-grid-community'
import { Inbox } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { RewardCard } from '@/features/rewards/reward-card'
import { useUpdateRewardStatus } from '@/features/rewards/use-update-reward-status'
import { useReferentRewards } from '@/features/rewarded-referents/use-referent-rewards'
import { rewardedReferentsKeys } from '@/features/rewarded-referents/query-keys'
import { useModuleOpener } from '@/features/modules/use-module-opener'
import { useAbilities } from '@/features/auth/use-abilities'
import type { TableRow } from '@/features/table/types'

/**
 * The card's inline status edit is gated on the module's own update ability
 * (spec 0060 D-8: no dedicated `reward_status` field-permission plumbed down
 * to this generic master/detail renderer yet — the UI hides, the backend
 * PATCH is the actual authorization boundary regardless).
 */
const REWARDED_REFERENTS_UPDATE_PERMISSION = 'rewarded-referents.update'

/** Origin type whose module page/modal we know how to open (spec 0059: only Opportunity today). */
const OPPORTUNITY_SOURCE_TYPE = 'opportunity'
const OPPORTUNITIES_DOMAIN = 'opportunities'

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
export function RewardDetailRenderer({ data, node, api }: ICellRendererParams<TableRow>) {
  const { t } = useTranslation()
  const referentId = typeof data?.id === 'number' ? data.id : null

  // Opening the origin honors the Opportunity module's open mode (spec 0042):
  // modal mounts the Sheet returned here, page mode navigates. Hooks stay
  // unconditional (rules-of-hooks) — the early returns below come after.
  const { openView, sheet } = useModuleOpener(OPPORTUNITIES_DOMAIN)

  const { data: rewards, isPending, isError, refetch } = useReferentRewards(referentId ?? 0, {
    enabled: referentId != null,
  })

  // Inline status edit (spec 0060 D-1, AC-029/AC-030): one shared mutation
  // for every card in this panel, gated on the module's update ability while
  // abilities are still loading (fail closed, no edit-affordance flash).
  const queryClient = useQueryClient()
  const { can, isLoading: abilitiesLoading } = useAbilities()
  const canEditStatus = !abilitiesLoading && can(REWARDED_REFERENTS_UPDATE_PERMISSION)
  const updateStatus = useUpdateRewardStatus({
    onSuccess: () => {
      if (referentId != null) {
        void queryClient.invalidateQueries({ queryKey: rewardedReferentsKeys.rewards(referentId) })
      }
    },
  })

  // AG Grid measures a detail row's auto-height when the detail cell first
  // mounts. This panel loads lazily, so on the FIRST expand it mounts as a
  // short skeleton, is measured, then grows once the cards arrive — leaving
  // them clipped until a collapse/re-expand (the second time the data is
  // already cached and renders full-height on mount). Observing the loaded
  // content and pushing its real height back to the grid keeps the row fitted
  // on the first open too. Only wired once the cards are on screen (`hasContent`
  // re-runs the effect); guarded so the mocked-params unit tests never touch it.
  const contentRef = useRef<HTMLDivElement>(null)
  const hasContent = !isPending && !isError && (rewards?.length ?? 0) > 0
  useEffect(() => {
    const el = contentRef.current
    if (!el || !node || !api || typeof ResizeObserver === 'undefined') {
      return
    }
    const observer = new ResizeObserver(() => {
      node.setRowHeight(el.offsetHeight)
      api.onRowHeightChanged()
    })
    observer.observe(el)
    return () => observer.disconnect()
  }, [node, api, hasContent])

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
    status: t('rewardedReferents.detail.status'),
    statusPlaceholder: t('rewardedReferents.detail.statusPlaceholder'),
    statusSearchPlaceholder: t('rewardedReferents.detail.statusSearchPlaceholder'),
    statusEmpty: t('rewardedReferents.detail.statusEmpty'),
    statusError: t('rewardedReferents.detail.statusError'),
    statusClearLabel: t('rewardedReferents.detail.statusClearLabel'),
    statusRetry: t('rewardedReferents.detail.statusRetry'),
  }

  return (
    // Bounded, internally-scrollable card grid: a referent can accumulate many
    // rewards, and an uncapped panel would blow up the expanded row's height
    // (all cards at once). The cap keeps the master row compact and lets AG
    // Grid's detail auto-height measure a stable, finite height; the list
    // scrolls inside.
    <div ref={contentRef} className="max-h-[28rem] overflow-y-auto">
      <div className="grid grid-cols-1 gap-3 p-3 sm:grid-cols-2 lg:grid-cols-3">
        {rewards.map((reward) => {
          const source = reward.source
          const onOpenSource =
            source?.type === OPPORTUNITY_SOURCE_TYPE
              ? () => openView({ id: source.id } as TableRow)
              : undefined
          const isStatusUpdating =
            updateStatus.isPending && updateStatus.variables?.rewardId === reward.id
          return (
            <RewardCard
              key={reward.id}
              reward={reward}
              labels={labels}
              onOpenSource={onOpenSource}
              canEditStatus={canEditStatus}
              onStatusChange={(rewardStatusId) =>
                updateStatus.mutate({ rewardId: reward.id, rewardStatusId })
              }
              isStatusUpdating={isStatusUpdating}
            />
          )
        })}
      </div>
      {sheet}
    </div>
  )
}

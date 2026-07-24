import type { ReactNode } from 'react'
import { cn } from '@/lib/utils'
import { RewardChip, type RewardChipProps } from '@/features/rewards/reward-chip'

/** One chip's props plus a stable React key (a reward assignment/type id). */
export type RewardChipListItem = RewardChipProps & { id: string | number }

interface RewardChipListProps {
  items: RewardChipListItem[]
  /** Trailing slot for an "add reward" affordance; the caller owns the button. */
  action?: ReactNode
  className?: string
}

/**
 * Flex-wrap row of reward chips plus an optional trailing action slot.
 * Renders nothing when there is nothing to show (no rewards and no action),
 * so callers can mount it unconditionally (spec 0059 D-3: the abbinamento
 * control sits under the reporter field regardless of state).
 */
export function RewardChipList({ items, action, className }: RewardChipListProps) {
  if (items.length === 0 && !action) {
    return null
  }

  return (
    <div className={cn('flex flex-wrap items-center gap-1.5', className)}>
      {items.map((item) => {
        const { id, ...chipProps } = item
        return <RewardChip key={id} {...chipProps} />
      })}
      {action}
    </div>
  )
}

import { Award } from 'lucide-react'
import { RecordSection } from '@/components/detail/record-panel'
import { RewardChip } from '@/features/rewards/reward-chip'
import type { RewardAssignmentRef } from '@/features/rewards/types'

interface RewardChipsSectionProps {
  /** Section heading, owned by the caller: each module names the block in its own namespace. */
  title: string
  rewards: RewardAssignmentRef[]
  className?: string
}

/**
 * The assigned "buoni" as their own titled block of a record's
 * `RecordSectionsGrid` — the SINGLE rendering shared by the Opportunity and the
 * Offerta detail (user directive 2026-08-31: "cerchiamo di rendere tutto
 * simile"), so the two records can never drift apart on it.
 *
 * Renders nothing when nothing is assigned, the same rule every other
 * `RecordSection` of those grids follows: on a read-only record an empty
 * section carries no information the reader can act on.
 */
export function RewardChipsSection({ title, rewards, className }: RewardChipsSectionProps) {
  if (rewards.length === 0) {
    return null
  }

  return (
    <RecordSection title={title} icon={<Award />} className={className}>
      <div className="flex flex-wrap gap-1.5">
        {rewards.map((reward) => (
          <RewardChip key={reward.id} rewardType={reward.reward_type} />
        ))}
      </div>
    </RecordSection>
  )
}

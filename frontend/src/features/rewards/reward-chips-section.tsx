import { Award } from 'lucide-react'
import { RecordSection } from '@/components/detail/record-panel'
import { RewardChip } from '@/features/rewards/reward-chip'
import type { RewardAssignmentRef } from '@/features/rewards/types'

interface RewardChipsSectionProps {
  /** Section heading, owned by the caller: each module names the block in its own namespace. */
  title: string
  rewards: RewardAssignmentRef[]
  /**
   * Record detail cards: the section stays visible when nothing is assigned,
   * showing this text, so the sections after it never shift (spec 0331 D-4).
   * Without it the section renders nothing, the create form's behavior.
   */
  emptyLabel?: string
  className?: string
}

/**
 * The assigned "buoni" as their own titled block of a record's
 * `RecordSectionsGrid` — the SINGLE rendering shared by the Opportunity and the
 * Offerta detail (user directive 2026-08-31: "cerchiamo di rendere tutto
 * simile"), so the two records can never drift apart on it.
 *
 * Renders nothing when nothing is assigned unless the caller passes an
 * `emptyLabel` (the detail cards, which keep the section in place).
 */
export function RewardChipsSection({ title, rewards, emptyLabel, className }: RewardChipsSectionProps) {
  if (rewards.length === 0) {
    return emptyLabel ? (
      <RecordSection title={title} icon={<Award />} className={className}>
        <p className="text-sm text-muted-foreground">{emptyLabel}</p>
      </RecordSection>
    ) : null
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

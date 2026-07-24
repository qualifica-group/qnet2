import { X } from 'lucide-react'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import { badgeColorClass } from '@/features/table/cell-renderers'
import { swatchClassFor } from '@/features/custom-fields/badge-color-tokens'
import type { RewardTypeRef } from '@/features/rewards/types'

interface RewardChipBaseProps {
  rewardType: RewardTypeRef
  className?: string
}

/**
 * `onRemove` and `removeLabel` travel together: a remove button always needs
 * an accessible name, and there is no generic fallback text available here
 * (this component takes no i18n dependency — the caller passes labels
 * already translated). Omitting `onRemove` renders a read-only chip.
 */
export type RewardChipProps =
  | (RewardChipBaseProps & { onRemove?: undefined; removeLabel?: never })
  | (RewardChipBaseProps & { onRemove: () => void; removeLabel: string })

/**
 * A single reward-type chip: a solid dot in the type's palette token + its
 * name, with an optional trailing remove button. Purely presentational (no
 * fetching, no domain logic) so the `rewarded-referents` module and the
 * Opportunity/Gestione Richiesta abbinamento control render the identical
 * chip (spec 0059 D-3).
 */
export function RewardChip({ rewardType, onRemove, removeLabel, className }: RewardChipProps) {
  const dotClass = swatchClassFor(rewardType.color)

  return (
    <span
      className={cn(
        'inline-flex max-w-full items-center gap-1.5 rounded-md border px-2.5 py-1 text-xs font-medium',
        badgeColorClass(rewardType.color),
        className,
      )}
    >
      {dotClass ? (
        <span className={cn('size-1.5 shrink-0 rounded-full', dotClass)} aria-hidden="true" />
      ) : null}
      <span className="truncate">{rewardType.name}</span>
      {onRemove ? (
        <Button
          type="button"
          variant="ghost"
          size="icon-xs"
          className="-mr-1 shrink-0 rounded-full text-inherit hover:bg-black/10 dark:hover:bg-white/10"
          onClick={onRemove}
          aria-label={removeLabel}
        >
          <X aria-hidden="true" className="size-3.5" />
        </Button>
      ) : null}
    </span>
  )
}

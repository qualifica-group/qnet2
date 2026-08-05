import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip'
import { BADGE_BASE, badgeColorClass } from '@/features/table/cell-renderers'
import { swatchClassFor } from '@/features/custom-fields/badge-color-tokens'
import { cn } from '@/lib/utils'
import type { OpportunityStatusEntry, OpportunityStatusSummary } from '@/features/opportunities/types'

interface OpportunityStatusBadgeProps {
  summary: OpportunityStatusSummary | null | undefined
  className?: string
}

/**
 * The Opportunity's COMPUTED status (spec 0082), drawn the same way everywhere
 * it appears — grid cell, detail header, form, request panel, reward card — so
 * the two shapes it can take never diverge between screens:
 *
 * - one distinct status (any number of quotes behind it, or the working-state
 *   fallback): the status name in its own color, exactly like every other
 *   status badge in the app;
 * - two or more: a neutral "N stati" badge whose tooltip lists "count x name"
 *   per status, since no single color could honestly represent the set.
 *
 * Renders nothing (an em dash placeholder is the caller's job) when the summary
 * carries no entry at all.
 */
export function OpportunityStatusBadge({ summary, className }: OpportunityStatusBadgeProps) {
  const { t } = useTranslation()
  const entries = summary?.entries ?? []

  if (entries.length === 0) {
    return null
  }

  if (entries.length === 1) {
    return <SingleStatusBadge entry={entries[0]} className={className} />
  }

  const breakdown = entries.map((entry) => `${entry.count} ${entry.name}`)

  return (
    <TooltipProvider>
      <Tooltip>
        <TooltipTrigger asChild>
          <Badge
            variant="secondary"
            className={cn(BADGE_BASE, 'cursor-default', className)}
            tabIndex={0}
            aria-label={breakdown.join(', ')}
          >
            {t('opportunities.status.multiple', { count: entries.length })}
          </Badge>
        </TooltipTrigger>
        <TooltipContent side="top" variant="light" className="max-w-64 p-0">
          <ul className="flex flex-col divide-y">
            {entries.map((entry) => (
              <li key={entry.id} className="px-3 py-1.5 text-sm">
                {entry.count} {entry.name}
              </li>
            ))}
          </ul>
        </TooltipContent>
      </Tooltip>
    </TooltipProvider>
  )
}

function SingleStatusBadge({ entry, className }: { entry: OpportunityStatusEntry; className?: string }) {
  const dotClass = swatchClassFor(entry.color)

  return (
    <Badge variant="secondary" className={cn(BADGE_BASE, 'gap-1.5', badgeColorClass(entry.color), className)}>
      {dotClass ? <span className={cn('size-1.5 shrink-0 rounded-full', dotClass)} aria-hidden="true" /> : null}
      <span className="truncate">{entry.name}</span>
    </Badge>
  )
}

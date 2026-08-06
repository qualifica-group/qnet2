import { useTranslation } from 'react-i18next'
import { AlertTriangle, CalendarClock, OctagonX } from 'lucide-react'
import { cn } from '@/lib/utils'
import { Badge } from '@/components/ui/badge'
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip'
import {
  BADGE_BASE,
  BADGE_COLOR_CLASSES,
  badgeColorClass,
  formatDateTime,
} from '@/features/table/cell-renderers'
import { swatchClassFor } from '@/features/custom-fields/badge-color-tokens'
import type { ContractAlert, ContractStatusRef } from '@/features/contracts/types'

/**
 * The `expiring`/`renewal_due` indicator (D-4/BR-6, MT-10's recipe): icon +
 * text always together (AC-049 — never color alone), decorative icon
 * (`aria-hidden`, the text carries the meaning). `null` renders nothing, the
 * caller decides the layout around it (grid cell vs. detail hero).
 */
export function ContractAlertBadge({ alert }: { alert: ContractAlert }) {
  const { t } = useTranslation()

  if (alert === 'expiring') {
    return (
      <Badge variant="secondary" className={cn(BADGE_BASE, 'gap-1', BADGE_COLOR_CLASSES.orange)}>
        <AlertTriangle aria-hidden="true" />
        {t('contracts.alerts.expiring')}
      </Badge>
    )
  }

  if (alert === 'renewal_due') {
    return (
      <Badge variant="secondary" className={cn(BADGE_BASE, 'gap-1', BADGE_COLOR_CLASSES.blue)}>
        <CalendarClock aria-hidden="true" />
        {t('contracts.alerts.renewalDue')}
      </Badge>
    )
  }

  return null
}

/** Plain colored status pill for the detail hero (grid cells reuse the existing `StatusBadgeCell` unchanged). */
export function ContractStatusBadge({ status }: { status: ContractStatusRef }) {
  const dotClass = swatchClassFor(status.color)
  return (
    <Badge variant="secondary" className={cn(BADGE_BASE, 'gap-1.5', badgeColorClass(status.color))}>
      {dotClass ? <span className={cn('size-1.5 shrink-0 rounded-full', dotClass)} aria-hidden="true" /> : null}
      <span className="truncate">{status.name}</span>
    </Badge>
  )
}

interface ContractSuspendedBadgeProps {
  suspendedAt: string | null
  previousStatus: ContractStatusRef | null
}

/**
 * "Sospeso" is the contract's STATE, not an alert (D-3): a destructive badge
 * with its own icon+text, plus a Tooltip carrying the reason/date — a local
 * copy of `StatusDescriptionHint`'s trigger pattern (quote-workflows is a
 * different domain, not imported cross-module).
 */
export function ContractSuspendedBadge({ suspendedAt, previousStatus }: ContractSuspendedBadgeProps) {
  const { t } = useTranslation()
  const suspendedAtLabel = formatDateTime(suspendedAt)

  return (
    <TooltipProvider>
      <Tooltip>
        <TooltipTrigger asChild>
          <button type="button" className="cursor-help">
            <Badge variant="destructive" className={cn(BADGE_BASE, 'gap-1')}>
              <OctagonX aria-hidden="true" />
              {t('contracts.detail.suspended')}
            </Badge>
          </button>
        </TooltipTrigger>
        <TooltipContent className="max-w-64">
          {t('contracts.detail.suspendedReason', {
            status: previousStatus?.name ?? t('contracts.detail.suspendedReasonUnknownStatus'),
            date: suspendedAtLabel || t('contracts.detail.suspendedReasonUnknownDate'),
          })}
        </TooltipContent>
      </Tooltip>
    </TooltipProvider>
  )
}

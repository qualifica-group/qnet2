import { useTranslation } from 'react-i18next'
import { AlertTriangle } from 'lucide-react'
import { cn } from '@/lib/utils'
import { Badge } from '@/components/ui/badge'
import { BADGE_BASE, BADGE_COLOR_CLASSES } from '@/features/table/cell-renderers'
import type { QuoteAlert } from '@/features/quotes/types'

/**
 * The `missing_offer_lines` indicator (spec 0102 D-4, mirrors the Contracts
 * `alert` recipe in `contract-status-badges.tsx`): icon + text always
 * together (AC-036 — never color alone), decorative icon (`aria-hidden`),
 * the label carries the meaning. `null` renders nothing, the caller decides
 * the layout around it (today: the grid cell only, D-4).
 */
export function QuoteAlertBadge({ alert }: { alert: QuoteAlert }) {
  const { t } = useTranslation()

  if (alert === 'missing_offer_lines') {
    return (
      <Badge variant="secondary" className={cn(BADGE_BASE, 'gap-1', BADGE_COLOR_CLASSES.amber)}>
        <AlertTriangle aria-hidden="true" />
        {t('quotes.alerts.missingOfferLines')}
      </Badge>
    )
  }

  return null
}

import { useTranslation } from 'react-i18next'
import { Lock } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip'
import { formatDateTime } from '@/lib/formatting/date-display'
import type { LineFulfillment, ResidualClosure } from '@/features/purchase-orders/types'

const BADGE_VARIANTS = { open: 'outline', partial: 'secondary', complete: 'default' } as const

/** Fulfilment state of a line (spec 0254 D-3), computed by the server. */
export function PurchaseOrderFulfillmentBadge({ fulfillment }: { fulfillment: LineFulfillment }) {
  const { t } = useTranslation()
  return (
    <Badge variant={BADGE_VARIANTS[fulfillment]} className="text-[11px]">
      {t(`purchaseOrderActions.fulfillment.${fulfillment}`)}
    </Badge>
  )
}

/** "Residuo chiuso" marker: reason, who and when in the tooltip (focusable, so keyboard users reach it). */
export function PurchaseOrderResidualClosedIndicator({ closure }: { closure: ResidualClosure }) {
  const { t } = useTranslation()
  return (
    <Tooltip>
      <TooltipTrigger asChild>
        <span
          tabIndex={0}
          role="img"
          aria-label={t('purchaseOrderActions.residual.closedLabel')}
          className="inline-flex items-center gap-1 rounded-md border px-1.5 py-0.5 text-[11px] text-muted-foreground"
        >
          <Lock className="size-3" aria-hidden="true" />
          {t('purchaseOrderActions.residual.closedShort')}
        </span>
      </TooltipTrigger>
      <TooltipContent className="max-w-xs">
        <p>{closure.reason}</p>
        <p className="opacity-80">
          {t('purchaseOrderActions.residual.closedBy', { name: closure.by.name, date: formatDateTime(closure.at) })}
        </p>
      </TooltipContent>
    </Tooltip>
  )
}

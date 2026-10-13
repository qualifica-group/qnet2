import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'
import { DynamicIcon } from '@/features/custom-fields/dynamic-icon'
import { usePurchaseRequestLineStatusCatalog } from '@/features/purchase-request-line-statuses/use-purchase-request-line-status-catalog'
import { badgeColorClass } from '@/features/table/cell-renderers'
import { cn } from '@/lib/utils'
import type { LineStatus, PurchaseRequestStatus } from '@/features/purchase-requests/types'

/**
 * Line status as the configured badge of the catalog (name, color, icon; spec
 * 0248). The name always travels with the color (never colour alone); while
 * the catalog loads, or for a key it does not know, the raw value is shown on
 * a neutral badge so the status is never blank.
 */
export function LineStatusBadge({ status, className }: { status: LineStatus; className?: string }) {
  const { byKey } = usePurchaseRequestLineStatusCatalog()
  const presentation = byKey.get(status)
  return (
    <Badge
      variant="secondary"
      className={cn('gap-1 text-[11px]', presentation ? badgeColorClass(presentation.color) : undefined, className)}
    >
      <DynamicIcon name={presentation?.icon} className="size-3" />
      {presentation?.name ?? status}
    </Badge>
  )
}

export function RequestStatusBadge({ status }: { status: PurchaseRequestStatus }) {
  const { t } = useTranslation()
  return (
    <Badge variant={status === 'open' ? 'secondary' : 'outline'} className="text-[11px]">
      {t(`purchaseRequests.requestStatuses.${status}`)}
    </Badge>
  )
}

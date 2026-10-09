import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'
import { cn } from '@/lib/utils'
import type { LineStatus, PurchaseRequestStatus } from '@/features/purchase-requests/types'

type BadgeVariant = 'default' | 'secondary' | 'destructive' | 'outline'

/** Token-based variant per line status; the label always travels with it (never colour alone). */
const LINE_VARIANTS: Record<LineStatus, { variant: BadgeVariant; className?: string }> = {
  pending_approval: { variant: 'outline' },
  approved: { variant: 'secondary' },
  ordered: { variant: 'default' },
  received: { variant: 'default', className: 'bg-primary/80' },
  rejected: { variant: 'destructive' },
  on_hold: { variant: 'outline', className: 'border-dashed' },
}

export function LineStatusBadge({ status, className }: { status: LineStatus; className?: string }) {
  const { t } = useTranslation()
  const { variant, className: tone } = LINE_VARIANTS[status]
  return (
    <Badge variant={variant} className={cn('text-[11px]', tone, className)}>
      {t(`purchaseRequests.lineStatuses.${status}`)}
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
